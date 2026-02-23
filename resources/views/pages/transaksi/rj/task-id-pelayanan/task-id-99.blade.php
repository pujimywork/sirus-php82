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

    /**
     * Cek apakah poli spesialis
     */
    private function isPoliSpesialis($poliId): bool
    {
        return DB::table('rsmst_polis')->where('poli_id', $poliId)->where('spesialis_status', '1')->exists();
    }

    /**
     * Proses TaskId 99 - Batal Antrian
     */
    public function prosesTaskId99()
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

            // Cek apakah TaskId4 atau TaskId5 sudah ada (pelayanan sudah berjalan)
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

            // Update taskId99 di data
            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
            }

            if (empty($data['taskIdPelayanan']['taskId99'])) {
                // Set taskId99 dengan waktu sekarang
                $waktuSekarang = Carbon::now()->format('d/m/Y H:i:s');
                $data['taskIdPelayanan']['taskId99'] = $waktuSekarang;
                $this->updateJsonData($this->rjNo, $data);
            }

            // Dapatkan noBooking
            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // Push ke BPJS jika poli spesialis
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                // Cek sudah dikirim atau belum
                if (($data['taskIdPelayanan']['taskId99Status'] ?? '') == 200 || ($data['taskIdPelayanan']['taskId99Status'] ?? '') == 208) {
                    $this->dispatch('toast', 'error', message: 'TaskId 99: sudah dilakukan.');
                    return;
                }

                // Konversi ke timestamp milisecond untuk BPJS
                $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId99'])->timestamp * 1000;
                $response = AntrianTrait::update_antrean($noBooking, 99, $waktuTimestamp, '')->getOriginalContent();

                $code = $response['metadata']['code'] ?? '';
                $message = $response['metadata']['message'] ?? '';

                // Simpan status response BPJS
                $data['taskIdPelayanan']['taskId99Status'] = $code;
                $this->updateJsonData($this->rjNo, $data);

                // Dispatch notifikasi
                $isSuccess = $code == 200 || $code == 208;
                $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 99: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
            }

            $this->dispatch('daftar-rj.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Update JSON ke database dengan pattern merge yang aman
     */
    private function updateJsonData($rjNo, $dataDaftarPoliRJ): void
    {
        if (empty($rjNo) || empty($dataDaftarPoliRJ)) {
            return;
        }

        // Whitelist field yang boleh diupdate
        $allowedFields = ['taskIdPelayanan'];

        // Ambil data existing dari database
        $existingData = $this->findDataRJ($rjNo);

        if (empty($existingData)) {
            return;
        }

        // Ambil field yang diizinkan dari data baru
        $formData = array_intersect_key($dataDaftarPoliRJ, array_flip($allowedFields));

        // Merge dengan data existing
        $mergedRJ = array_replace_recursive($existingData, $formData);
        // Pastikan rjNo tetap sama
        $mergedRJ['rjNo'] = $rjNo;

        // Simpan JSON
        $this->updateJsonRJ($rjNo, $mergedRJ);
    }
};
?>

<!-- Button untuk TaskId99 (Batal Antrian) -->
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
