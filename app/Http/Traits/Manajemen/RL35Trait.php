<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.5 Kunjungan (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ KONSEP                                                              │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ "Kunjungan" = setiap visit (BUKAN distinct pasien). 1 pasien yg     │
 * │ datang 3× dalam bulan = 3 kunjungan.                                │
 * │                                                                     │
 * │ Source data:                                                        │
 * │   - rstxn_rjhdrs   (RJ poliklinik) — kategori 1-23, 25-34           │
 * │   - rstxn_ugdhdrs  (UGD)           — kategori 24 (Rawat Darurat)    │
 * │   - rstxn_rihdrs   TIDAK termasuk (RI bukan kunjungan poli rawat    │
 * │     jalan)                                                          │
 * │                                                                     │
 * │ Pemecahan kolom (4 cell + total per row):                           │
 * │   - Dalam Kota L/P  : kab_id = '3504' (Tulungagung) × p.sex         │
 * │   - Luar Kota L/P   : kab_id != '3504' × p.sex                      │
 * │   - Total           : sum 4 cell                                    │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ MAPPING JENIS KEGIATAN (priority chain)                             │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Step #0  source = UGD                  → 24 Rawat Darurat           │
 * │ Step #1  POLI_TO_SIRS_RL35 by poli_id  → 1-34 (eksplisit map)       │
 * │ Step #2  fallback                      → 100 Tidak Ada Data         │
 * │                                                                     │
 * │ Kategori SIRS yg perlu sub-classification (Neonatal vs Lainnya,     │
 * │ Ibu Hamil vs Lainnya, Stroke vs Lainnya) BELUM diimplementasikan —  │
 * │ semua dipetakan ke "Lainnya" sub-category. Untuk aktifkan butuh:    │
 * │   - id 3 Anak (Neonatal)   : umur < 28 hari                         │
 * │   - id 5 OB (Ibu Hamil)    : ICD O00-O99 atau diagnosis hamil       │
 * │   - id 30 Bedah Saraf Stroke / 32 Saraf Stroke: ICD I60-I69         │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ ROW SPECIAL (id 66, 77, 99, 100)                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ id 66  Rata-rata hari poliklinik buka                               │
 * │        → COUNT DISTINCT(rj_date) di rstxn_rjhdrs untuk periode      │
 * │          (semua kolom L/P/Dalam/Luar diisi sama dengan jumlah hari) │
 * │                                                                     │
 * │ id 77  Rata-rata kunjungan per hari                                 │
 * │        → ROUND(total_kunjungan / hari_buka) per cell                │
 * │                                                                     │
 * │ id 99  TOTAL                                                        │
 * │        → SUM kolom row 1-34 + 100                                   │
 * │                                                                     │
 * │ id 100 Tidak Ada Data                                               │
 * │        → kunjungan dengan poli_id tidak ke-map (tidak ada di        │
 * │          POLI_TO_SIRS_RL35) atau alamat pasien missing              │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * Filter: rj_status NOT IN ('A','F') (exclude Antrian belum dilayani &
 * Batal), klaim_id != 'KR'.
 */
trait RL35Trait
{
    /** Kode kabupaten Tulungagung (BPS). Pasien dengan kab_id ini = Dalam Kota. */
    private const KAB_TULUNGAGUNG = '3504';

    /**
     * Mapping eksplisit poli_id → SIRS RL 3.5 specialty ID.
     * Untuk poli_id yang tidak ada di map ini, fallback ke 100 (Tidak Ada Data).
     */
    private const POLI_TO_SIRS_RL35 = [
        '1'  => 23,   // POLI UMUM         → Umum
        '2'  => 14,   // POLI GIGI         → Gigi & Mulut
        '3'  => 1,    // POLI DALAM        → Penyakit Dalam
        '4'  => 33,   // POLI SYARAF       → Saraf (Lainnya). Stroke=32 butuh ICD
        '5'  => 2,    // POLI BEDAH        → Bedah
        '6'  => 6,    // POLI OBGIN        → Obstetri & Ginekologi Lainnya.
                      //                     Ibu Hamil=5 butuh ICD O-code
        '7'  => 18,   // POLI ORTHOPEDI    → Bedah Orthopedi
        '8'  => 26,   // POLI AKUPUNTUR    → Akupungtur Medik
        '9'  => 24,   // UGD               → Rawat Darurat (handled also via UGD source)
        '10' => 100,  // OK                → Tidak Ada Data (ruang operasi)
        '11' => 4,    // POLI ANAK         → Kesehatan Anak Lainnya. Neonatal=3 umur<28h
        '12' => 25,   // POLI FISIOTERAPY  → Rehabilitasi Medik
        '13' => 27,   // POLI GIZI         → Konsultasi Gizi
        '14' => 8,    // POLI PSIKIATRI    → Jiwa
        '15' => 17,   // POLI RADIOLOGI    → Radiologi
        '16' => 16,   // POLI JANTUNG      → Kardiologi
        '17' => 100,  // OBAT KRONIS/PRB   → Tidak Ada Data
        '18' => 19,   // POLI TB           → Paru-Paru
        '19' => 7,    // POLI KIA / KB     → Keluarga Berencana
        '20' => 25,   // POLI REHAB MEDIS  → Rehabilitasi Medik
        '21' => 100,  // POLI IMUNISASI    → Tidak Ada Data
        '22' => 100,  // LABORATORIUM      → Tidak Ada Data
        '23' => 17,   // RONTGEN           → Radiologi
        '24' => 19,   // POLI PARU         → Paru-Paru
        '25' => 12,   // POLI MATA         → Mata
    ];

    /** Daftar Jenis Kegiatan RL 3.5 SIRS Kemenkes (urutan & ID resmi). */
    public const JENIS_KEGIATAN_LIST = [
        ['id' => 1,  'no' => '1',   'nama' => 'Penyakit Dalam'],
        ['id' => 2,  'no' => '2',   'nama' => 'Bedah'],
        ['id' => 3,  'no' => '3',   'nama' => 'Kesehatan Anak (Neonatal)'],
        ['id' => 4,  'no' => '4',   'nama' => 'Kesehatan Anak Lainnya'],
        ['id' => 5,  'no' => '5',   'nama' => 'Obstetri & Ginekologi (Ibu Hamil)'],
        ['id' => 6,  'no' => '6',   'nama' => 'Obstetri & Ginekologi Lainnya'],
        ['id' => 7,  'no' => '7',   'nama' => 'Keluarga Berencana'],
        ['id' => 8,  'no' => '8',   'nama' => 'Jiwa'],
        ['id' => 9,  'no' => '9',   'nama' => 'Napza'],
        ['id' => 10, 'no' => '10',  'nama' => 'Psikologi'],
        ['id' => 11, 'no' => '11',  'nama' => 'THT'],
        ['id' => 12, 'no' => '12',  'nama' => 'Mata'],
        ['id' => 13, 'no' => '13',  'nama' => 'Kulit dan Kelamin'],
        ['id' => 14, 'no' => '14',  'nama' => 'Gigi & Mulut'],
        ['id' => 15, 'no' => '15',  'nama' => 'Geriatri'],
        ['id' => 16, 'no' => '16',  'nama' => 'Kardiologi'],
        ['id' => 17, 'no' => '17',  'nama' => 'Radiologi'],
        ['id' => 18, 'no' => '18',  'nama' => 'Bedah Orthopedi'],
        ['id' => 19, 'no' => '19',  'nama' => 'Paru - Paru'],
        ['id' => 20, 'no' => '20',  'nama' => 'Kanker'],
        ['id' => 21, 'no' => '21',  'nama' => 'Uronefrologi'],
        ['id' => 22, 'no' => '22',  'nama' => 'Kusta'],
        ['id' => 23, 'no' => '23',  'nama' => 'Umum'],
        ['id' => 24, 'no' => '24',  'nama' => 'Rawat Darurat'],
        ['id' => 25, 'no' => '25',  'nama' => 'Rehabilitasi Medik'],
        ['id' => 26, 'no' => '26',  'nama' => 'Akupungtur Medik'],
        ['id' => 27, 'no' => '27',  'nama' => 'Konsultasi Gizi'],
        ['id' => 28, 'no' => '28',  'nama' => 'Day Care'],
        ['id' => 29, 'no' => '29',  'nama' => 'Medical Check Up'],
        ['id' => 30, 'no' => '30',  'nama' => 'Bedah Saraf (Stroke)'],
        ['id' => 31, 'no' => '31',  'nama' => 'Bedah Saraf (Lainnya)'],
        ['id' => 32, 'no' => '32',  'nama' => 'Saraf (Stroke)'],
        ['id' => 33, 'no' => '33',  'nama' => 'Saraf (Lainnya)'],
        ['id' => 34, 'no' => '34',  'nama' => 'Lain - Lain'],
        ['id' => 66, 'no' => '66',  'nama' => 'Rata-rata hari poliklinik buka'],
        ['id' => 77, 'no' => '77',  'nama' => 'Rata-rata kunjungan per hari'],
        ['id' => 99, 'no' => '99',  'nama' => 'TOTAL'],
        ['id' => 100,'no' => '100', 'nama' => 'Tidak Ada Data'],
    ];

    /**
     * Compute 1 bulan laporan. Output: 38 row × 5 kolom (dalam_l, dalam_p,
     * luar_l, luar_p, total).
     */
    protected function computeRL35(int $bulan, int $tahun): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();

        $buckets = $this->initRl35Buckets();

        // ─── Source 1: RJ poliklinik ─────────────────────────────────
        // Per visit: poli_id → SIRS specialty, kab_id → dalam/luar, sex
        $rjRows = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select(['h.poli_id', 'p.sex', 'p.kab_id'])
            ->get();

        foreach ($rjRows as $r) {
            $sirsId = self::POLI_TO_SIRS_RL35[(string) $r->poli_id] ?? 100;
            $cell   = $this->cellKeyFor((string) ($r->kab_id ?? ''), (string) ($r->sex ?? ''));
            if ($cell === null) {
                // Sex/alamat tidak valid → masuk Tidak Ada Data row tapi
                // tidak punya cell yg cocok. Skip aja (tidak count).
                continue;
            }
            $buckets[$sirsId][$cell]++;
        }

        // ─── Source 2: UGD = kategori 24 Rawat Darurat ───────────────
        $ugdRows = DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select(['p.sex', 'p.kab_id'])
            ->get();

        foreach ($ugdRows as $r) {
            $cell = $this->cellKeyFor((string) ($r->kab_id ?? ''), (string) ($r->sex ?? ''));
            if ($cell === null) {
                continue;
            }
            $buckets[24][$cell]++;
        }

        // ─── id 66: Rata-rata hari poliklinik buka ───────────────────
        // Definisi: jumlah hari unik di mana ada minimal 1 RJ visit (status valid)
        $hariBuka = (int) DB::table('rstxn_rjhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where('klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
            ->distinct()
            ->count(DB::raw("TRUNC(rj_date)"));

        // Letakkan di semua 4 cell (sama nilainya, sesuai konvensi RL 3.5)
        $buckets[66] = [
            'dalam_l' => $hariBuka,
            'dalam_p' => $hariBuka,
            'luar_l'  => $hariBuka,
            'luar_p'  => $hariBuka,
        ];

        // ─── id 99: TOTAL = sum row 1-34 + 100 ───────────────────────
        $totalCells = ['dalam_l' => 0, 'dalam_p' => 0, 'luar_l' => 0, 'luar_p' => 0];
        for ($i = 1; $i <= 34; $i++) {
            foreach ($totalCells as $k => $_) {
                $totalCells[$k] += $buckets[$i][$k] ?? 0;
            }
        }
        foreach ($totalCells as $k => $_) {
            $totalCells[$k] += $buckets[100][$k] ?? 0;
        }
        $buckets[99] = $totalCells;

        // ─── id 77: Rata-rata kunjungan per hari = TOTAL / hari_buka ─
        $buckets[77] = [
            'dalam_l' => $hariBuka > 0 ? (int) round($totalCells['dalam_l'] / $hariBuka) : 0,
            'dalam_p' => $hariBuka > 0 ? (int) round($totalCells['dalam_p'] / $hariBuka) : 0,
            'luar_l'  => $hariBuka > 0 ? (int) round($totalCells['luar_l']  / $hariBuka) : 0,
            'luar_p'  => $hariBuka > 0 ? (int) round($totalCells['luar_p']  / $hariBuka) : 0,
        ];

        // ─── Build flat output (38 row sesuai JENIS_KEGIATAN_LIST) ───
        $out = [];
        foreach (self::JENIS_KEGIATAN_LIST as $jp) {
            $cells = $buckets[$jp['id']] ?? ['dalam_l' => 0, 'dalam_p' => 0, 'luar_l' => 0, 'luar_p' => 0];
            $total = $cells['dalam_l'] + $cells['dalam_p'] + $cells['luar_l'] + $cells['luar_p'];
            $out[] = [
                'id'      => $jp['id'],
                'no'      => $jp['no'],
                'nama'    => $jp['nama'],
                'dalam_l' => $cells['dalam_l'],
                'dalam_p' => $cells['dalam_p'],
                'luar_l'  => $cells['luar_l'],
                'luar_p'  => $cells['luar_p'],
                'total'   => $total,
            ];
        }
        return $out;
    }

    private function initRl35Buckets(): array
    {
        $template = ['dalam_l' => 0, 'dalam_p' => 0, 'luar_l' => 0, 'luar_p' => 0];
        $buckets = [];
        foreach (self::JENIS_KEGIATAN_LIST as $jp) {
            $buckets[$jp['id']] = $template;
        }
        return $buckets;
    }

    /**
     * Klasifikasi cell berdasar kab_id & sex.
     *
     * @return string|null  'dalam_l' / 'dalam_p' / 'luar_l' / 'luar_p',
     *                      atau null kalau sex tidak valid
     */
    private function cellKeyFor(string $kabId, string $sex): ?string
    {
        if ($sex !== 'L' && $sex !== 'P') {
            return null;
        }
        $isDalam = $kabId === self::KAB_TULUNGAGUNG;
        return ($isDalam ? 'dalam_' : 'luar_') . strtolower($sex);
    }
}
