<?php
// resources/views/pages/transaksi/ugd/..../taskid99-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

/**
 * KOMPONEN AKSI Batal antrian apotek UGD (TaskId99) — berisi fungsi.
 *
 * Cetak-pattern: di-mount SEKALI sebagai sibling di antrian-apotek-ugd. Tombol Batal
 * tiap baris ada di antrian-apotek-ugd dan memicu komponen ini via
 * wire:click="$dispatch('task-id-batal-proses-ugd', { rjNo })" (aksi Livewire).
 * Nol komponen Livewire per baris. Logika prosesTaskId99 IDENTIK versi lama.
 */
new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;

    /* ===============================
     | ROUTER — dipicu tombol Batal baris via wire:click $dispatch
     =============================== */
    #[On('task-id-batal-proses-ugd')]
    public function proses(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->prosesTaskId99();
    }

    /* ===============================
     | PROSES TASKID99 — Batalkan Antrian
     |
     | Pola:
     |   1. Guard awal (empty rjNo)
     |   2. DB::transaction: lockUGDRow → findDataUGD → guard taskId6/7/99
     |      → set taskId99 → updateJsonUGD
     |   3. dispatch DI LUAR transaksi
     =============================== */
    public function prosesTaskId99(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong.');
            return;
        }

        try {
            $message = '';

            DB::transaction(function () use (&$message) {
                // 1. Lock row dulu — hard stop setelah lock
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // 3. Hard stop — tidak bisa batalkan jika taskId6/7 sudah ada
                if (!empty($data['taskIdPelayanan']['taskId6'] ?? null)) {
                    throw new \RuntimeException('Tidak dapat membatalkan antrian karena TaskId6 (Masuk Apotek) sudah tercatat.');
                }

                if (!empty($data['taskIdPelayanan']['taskId7'] ?? null)) {
                    throw new \RuntimeException('Tidak dapat membatalkan antrian karena TaskId7 (Keluar Apotek) sudah tercatat.');
                }

                // 4. Guard idempoten — jika taskId99 sudah ada, skip update
                if (!empty($data['taskIdPelayanan']['taskId99'])) {
                    $message = "TaskId99 sudah tercatat pada {$data['taskIdPelayanan']['taskId99']}.";
                    return;
                }

                // 5. Set taskId99 + simpan JSON
                $data['taskIdPelayanan'] ??= [];
                $data['taskIdPelayanan']['taskId99'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                $this->updateJsonUGD($this->rjNo, $data);

                $message = 'Antrian berhasil dibatalkan.';
            });

            // 6. Notify + dispatch — di luar transaksi
            $this->dispatch('toast', type: 'success', message: $message);
            $this->dispatch('refresh-after-ugd.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
};
?>

{{-- Indikator proses global (host tak punya tombol sendiri — tombol Batal ada di baris antrian-apotek-ugd). --}}
<div wire:key="task-id-99-ugd-host">
    <div wire:loading wire:target="proses, prosesTaskId99"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-rose-600 rounded-xl shadow-lg dark:bg-rose-500">
        <x-loading />
        Membatalkan antrian…
    </div>
</div>
