<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    public ?int $slsNo = null;
    public bool $isLoading = false;

    /* ===============================
     | PROSES TASKID7 — Keluar Apotek RI
     =============================== */
    public function prosesTaskId7(): void
    {
        if (empty($this->slsNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor SLS tidak boleh kosong.');
            return;
        }

        $this->isLoading = true;

        try {
            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            $message = '';

            DB::transaction(function () use ($waktuSekarang, &$message) {
                $sls = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
                if (!$sls) {
                    throw new \RuntimeException('Resep tidak ditemukan.');
                }

                if (empty($sls->waktu_masuk_pelayanan)) {
                    throw new \RuntimeException('Masuk apotek (TaskId6) harus dilakukan terlebih dahulu.');
                }

                if (!empty($sls->waktu_selesai_pelayanan)) {
                    $message = 'Keluar apotek sudah tercatat.';
                    return;
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'waktu_selesai_pelayanan' => DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                $this->mirrorTaskId7((int) $sls->rihdr_no, $this->slsNo, $waktuSekarang);

                $message = "Berhasil keluar apotek pada {$waktuSekarang}.";
            });

            $this->dispatch('toast', type: 'success', message: $message);
            $this->dispatch('refresh-after-antrian-apotek-ri.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }

    private function mirrorTaskId7(int $riHdrNo, int $slsNo, string $now): void
    {
        $row = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->first();
        if (!$row) {
            return;
        }

        try {
            $data = $row->datadaftarri_json
                ? json_decode($row->datadaftarri_json, true, 512, JSON_THROW_ON_ERROR)
                : [];
        } catch (\JsonException) {
            $data = [];
        }

        $data['apotekHdr'] ??= [];
        $idx = collect($data['apotekHdr'])->search(fn($h) => (int) ($h['slsNo'] ?? 0) === $slsNo);
        if ($idx === false) {
            $data['apotekHdr'][] = ['slsNo' => $slsNo];
            $idx = count($data['apotekHdr']) - 1;
        }

        $data['apotekHdr'][$idx]['taskIdPelayanan'] ??= [];
        if (empty($data['apotekHdr'][$idx]['taskIdPelayanan']['taskId7'])) {
            $data['apotekHdr'][$idx]['taskIdPelayanan']['taskId7'] = $now;
        }

        DB::table('rstxn_rihdrs')
            ->where('rihdr_no', $riHdrNo)
            ->update([
                'datadaftarri_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
    }
};
?>

<div class="inline-block">
    <x-primary-button wire:click="prosesTaskId7" wire:loading.attr="disabled" wire:target="prosesTaskId7"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId7 (Keluar Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId7">TaskId7</span>
        <span wire:loading wire:target="prosesTaskId7"><x-loading /></span>
    </x-primary-button>
</div>
