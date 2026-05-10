<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 4.3 — 10 Besar Kematian Penyakit Rawat Inap (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Periode: TAHUNAN.                                                   │
 * │ Row: TOP 10 ICD-10 dengan jumlah PASIEN MENINGGAL terbanyak,        │
 * │ sorted desc by mati_total (BUKAN total H+M seperti RL 4.2).         │
 * │ Kolom (6 metrik) — sama dengan RL 4.2:                              │
 * │   - Hidup & Mati per gender: Laki | Perempuan | Total               │
 * │   - Pasien Keluar Mati per gender: Laki | Perempuan | Total         │
 * │                                                                     │
 * │ Source: rstxn_rihdrs (RI saja, exit_date di tahun, ri_status ≠ 'F', │
 * │ klaim_id ≠ 'KR'). Diagnosis utama dari JSON                          │
 * │ datadaftarri_json.diagnosis[0].icdX. "Mati" = SNOMED 419099009      │
 * │ di JSON.                                                            │
 * │                                                                     │
 * │ Catatan:                                                            │
 * │   - ICD yg total mati = 0 dikecualikan dari ranking (tidak ada      │
 * │     kematian, tidak relevan untuk laporan kematian).                │
 * │   - Pasien tanpa diagnosis utama dikecualikan.                      │
 * │   - Beda dari RL 4.2 hanya di sorting key (mati desc vs total desc).│
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL43Trait
{
    private const DEATH_PATTERN_RL43 = '"tindakLanjutKode":"419099009"';

    /**
     * Compute 10 besar kematian RI 1 tahun. Output: array max 10 row × 6 metrik.
     */
    protected function computeRL43(int $tahun, int $limit = 10): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        // buckets[icd] = ['desc' => ..., 'L' => 0, 'P' => 0, 'matiL' => 0, 'matiP' => 0]
        $buckets = [];

        $rows = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.ri_status')->orWhere('h.ri_status', '!=', 'F'))
            ->select(['h.datadaftarri_json', 'p.sex'])
            ->get();

        foreach ($rows as $r) {
            $sex = (string) ($r->sex ?? '');
            if ($sex !== 'L' && $sex !== 'P') {
                continue;
            }

            $jsonRaw = (string) ($r->datadaftarri_json ?? '');
            if ($jsonRaw === '') {
                continue;
            }

            $jsonArr = json_decode($jsonRaw, true);
            if (!is_array($jsonArr)) {
                continue;
            }

            $diag = $jsonArr['diagnosis'] ?? null;
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
                    'desc'  => $icdDesc,
                    'L'     => 0,
                    'P'     => 0,
                    'matiL' => 0,
                    'matiP' => 0,
                ];
            }

            $buckets[$icd][$sex]++;

            // Mati?
            if (str_contains($jsonRaw, self::DEATH_PATTERN_RL43)) {
                $buckets[$icd]['mati' . $sex]++;
            }
        }

        // Filter: hanya ICD yang ada kematian (mati_total > 0)
        $buckets = array_filter($buckets, fn($b) => ($b['matiL'] + $b['matiP']) > 0);

        // Sort desc by mati_total
        uasort($buckets, function ($a, $b) {
            $matiA = $a['matiL'] + $a['matiP'];
            $matiB = $b['matiL'] + $b['matiP'];
            return $matiB <=> $matiA;
        });

        $top = array_slice($buckets, 0, $limit, true);

        // Build flat output
        $out = [];
        foreach ($top as $icd => $b) {
            $out[] = [
                'icd'        => $icd,
                'icd_desc'   => $b['desc'],
                'laki'       => $b['L'],
                'perempuan'  => $b['P'],
                'total'      => $b['L'] + $b['P'],
                'mati_l'     => $b['matiL'],
                'mati_p'     => $b['matiP'],
                'mati_total' => $b['matiL'] + $b['matiP'],
            ];
        }
        return $out;
    }
}
