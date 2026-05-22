<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\AntrianTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait, AntrianTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;
    public bool $isDone = false;

    /**
     * Cek apakah poli spesialis
     */
    private function isPoliSpesialis($poliId): bool
    {
        return DB::table('rsmst_polis')->where('poli_id', $poliId)->where('spesialis_status', '1')->exists();
    }

    /**
     * Proses Get TaskId Antrean dari BPJS
     */
    public function prosesTaskidAntrean()
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        $this->isLoading = true;

        try {
            $data = $this->findDataRJ($this->rjNo);
            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // Dapatkan noBooking
            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // Ambil task list dari BPJS jika poli spesialis
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                // Cek sudah dikirim atau belum (200 atau 208)
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

            $this->dispatch('refresh-after-rj.saved');
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
        class="!px-4 !py-2 text-sm {{ $isDone ? '!opacity-60' : '' }}" title="{{ $isDone ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mengambil TaskId Antrean dari BPJS' }}">

        <span wire:loading.remove wire:target="prosesTaskidAntrean">
            TaskId Antrean
        </span>

        <span wire:loading wire:target="prosesTaskidAntrean">
            <x-loading />
        </span>
    </x-primary-button>
</div>
