<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;

/**
 * Gabungan aksi Task ID apotek RI-Resep (TaskId6 Masuk Apotek, TaskId7 Keluar Apotek)
 * dalam SATU komponen per baris.
 *
 * Sebelumnya 2 komponen Livewire terpisah (task-id-6, task-id-7) di-mount per baris
 * di antrian-ri-resep → 2x jumlah komponen (pemicu payload berat / TooManyComponents).
 * Digabung: logika tiap aksi identik (per slsNo, update imtxn_slshdrs + mirror ke
 * rstxn_rihdrs.datadaftarri_json apotekHdr[].taskId6/7), spinner per tombol tetap
 * terisolasi via wire:target. Root display:contents + wrapper inline-block per tombol
 * → tata letak flex parent tidak berubah. Hanya dipakai di antrian-ri-resep.
 */
new class extends Component {
    public ?int $slsNo = null;
    // #[Reactive] → tombol ikut redup saat parent re-render (ri-resep-refresh-after-antrian.saved), tanpa remount.
    #[Reactive]
    public bool $isDone6 = false;
    #[Reactive]
    public bool $isDone7 = false;

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
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PROSES TASKID7 — Keluar Apotek RI
     =============================== */
    public function prosesTaskId7(): void
    {
        if (empty($this->slsNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor SLS tidak boleh kosong.');
            return;
        }

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
            $this->dispatch('ri-resep-refresh-after-antrian.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    private function mirrorTaskId6(int $riHdrNo, int $slsNo, string $now): void
    {
        $row = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->first();
        if (!$row) {
            return;
        }

        try {
            $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $riHdrNo, 'datadaftarri_json');
            $data = $jsonRaw !== ''
                ? json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR)
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

    private function mirrorTaskId7(int $riHdrNo, int $slsNo, string $now): void
    {
        $row = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->first();
        if (!$row) {
            return;
        }

        try {
            $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $riHdrNo, 'datadaftarri_json');
            $data = $jsonRaw !== ''
                ? json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR)
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

<div class="contents">
    {{-- TaskId6 (Masuk Apotek) --}}
    <div class="inline-block">
        <x-primary-button wire:click="prosesTaskId6" wire:loading.attr="disabled" wire:target="prosesTaskId6"
            class="!px-4 !py-2 text-sm {{ $isDone6 ? '!opacity-60' : '' }}" title="{{ $isDone6 ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mencatat TaskId6 (Masuk Apotek)' }}">
            <span wire:loading.remove wire:target="prosesTaskId6">TaskId6</span>
            <span wire:loading wire:target="prosesTaskId6"><x-loading /></span>
        </x-primary-button>
    </div>

    {{-- TaskId7 (Keluar Apotek) --}}
    <div class="inline-block">
        <x-primary-button wire:click="prosesTaskId7" wire:loading.attr="disabled" wire:target="prosesTaskId7"
            class="!px-4 !py-2 text-sm {{ $isDone7 ? '!opacity-60' : '' }}" title="{{ $isDone7 ? 'Sudah dijalankan, klik untuk update' : 'Klik untuk mencatat TaskId7 (Keluar Apotek)' }}">
            <span wire:loading.remove wire:target="prosesTaskId7">TaskId7</span>
            <span wire:loading wire:target="prosesTaskId7"><x-loading /></span>
        </x-primary-button>
    </div>
</div>
