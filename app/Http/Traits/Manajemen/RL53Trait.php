<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 5.3 — 10 Besar Kunjungan Penyakit Rawat Jalan (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Periode: TAHUNAN.                                                   │
 * │ Row: TOP 10 ICD-10 dengan jumlah KUNJUNGAN terbanyak di RJ          │
 * │ poliklinik (sorted desc by total kunjungan).                        │
 * │ Kolom (6 metrik):                                                   │
 * │   - Jumlah Kasus Baru per gender: L | P | Total                     │
 * │   - Jumlah Kunjungan per gender:  L | P | Total                     │
 * │                                                                     │
 * │ KONSEP "Kasus Baru" vs "Kunjungan" (sama dengan RL 5.1):            │
 * │   - "Kasus Baru" = DISTINCT reg_no per (icd, gender)                │
 * │     (1 pasien dgn multiple visit ICD-sama → 1 kasus baru).          │
 * │   - "Kunjungan" = COUNT semua visits.                               │
 * │                                                                     │
 * │ Source: rstxn_rjhdrs (RJ saja, rj_date di tahun, rj_status NOT IN   │
 * │ ('A','F'), klaim_id ≠ 'KR'). Diagnosis utama dari JSON               │
 * │ datadaftarpolirj_json.diagnosis[0].icdX.                             │
 * │                                                                     │
 * │ Pasien tanpa diagnosis utama dikecualikan dari ranking.             │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL53Trait
{
    /**
     * Compute 10 besar kunjungan penyakit RJ 1 tahun.
     */
    protected function computeRL53(int $tahun, int $limit = 10): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        // buckets[icd] = ['desc', 'kasus_l_set', 'kasus_p_set', 'kunj_l', 'kunj_p']
        $buckets = [];

        $rows = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->select(['h.reg_no', 'h.datadaftarpolirj_json', 'p.sex'])
            ->get();

        foreach ($rows as $r) {
            $sex = (string) ($r->sex ?? '');
            if ($sex !== 'L' && $sex !== 'P') {
                continue;
            }

            $jsonRaw = (string) ($r->datadaftarpolirj_json ?? '');
            if ($jsonRaw === '') {
                continue;
            }

            $arr = json_decode($jsonRaw, true);
            if (!is_array($arr)) {
                continue;
            }
            $diag = $arr['diagnosis'] ?? null;
            if (!is_array($diag) || empty($diag)) {
                continue;
            }
            $first = $diag[0] ?? null;
            if (!is_array($first)) {
                continue;
            }
            $icd = trim((string) ($first['icdX'] ?? ''));
            if ($icd === '') {
                continue;
            }
            $icdDesc = trim((string) ($first['icdDesc'] ?? $first['diag_desc'] ?? ''));

            if (!isset($buckets[$icd])) {
                $buckets[$icd] = [
                    'desc'        => $icdDesc,
                    'kasus_l_set' => [],
                    'kasus_p_set' => [],
                    'kunj_l'      => 0,
                    'kunj_p'      => 0,
                ];
            }

            $regNo = (string) ($r->reg_no ?? '');
            if ($sex === 'L') {
                $buckets[$icd]['kunj_l']++;
                if ($regNo !== '') {
                    $buckets[$icd]['kasus_l_set'][$regNo] = true;
                }
            } else {
                $buckets[$icd]['kunj_p']++;
                if ($regNo !== '') {
                    $buckets[$icd]['kasus_p_set'][$regNo] = true;
                }
            }
        }

        // Sort desc by total kunjungan
        uasort($buckets, function ($a, $b) {
            $ka = $a['kunj_l'] + $a['kunj_p'];
            $kb = $b['kunj_l'] + $b['kunj_p'];
            return $kb <=> $ka;
        });

        $top = array_slice($buckets, 0, $limit, true);

        $out = [];
        foreach ($top as $icd => $b) {
            $kasusL = count($b['kasus_l_set']);
            $kasusP = count($b['kasus_p_set']);
            $out[] = [
                'icd'         => $icd,
                'icd_desc'    => $b['desc'],
                'kasus_l'     => $kasusL,
                'kasus_p'     => $kasusP,
                'kasus_total' => $kasusL + $kasusP,
                'kunj_l'      => $b['kunj_l'],
                'kunj_p'      => $b['kunj_p'],
                'kunj_total'  => $b['kunj_l'] + $b['kunj_p'],
            ];
        }
        return $out;
    }
}
