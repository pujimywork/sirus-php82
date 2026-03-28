<?php
// resources/views/pages/transaksi/ugd/..../taskid6-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

    /* ===============================
     | PROSES TASKID6 — Masuk Apotek
     |
     | Pola:
     |   1. Guard awal (empty rjNo, taskId5)
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

        $this->isLoading = true;

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

                // 3. Guard taskId5 harus ada
                if (empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                    throw new \RuntimeException('TaskId5 (Panggil Antrian) harus dilakukan terlebih dahulu.');
                }

                // 4. Guard idempoten — jika taskId6 sudah ada, skip update
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
                            $dataJson = json_decode($item->datadaftarugd_json, true) ?: [];
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
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }
};
?>

<div class="inline-block">
    <x-primary-button wire:click="prosesTaskId6" wire:loading.attr="disabled" wire:target="prosesTaskId6"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId6 (Masuk Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId6">TaskId6</span>
        <span wire:loading wire:target="prosesTaskId6"><x-loading /></span>
    </x-primary-button>
</div>
