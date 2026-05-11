<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Rad;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Pemeriksaan Radiologi.
 *
 * Sumber data:
 *   - rstxn_rjrads (RJ), rstxn_ugdrads (UGD), rstxn_riradiologs (RI)
 *   - rsmst_radiologis (master item rad)
 *
 * Catatan:
 *   - Filter periode: RJ/UGD = rj_date, RI = exit_date (BPJS reporting)
 *   - Pasien Kronis (klaim_id='KR') di-exclude
 *   - Untuk item ranking: group by rad_id
 */
trait PemeriksaanRadTrait
{
    /**
     * Aggregate per periode + source via UNION ALL.
     */
    protected function buildPemeriksaanRadAggregate($start, $end, string $groupColRJUGD, string $groupColRI)
    {
        $rj = DB::table('rstxn_rjrads as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select([
                DB::raw("{$groupColRJUGD} as periode"),
                DB::raw("'RJ' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.rad_price), 0) as revenue'),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRJUGD));

        $ugd = DB::table('rstxn_ugdrads as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select([
                DB::raw("{$groupColRJUGD} as periode"),
                DB::raw("'UGD' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.rad_price), 0) as revenue'),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRJUGD));

        $ri = DB::table('rstxn_riradiologs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->select([
                DB::raw("{$groupColRI} as periode"),
                DB::raw("'RI' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.rirad_price), 0) as revenue'),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRI));

        return $rj->unionAll($ugd)->unionAll($ri)->get();
    }

    protected function pivotPemeriksaanByPeriode($aggregate): array
    {
        $byPeriode = [];
        foreach ($aggregate as $r) {
            $key = (string) $r->periode;
            if (!isset($byPeriode[$key])) {
                $byPeriode[$key] = ['periode' => $key, 'rj' => 0, 'ugd' => 0, 'ri' => 0, 'total' => 0, 'revenue' => 0.0];
            }
            $sourceKey = strtolower($r->source);
            $byPeriode[$key][$sourceKey] = (int) $r->total;
            $byPeriode[$key]['total'] += (int) $r->total;
            $byPeriode[$key]['revenue'] += (float) $r->revenue;
        }
        return $byPeriode;
    }

    protected function fillPemeriksaanRow(array $byPeriode, string $periodeKey, string $label): array
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
        ];
    }

    protected function totalsPemeriksaan(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'rj' => $sum('rj'), 'ugd' => $sum('ugd'), 'ri' => $sum('ri'),
            'total' => $sum('total'), 'revenue' => $sum('revenue'),
        ];
    }

    protected function chartDataPemeriksaan(array $rows): array
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
     * Ranking item radiologi terbanyak di periode (count + revenue per rad_id).
     */
    protected function topItemRad($start, $end, int $limit = 20)
    {
        $rj = DB::table('rstxn_rjrads as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select(['l.rad_id', DB::raw('COUNT(*) as total'), DB::raw('NVL(SUM(l.rad_price), 0) as revenue')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('l.rad_id');

        $ugd = DB::table('rstxn_ugdrads as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select(['l.rad_id', DB::raw('COUNT(*) as total'), DB::raw('NVL(SUM(l.rad_price), 0) as revenue')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('l.rad_id');

        $ri = DB::table('rstxn_riradiologs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->select(['l.rad_id', DB::raw('COUNT(*) as total'), DB::raw('NVL(SUM(l.rirad_price), 0) as revenue')])
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('l.rad_id');

        $union = $rj->unionAll($ugd)->unionAll($ri);

        return DB::query()
            ->fromSub($union, 'u')
            ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'u.rad_id')
            ->select([
                'u.rad_id',
                DB::raw('MAX(m.rad_desc) as item_name'),
                DB::raw('SUM(u.total) as total'),
                DB::raw('SUM(u.revenue) as revenue'),
            ])
            ->groupBy('u.rad_id')
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
