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
     | PROSES TASK ID 99 (Batal Antrian)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking
     | 2. Guard: tidak bisa batal jika taskId4 atau taskId5 sudah ada
     | 3. Guard: tidak bisa batal jika taskId99 sudah pernah dikirim sukses ke BPJS
     | 4. Set taskId99 timestamp jika belum ada
     | 5. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
     | 6. lockRJRow + patch hanya key taskIdPelayanan — ATOMIK
    =============================== */
    public function prosesTaskId99(): void
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

            // 3. Guard: tidak bisa batal jika taskId4 sudah ada
            if (!empty($data['taskIdPelayanan']['taskId4'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat membatalkan antrian karena TaskId4 (Masuk Poli) sudah tercatat', title: 'Gagal');
                return;
            }

            // 4. Guard: tidak bisa batal jika taskId5 sudah ada
            if (!empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat membatalkan antrian karena TaskId5 (Panggil Antrian) sudah tercatat', title: 'Gagal');
                return;
            }

            // 5. Validasi noBooking
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 6. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            // 7. Notifikasi jika taskId99 sudah pernah tercatat
            if (!empty($data['taskIdPelayanan']['taskId99'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId99 sudah tercatat: {$data['taskIdPelayanan']['taskId99']}", title: 'Info');
            }

            // 8. Set taskId99 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId99'])) {
                $data['taskIdPelayanan']['taskId99'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            }

            // 9. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId99Status'] ?? '';

                // Berbeda dengan taskId lain: jika sudah sukses, HARD STOP — tidak boleh kirim ulang pembatalan
                if ($status == 200 || $status == 208) {
                    $this->dispatch('toast', type: 'error', message: 'TaskId 99 sudah pernah dikirim ke BPJS, tidak dapat dikirim ulang', title: 'Info');
                    return;
                }

                $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId99'], config('app.timezone'))->timestamp * 1000;

                $response = AntrianTrait::update_antrean($noBooking, 99, $waktuTimestamp, '')->getOriginalContent();
                $code = $response['metadata']['code'] ?? '';
                $message = $response['metadata']['message'] ?? '';
                $isSuccess = $code == 200 || $code == 208;

                $data['taskIdPelayanan']['taskId99Status'] = $code;

                $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 99: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
            }

            // 10. Simpan ke DB — lock + patch hanya key taskIdPelayanan
            DB::transaction(function () use ($data) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock — patch hanya key taskIdPelayanan
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

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
