<?php
// resources/views/pages/transaksi/ugd/..../taskid4-ugd.blade.php

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

    public function prosesTaskId4(): void
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

            if (empty($data['taskIdPelayanan']['taskId3'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId3 (Masuk Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'waktu_masuk_ugd' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                ]);

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (!empty($data['taskIdPelayanan']['taskId4'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId4 sudah tercatat: {$data['taskIdPelayanan']['taskId4']}", title: 'Info');
            }

            if (empty($data['taskIdPelayanan']['taskId4'])) {
                $data['taskIdPelayanan']['taskId4'] = $waktuSekarang;
                $needUpdate = true;
            }

            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId4Status'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId4'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 4, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';

                    $data['taskIdPelayanan']['taskId4Status'] = $code;
                    $needUpdate = true;

                    $isSuccess = $code == 200 || $code == 208;
                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 4: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 4 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            if ($needUpdate) {
                $existingData = $this->findDataUGD($this->rjNo);
                if (!empty($existingData)) {
                    $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                    $this->updateJsonUGD($this->rjNo, $existingData);
                }
            }

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk UGD pada {$waktuSekarang}", title: 'Berhasil');
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
    <x-primary-button wire:click="prosesTaskId4" wire:loading.attr="disabled" wire:target="prosesTaskId4"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId4 (Masuk UGD)">
        <span wire:loading.remove wire:target="prosesTaskId4">
            TaskId4
        </span>
        <span wire:loading wire:target="prosesTaskId4">
            <x-loading />
        </span>
    </x-primary-button>
</div>
