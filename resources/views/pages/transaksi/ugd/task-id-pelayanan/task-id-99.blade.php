<?php
// resources/views/pages/transaksi/ugd/..../taskid99-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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

    public function prosesTaskId99(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong', title: 'Peringatan');
            return;
        }

        $this->isLoading = true;
        $needUpdate = false;

        try {
            $data = $this->findDataUGD($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan', title: 'Error');
                return;
            }

            if (!empty($data['taskIdPelayanan']['taskId4'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat membatalkan antrian karena TaskId4 (Selesai Pelayanan) sudah tercatat', title: 'Gagal');
                return;
            }

            if (!empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat membatalkan antrian karena TaskId5 (Panggil Antrian) sudah tercatat', title: 'Gagal');
                return;
            }

            if (!empty($data['taskIdPelayanan']['taskId99'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId99 sudah tercatat: {$data['taskIdPelayanan']['taskId99']}", title: 'Info');
            }

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId99'])) {
                $data['taskIdPelayanan']['taskId99'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $needUpdate = true;
            }

            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId99Status'] ?? '';

                if ($status == 200 || $status == 208) {
                    $this->dispatch('toast', type: 'error', message: 'TaskId 99 sudah pernah dikirim ke BPJS', title: 'Info');
                    return;
                }

                $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId99'], config('app.timezone'))->timestamp * 1000;

                $response = AntrianTrait::update_antrean($noBooking, 99, $waktuTimestamp, '')->getOriginalContent();
                $code = $response['metadata']['code'] ?? '';
                $message = $response['metadata']['message'] ?? '';

                $data['taskIdPelayanan']['taskId99Status'] = $code;
                $needUpdate = true;

                $isSuccess = $code == 200 || $code == 208;
                $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 99: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
            }

            if ($needUpdate) {
                $existingData = $this->findDataUGD($this->rjNo);
                if (!empty($existingData)) {
                    $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                    $this->updateJsonUGD($this->rjNo, $existingData);
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
    <x-danger-button wire:click="prosesTaskId99" wire:loading.attr="disabled" wire:target="prosesTaskId99"
        class="!px-2 !py-1 text-xs" title="Klik untuk membatalkan antrian (hanya bisa sebelum TaskId4/5)">
        <span wire:loading.remove wire:target="prosesTaskId99">
            Batal
        </span>
        <span wire:loading wire:target="prosesTaskId99">
            <x-loading />
        </span>
    </x-danger-button>
</div>
