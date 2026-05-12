<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Rad;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Permintaan Radiologi (rad-bulanan & rad-tahunan).
 *
 * Konvensi:
 *   - 3 sumber permintaan: rstxn_rjrads (RJ), rstxn_ugdrads (UGD), rstxn_riradiologs (RI)
 *   - Filter periode RJ/UGD = rj_date, RI = exit_date (BPJS reporting)
 *   - Pasien Kronis (klaim_id='KR') di-exclude
 *   - "Dokter pengirim" Rad = kolom dr_pengirim langsung di tabel rad
 *   - Catatan: RI pakai kolom rirad_price (bukan rad_price) dan FK rihdr_no
 */
trait PermintaanRadTrait
{
    protected function buildPermintaanRadAggregate($start, $end, string $groupColRJUGD, string $groupColRI)
    {
        $rj = DB::table('rstxn_rjrads as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupColRJUGD} as periode"),
                DB::raw("'RJ' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.rad_price), 0) as revenue'),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRJUGD));

        $ugd = DB::table('rstxn_ugdrads as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupColRJUGD} as periode"),
                DB::raw("'UGD' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.rad_price), 0) as revenue'),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRJUGD));

        // RI pakai rstxn_riradiologs + rihdr_no + rirad_price
        $ri = DB::table('rstxn_riradiologs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupColRI} as periode"),
                DB::raw("'RI' as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(l.rirad_price), 0) as revenue'),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupColRI));

        return $rj->unionAll($ugd)->unionAll($ri)->get();
    }

    /**
     * Pivot hasil aggregate (per periode, source) jadi 1 row per periode.
     * Sama dengan PermintaanLabTrait — return shape identik.
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
     * Top dokter pengirim Rad — pakai kolom dr_pengirim langsung di tabel rad.
     */
    protected function topDokterRad($start, $end, int $limit = 10)
    {
        $rj = DB::table('rstxn_rjrads as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select(['l.dr_pengirim', DB::raw('COUNT(*) as total')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('l.dr_pengirim')
            ->groupBy('l.dr_pengirim');

        $ugd = DB::table('rstxn_ugdrads as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->select(['l.dr_pengirim', DB::raw('COUNT(*) as total')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('l.dr_pengirim')
            ->groupBy('l.dr_pengirim');

        $ri = DB::table('rstxn_riradiologs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->select(['l.dr_pengirim', DB::raw('COUNT(*) as total')])
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('l.dr_pengirim')
            ->groupBy('l.dr_pengirim');

        $union = $rj->unionAll($ugd)->unionAll($ri);

        return DB::query()
            ->fromSub($union, 'u')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'u.dr_pengirim')
            ->select([
                DB::raw('u.dr_pengirim as dr_id'),
                DB::raw('MAX(d.dr_name) as dr_name'),
                DB::raw('SUM(u.total) as total'),
            ])
            ->groupBy('u.dr_pengirim')
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
