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
     * Proses TaskId 3 - Masuk Antrian
     */
    public function prosesTaskId3()
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        $this->isLoading = true;
        $needUpdate = false; // Flag untuk menandakan perlu update

        try {
            $data = $this->findDataRJ($this->rjNo);
            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // Cek apakah sudah tercatat
            if (!empty($data['taskIdPelayanan']['taskId3'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId3 sudah tercatat: {$data['taskIdPelayanan']['taskId3']}", title: 'Info');
            }

            // Update taskId3 di data (hanya jika belum ada)
            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId3'])) {
                // Set taskId3 dengan waktu sekarang
                $waktuSekarang = Carbon::now()->format('d/m/Y H:i:s');
                $data['taskIdPelayanan']['taskId3'] = $waktuSekarang;
                $needUpdate = true;
            }

            // Dapatkan noBooking
            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // Push ke BPJS jika poli spesialis
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                // Cek sudah dikirim atau belum (200 atau 208)
                $status = $data['taskIdPelayanan']['taskId3Status'] ?? '';
                if (empty($status) || ($status != 200 && $status != 208)) {
                    // Konversi ke timestamp milisecond untuk BPJS
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId3'])->timestamp * 1000;
                    $response = AntrianTrait::update_antrean($noBooking, 3, $waktuTimestamp, '')->getOriginalContent();

                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';

                    // Simpan status response BPJS
                    $data['taskIdPelayanan']['taskId3Status'] = $code;
                    $needUpdate = true;

                    // Dispatch notifikasi
                    $isSuccess = $code == 200 || $code == 208;
                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 3: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 3 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            // SATU KALI UPDATE: Simpan semua perubahan ke JSON jika ada yang berubah
            if ($needUpdate) {
                $this->updateJsonData($this->rjNo, $data);
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
     * HANYA DIPANGGIL 1 KALI di akhir proses
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

<div class="inline-block">
    <x-primary-button wire:click="prosesTaskId3" wire:loading.attr="disabled" wire:target="prosesTaskId3"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId3 (Masuk Antrian)">

        <span wire:loading.remove wire:target="prosesTaskId3">
            TaskId3
        </span>

        <span wire:loading wire:target="prosesTaskId3">
            <x-loading />
        </span>
    </x-primary-button>
</div>
