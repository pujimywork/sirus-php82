<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 5.1 Morbiditas Pasien Rawat Jalan (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Periode: TAHUNAN.                                                   │
 * │ Row: dinamis berdasarkan ICD-10 yang muncul di periode (1 row =     │
 * │ 1 ICD code dari diagnosis utama RJ).                                │
 * │ Kolom (56):                                                         │
 * │   - 25 age group × 2 gender = 50 cell (Kasus Baru per umur×gender)  │
 * │   - 3 total Kasus Baru per gender (Laki, Perempuan, Total)          │
 * │   - 3 Jumlah Kunjungan per gender (Laki, Perempuan, Total)          │
 * │                                                                     │
 * │ KONSEP "Kasus Baru" vs "Kunjungan":                                 │
 * │   - "Kasus Baru" = pasien unik per ICD per tahun. 1 pasien dengan   │
 * │     multiple visit ICD-yang-sama → tetap 1 kasus baru.              │
 * │   - "Kunjungan" = setiap visit. 1 pasien × 5 visit = 5 kunjungan.   │
 * │   - Distinct di SIRS sense: per (reg_no, icd) pair.                 │
 * │                                                                     │
 * │ 25 Age Group sama dengan RL 4.1 (lihat AGE_GROUPS_RL41 di RL41Trait)│
 * │ Tanpa "Hidup/Mati" karena RJ outpatient tidak ada metric kematian.  │
 * │                                                                     │
 * │ Source: rstxn_rjhdrs (RJ saja, filter rj_date di tahun, rj_status   │
 * │ NOT IN ('A','F'), klaim_id ≠ 'KR'). Diagnosis utama dari JSON       │
 * │ datadaftarpolirj_json.diagnosis[0].icdX. Pasien tanpa diagnosis →   │
 * │ row '0' Tidak Ada Data.                                             │
 * │                                                                     │
 * │ Klasifikasi umur: pakai birth_date pasien dengan rj_date sebagai    │
 * │ reference (tanggal kunjungan). Carbon::diffInYears untuk akurasi    │
 * │ leap year. Untuk pasien <28 hari pakai LOS rj_date − birth_date.    │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL51Trait
{
    /** Reuse 25 age group dari RL 4.1 (AGE_GROUPS_RL41 di RL41Trait). */
    public const AGE_GROUPS_RL51 = [
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
    protected function computeRL51(int $tahun): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        // buckets[icd][ageIdx][sex] = ['kasus_set' => Set<reg_no>, 'kunjungan' => count]
        // Track distinct reg_no untuk kasus baru, total visit untuk kunjungan.
        // Karena PHP tidak punya Set, pakai array dengan reg_no as key (=> true).
        $buckets    = [];
        $totalKunjL = []; // [icd => kunjungan_l]
        $totalKunjP = [];
        $kasusSet   = []; // [icd][sex] => set of reg_no (untuk total kasus baru per gender)
        $bucketsMeta = [];

        $rows = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select([
                'h.rj_no',
                'h.reg_no',
                'h.rj_date',
                'h.datadaftarpolirj_json',
                'p.sex',
                'p.birth_date',
            ])
            ->get();

        foreach ($rows as $r) {
            $sex = (string) ($r->sex ?? '');
            if ($sex !== 'L' && $sex !== 'P') {
                continue;
            }

            $jsonRaw = (string) ($r->datadaftarpolirj_json ?? '');
            [$icd, $icdDesc] = $this->extractPrimaryIcdRl51($jsonRaw);
            if ($icd === '') {
                $icd = '0';
                $icdDesc = 'Tidak Ada Data';
            }

            // Klasifikasi umur (rj_date sbg reference)
            $ageIdx = $this->classifyAgeRl51($r->birth_date, $r->rj_date);

            // Init bucket kalau belum ada
            if (!isset($buckets[$icd])) {
                $buckets[$icd] = [];
                for ($i = 0; $i < 25; $i++) {
                    $buckets[$icd][$i] = [
                        'L' => ['kasus' => [], 'visits' => 0],
                        'P' => ['kasus' => [], 'visits' => 0],
                    ];
                }
                $totalKunjL[$icd] = 0;
                $totalKunjP[$icd] = 0;
                $kasusSet[$icd] = ['L' => [], 'P' => []];
                $bucketsMeta[$icd] = ['desc' => $icdDesc];
            }

            $regNo = (string) ($r->reg_no ?? '');

            // Increment kunjungan (per cell + total per gender)
            $buckets[$icd][$ageIdx][$sex]['visits']++;
            if ($sex === 'L') {
                $totalKunjL[$icd]++;
            } else {
                $totalKunjP[$icd]++;
            }

            // Track kasus baru — 1 reg_no = 1 kasus baru per (icd, age, sex)
            // Note: kalau sama reg_no muncul di age group berbeda (e.g., umur
            // beda krn beda waktu visit), tetap distinct per cell. Namun untuk
            // total per gender, distinct global (reg_no unique per icd-sex).
            if ($regNo !== '') {
                $buckets[$icd][$ageIdx][$sex]['kasus'][$regNo] = true;
                $kasusSet[$icd][$sex][$regNo] = true;
            }
        }

        // Sort by icd ASC, "0" di akhir
        $icdKeys = array_keys($buckets);
        usort($icdKeys, function ($a, $b) {
            if ($a === '0') return 1;
            if ($b === '0') return -1;
            return strcmp($a, $b);
        });

        $out = [];
        foreach ($icdKeys as $icd) {
            // Convert kasus set → count
            $cells = [];
            for ($i = 0; $i < 25; $i++) {
                $cells[$i] = [
                    'L' => count($buckets[$icd][$i]['L']['kasus']),
                    'P' => count($buckets[$icd][$i]['P']['kasus']),
                ];
            }
            $kasusL = count($kasusSet[$icd]['L']);
            $kasusP = count($kasusSet[$icd]['P']);

            $out[] = [
                'icd'         => $icd,
                'icd_desc'    => $bucketsMeta[$icd]['desc'],
                'cells'       => $cells,
                'kasus_l'     => $kasusL,
                'kasus_p'     => $kasusP,
                'kasus_total' => $kasusL + $kasusP,
                'kunj_l'      => $totalKunjL[$icd],
                'kunj_p'      => $totalKunjP[$icd],
                'kunj_total'  => $totalKunjL[$icd] + $totalKunjP[$icd],
            ];
        }
        return $out;
    }

    /**
     * Extract primary ICD-10 from RJ JSON.
     */
    private function extractPrimaryIcdRl51(string $rawJson): array
    {
        if ($rawJson === '') {
            return ['', ''];
        }
        $arr = json_decode($rawJson, true);
        if (!is_array($arr)) {
            return ['', ''];
        }
        $diag = $arr['diagnosis'] ?? null;
        if (!is_array($diag) || empty($diag)) {
            return ['', ''];
        }
        $first = $diag[0] ?? null;
        if (!is_array($first)) {
            return ['', ''];
        }
        return [
            trim((string) ($first['icdX'] ?? '')),
            trim((string) ($first['icdDesc'] ?? $first['diag_desc'] ?? '')),
        ];
    }

    /**
     * Klasifikasi umur ke 25 age group.
     * Reference date = rj_date (saat kunjungan), bukan exit_date seperti RI.
     *
     * @return int 0..24
     */
    protected function classifyAgeRl51($birthDate, $rjDate): int
    {
        try {
            $ref = Carbon::parse($rjDate);
        } catch (\Throwable $e) {
            return 7; // default 1-4 tahun
        }

        if (!$birthDate) {
            return 7;
        }
        try {
            $birth = Carbon::parse($birthDate);
        } catch (\Throwable $e) {
            return 7;
        }

        $umurDays = max(0, $birth->diffInDays($ref));

        // Perinatal (<28 hari): pakai detik untuk precision jam
        if ($umurDays < 28) {
            $umurSeconds = $ref->getTimestamp() - $birth->getTimestamp();
            if ($umurSeconds < 3600)  return 0;  // < 1 Jam
            if ($umurSeconds < 86400) return 1;  // 1 - 23 Jam
            if ($umurDays <= 7)       return 2;  // 1 - 7 Hari
            return 3;                            // 8 - 28 Hari
        }

        if ($umurDays < 90)  return 4;
        if ($umurDays < 180) return 5;
        if ($umurDays < 365) return 6;

        $umurYears = (int) $birth->diffInYears($ref);
        if ($umurYears < 5)   return 7;
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
        return 24;
    }
}
