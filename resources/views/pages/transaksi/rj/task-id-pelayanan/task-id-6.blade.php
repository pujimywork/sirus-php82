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
     * Proses TaskId 6 - Selesai Konsultasi / Masuk Apotek
     */
    public function prosesTaskId6()
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

            // Cek apakah TaskId5 sudah ada
            if (empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId5 (Panggil Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            $waktuSekarang = Carbon::now()->format('d/m/Y H:i:s');
            $noBooking = $data['noBooking'] ?? null;

            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // UPDATE: waktu_masuk_apt di tabel rstxn_rjhdrs (ini bukan JSON, tetap perlu dijalankan)
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'waktu_masuk_apt' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                ]);

            // UPDATE taskId6 di data (hanya jika belum ada)
            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId6'])) {
                // BUAT noAntrianApotek jika belum ada
                if (empty($data['noAntrianApotek'])) {
                    $eresepRacikanCount = collect($data['eresepRacikan'] ?? [])->count();
                    $jenisResep = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';

                    // Hitung nomor antrian hari ini
                    $refDate = Carbon::now()->format('d/m/Y');
                    $query = DB::table('rstxn_rjhdrs')->select('datadaftarpolirj_json')->where('rj_status', '!=', 'F')->where('klaim_id', '!=', 'KR')->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)->get();

                    $nomerAntrian = $query
                        ->filter(function ($item) {
                            $dataJson = json_decode($item->datadaftarpolirj_json, true) ?: [];
                            return isset($dataJson['noAntrianApotek']);
                        })
                        ->count();

                    $noAntrian = $data['klaimId'] != 'KR' ? $nomerAntrian + 1 : 9999;

                    $data['noAntrianApotek'] = [
                        'noAntrian' => $noAntrian,
                        'jenisResep' => $jenisResep,
                    ];
                    $needUpdate = true;
                }

                $data['taskIdPelayanan']['taskId6'] = $waktuSekarang;
                $needUpdate = true;
            }

            // KIRIM antrian apotek ke BPJS jika poli spesialis
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                if (empty($data['taskIdPelayanan']['tambahAntrianApotek']) || ($data['taskIdPelayanan']['tambahAntrianApotek'] != 200 && $data['taskIdPelayanan']['tambahAntrianApotek'] != 208)) {
                    $this->pushAntreanApotek($data, $noBooking, $data['noAntrianApotek']['jenisResep'], $data['noAntrianApotek']['noAntrian']);
                    // pushAntreanApotek akan memodifikasi $data (passed by reference) dan set $needUpdate = true
                }
            }

            // KIRIM TaskId6 ke BPJS jika poli spesialis
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                if (empty($data['taskIdPelayanan']['taskId6Status']) || ($data['taskIdPelayanan']['taskId6Status'] != 200 && $data['taskIdPelayanan']['taskId6Status'] != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId6'])->timestamp * 1000;
                    $response = AntrianTrait::update_antrean($noBooking, 6, $waktuTimestamp, '')->getOriginalContent();

                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';

                    $data['taskIdPelayanan']['taskId6Status'] = $code;
                    $needUpdate = true;

                    $isSuccess = $code == 200 || $code == 208;
                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 6: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                }
            }

            // SATU KALI UPDATE: Simpan semua perubahan ke JSON jika ada yang berubah
            if ($needUpdate) {
                $this->updateJsonData($this->rjNo, $data);
            }

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk apotek pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('daftar-rj.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Push antrian apotek ke BPJS dan simpan response ke JSON
     * Dipanggil dengan passing by reference untuk menghindari multiple update
     */
    private function pushAntreanApotek(&$data, $noBooking, $jenisResep, $nomerAntrean): void
    {
        try {
            // Panggil API tambah antrian farmasi
            $response = AntrianTrait::tambah_antrean_farmasi($noBooking, $jenisResep, $nomerAntrean, '')->getOriginalContent();

            $code = $response['metadata']['code'] ?? '';
            $message = $response['metadata']['message'] ?? '';

            // Inisialisasi struktur jika belum ada
            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
            }

            // Simpan response ke dalam struktur data
            $data['taskIdPelayanan']['tambahAntrianApotek'] = $code;

            // Dispatch notifikasi
            $isSuccess = $code == 200 || $code == 208;
            $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: 'Antrian Apotek: ' . $message, title: $isSuccess ? 'Berhasil' : 'Gagal');

            // Set flag bahwa data berubah (needUpdate = true)
            // Karena parameter $data adalah reference, kita tidak perlu mengembalikan nilai
            // Flag akan dicek di method pemanggil
        } catch (\Exception $e) {
            $data['taskIdPelayanan']['tambahAntrianApotek'] = 500;
            $this->dispatch('toast', type: 'error', message: 'Gagal push antrian apotek: ' . $e->getMessage(), title: 'Error');
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
        $allowedFields = ['taskIdPelayanan', 'noAntrianApotek'];

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

<!-- Button untuk TaskId6 -->
<div class="inline-block">
    <x-primary-button wire:click="prosesTaskId6" wire:loading.attr="disabled" wire:target="prosesTaskId6"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId6 (Masuk Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId6">
            TaskId6
        </span>
        <span wire:loading wire:target="prosesTaskId6">
            <x-loading />
        </span>
    </x-primary-button>
</div>
