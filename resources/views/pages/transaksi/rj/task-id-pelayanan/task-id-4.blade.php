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
     * Proses TaskId 4 - Masuk Poli
     */
    public function prosesTaskId4()
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

            // Cek apakah TaskId3 sudah ada
            if (empty($data['taskIdPelayanan']['taskId3'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId3 (Masuk Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $waktuSekarang = Carbon::now()->format('d/m/Y H:i:s');
            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // UPDATE: waktu_masuk_poli di tabel rstxn_rjhdrs
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'waktu_masuk_poli' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                ]);

            // UPDATE taskId4 di data (hanya jika belum ada)
            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            // Cek apakah sudah tercatat
            if (!empty($data['taskIdPelayanan']['taskId4'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId4 sudah tercatat: {$data['taskIdPelayanan']['taskId4']}", title: 'Info');
            }

            if (empty($data['taskIdPelayanan']['taskId4'])) {
                $data['taskIdPelayanan']['taskId4'] = $waktuSekarang;
                $needUpdate = true;
            }

            // KIRIM TaskId4 ke BPJS jika poli spesialis
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                // Cek sudah dikirim atau belum (200 atau 208)
                if (empty($data['taskIdPelayanan']['taskId4Status']) || ($data['taskIdPelayanan']['taskId4Status'] != 200 && $data['taskIdPelayanan']['taskId4Status'] != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId4'])->timestamp * 1000;
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

            // SATU KALI UPDATE: Simpan semua perubahan ke JSON jika ada yang berubah
            if ($needUpdate) {
                $this->updateJsonData($this->rjNo, $data);
            }

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk poli pada {$waktuSekarang}", title: 'Berhasil');
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
        $mergedRJ['rjNo'] = $rjNo;

        // Simpan JSON
        $this->updateJsonRJ($rjNo, $mergedRJ);
    }
};
?>

<!-- Button untuk TaskId4 -->
<div class="inline-block">
    <x-primary-button wire:click="prosesTaskId4" wire:loading.attr="disabled" wire:target="prosesTaskId4"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId4 (Masuk Poli)">
        <span wire:loading.remove wire:target="prosesTaskId4">
            TaskId4
        </span>
        <span wire:loading wire:target="prosesTaskId4">
            <x-loading />
        </span>
    </x-primary-button>
</div>
