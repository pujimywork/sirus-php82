<?php
// resources/views/pages/transaksi/ugd/..../taskid99-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

    /* ===============================
     | PROSES TASKID99 — Batalkan Antrian
     |
     | Pola:
     |   1. Guard awal (empty rjNo)
     |   2. DB::transaction: lockUGDRow → findDataUGD → guard taskId4/5/99
     |      → set taskId99 → updateJsonUGD
     |   3. dispatch DI LUAR transaksi
     =============================== */
    public function prosesTaskId99(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong.');
            return;
        }

        $this->isLoading = true;

        try {
            $message = '';

            DB::transaction(function () use (&$message) {
                // 1. Lock row dulu — hard stop setelah lock
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // 3. Hard stop — tidak bisa batalkan jika taskId4/5 sudah ada
                if (!empty($data['taskIdPelayanan']['taskId4'] ?? null)) {
                    throw new \RuntimeException('Tidak dapat membatalkan antrian karena TaskId4 (Selesai Pelayanan) sudah tercatat.');
                }

                if (!empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                    throw new \RuntimeException('Tidak dapat membatalkan antrian karena TaskId5 (Panggil Antrian) sudah tercatat.');
                }

                // 4. Guard idempoten — jika taskId99 sudah ada, skip update
                if (!empty($data['taskIdPelayanan']['taskId99'])) {
                    $message = "TaskId99 sudah tercatat pada {$data['taskIdPelayanan']['taskId99']}.";
                    return;
                }

                // 5. Set taskId99 + simpan JSON
                $data['taskIdPelayanan'] ??= [];
                $data['taskIdPelayanan']['taskId99'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                $this->updateJsonUGD($this->rjNo, $data);

                $message = 'Antrian berhasil dibatalkan.';
            });

            // 6. Notify + dispatch — di luar transaksi
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
    <x-danger-button wire:click="prosesTaskId99" wire:loading.attr="disabled" wire:target="prosesTaskId99"
        class="!px-2 !py-1 text-xs" title="Klik untuk membatalkan antrian (hanya bisa sebelum TaskId4/5)">
        <span wire:loading.remove wire:target="prosesTaskId99">Batal</span>
        <span wire:loading wire:target="prosesTaskId99"><x-loading /></span>
    </x-danger-button>
</div>
