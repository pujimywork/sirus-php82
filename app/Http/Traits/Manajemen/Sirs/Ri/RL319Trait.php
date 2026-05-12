<?php

namespace App\Http\Traits\Manajemen\Sirs\Ri;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.19 Cara Bayar (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ 9 baris cara pembayaran resmi SIRS:                                 │
 * │   1     Membayar Sendiri                                            │
 * │   2.1   Asuransi JKN (BPJS Kesehatan)                               │
 * │   2.2   Asuransi Pemerintah Daerah (Jamkesda)                       │
 * │   2.3   Asuransi Pemerintah Lainnya                                 │
 * │   2.4   Asuransi Swasta                                             │
 * │   3     Keringanan (Cost Sharing)                                   │
 * │   4.1   Kartu Sehat                                                 │
 * │   4.2   Keterangan Tidak Mampu                                      │
 * │   4.3   Lain-Lain                                                   │
 * │                                                                     │
 * │ Periode: TAHUNAN (1 Jan - 31 Des).                                  │
 * │                                                                     │
 * │ Kolom (6):                                                          │
 * │   - Pasien Rawat Inap : Jumlah Pasien Keluar | Jumlah Lama Dirawat  │
 * │   - Jumlah Pasien Rawat Jalan (single col)                          │
 * │   - Pasien Rawat Jalan: Laboratorium | Radiologi | Lain-lain        │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ MAPPING KLAIM_ID → SIRS BUCKET (eksplisit)                          │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Strategi 2 lapis (mirip POLI_TO_SIRS_RL35):                         │
 * │   1. Eksplisit lookup di KLAIM_ID_TO_SIRS — 13 klaim aktual         │
 * │      RSI Madinah, override pasti.                                   │
 * │   2. Fallback keyword match (untuk klaim_id baru yang belum         │
 * │      ditambahkan di constant — mis. asuransi swasta baru).          │
 * │                                                                     │
 * │ Mapping eksplisit (per data master & instruksi user 2026-05-10):    │
 * │   PB  PBI N/A             BPJS  → 2.1 BPJS Kesehatan                │
 * │   JM  JKN MANDIRI         BPJS  → 2.1 BPJS Kesehatan                │
 * │   HI  ASKES (jgn dipakai) BPJS  → 2.1 BPJS Kesehatan (ASKES legacy) │
 * │   JR  JASA RAHARJA        UMUM  → 2.3 Asuransi Pemerintah Lainnya   │
 * │   JS  BPJS KETENAGAKERJAAN BPJS → 2.3 Asuransi Pemerintah Lainnya   │
 * │       (BPJS-TK ≠ BPJS Kesehatan)                                    │
 * │   TP  TNI POLRI (jgn dipakai) BPJS → 2.3 Asuransi Pemerintah Lain   │
 * │   JML ASURANSI LAIN       BPJS  → 2.4 Asuransi Swasta               │
 * │   JK  JAMKESMAS           BPJS  → 4.1 Kartu Sehat (Jamkesmas legacy)│
 * │   UM  UMUM                UMUM  → 1   Membayar Sendiri              │
 * │   KW  KRYAWN MDN (jgn dipakai) UMUM → 1 Membayar Sendiri            │
 * │   HC  HOME CARE           UMUM  → 1   Membayar Sendiri              │
 * │   KR  KRONIS              -     → 1   Membayar Sendiri (per user)   │
 * │   DK  DOKEL               DOKEL → 4.3 Lain-Lain                     │
 * │                                                                     │
 * │ Filter: status valid (RJ/UGD rj_status NOT IN ('A','F'), RI         │
 * │ ri_status != 'F'). Kronis (KR) TIDAK di-exclude di RL 3.19 (beda    │
 * │ dari RL lain).                                                      │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL319Trait
{
    /**
     * Mapping eksplisit klaim_id → SIRS bucket id (1-9).
     * Disusun berdasarkan 13 klaim aktual rsmst_klaimtypes RSI Madinah.
     * Untuk klaim_id baru yang tidak ada di map ini, fallback ke
     * keyword match di classifyRl319Klaim().
     */
    private const KLAIM_ID_TO_SIRS = [
        // BPJS Kesehatan (2.1)
        'PB'  => 2,  // PBI N/A (BPJS)
        'JM'  => 2,  // JKN MANDIRI (BPJS)
        'HI'  => 2,  // ASKES (legacy BPJS — jgn dipakai)

        // Asuransi Pemerintah Lainnya (2.3)
        'JR'  => 4,  // JASA RAHARJA
        'JS'  => 4,  // BPJS KETENAGAKERJAAN (≠ BPJS Kesehatan, tapi pemerintah)
        'TP'  => 4,  // TNI POLRI (jgn dipakai)

        // Asuransi Swasta (2.4)
        'JML' => 5,  // ASURANSI LAIN

        // Kartu Sehat / Jamkesmas (4.1)
        'JK'  => 7,  // JAMKESMAS (legacy kartu sehat / KIS predecessor)

        // Membayar Sendiri (1)
        'UM'  => 1,  // UMUM
        'KW'  => 1,  // KRYAWN MDN (karyawan internal — jgn dipakai)
        'HC'  => 1,  // HOME CARE
        'KR'  => 1,  // KRONIS (per instruksi user — bayar sendiri, BUKAN BPJS rujuk balik)

        // Lain-Lain (4.3)
        'DK'  => 9,  // DOKEL
    ];

    /** Daftar Cara Pembayaran RL 3.19 SIRS Kemenkes. */
    public const CARA_BAYAR_LIST = [
        ['id' => 1, 'no' => '1',   'nama' => 'Membayar Sendiri'],
        ['id' => 2, 'no' => '2.1', 'nama' => 'Asuransi JKN (BPJS Kesehatan)'],
        ['id' => 3, 'no' => '2.2', 'nama' => 'Asuransi Pemerintah Daerah (Jamkesda)'],
        ['id' => 4, 'no' => '2.3', 'nama' => 'Asuransi Pemerintah Lainnya'],
        ['id' => 5, 'no' => '2.4', 'nama' => 'Asuransi Swasta'],
        ['id' => 6, 'no' => '3',   'nama' => 'Keringanan (Cost Sharing)'],
        ['id' => 7, 'no' => '4.1', 'nama' => 'Kartu Sehat'],
        ['id' => 8, 'no' => '4.2', 'nama' => 'Keterangan Tidak Mampu'],
        ['id' => 9, 'no' => '4.3', 'nama' => 'Lain-Lain'],
    ];

    /**
     * Compute 1 tahun laporan. Output: 9 row × 6 metrik.
     */
    protected function computeRL319(int $tahun): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        // Inisialisasi bucket
        $buckets = [];
        foreach (self::CARA_BAYAR_LIST as $cb) {
            $buckets[$cb['id']] = [
                'ranap_keluar'    => 0,
                'ranap_lama'      => 0,
                'rajal_pasien'    => 0,
                'rajal_lab'       => 0,
                'rajal_radiologi' => 0,
                'rajal_lain'      => 0,
            ];
        }

        // Pre-load klaim master untuk classifier (klaim_id → bucket id)
        $klaimMap = $this->loadRl319KlaimMap();

        // ─── RI: Pasien Keluar + Lama Dirawat per klaim ──────────────
        $riRows = DB::table('rstxn_rihdrs')
            ->whereBetween('exit_date', [$start, $end])
            ->whereNotNull('exit_date')
            ->where(fn($q) => $q->whereNull('ri_status')->orWhere('ri_status', '!=', 'F'))
            ->select('klaim_id', DB::raw('COUNT(*) as keluar'), DB::raw('SUM(NVL(exit_date - entry_date, 0)) as lama'))
            ->groupBy('klaim_id')
            ->get();

        foreach ($riRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['ranap_keluar'] += (int) $r->keluar;
            $buckets[$bid]['ranap_lama']   += (int) round((float) $r->lama);
        }

        // ─── RJ: Jumlah pasien rawat jalan per klaim ─────────────────
        $rjRows = DB::table('rstxn_rjhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
            ->select('klaim_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('klaim_id')
            ->get();

        foreach ($rjRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['rajal_pasien'] += (int) $r->cnt;
        }

        // ─── UGD: hitung sebagai pasien rawat jalan juga ─────────────
        $ugdRows = DB::table('rstxn_ugdhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
            ->select('klaim_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('klaim_id')
            ->get();

        foreach ($ugdRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['rajal_pasien'] += (int) $r->cnt;
        }

        // ─── RJ Lab: distinct rj_no yang punya order lab ─────────────
        $rjLabRows = DB::table('rstxn_rjhdrs as h')
            ->join('rstxn_rjlabs as l', 'l.rj_no', '=', 'h.rj_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select('h.klaim_id', DB::raw('COUNT(DISTINCT h.rj_no) as cnt'))
            ->groupBy('h.klaim_id')
            ->get();

        foreach ($rjLabRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['rajal_lab'] += (int) $r->cnt;
        }

        // ─── UGD Lab ─────────────────────────────────────────────────
        $ugdLabRows = DB::table('rstxn_ugdhdrs as h')
            ->join('rstxn_ugdlabs as l', 'l.rj_no', '=', 'h.rj_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select('h.klaim_id', DB::raw('COUNT(DISTINCT h.rj_no) as cnt'))
            ->groupBy('h.klaim_id')
            ->get();

        foreach ($ugdLabRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['rajal_lab'] += (int) $r->cnt;
        }

        // ─── RJ Rad: distinct rj_no yang punya order rad ─────────────
        $rjRadRows = DB::table('rstxn_rjhdrs as h')
            ->join('rstxn_rjrads as r', 'r.rj_no', '=', 'h.rj_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select('h.klaim_id', DB::raw('COUNT(DISTINCT h.rj_no) as cnt'))
            ->groupBy('h.klaim_id')
            ->get();

        foreach ($rjRadRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['rajal_radiologi'] += (int) $r->cnt;
        }

        // ─── UGD Rad ─────────────────────────────────────────────────
        $ugdRadRows = DB::table('rstxn_ugdhdrs as h')
            ->join('rstxn_ugdrads as r', 'r.rj_no', '=', 'h.rj_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select('h.klaim_id', DB::raw('COUNT(DISTINCT h.rj_no) as cnt'))
            ->groupBy('h.klaim_id')
            ->get();

        foreach ($ugdRadRows as $r) {
            $bid = $klaimMap[(string) ($r->klaim_id ?? '')] ?? 9;
            $buckets[$bid]['rajal_radiologi'] += (int) $r->cnt;
        }

        // ─── Lain-lain rajal: pasien_rajal - lab - rad (approx) ──────
        // Note: lab/rad bisa overlap pada 1 visit; angka ini approximation
        // konservatif bawah. Untuk akurat butuh distinct rj_no yang TIDAK
        // punya lab AND TIDAK punya rad — tapi di sini pakai max(0, sisa).
        foreach ($buckets as $bid => &$b) {
            $b['rajal_lain'] = max(0, $b['rajal_pasien'] - $b['rajal_lab'] - $b['rajal_radiologi']);
        }
        unset($b);

        // Build flat output
        $out = [];
        foreach (self::CARA_BAYAR_LIST as $cb) {
            $out[] = ['id' => $cb['id'], 'no' => $cb['no'], 'nama' => $cb['nama']] + $buckets[$cb['id']];
        }
        return $out;
    }

    /**
     * Lookup klaim_id → SIRS bucket id (1-9). Strategi 2 lapis:
     *   1. Eksplisit lookup di KLAIM_ID_TO_SIRS (13 klaim RSI Madinah).
     *   2. Fallback keyword match classifyRl319Klaim() untuk klaim_id baru
     *      yang belum ditambahkan di constant.
     */
    private function loadRl319KlaimMap(): array
    {
        $rows = DB::table('rsmst_klaimtypes')
            ->select('klaim_id', 'klaim_desc', 'klaim_status')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $kid = (string) ($r->klaim_id ?? '');
            // Strategi 1: eksplisit map by klaim_id
            if ($kid !== '' && isset(self::KLAIM_ID_TO_SIRS[$kid])) {
                $map[$kid] = self::KLAIM_ID_TO_SIRS[$kid];
                continue;
            }
            // Strategi 2: fallback keyword match
            $map[$kid] = $this->classifyRl319Klaim(
                $kid,
                (string) ($r->klaim_desc ?? ''),
                (string) ($r->klaim_status ?? '')
            );
        }
        return $map;
    }

    /**
     * Klasifikasi klaim → SIRS bucket id (1-9). Priority chain.
     */
    protected function classifyRl319Klaim(string $klaimId, string $klaimDesc, string $klaimStatus): int
    {
        $statusU = mb_strtoupper(trim($klaimStatus));
        $descU   = mb_strtoupper(trim($klaimDesc));
        $idU     = mb_strtoupper(trim($klaimId));

        // #1 BPJS / Kronis (rujuk balik BPJS)
        if ($statusU === 'BPJS' || $idU === 'JM' || $idU === 'KR' || str_contains($descU, 'BPJS') || str_contains($descU, 'JKN')) {
            return 2; // 2.1 BPJS Kesehatan
        }

        // #2 Jamkesda
        if (str_contains($descU, 'JAMKESDA')) {
            return 3; // 2.2
        }

        // #3 Asuransi Pemerintah Lainnya
        if (str_contains($descU, 'JAMPERSAL') || str_contains($descU, 'JKD') ||
            str_contains($descU, 'JASA RAHARJA') || str_contains($descU, 'PEMERINTAH')) {
            return 4; // 2.3
        }

        // #4 Asuransi Swasta
        if (str_contains($descU, 'INHEALTH') || str_contains($descU, 'MANDIRI INHEALTH') ||
            str_contains($descU, 'PRUDENTIAL') || str_contains($descU, 'AXA') ||
            str_contains($descU, 'ALLIANZ') || str_contains($descU, 'AVRIST') ||
            str_contains($descU, 'AETNA') || str_contains($descU, 'ASURANSI') ||
            str_contains($descU, 'INSURANCE')) {
            return 5; // 2.4
        }

        // #5 Cost Sharing / Keringanan
        if (str_contains($descU, 'COST SHARING') || str_contains($descU, 'KERINGANAN')) {
            return 6; // 3
        }

        // #6 Kartu Sehat / Jamkesmas
        if (str_contains($descU, 'KARTU SEHAT') || str_contains($descU, 'JAMKESMAS') ||
            str_contains($descU, 'KIS')) {
            return 7; // 4.1
        }

        // #7 Tidak Mampu / Gakin / SKTM
        if (str_contains($descU, 'TIDAK MAMPU') || str_contains($descU, 'GAKIN') ||
            str_contains($descU, 'SKTM')) {
            return 8; // 4.2
        }

        // #8 Umum (bayar sendiri)
        if ($statusU === 'UMUM' || $statusU === '' || str_contains($descU, 'UMUM') ||
            str_contains($descU, 'BAYAR SENDIRI') || str_contains($descU, 'PRIBADI') ||
            $idU === 'UM' || $idU === 'TN') {
            return 1; // 1
        }

        // #9 default Lain-Lain
        return 9; // 4.3
    }
}
