<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Rad;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic Laporan Pemeriksaan Radiologi — Detail per pasien + Rekap per unit.
 *
 * Sumber (radiologi tidak punya "luar", semua in-house):
 *   - RJ  → rstxn_rjrads       (rj_no → rstxn_rjhdrs; harga rad_price; status rj_status)
 *   - UGD → rstxn_ugdrads      (rj_no → rstxn_ugdhdrs; harga rad_price; status rj_status)
 *   - RI  → rstxn_riradiologs  (rihdr_no → rstxn_rihdrs; harga rirad_price; status ri_status)
 *   - Master item: rsmst_radiologis (rad_id → rad_desc)
 *   - Pasien: rsmst_pasiens (reg_no → reg_name, sex)
 *
 * Konvensi:
 *   - Periode di-anchor ke TANGGAL PEMERIKSAAN: RJ/UGD = rj_date (header, satu hari),
 *     RI = rirad_date (tgl rad pada detail). Bukan tgl pulang — supaya angka bulan FINAL.
 *   - Hitung semua item ber-harga KECUALI transaksi induk BATAL (status 'F').
 */
trait PemeriksaanRadDetailTrait
{
    /**
     * Query DETAIL per item (UNION RJ + UGD + RI), siap dipaginate (fromSub → ROWNUM benar).
     */
    protected function detailRadQuery($start, $end, array $filters = [])
    {
        $unit = $filters['unit'] ?? '';
        $keyword = trim($filters['pemeriksaan'] ?? '');
        $keywordUpper = $keyword !== '' ? '%' . mb_strtoupper($keyword) . '%' : null;

        $rj = $this->radDetailUnit('rstxn_rjrads', 'rstxn_rjhdrs', 'rj_no', 'rj_date', 'rad_price', 'rj_status', 'RJ', $start, $end, $keywordUpper);
        $ugd = $this->radDetailUnit('rstxn_ugdrads', 'rstxn_ugdhdrs', 'rj_no', 'rj_date', 'rad_price', 'rj_status', 'UGD', $start, $end, $keywordUpper);
        $ri = $this->radDetailUnit('rstxn_riradiologs', 'rstxn_rihdrs', 'rihdr_no', 'rirad_date', 'rirad_price', 'ri_status', 'RI', $start, $end, $keywordUpper);

        $union = match ($unit) {
            'RJ'  => $rj,
            'UGD' => $ugd,
            'RI'  => $ri,
            default => $rj->unionAll($ugd)->unionAll($ri),
        };

        return DB::query()->fromSub($union, 'u')->orderByDesc('u.tgl_sort');
    }

    /**
     * Satu subquery unit. $joinKey = kolom penghubung detail↔header (rj_no / rihdr_no).
     * $dateCol/$priceCol/$statusCol relatif alias: RJ/UGD tanggal di header (h), RI di detail (l).
     */
    private function radDetailUnit(string $detailTable, string $headerTable, string $joinKey, string $dateCol, string $priceCol, string $statusCol, string $unit, $start, $end, ?string $keywordUpper)
    {
        // RI: tanggal & harga di DETAIL (l); RJ/UGD: tanggal di HEADER (h), harga di detail.
        $isRi = $unit === 'RI';
        $dateExpr = $isRi ? "l.{$dateCol}" : "h.{$dateCol}";

        $query = DB::table("{$detailTable} as l")
            ->join("{$headerTable} as h", "h.{$joinKey}", '=', "l.{$joinKey}")
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'l.rad_id')
            ->select([
                DB::raw("to_char({$dateExpr}, 'YYYY-MM-DD HH24:MI:SS') as tgl_sort"),
                DB::raw("to_char({$dateExpr}, 'DD/MM/YYYY') as tgl"),
                DB::raw('h.reg_no as reg_no'),
                DB::raw('p.reg_name as nama'),
                DB::raw('TRIM(m.rad_desc) as pemeriksaan'),
                DB::raw("'{$unit}' as unit"),
                DB::raw("l.{$priceCol} as harga"),
                DB::raw("h.{$statusCol} as trx_status"),
                DB::raw('p.sex as sex'),
            ])
            ->whereBetween(DB::raw($dateExpr), [$start, $end])
            ->whereNotNull("l.{$priceCol}")
            ->whereRaw("NVL(h.{$statusCol}, '?') <> 'F'");

        if ($keywordUpper !== null) {
            $query->whereRaw('UPPER(m.rad_desc) LIKE ?', [$keywordUpper]);
        }

        return $query;
    }

    /**
     * REKAP: jumlah pemeriksaan + revenue per unit (RJ/UGD/RI) + total.
     */
    protected function recapRad($start, $end): array
    {
        $rj = $this->radRecapUnit('rstxn_rjrads', 'rstxn_rjhdrs', 'rj_no', 'rj_date', 'rad_price', 'rj_status', 'RJ', $start, $end);
        $ugd = $this->radRecapUnit('rstxn_ugdrads', 'rstxn_ugdhdrs', 'rj_no', 'rj_date', 'rad_price', 'rj_status', 'UGD', $start, $end);
        $ri = $this->radRecapUnit('rstxn_riradiologs', 'rstxn_rihdrs', 'rihdr_no', 'rirad_date', 'rirad_price', 'ri_status', 'RI', $start, $end);

        return [
            'rj'    => $rj,
            'ugd'   => $ugd,
            'ri'    => $ri,
            'total' => [
                'jml'     => $rj['jml'] + $ugd['jml'] + $ri['jml'],
                'revenue' => $rj['revenue'] + $ugd['revenue'] + $ri['revenue'],
            ],
        ];
    }

    private function radRecapUnit(string $detailTable, string $headerTable, string $joinKey, string $dateCol, string $priceCol, string $statusCol, string $unit, $start, $end): array
    {
        $dateExpr = $unit === 'RI' ? "l.{$dateCol}" : "h.{$dateCol}";

        $row = DB::table("{$detailTable} as l")
            ->join("{$headerTable} as h", "h.{$joinKey}", '=', "l.{$joinKey}")
            ->whereBetween(DB::raw($dateExpr), [$start, $end])
            ->whereNotNull("l.{$priceCol}")
            ->whereRaw("NVL(h.{$statusCol}, '?') <> 'F'")
            ->selectRaw("COUNT(*) as jml, NVL(SUM(l.{$priceCol}), 0) as revenue")
            ->first();

        return ['jml' => (int) ($row->jml ?? 0), 'revenue' => (float) ($row->revenue ?? 0)];
    }

    /**
     * Rekap PER JENIS pemeriksaan radiologi (semua jenis, urut terbanyak).
     */
    protected function perJenisRad($start, $end)
    {
        $rj = $this->radPerJenisUnit('rstxn_rjrads', 'rstxn_rjhdrs', 'rj_no', 'rj_date', 'rad_price', 'rj_status', 'RJ', $start, $end);
        $ugd = $this->radPerJenisUnit('rstxn_ugdrads', 'rstxn_ugdhdrs', 'rj_no', 'rj_date', 'rad_price', 'rj_status', 'UGD', $start, $end);
        $ri = $this->radPerJenisUnit('rstxn_riradiologs', 'rstxn_rihdrs', 'rihdr_no', 'rirad_date', 'rirad_price', 'ri_status', 'RI', $start, $end);

        $union = $rj->unionAll($ugd)->unionAll($ri);

        return DB::query()
            ->fromSub($union, 'u')
            ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'u.rad_id')
            ->select([
                'u.rad_id',
                DB::raw('TRIM(MAX(m.rad_desc)) as nama'),
                DB::raw('SUM(u.cnt) as jml'),
                DB::raw('SUM(u.rev) as total'),
            ])
            ->groupBy('u.rad_id')
            ->orderByDesc('jml')
            ->get();
    }

    private function radPerJenisUnit(string $detailTable, string $headerTable, string $joinKey, string $dateCol, string $priceCol, string $statusCol, string $unit, $start, $end)
    {
        $dateExpr = $unit === 'RI' ? "l.{$dateCol}" : "h.{$dateCol}";

        return DB::table("{$detailTable} as l")
            ->join("{$headerTable} as h", "h.{$joinKey}", '=', "l.{$joinKey}")
            ->select([
                'l.rad_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw("NVL(SUM(l.{$priceCol}), 0) as rev"),
            ])
            ->whereBetween(DB::raw($dateExpr), [$start, $end])
            ->whereNotNull("l.{$priceCol}")
            ->whereRaw("NVL(h.{$statusCol}, '?') <> 'F'")
            ->groupBy('l.rad_id');
    }
}
