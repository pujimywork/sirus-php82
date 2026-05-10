<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Lab;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Pemeriksaan Laboratorium.
 *
 * Sumber data:
 *   - lbtxn_checkupdtls (item-level — 1 row = 1 item lab seperti Hb, GDS, dll)
 *   - lbtxn_checkuphdrs (header dengan checkup_date + checkup_rjri)
 *   - lbmst_clabitems (master item lab)
 *
 * Catatan:
 *   - Hanya item dengan price NOT NULL yang dihitung (item billable, exclude header/group)
 *   - checkup_rjri = 'RJ' | 'UGD' | 'RI' untuk breakdown source
 */
trait PemeriksaanLabTrait
{
    /**
     * Aggregate per periode + source.
     */
    protected function buildPemeriksaanLabAggregate($start, $end, string $groupSql)
    {
        return DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->select([
                DB::raw("{$groupSql} as periode"),
                DB::raw("NVL(h.status_rjri, '?') as source"),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(d.price), 0) as revenue'),
            ])
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price')
            ->groupBy(DB::raw($groupSql), DB::raw("NVL(h.status_rjri, '?')"))
            ->get();
    }

    /**
     * Pivot per periode dengan kolom rj/ugd/ri.
     */
    protected function pivotPemeriksaanByPeriode($aggregate): array
    {
        $byPeriode = [];
        foreach ($aggregate as $r) {
            $key = (string) $r->periode;
            if (!isset($byPeriode[$key])) {
                $byPeriode[$key] = [
                    'periode' => $key,
                    'rj' => 0, 'ugd' => 0, 'ri' => 0,
                    'total' => 0, 'revenue' => 0.0,
                ];
            }
            $sourceKey = strtolower($r->source);
            if (in_array($sourceKey, ['rj', 'ugd', 'ri'], true)) {
                $byPeriode[$key][$sourceKey] += (int) $r->total;
            }
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
            'rj'      => $sum('rj'),
            'ugd'     => $sum('ugd'),
            'ri'      => $sum('ri'),
            'total'   => $sum('total'),
            'revenue' => $sum('revenue'),
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
     * Ranking item lab terbanyak di periode (count + revenue per item).
     */
    protected function topItemLab($start, $end, int $limit = 20)
    {
        return DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->leftJoin('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->select([
                'd.clabitem_id',
                DB::raw('MAX(m.clabitem_desc) as item_name'),
                DB::raw('COUNT(*) as total'),
                DB::raw('NVL(SUM(d.price), 0) as revenue'),
            ])
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price')
            ->groupBy('d.clabitem_id')
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
