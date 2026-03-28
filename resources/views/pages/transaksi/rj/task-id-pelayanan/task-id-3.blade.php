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

    /* ===============================
     | PROSES TASK ID 3
     |
     | Alur:
     | 1. Guard rjNo + data kosong
     | 2. Cek taskId3 sudah ada — set jika belum
     | 3. Cek poli spesialis → push ke BPJS jika perlu (DI LUAR transaksi)
     | 4. lockRJRow + patch hanya key taskIdPelayanan ke DB
    =============================== */
    public function prosesTaskId3(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        $this->isLoading = true;

        try {
            // 2. Ambil data RJ — tanpa lock dulu, hanya untuk baca awal
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // 3. Validasi noBooking sebelum lanjut
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 4. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            // 5. Notifikasi jika taskId3 sudah pernah tercatat
            if (!empty($data['taskIdPelayanan']['taskId3'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId3 sudah tercatat: {$data['taskIdPelayanan']['taskId3']}", title: 'Info');
            }

            // 6. Set taskId3 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId3'])) {
                $data['taskIdPelayanan']['taskId3'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            }

            // 7. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId3Status'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId3'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 3, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $isSuccess = $code == 200 || $code == 208;

                    $data['taskIdPelayanan']['taskId3Status'] = $code;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 3: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 3 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            // 8. Simpan ke DB — lock + patch hanya key taskIdPelayanan
            DB::transaction(function () use ($data) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock untuk menghindari overwrite perubahan lain
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                // Patch hanya key taskIdPelayanan — key lain tidak tersentuh
                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];

                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('refresh-after-rj.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        } finally {
            $this->isLoading = false;
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function isPoliSpesialis($poliId): bool
    {
        return DB::table('rsmst_polis')->where('poli_id', $poliId)->where('spesialis_status', '1')->exists();
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
