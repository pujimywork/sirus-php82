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
     | PROSES TASK ID 7 (Keluar Apotek)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId6 prerequisite
     | 2. Set taskId7 timestamp jika belum ada
     | 3. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
     | 4. lockRJRow + update waktu_selesai_pelayanan + patch taskIdPelayanan — ATOMIK
    =============================== */
    public function prosesTaskId7(): void
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

            // 3. Validasi prerequisite: taskId6 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId6'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId6 (Masuk Apotek) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            // 4. Validasi noBooking
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 5. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            // 6. Notifikasi jika taskId7 sudah pernah tercatat
            if (!empty($data['taskIdPelayanan']['taskId7'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId7 sudah tercatat: {$data['taskIdPelayanan']['taskId7']}", title: 'Info');
            }

            // 7. Set taskId7 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId7'])) {
                $data['taskIdPelayanan']['taskId7'] = $waktuSekarang;
            }

            // 8. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId7Status'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId7'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 7, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $isSuccess = $code == 200 || $code == 208;

                    $data['taskIdPelayanan']['taskId7Status'] = $code;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 7: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 7 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            // 9. Simpan ke DB — lock + update waktu_selesai_pelayanan + patch taskIdPelayanan atomik
            DB::transaction(function () use ($data, $waktuSekarang) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock — patch hanya key taskIdPelayanan
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                // Update waktu_selesai_pelayanan di header — atomik dengan JSON update
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_selesai_pelayanan' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil keluar apotek pada {$waktuSekarang}", title: 'Berhasil');
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
    <x-primary-button wire:click="prosesTaskId7" wire:loading.attr="disabled" wire:target="prosesTaskId7"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId7 (Keluar Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId7">
            TaskId7
        </span>
        <span wire:loading wire:target="prosesTaskId7">
            <x-loading />
        </span>
    </x-primary-button>
</div>
