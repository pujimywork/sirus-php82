<?php
// resources/views/pages/transaksi/ugd/..../taskid-antrean-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AntrianTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait, AntrianTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

    private function isPoliSpesialis($poliId): bool
    {
        return DB::table('rsmst_polis')->where('poli_id', $poliId)->where('spesialis_status', '1')->exists();
    }

    public function prosesTaskidAntrean(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong', title: 'Peringatan');
            return;
        }

        $this->isLoading = true;

        try {
            $data = $this->findDataUGD($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan', title: 'Error');
                return;
            }

            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskidAntreanStatus'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $response = AntrianTrait::taskid_antrean($noBooking)->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $listTaskId = $response['response']['listTaskId'] ?? [];
                    $isSuccess = $code == 200 || $code == 208;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: $isSuccess ? 'TaskId tercatat: ' . implode(', ', array_column($listTaskId, 'taskId')) : "TaskId Antrean: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId Antrean sudah pernah diambil dari BPJS', title: 'Info');
                }
            }

            $this->dispatch('refresh-after-ugd.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        } finally {
            $this->isLoading = false;
        }
    }
};
?>

<div class="inline-block">
    <x-primary-button wire:click="prosesTaskidAntrean" wire:loading.attr="disabled" wire:target="prosesTaskidAntrean"
        class="!px-2 !py-1 text-xs" title="Klik untuk mengambil TaskId Antrean dari BPJS">
        <span wire:loading.remove wire:target="prosesTaskidAntrean">
            TaskId Antrean
        </span>
        <span wire:loading wire:target="prosesTaskidAntrean">
            <x-loading />
        </span>
    </x-primary-button>
</div>
