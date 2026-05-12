<?php

namespace App\Http\Traits\Manajemen\Gudang;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Transfer Antar Ruangan (bulanan & tahunan).
 *
 * Sumber data:
 *   - Medis     → imtxn_trfhdrs + imtxn_trfdtls
 *   - Non-medis → imtxn_trfhdrnonmedes + imtxn_trfdtlnonmedes
 *
 * Status transfer:
 *   - 'A' = Belum Diproses (Draft)
 *   - 'L' = Sudah Diproses (Posted)
 *   - 'F' = Dibatalkan
 *
 * Konvensi:
 *   - Filter kategori: 'all' (gabungan), 'medis', 'nonmedis'.
 *   - Aggregate dipisah per tabel di SQL, lalu di-merge per-periode di PHP
 *     untuk menghindari Oracle subquery yang kompleks.
 */
trait TransferAntarRuanganTrait
{
    public const KATEGORI_ALL = 'all';
    public const KATEGORI_MEDIS = 'medis';
    public const KATEGORI_NONMEDIS = 'nonmedis';

    /**
     * Aggregate gabungan per-periode.
     *
     * @param  string  $groupSql Oracle expression untuk grouping (mis. "TO_CHAR(h.trf_date,'MM')")
     * @return Collection ter-keyed by periode (string sesuai $groupSql output)
     */
    protected function buildTrfAggregate($start, $end, string $groupSql, string $kategori = self::KATEGORI_ALL): Collection
    {
        $merged = collect();

        if ($kategori === self::KATEGORI_ALL || $kategori === self::KATEGORI_MEDIS) {
            $merged = $this->mergePeriodeMaps(
                $merged,
                $this->aggregateOneTable($start, $end, $groupSql, 'imtxn_trfhdrs', 'imtxn_trfdtls', self::KATEGORI_MEDIS),
            );
        }

        if ($kategori === self::KATEGORI_ALL || $kategori === self::KATEGORI_NONMEDIS) {
            $merged = $this->mergePeriodeMaps(
                $merged,
                $this->aggregateOneTable($start, $end, $groupSql, 'imtxn_trfhdrnonmedes', 'imtxn_trfdtlnonmedes', self::KATEGORI_NONMEDIS),
            );
        }

        return $merged;
    }

    /**
     * Aggregate satu kategori (medis ATAU non-medis) — return Collection ter-keyed by periode.
     */
    private function aggregateOneTable($start, $end, string $groupSql, string $hdrTable, string $dtlTable, string $kategori): Collection
    {
        // Subquery item_count + qty_sum per trf_no — agar kita bisa SUM di level periode tanpa double-count.
        $sub = "(SELECT trf_no, COUNT(*) item_count, NVL(SUM(qty),0) qty_sum FROM {$dtlTable} GROUP BY trf_no)";

        $rows = DB::table("{$hdrTable} as h")
            ->leftJoin(DB::raw("{$sub} d"), 'd.trf_no', '=', 'h.trf_no')
            ->select([
                DB::raw("{$groupSql} as periode"),
                DB::raw('COUNT(DISTINCT h.trf_no) as total_trf'),
                DB::raw("SUM(CASE WHEN h.trf_status='A' THEN 1 ELSE 0 END) as draft"),
                DB::raw("SUM(CASE WHEN h.trf_status='L' THEN 1 ELSE 0 END) as posted"),
                DB::raw("SUM(CASE WHEN h.trf_status='F' THEN 1 ELSE 0 END) as batal"),
                DB::raw('NVL(SUM(d.item_count),0) as total_item'),
                DB::raw('NVL(SUM(d.qty_sum),0) as total_qty'),
            ])
            ->whereBetween('h.trf_date', [$start, $end])
            ->groupBy(DB::raw($groupSql))
            ->get();

        // Tambahkan kolom medis/nonmedis count + key by periode.
        return $rows->mapWithKeys(function ($r) use ($kategori) {
            return [
                (string) $r->periode => (object) [
                    'periode' => (string) $r->periode,
                    'total_trf' => (int) $r->total_trf,
                    'draft' => (int) $r->draft,
                    'posted' => (int) $r->posted,
                    'batal' => (int) $r->batal,
                    'total_item' => (int) $r->total_item,
                    'total_qty' => (float) $r->total_qty,
                    'medis' => $kategori === self::KATEGORI_MEDIS ? (int) $r->total_trf : 0,
                    'nonmedis' => $kategori === self::KATEGORI_NONMEDIS ? (int) $r->total_trf : 0,
                ],
            ];
        });
    }

    /**
     * Gabungkan dua periode-map dengan penjumlahan kolom angka.
     */
    private function mergePeriodeMaps(Collection $a, Collection $b): Collection
    {
        $out = $a->all();
        foreach ($b as $periode => $row) {
            if (!isset($out[$periode])) {
                $out[$periode] = clone $row;
                continue;
            }
            $existing = $out[$periode];
            $existing->total_trf += $row->total_trf;
            $existing->draft += $row->draft;
            $existing->posted += $row->posted;
            $existing->batal += $row->batal;
            $existing->total_item += $row->total_item;
            $existing->total_qty += $row->total_qty;
            $existing->medis += $row->medis;
            $existing->nonmedis += $row->nonmedis;
        }
        ksort($out);
        return collect($out);
    }

    /**
     * Fill 1 row dari aggregate hasil buildTrfAggregate.
     * Periode tanpa data → semua kolom 0.
     */
    protected function fillTrfRow(?object $r, string $label, string $short): array
    {
        return [
            'periode_label' => $label,
            'periode_short' => $short,
            'total_trf' => (int) ($r->total_trf ?? 0),
            'draft' => (int) ($r->draft ?? 0),
            'posted' => (int) ($r->posted ?? 0),
            'batal' => (int) ($r->batal ?? 0),
            'medis' => (int) ($r->medis ?? 0),
            'nonmedis' => (int) ($r->nonmedis ?? 0),
            'total_item' => (int) ($r->total_item ?? 0),
            'total_qty' => (float) ($r->total_qty ?? 0),
        ];
    }

    /**
     * Sum semua kolom angka di rows → total summary.
     */
    protected function totalsTrf(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'total_trf' => $sum('total_trf'),
            'draft' => $sum('draft'),
            'posted' => $sum('posted'),
            'batal' => $sum('batal'),
            'medis' => $sum('medis'),
            'nonmedis' => $sum('nonmedis'),
            'total_item' => $sum('total_item'),
            'total_qty' => $sum('total_qty'),
        ];
    }

    /**
     * Breakdown per pasangan lokasi (FROM → TO) di rentang periode.
     * Gabungkan medis + non-medis sesuai filter kategori, sort desc by total.
     */
    protected function lokasiBreakdownTrf($start, $end, string $kategori = self::KATEGORI_ALL): Collection
    {
        $merged = collect();

        if ($kategori === self::KATEGORI_ALL || $kategori === self::KATEGORI_MEDIS) {
            $merged = $merged->concat($this->lokasiBreakdownOne($start, $end, 'imtxn_trfhdrs', 'imtxn_trfdtls'));
        }
        if ($kategori === self::KATEGORI_ALL || $kategori === self::KATEGORI_NONMEDIS) {
            $merged = $merged->concat($this->lokasiBreakdownOne($start, $end, 'imtxn_trfhdrnonmedes', 'imtxn_trfdtlnonmedes'));
        }

        // Group (from-to) dari kedua kategori — jumlahkan total + qty.
        return $merged
            ->groupBy(fn($r) => $r->sl_codefrom . '|' . $r->sl_codeto)
            ->map(function ($g) {
                $first = $g->first();
                return (object) [
                    'sl_codefrom' => $first->sl_codefrom,
                    'sl_codeto' => $first->sl_codeto,
                    'sl_name_from' => $first->sl_name_from,
                    'sl_name_to' => $first->sl_name_to,
                    'total_trf' => (int) $g->sum('total_trf'),
                    'total_qty' => (float) $g->sum('total_qty'),
                ];
            })
            ->sortByDesc('total_trf')
            ->values();
    }

    private function lokasiBreakdownOne($start, $end, string $hdrTable, string $dtlTable): Collection
    {
        $sub = "(SELECT trf_no, NVL(SUM(qty),0) qty_sum FROM {$dtlTable} GROUP BY trf_no)";

        return DB::table("{$hdrTable} as h")
            ->leftJoin('immst_stocklocations as lf', 'lf.sl_code', '=', 'h.sl_codefrom')
            ->leftJoin('immst_stocklocations as lt', 'lt.sl_code', '=', 'h.sl_codeto')
            ->leftJoin(DB::raw("{$sub} d"), 'd.trf_no', '=', 'h.trf_no')
            ->select([
                'h.sl_codefrom',
                'h.sl_codeto',
                DB::raw('MAX(lf.sl_name) as sl_name_from'),
                DB::raw('MAX(lt.sl_name) as sl_name_to'),
                DB::raw('COUNT(DISTINCT h.trf_no) as total_trf'),
                DB::raw('NVL(SUM(d.qty_sum),0) as total_qty'),
            ])
            ->whereBetween('h.trf_date', [$start, $end])
            ->groupBy('h.sl_codefrom', 'h.sl_codeto')
            ->get();
    }

    /**
     * Breakdown per barang di rentang periode — qty & nilai (qty × cost_price).
     * Cost_price = HPP master, valuasi paling representatif untuk transfer internal.
     * Sort desc by total_value default.
     */
    protected function barangBreakdownTrf($start, $end, string $kategori = self::KATEGORI_ALL): Collection
    {
        $merged = collect();

        if ($kategori === self::KATEGORI_ALL || $kategori === self::KATEGORI_MEDIS) {
            $merged = $merged->concat($this->barangBreakdownOne(
                $start, $end,
                'imtxn_trfhdrs', 'imtxn_trfdtls', 'immst_products',
                self::KATEGORI_MEDIS,
            ));
        }
        if ($kategori === self::KATEGORI_ALL || $kategori === self::KATEGORI_NONMEDIS) {
            $merged = $merged->concat($this->barangBreakdownOne(
                $start, $end,
                'imtxn_trfhdrnonmedes', 'imtxn_trfdtlnonmedes', 'immst_productsnon',
                self::KATEGORI_NONMEDIS,
            ));
        }

        // Group product_id; karena product_id medis vs non-medis berbeda namespace, group key gabungan kategori|product_id.
        return $merged
            ->groupBy(fn($r) => $r->kategori . '|' . $r->product_id)
            ->map(function ($g) {
                $first = $g->first();
                return (object) [
                    'kategori' => $first->kategori,
                    'product_id' => $first->product_id,
                    'product_name' => $first->product_name,
                    'cost_price' => (float) $first->cost_price,
                    'total_qty' => (float) $g->sum('total_qty'),
                    'total_trf' => (int) $g->sum('total_trf'),
                    'total_value' => (float) $g->sum('total_value'),
                ];
            })
            ->sortByDesc('total_value')
            ->values();
    }

    private function barangBreakdownOne($start, $end, string $hdrTable, string $dtlTable, string $masterTable, string $kategori): Collection
    {
        // Join detail dengan header (filter trf_date) dan master (cost_price + nama).
        $rows = DB::table("{$dtlTable} as d")
            ->join("{$hdrTable} as h", 'h.trf_no', '=', 'd.trf_no')
            ->leftJoin("{$masterTable} as p", 'p.product_id', '=', 'd.product_id')
            ->select([
                'd.product_id',
                DB::raw('MAX(p.product_name) as product_name'),
                DB::raw('NVL(MAX(p.cost_price),0) as cost_price'),
                DB::raw('COUNT(DISTINCT h.trf_no) as total_trf'),
                DB::raw('NVL(SUM(d.qty),0) as total_qty'),
                DB::raw('NVL(SUM(d.qty * NVL(p.cost_price,0)),0) as total_value'),
            ])
            ->whereBetween('h.trf_date', [$start, $end])
            ->groupBy('d.product_id')
            ->get();

        // Tag kategori biar bisa di-group bareng di caller.
        return $rows->map(function ($r) use ($kategori) {
            $r->kategori = $kategori;
            return $r;
        });
    }

    /**
     * Data untuk Chart.js — array struktur seragam.
     */
    protected function chartDataTrf(array $rows): array
    {
        return [
            'labels' => array_column($rows, 'periode_label'),
            'posted' => array_column($rows, 'posted'),
            'draft' => array_column($rows, 'draft'),
            'batal' => array_column($rows, 'batal'),
            'total_trf' => array_column($rows, 'total_trf'),
            'total_qty' => array_column($rows, 'total_qty'),
        ];
    }

    protected function bulanLabelTrf(int $m): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
    }
}
