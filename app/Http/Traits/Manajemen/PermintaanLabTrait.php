<?php

namespace App\Http\Traits\Manajemen;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Permintaan Laboratorium (lab-bulanan & lab-tahunan).
 *
 * Konvensi:
 *   - 3 sumber permintaan (UNION): rstxn_rjlabs (RJ), rstxn_ugdlabs (UGD), rstxn_rilabs (RI)
 *   - Filter periode:
 *       RJ/UGD pakai rj_date dari header
 *       RI pakai exit_date dari header (sesuai konvensi reporting RI)
 *   - Pasien Kronis (klaim_id='KR') di-exclude
 *   - "Dokter pengirim" Lab = dr_id dari header (dokter penanggung jawab kasus)
 *   - Revenue = SUM(lab_price)
 */
trait PermintaanLabTrait
{
    /**
     * Aggregate per (periode, source) — pivot di PHP jadi 1 row per periode.
     */
    protected function buildPermintaanLabAggregate($start, $end, string $groupColRJUGD, string $groupColRI)
    {
        // RJ
        $rj = DB::table('rstxn_rjlabs as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupColRJUGD} as periode"),
                DB::raw("'RJ' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.lab_price), 0) as revenue'),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRJUGD));

        // UGD
        $ugd = DB::table('rstxn_ugdlabs as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupColRJUGD} as periode"),
                DB::raw("'UGD' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.lab_price), 0) as revenue'),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRJUGD));

        // RI
        $ri = DB::table('rstxn_rilabs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupColRI} as periode"),
                DB::raw("'RI' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.lab_price), 0) as revenue'),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRI));

        // UNION ALL via Laravel query builder
        return $rj->unionAll($ugd)->unionAll($ri)->get();
    }

    /**
     * Pivot hasil aggregate (per periode, source) jadi 1 row per periode dengan kolom rj/ugd/ri.
     */
    protected function pivotPermintaanByPeriode($aggregate): array
    {
        $byPeriode = [];
        foreach ($aggregate as $r) {
            $key = (string) $r->periode;
            if (!isset($byPeriode[$key])) {
                $byPeriode[$key] = [
                    'periode' => $key,
                    'rj' => 0, 'ugd' => 0, 'ri' => 0,
                    'total' => 0, 'revenue' => 0.0,
                    'bpjs' => 0, 'umum' => 0,
                ];
            }
            $sourceKey = strtolower($r->source);
            $byPeriode[$key][$sourceKey] = (int) $r->total;
            $byPeriode[$key]['total'] += (int) $r->total;
            $byPeriode[$key]['revenue'] += (float) $r->revenue;
            $byPeriode[$key]['bpjs'] += (int) $r->bpjs;
            $byPeriode[$key]['umum'] += (int) $r->umum;
        }
        return $byPeriode;
    }

    protected function fillPermintaanRow(array $byPeriode, string $periodeKey, string $label): array
    {
        $r = $byPeriode[$periodeKey] ?? null;
        return [
            'periode_label' => $label,
            'periode_short' => $periodeKey,
            'rj'      => $r['rj'] ?? 0,
            'ugd'     => $r['ugd'] ?? 0,
            'ri'      => $r['ri'] ?? 0,
            'total'   => $r['total'] ?? 0,
            'revenue' => $r['revenue'] ?? 0,
            'bpjs'    => $r['bpjs'] ?? 0,
            'umum'    => $r['umum'] ?? 0,
        ];
    }

    protected function totalsPermintaan(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'rj'      => $sum('rj'),
            'ugd'     => $sum('ugd'),
            'ri'      => $sum('ri'),
            'total'   => $sum('total'),
            'revenue' => $sum('revenue'),
            'bpjs'    => $sum('bpjs'),
            'umum'    => $sum('umum'),
        ];
    }

    protected function chartDataPermintaan(array $rows): array
    {
        return [
            'labels' => array_column($rows, 'periode_label'),
            'rj'     => array_column($rows, 'rj'),
            'ugd'    => array_column($rows, 'ugd'),
            'ri'     => array_column($rows, 'ri'),
            'total'  => array_column($rows, 'total'),
        ];
    }

    /**
     * Top dokter pengirim Lab — gabungan RJ+UGD+RI, dr_id dari header.
     */
    protected function topDokterLab($start, $end, int $limit = 10)
    {
        // Helper subquery: gabungan dr_id + count permintaan per source
        $rj = DB::table('rstxn_rjlabs as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select(['h.dr_id', DB::raw('COUNT(*) as total')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('h.dr_id');

        $ugd = DB::table('rstxn_ugdlabs as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select(['h.dr_id', DB::raw('COUNT(*) as total')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('h.dr_id');

        $ri = DB::table('rstxn_rilabs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->select(['h.dr_id', DB::raw('COUNT(*) as total')])
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('h.dr_id');

        // Gabung 3 sources, lalu sum di outer query
        $union = $rj->unionAll($ugd)->unionAll($ri);

        return DB::query()
            ->fromSub($union, 'u')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'u.dr_id')
            ->select([
                'u.dr_id',
                DB::raw('MAX(d.dr_name) as dr_name'),
                DB::raw('SUM(u.total) as total'),
            ])
            ->groupBy('u.dr_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    protected function bulanLabel(int $m): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
    }
}
