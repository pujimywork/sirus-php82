<?php
// resources/views/pages/transaksi/ugd/..../taskid6-ugd.blade.php

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

    public function prosesTaskId6(): void
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

            if (empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId5 (Panggil Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
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
                    'waktu_masuk_apt' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                ]);

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId6'])) {
                if (empty($data['noAntrianApotek'])) {
                    $eresepRacikanCount = collect($data['eresepRacikan'] ?? [])->count();
                    $jenisResep = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';

                    $refDate = Carbon::now(config('app.timezone'))->format('d/m/Y');
                    $query = DB::table('rstxn_ugdhdrs')->select('datadaftarugd_json')->where('rj_status', '!=', 'F')->where('klaim_id', '!=', 'KR')->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)->get();

                    $nomerAntrian = $query
                        ->filter(function ($item) {
                            $dataJson = json_decode($item->datadaftarugd_json, true) ?: [];
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

            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $statusApotek = $data['taskIdPelayanan']['tambahAntrianApotek'] ?? '';
                if (empty($statusApotek) || ($statusApotek != 200 && $statusApotek != 208)) {
                    $this->pushAntreanApotek($data, $noBooking, $data['noAntrianApotek']['jenisResep'], $data['noAntrianApotek']['noAntrian']);
                    $needUpdate = true;
                }

                $status = $data['taskIdPelayanan']['taskId6Status'] ?? '';
                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId6'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 6, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';

                    $data['taskIdPelayanan']['taskId6Status'] = $code;
                    $needUpdate = true;

                    $isSuccess = $code == 200 || $code == 208;
                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 6: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                }
            }

            if ($needUpdate) {
                $existingData = $this->findDataUGD($this->rjNo);
                if (!empty($existingData)) {
                    $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                    $existingData['noAntrianApotek'] = $data['noAntrianApotek'];
                    $this->updateJsonUGD($this->rjNo, $existingData);
                }
            }

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk apotek pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-ugd.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        } finally {
            $this->isLoading = false;
        }
    }

    private function pushAntreanApotek(array &$data, $noBooking, $jenisResep, $nomerAntrean): void
    {
        try {
            $response = AntrianTrait::tambah_antrean_farmasi($noBooking, $jenisResep, $nomerAntrean, '')->getOriginalContent();
            $code = $response['metadata']['code'] ?? '';
            $message = $response['metadata']['message'] ?? '';

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
            }

            $data['taskIdPelayanan']['tambahAntrianApotek'] = $code;

            $isSuccess = $code == 200 || $code == 208;
            $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: 'Antrian Apotek: ' . $message, title: $isSuccess ? 'Berhasil' : 'Gagal');
        } catch (\Exception $e) {
            $data['taskIdPelayanan']['tambahAntrianApotek'] = 500;
            $this->dispatch('toast', type: 'error', message: 'Gagal push antrian apotek: ' . $e->getMessage(), title: 'Error');
        }
    }
};
?>

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
