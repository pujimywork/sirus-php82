<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 4.2 — 10 Besar Penyakit Rawat Inap (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Periode: TAHUNAN.                                                   │
 * │ Row: TOP 10 ICD-10 paling banyak di periode (sorted desc by total). │
 * │ Kolom (6 metrik):                                                   │
 * │   - Hidup & Mati per gender: Laki | Perempuan | Total               │
 * │   - Pasien Keluar Mati per gender: Laki | Perempuan | Total         │
 * │                                                                     │
 * │ Source: rstxn_rihdrs (RI saja, exit_date di tahun, ri_status ≠ 'F', │
 * │ klaim_id ≠ 'KR'). Diagnosis utama dari JSON                          │
 * │ datadaftarri_json.diagnosis[0].icdX. "Mati" = SNOMED 419099009      │
 * │ di JSON (konsisten dengan RL 4.1 dan deteksi meninggal lain).       │
 * │                                                                     │
 * │ Catatan: 1 admisi = 1 entry (diagnosis utama saja). Pasien tanpa    │
 * │ diagnosis (icdX kosong) tidak masuk top 10 — dikecualikan dari      │
 * │ ranking karena tidak punya kategori penyakit.                       │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL42Trait
{
    private const DEATH_PATTERN_RL42 = '"tindakLanjutKode":"419099009"';

    /**
     * Compute 10 besar penyakit RI 1 tahun. Output: array max 10 row × 6 metrik.
     */
    protected function computeRL42(int $tahun, int $limit = 10): array
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

            // Diagnosis utama
            $diag = $jsonArr['diagnosis'] ?? null;
            if (!is_array($diag) || empty($diag)) {
                continue; // skip pasien tanpa diagnosis (tidak masuk top 10)
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
            if (str_contains($jsonRaw, self::DEATH_PATTERN_RL42)) {
                $buckets[$icd]['mati' . $sex]++;
            }
        }

        // Sort desc by total (L+P), then take top N
        uasort($buckets, function ($a, $b) {
            $totA = $a['L'] + $a['P'];
            $totB = $b['L'] + $b['P'];
            return $totB <=> $totA;
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
