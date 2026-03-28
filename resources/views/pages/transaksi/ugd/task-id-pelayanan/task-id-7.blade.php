<?php
// resources/views/pages/transaksi/ugd/..../taskid7-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

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

        $this->isLoading = true;

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
    <x-primary-button wire:click="prosesTaskId7" wire:loading.attr="disabled" wire:target="prosesTaskId7"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId7 (Keluar Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId7">TaskId7</span>
        <span wire:loading wire:target="prosesTaskId7"><x-loading /></span>
    </x-primary-button>
</div>
