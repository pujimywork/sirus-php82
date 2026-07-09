<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;

/**
 * KOMPONEN AKSI Task ID apotek RI-Resep (TaskId6 Masuk Apotek, TaskId7 Keluar
 * Apotek) — berisi SEMUA fungsi/logika.
 *
 * Arsitektur "cetak-pattern": komponen ini di-mount SEKALI sebagai sibling di
 * antrian-ri-resep (bukan per baris). Tombol tiap baris ada di antrian-ri-resep
 * dan memicu komponen ini via
 * wire:click="$dispatch('task-id-apotek-proses-ri', { slsNo, aksi })" (aksi
 * Livewire, bukan Alpine).
 *
 * Sebelumnya komponen ini di-mount per baris dengan prop #[Reactive] → saat list
 * re-render pasca 'ri-resep-refresh-after-antrian.saved', semua child reactive ikut
 * satu batch → TooManyComponents saat baris banyak. Dengan mount sekali, batch tak
 * lagi skala jumlah baris.
 *
 * ⚠️ Logika tiap aksi (per slsNo, update imtxn_slshdrs + mirror ke
 *    rstxn_rihdrs.datadaftarri_json apotekHdr[].taskId6/7) IDENTIK versi lama.
 */
new class extends Component {
    public ?int $slsNo = null;

    /* ===============================
     | ROUTER — dipicu tombol baris via wire:click $dispatch
     | Detail event: { slsNo, aksi } dengan aksi ∈ {'6','7'}.
     =============================== */
    #[On('task-id-apotek-proses-ri')]
    public function proses(int $slsNo, string $aksi): void
    {
        $this->slsNo = $slsNo;

        match ($aksi) {
            '6' => $this->prosesTaskId6(),
            '7' => $this->prosesTaskId7(),
            default => null,
        };
    }

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

                // E-resep RI menstempel waktu_masuk_pelayanan saat dokter KIRIM resep
                // (sendToApotek → sysdate), jadi waktu masuk bisa sudah terisi di sini.
                // TaskId6 harus tetap MENOMORI antrian bila no_antrian belum ada —
                // dulu early-return "sudah tercatat" membuat no_antrian tak pernah dibuat.
                $sudahMasuk = !empty($sls->waktu_masuk_pelayanan);
                $sudahAntri = !empty($sls->no_antrian);

                if ($sudahMasuk && $sudahAntri) {
                    $message = 'Masuk apotek sudah tercatat.';
                    return;
                }

                $riHdrNo = (int) $sls->rihdr_no;
                $update = [];

                if (!$sudahMasuk) {
                    $update['waktu_masuk_pelayanan'] = DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')");
                }

                if (!$sudahAntri) {
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

                    $update['no_antrian'] = $noAntrian;
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update($update);

                // Mirror ke datadaftarri_json (apotekHdr[].taskId6) — bila waktu masuk
                // sudah terisi sebelumnya, pakai waktu itu (bukan waktu klik sekarang).
                $waktuMirror = $waktuSekarang;
                if ($sudahMasuk) {
                    $waktuMirror = DB::table('imtxn_slshdrs')
                        ->where('sls_no', $this->slsNo)
                        ->selectRaw("to_char(waktu_masuk_pelayanan,'dd/mm/yyyy hh24:mi:ss') as w")
                        ->value('w') ?: $waktuSekarang;
                }
                $this->mirrorTaskId6($riHdrNo, $this->slsNo, $waktuMirror);

                $message = $sudahMasuk
                    ? 'Nomor antrian apotek dibuat (waktu masuk sudah tercatat saat resep dikirim).'
                    : "Berhasil masuk apotek pada {$waktuSekarang}.";
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

{{-- Indikator proses global (host tak punya tombol sendiri — tombol ada di baris antrian-ri-resep). --}}
<div wire:key="task-id-apotek-actions-ri-host">
    <div wire:loading wire:target="proses, prosesTaskId6, prosesTaskId7"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-blue-600 rounded-xl shadow-lg dark:bg-blue-500">
        <x-loading />
        Memproses Task ID Apotek…
    </div>
</div>
