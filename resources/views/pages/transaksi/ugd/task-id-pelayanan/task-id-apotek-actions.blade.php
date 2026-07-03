<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

/**
 * Gabungan aksi Task ID apotek UGD (TaskId6 Masuk Apotek, TaskId7 Keluar Apotek)
 * dalam SATU komponen per baris.
 *
 * Sebelumnya 2 komponen Livewire terpisah (task-id-6, task-id-7) di-mount per baris
 * di antrian-apotek-ugd → 2x jumlah komponen (pemicu payload berat / TooManyComponents).
 * Digabung: logika tiap aksi identik (lock → find → guard idempoten → penomoran
 * noAntrianApotek anti-race → updateJson), spinner per tombol tetap terisolasi via
 * wire:target. Root display:contents + wrapper inline-block per tombol → tata letak
 * grid parent tidak berubah. Tombol Batal (task-id-99) tetap komponen terpisah karena
 * posisi & gate role-nya berbeda. Hanya dipakai di antrian-apotek-ugd.
 */
new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    // #[Reactive] → tombol ikut redup saat parent re-render (refresh-after-apotek.saved), tanpa remount.
    #[Reactive]
    public bool $isDone6 = false;
    #[Reactive]
    public bool $isDone7 = false;

    /* ===============================
     | PROSES TASKID6 — Masuk Apotek
     |
     | Pola:
     |   1. Guard awal (empty rjNo)
     |   2. DB::transaction: lockUGDRow → findDataUGD → update waktu_masuk_apt
     |      + noAntrianApotek (atomik, cegah race condition) → updateJsonUGD
     |   3. dispatch + isLoading = false DI LUAR transaksi
     =============================== */
    public function prosesTaskId6(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong.');
            return;
        }

        try {
            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            $message = '';

            DB::transaction(function () use ($waktuSekarang, &$message) {
                // 1. Lock row dulu — cegah race condition noAntrianApotek
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // 3. Guard idempoten — jika taskId6 sudah ada, skip update
                if (!empty($data['taskIdPelayanan']['taskId6'])) {
                    $message = "TaskId6 sudah dicatat pada {$data['taskIdPelayanan']['taskId6']}.";
                    return;
                }

                // 5. Update waktu_masuk_apt di header — atomik dengan JSON
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_masuk_apt' => DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                // 6. Set taskId6
                $data['taskIdPelayanan'] ??= [];
                $data['taskIdPelayanan']['taskId6'] = $waktuSekarang;

                // 7. Hitung noAntrianApotek — di dalam lock agar tidak ada dua pasien
                //    mendapat nomor yang sama
                if (empty($data['noAntrianApotek'])) {
                    $eresepRacikanCount = collect($data['eresepRacikan'] ?? [])->count();
                    $jenisResep = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';
                    $refDate = Carbon::now(config('app.timezone'))->format('d/m/Y');

                    // Hitung berapa pasien UGD hari ini yang sudah punya noAntrianApotek
                    $nomerAntrian = DB::table('rstxn_ugdhdrs')
                        ->select('datadaftarugd_json')
                        ->where('rj_status', '!=', 'F')
                        ->where('klaim_id', '!=', 'KR')
                        ->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)
                        ->get()
                        ->filter(function ($item) {
                            $dataJson = json_decode(OracleLob::toString($item->datadaftarugd_json) ?: '{}', true) ?: [];
                            return isset($dataJson['noAntrianApotek']);
                        })
                        ->count();

                    $noAntrian = ($data['klaimId'] ?? '') !== 'KR' ? $nomerAntrian + 1 : 9999;

                    $data['noAntrianApotek'] = [
                        'noAntrian' => $noAntrian,
                        'jenisResep' => $jenisResep,
                    ];
                }

                // 8. Simpan JSON — row sudah di-lock
                $this->updateJsonUGD($this->rjNo, $data);

                $message = "Berhasil masuk apotek pada {$waktuSekarang}.";
            });

            // 9. Notify + dispatch — di luar transaksi
            $this->dispatch('toast', type: 'success', message: $message);
            $this->dispatch('refresh-after-ugd.saved');
            $this->dispatch('refresh-after-apotek.saved'); // refresh list apotek ini → nomor antrian langsung tampil
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PROSES TASKID7 — Keluar Apotek
     |
     | Pola:
     |   1. Guard awal (empty rjNo, taskId6)
     |   2. DB::transaction: lockUGDRow → findDataUGD → guard idempoten
     |      → update waktu_selesai_pelayanan + JSON atomik
     |   3. dispatch DI LUAR transaksi
     =============================== */
    public function prosesTaskId7(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong.');
            return;
        }

        try {
            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            $message = '';

            DB::transaction(function () use ($waktuSekarang, &$message) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // 3. Guard taskId6 harus ada
                if (empty($data['taskIdPelayanan']['taskId6'] ?? null)) {
                    throw new \RuntimeException('TaskId6 (Masuk Apotek) harus dilakukan terlebih dahulu.');
                }

                // 4. Guard idempoten — jika taskId7 sudah ada, skip update
                if (!empty($data['taskIdPelayanan']['taskId7'])) {
                    $message = "TaskId7 sudah tercatat pada {$data['taskIdPelayanan']['taskId7']}.";
                    return;
                }

                // 5. Update waktu_selesai_pelayanan di header — atomik dengan JSON
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_selesai_pelayanan' => DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                // 6. Set taskId7 + simpan JSON
                $data['taskIdPelayanan'] ??= [];
                $data['taskIdPelayanan']['taskId7'] = $waktuSekarang;

                $this->updateJsonUGD($this->rjNo, $data);

                $message = "Berhasil keluar apotek pada {$waktuSekarang}.";
            });

            // 7. Notify + dispatch — di luar transaksi
            $this->dispatch('toast', type: 'success', message: $message);
            $this->dispatch('refresh-after-ugd.saved');
            $this->dispatch('refresh-after-apotek.saved'); // refresh list apotek ini → nomor antrian langsung tampil
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
};
?>

<div class="contents">
    {{-- TaskId6 (Masuk Apotek) --}}
    <div class="inline-block">
        <x-primary-button wire:click="prosesTaskId6" wire:loading.attr="disabled" wire:target="prosesTaskId6"
            class="!px-4 !py-2 text-sm {{ $isDone6 ? '!opacity-60' : '' }}" title="{{ $isDone6 ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mencatat TaskId6 (Masuk Apotek)' }}">
            <span wire:loading.remove wire:target="prosesTaskId6">TaskId6</span>
            <span wire:loading wire:target="prosesTaskId6"><x-loading /></span>
        </x-primary-button>
    </div>

    {{-- TaskId7 (Keluar Apotek) --}}
    <div class="inline-block">
        <x-primary-button wire:click="prosesTaskId7" wire:loading.attr="disabled" wire:target="prosesTaskId7"
            class="!px-4 !py-2 text-sm {{ $isDone7 ? '!opacity-60' : '' }}" title="{{ $isDone7 ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mencatat TaskId7 (Keluar Apotek)' }}">
            <span wire:loading.remove wire:target="prosesTaskId7">TaskId7</span>
            <span wire:loading wire:target="prosesTaskId7"><x-loading /></span>
        </x-primary-button>
    </div>
</div>
