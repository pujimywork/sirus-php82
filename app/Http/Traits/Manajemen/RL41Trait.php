<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 4.1 Morbiditas Pasien Rawat Inap (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Periode: TAHUNAN.                                                   │
 * │ Row: dinamis berdasarkan ICD-10 yang muncul di periode (1 row =     │
 * │ 1 ICD code dari diagnosis utama RI).                                │
 * │ Kolom (56):                                                         │
 * │   - 25 age group × 2 gender = 50 cell (Hidup+Mati combined)         │
 * │   - 3 total H+M per gender (Laki, Perempuan, Total)                 │
 * │   - 3 mati per gender (Laki, Perempuan, Total)                      │
 * │                                                                     │
 * │ 25 Age Group (urutan resmi SIRS):                                   │
 * │   1. < 1 Jam               (entry → exit < 1 hr)                    │
 * │   2. 1-23 Jam              (1 hr ≤ exit-entry < 24 hr)              │
 * │   3. 1-7 Hari              (1 day ≤ ... ≤ 7 day)                    │
 * │   4. 8-28 Hari             (perinatal late)                         │
 * │   5. 29 Hari - <3 Bulan                                             │
 * │   6. 3 - <6 Bulan                                                   │
 * │   7. 6 - 11 Bulan                                                   │
 * │   8. 1 - 4 Tahun                                                    │
 * │   9. 5 - 9 Tahun                                                    │
 * │   10. 10 - 14 Tahun                                                 │
 * │   11. 15 - 19 Tahun                                                 │
 * │   12. 20 - 24 Tahun                                                 │
 * │   13. 25 - 29 Tahun                                                 │
 * │   14. 30 - 34 Tahun                                                 │
 * │   15. 35 - 39 Tahun                                                 │
 * │   16. 40 - 44 Tahun                                                 │
 * │   17. 45 - 49 Tahun                                                 │
 * │   18. 50 - 54 Tahun                                                 │
 * │   19. 55 - 59 Tahun                                                 │
 * │   20. 60 - 64 Tahun                                                 │
 * │   21. 65 - 69 Tahun                                                 │
 * │   22. 70 - 74 Tahun                                                 │
 * │   23. 75 - 79 Tahun                                                 │
 * │   24. 80 - 84 Tahun                                                 │
 * │   25. ≥ 85 Tahun                                                    │
 * │                                                                     │
 * │ Catatan umur: untuk perinatal (group 1-4), umur dihitung sbg        │
 * │ exit_date − entry_date (length of stay), karena pasien neonatal     │
 * │ baru lahir biasanya entry_date = birth_date (atau dekat). Untuk     │
 * │ group 5+, umur dihitung dari birth_date pasien ke exit_date.        │
 * │                                                                     │
 * │ "Mati" terdeteksi via JSON tindakLanjutKode='419099009' (SNOMED).   │
 * │                                                                     │
 * │ Source: rstxn_rihdrs (RI saja, filter exit_date di tahun, ri_status │
 * │ ≠ 'F'). Diagnosis utama dari JSON datadaftarri_json path             │
 * │ diagnosis[0].icdX. Pasien tanpa diagnosis → row '0' Tidak Ada Data. │
 * │                                                                     │
 * │ Filter umum: klaim_id ≠ 'KR'.                                       │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL41Trait
{
    private const DEATH_PATTERN = '"tindakLanjutKode":"419099009"';

    /** 25 age group SIRS RL 4.1. Order = posisi index 0-24. */
    public const AGE_GROUPS_RL41 = [
        ['idx' => 0,  'label' => '< 1 Jam'],
        ['idx' => 1,  'label' => '1 - 23 Jam'],
        ['idx' => 2,  'label' => '1 - 7 Hari'],
        ['idx' => 3,  'label' => '8 - 28 Hari'],
        ['idx' => 4,  'label' => '29 Hari - <3 Bulan'],
        ['idx' => 5,  'label' => '3 - <6 Bulan'],
        ['idx' => 6,  'label' => '6 - 11 Bulan'],
        ['idx' => 7,  'label' => '1 - 4 Tahun'],
        ['idx' => 8,  'label' => '5 - 9 Tahun'],
        ['idx' => 9,  'label' => '10 - 14 Tahun'],
        ['idx' => 10, 'label' => '15 - 19 Tahun'],
        ['idx' => 11, 'label' => '20 - 24 Tahun'],
        ['idx' => 12, 'label' => '25 - 29 Tahun'],
        ['idx' => 13, 'label' => '30 - 34 Tahun'],
        ['idx' => 14, 'label' => '35 - 39 Tahun'],
        ['idx' => 15, 'label' => '40 - 44 Tahun'],
        ['idx' => 16, 'label' => '45 - 49 Tahun'],
        ['idx' => 17, 'label' => '50 - 54 Tahun'],
        ['idx' => 18, 'label' => '55 - 59 Tahun'],
        ['idx' => 19, 'label' => '60 - 64 Tahun'],
        ['idx' => 20, 'label' => '65 - 69 Tahun'],
        ['idx' => 21, 'label' => '70 - 74 Tahun'],
        ['idx' => 22, 'label' => '75 - 79 Tahun'],
        ['idx' => 23, 'label' => '80 - 84 Tahun'],
        ['idx' => 24, 'label' => '≥ 85 Tahun'],
    ];

    /**
     * Compute 1 tahun laporan. Output: array of icd rows.
     */
    protected function computeRL41(int $tahun): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        // buckets[icd][ageIdx][sex] = count
        // buckets_mati[icd][sex] = count meninggal
        // buckets_meta[icd] = ['desc' => ...]
        $buckets     = [];
        $bucketsMati = [];
        $bucketsMeta = [];

        $rows = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.ri_status')->orWhere('h.ri_status', '!=', 'F'))
            ->select([
                'h.rihdr_no',
                'h.entry_date',
                'h.exit_date',
                'h.datadaftarri_json',
                'p.sex',
                'p.birth_date',
            ])
            ->get();

        foreach ($rows as $r) {
            $sex = (string) ($r->sex ?? '');
            if ($sex !== 'L' && $sex !== 'P') {
                continue; // skip data tanpa gender
            }

            $jsonRaw = (string) ($r->datadaftarri_json ?? '');
            $jsonArr = $this->decodeRl41Json($jsonRaw);

            // Diagnosis utama: ambil item pertama dari array diagnosis
            [$icd, $icdDesc] = $this->extractPrimaryIcd($jsonArr);
            if ($icd === '') {
                $icd = '0';
                $icdDesc = 'Tidak Ada Data';
            }

            // Umur klasifikasi
            $ageIdx = $this->classifyAgeRl41($r->birth_date, $r->entry_date, $r->exit_date);

            // Init bucket kalau belum ada
            if (!isset($buckets[$icd])) {
                $buckets[$icd] = [];
                for ($i = 0; $i < 25; $i++) {
                    $buckets[$icd][$i] = ['L' => 0, 'P' => 0];
                }
                $bucketsMati[$icd] = ['L' => 0, 'P' => 0];
                $bucketsMeta[$icd] = ['desc' => $icdDesc];
            }

            $buckets[$icd][$ageIdx][$sex]++;

            // Mati?
            if ($jsonRaw !== '' && str_contains($jsonRaw, self::DEATH_PATTERN)) {
                $bucketsMati[$icd][$sex]++;
            }
        }

        // Sort by icd ASC, except '0' (Tidak Ada Data) di akhir
        $icdKeys = array_keys($buckets);
        usort($icdKeys, function ($a, $b) {
            if ($a === '0') return 1;
            if ($b === '0') return -1;
            return strcmp($a, $b);
        });

        // Build flat output
        $out = [];
        foreach ($icdKeys as $icd) {
            $cells = $buckets[$icd];
            $totalL = array_sum(array_column($cells, 'L'));
            $totalP = array_sum(array_column($cells, 'P'));
            $matiL = $bucketsMati[$icd]['L'];
            $matiP = $bucketsMati[$icd]['P'];

            $out[] = [
                'icd'        => $icd,
                'icd_desc'   => $bucketsMeta[$icd]['desc'],
                'cells'      => $cells,        // [0..24] => ['L' => n, 'P' => n]
                'total_l'    => $totalL,
                'total_p'    => $totalP,
                'total'      => $totalL + $totalP,
                'mati_l'     => $matiL,
                'mati_p'     => $matiP,
                'mati_total' => $matiL + $matiP,
            ];
        }
        return $out;
    }

    /**
     * Decode datadaftarri_json — return [] kalau invalid.
     */
    private function decodeRl41Json(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Extract diagnosis utama (icdX, icdDesc) dari JSON.
     * Diagnosis utama = item pertama di array diagnosis.
     * Return ['', ''] kalau tidak ada.
     */
    private function extractPrimaryIcd(array $json): array
    {
        $diag = $json['diagnosis'] ?? null;
        if (!is_array($diag) || empty($diag)) {
            return ['', ''];
        }
        $first = $diag[0] ?? null;
        if (!is_array($first)) {
            return ['', ''];
        }
        $icd = trim((string) ($first['icdX'] ?? ''));
        $desc = trim((string) ($first['icdDesc'] ?? $first['diag_desc'] ?? ''));
        return [$icd, $desc];
    }

    /**
     * Klasifikasi umur ke 25 age group SIRS RL 4.1.
     *
     * Strategi:
     *   - LOS = exit − entry (untuk perinatal awal, group 1-4)
     *   - Umur = exit − birth (untuk group 5+, dewasa)
     *
     * Untuk neonatal (entry_date dekat birth_date), LOS lebih akurat
     * sebagai indikator "umur saat keluar" daripada birth-exit.
     *
     * @return int  0..24 indeks age group
     */
    protected function classifyAgeRl41($birthDate, $entryDate, $exitDate): int
    {
        try {
            $exit = Carbon::parse($exitDate);
        } catch (\Throwable $e) {
            return 7; // default 1-4 tahun (paling generic)
        }

        // Hitung umur dari birth_date kalau tersedia
        $umurDays = null;
        if ($birthDate) {
            try {
                $birth = Carbon::parse($birthDate);
                $umurDays = max(0, $birth->diffInDays($exit));
            } catch (\Throwable $e) {
                $umurDays = null;
            }
        }

        // Untuk perinatal (umur < 28 hari), pakai LOS lebih akurat
        // karena entry_date biasanya = birth_date (newborn admission)
        if ($umurDays !== null && $umurDays < 28) {
            try {
                $entry = Carbon::parse($entryDate);
                $losSeconds = max(0, $entry->getTimestamp());
                $losSeconds = $exit->getTimestamp() - $entry->getTimestamp();

                if ($losSeconds < 3600) return 0;          // < 1 Jam
                if ($losSeconds < 86400) return 1;         // 1 - 23 Jam
                $losDays = (int) ($losSeconds / 86400);
                if ($losDays <= 7) return 2;               // 1 - 7 Hari
                if ($losDays <= 28) return 3;              // 8 - 28 Hari
            } catch (\Throwable $e) {
                // fall through ke umur-based
            }
        }

        // Umur-based binning untuk group 5+
        if ($umurDays === null) {
            return 7; // default 1-4 tahun kalau birth_date hilang
        }

        if ($umurDays < 90)   return 4;   // 29 Hari - <3 Bulan
        if ($umurDays < 180)  return 5;   // 3 - <6 Bulan
        if ($umurDays < 365)  return 6;   // 6 - 11 Bulan

        // Pakai Carbon::diffInYears untuk umur >= 1 tahun (calendar-aware,
        // handle leap year & exact birthday edge case)
        try {
            $birth = Carbon::parse($birthDate);
            $umurYears = (int) $birth->diffInYears($exit);
        } catch (\Throwable $e) {
            $umurYears = (int) ($umurDays / 365.25);
        }

        if ($umurYears < 5)   return 7;   // 1 - 4 Tahun
        if ($umurYears < 10)  return 8;
        if ($umurYears < 15)  return 9;
        if ($umurYears < 20)  return 10;
        if ($umurYears < 25)  return 11;
        if ($umurYears < 30)  return 12;
        if ($umurYears < 35)  return 13;
        if ($umurYears < 40)  return 14;
        if ($umurYears < 45)  return 15;
        if ($umurYears < 50)  return 16;
        if ($umurYears < 55)  return 17;
        if ($umurYears < 60)  return 18;
        if ($umurYears < 65)  return 19;
        if ($umurYears < 70)  return 20;
        if ($umurYears < 75)  return 21;
        if ($umurYears < 80)  return 22;
        if ($umurYears < 85)  return 23;
        return 24; // ≥ 85 Tahun
    }
}
