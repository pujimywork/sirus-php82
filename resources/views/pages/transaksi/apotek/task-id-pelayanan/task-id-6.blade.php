<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    public ?int $slsNo = null;
    public bool $isLoading = false;

    /* ===============================
     | PROSES TASKID6 — Masuk Apotek RI
     |
     | Pola sama dengan UGD task-id-6, tapi:
     |   - per slsNo (bukan rjNo)
     |   - update imtxn_slshdrs (waktu_masuk_pelayanan + no_antrian)
     |   - mirror ke rstxn_rihdrs.datadaftarri_json di apotekHdr[].taskId6
     =============================== */
    public function prosesTaskId6(): void
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

                if (!empty($sls->waktu_masuk_pelayanan)) {
                    $message = 'Masuk apotek sudah tercatat.';
                    return;
                }

                $riHdrNo = (int) $sls->rihdr_no;
                $klaimId = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->value('klaim_id');

                // Hitung no_antrian: BPJS Kronis = 999, lainnya auto-increment per hari
                if ($klaimId === 'KR') {
                    $noAntrian = 999;
                } else {
                    $todayStart = Carbon::today(config('app.timezone'));
                    $todayEnd = Carbon::today(config('app.timezone'))->endOfDay();
                    $maxNo = (int) DB::table('imtxn_slshdrs')
                        ->whereBetween('sls_date', [$todayStart, $todayEnd])
                        ->whereNotNull('no_antrian')
                        ->where('no_antrian', '!=', 999)
                        ->max('no_antrian');
                    $noAntrian = $maxNo + 1;
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'waktu_masuk_pelayanan' => DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')"),
                        'no_antrian' => $sls->no_antrian ?: $noAntrian,
                    ]);

                // Mirror ke datadaftarri_json (apotekHdr[].taskId6)
                $this->mirrorTaskId6($riHdrNo, $this->slsNo, $waktuSekarang);

                $message = "Berhasil masuk apotek pada {$waktuSekarang}.";
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

    private function mirrorTaskId6(int $riHdrNo, int $slsNo, string $now): void
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
        if (empty($data['apotekHdr'][$idx]['taskIdPelayanan']['taskId6'])) {
            $data['apotekHdr'][$idx]['taskIdPelayanan']['taskId6'] = $now;
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
    <x-primary-button wire:click="prosesTaskId6" wire:loading.attr="disabled" wire:target="prosesTaskId6"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId6 (Masuk Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId6">TaskId6</span>
        <span wire:loading wire:target="prosesTaskId6"><x-loading /></span>
    </x-primary-button>
</div>
