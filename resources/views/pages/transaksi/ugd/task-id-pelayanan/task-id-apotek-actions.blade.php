<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

/**
 * KOMPONEN AKSI Task ID apotek UGD (TaskId6 Masuk Apotek, TaskId7 Keluar Apotek)
 * — berisi SEMUA fungsi/logika.
 *
 * Arsitektur "cetak-pattern": komponen ini di-mount SEKALI sebagai sibling di
 * antrian-apotek-ugd (bukan per baris). Tombol tiap baris ada di antrian-apotek-ugd
 * dan memicu komponen ini via
 * wire:click="$dispatch('task-id-apotek-proses-ugd', { rjNo, aksi })" (aksi
 * Livewire, bukan Alpine).
 *
 * Sebelumnya komponen ini di-mount per baris dengan prop #[Reactive] → saat list
 * re-render pasca 'refresh-after-apotek.saved', semua child reactive ikut satu
 * batch → TooManyComponents saat baris banyak. Dengan mount sekali, batch tak lagi
 * skala jumlah baris. Tombol Batal (task-id-99) tetap terpisah.
 *
 * ⚠️ Logika tiap aksi (lock → find → guard idempoten → penomoran noAntrianApotek
 *    anti-race → updateJson) IDENTIK versi lama.
 */
new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;

    /* ===============================
     | ROUTER — dipicu tombol baris via wire:click $dispatch
     | Detail event: { rjNo, aksi } dengan aksi ∈ {'6','7'}.
     =============================== */
    #[On('task-id-apotek-proses-ugd')]
    public function proses(int $rjNo, string $aksi): void
    {
        $this->rjNo = $rjNo;

        match ($aksi) {
            '6' => $this->prosesTaskId6(),
            '7' => $this->prosesTaskId7(),
            default => null,
        };
    }

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

{{-- Indikator proses global (host tak punya tombol sendiri — tombol ada di baris antrian-apotek-ugd). --}}
<div wire:key="task-id-apotek-actions-ugd-host">
    <div wire:loading wire:target="proses, prosesTaskId6, prosesTaskId7"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-blue-600 rounded-xl shadow-lg dark:bg-blue-500">
        <x-loading />
        Memproses Task ID Apotek…
    </div>
</div>
