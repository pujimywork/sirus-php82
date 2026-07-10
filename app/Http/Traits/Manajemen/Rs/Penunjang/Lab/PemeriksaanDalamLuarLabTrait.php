<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Lab;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Pemeriksaan Lab — Dalam (RS sendiri) vs Luar (rujukan).
 *
 * Sumber data (Administrasi Laborat):
 *   - Lab DALAM  → lbtxn_checkupdtls    (item internal; harga di kolom price, clabitem_id → master)
 *   - Lab LUAR   → lbtxn_checkupoutdtls (item eksternal/rujukan; harga di labout_price, desc free-text)
 *   - Header     → lbtxn_checkuphdrs    (checkup_date, reg_no, status_rjri = RJ|UGD|RI, patient_name)
 *   - Pasien     → rsmst_pasiens        (reg_name)
 *
 * Konvensi:
 *   - Hanya item dengan harga NOT NULL yang dihitung (item billable, exclude header/group).
 *   - Periode & filter pakai h.checkup_date (bisa 1 bulan atau 1 tahun penuh).
 *   - "Pemeriksaan" = jumlah item (COUNT baris detail), bukan jumlah order.
 */
trait PemeriksaanDalamLuarLabTrait
{
    /**
     * Query DETAIL per item (UNION dalam + luar), siap dipaginate.
     * Union dibungkus fromSub supaya ROWNUM pagination Oracle benar
     * (paginate langsung di atas UNION menghitung total salah).
     */
    protected function detailDalamLuarQuery($start, $end, array $filters = [])
    {
        $jenis = $filters['jenis'] ?? '';
        $unit = $filters['unit'] ?? '';
        $kw = trim($filters['pemeriksaan'] ?? '');
        $kwUp = $kw !== '' ? '%' . mb_strtoupper($kw) . '%' : null;

        $dalam = DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->leftJoin('rstxn_rjhdrs as rjh', function ($j) {
                $j->on('rjh.rj_no', '=', 'h.ref_no')->where('h.status_rjri', 'RJ');
            })
            ->leftJoin('rstxn_ugdhdrs as ugh', function ($j) {
                $j->on('ugh.rj_no', '=', 'h.ref_no')->where('h.status_rjri', 'UGD');
            })
            ->leftJoin('rstxn_rihdrs as rih', function ($j) {
                $j->on('rih.rihdr_no', '=', 'h.ref_no')->where('h.status_rjri', 'RI');
            })
            ->select([
                DB::raw("to_char(h.checkup_date, 'YYYY-MM-DD HH24:MI:SS') as tgl_sort"),
                DB::raw("to_char(h.checkup_date, 'DD/MM/YYYY') as tgl"),
                DB::raw('h.reg_no as reg_no'),
                DB::raw('NVL(p.reg_name, h.patient_name) as nama'),
                DB::raw('TRIM(m.clabitem_desc) as pemeriksaan'),
                DB::raw("'DALAM' as jenis"),
                DB::raw('d.price as harga'),
                DB::raw("NVL(h.status_rjri, '-') as unit"),
                DB::raw('p.sex as sex'),
                DB::raw("CASE h.status_rjri WHEN 'RJ' THEN rjh.rj_status WHEN 'UGD' THEN ugh.rj_status WHEN 'RI' THEN rih.ri_status END as trx_status"),
            ])
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price');
        if ($unit !== '') {
            $dalam->where('h.status_rjri', $unit);
        }
        if ($kwUp !== null) {
            $dalam->whereRaw('UPPER(m.clabitem_desc) LIKE ?', [$kwUp]);
        }

        $luar = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'o.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rstxn_rjhdrs as rjh', function ($j) {
                $j->on('rjh.rj_no', '=', 'h.ref_no')->where('h.status_rjri', 'RJ');
            })
            ->leftJoin('rstxn_ugdhdrs as ugh', function ($j) {
                $j->on('ugh.rj_no', '=', 'h.ref_no')->where('h.status_rjri', 'UGD');
            })
            ->leftJoin('rstxn_rihdrs as rih', function ($j) {
                $j->on('rih.rihdr_no', '=', 'h.ref_no')->where('h.status_rjri', 'RI');
            })
            ->select([
                DB::raw("to_char(h.checkup_date, 'YYYY-MM-DD HH24:MI:SS') as tgl_sort"),
                DB::raw("to_char(h.checkup_date, 'DD/MM/YYYY') as tgl"),
                DB::raw('h.reg_no as reg_no'),
                DB::raw('NVL(p.reg_name, h.patient_name) as nama'),
                DB::raw('TRIM(o.labout_desc) as pemeriksaan'),
                DB::raw("'LUAR' as jenis"),
                DB::raw('o.labout_price as harga'),
                DB::raw("NVL(h.status_rjri, '-') as unit"),
                DB::raw('p.sex as sex'),
                DB::raw("CASE h.status_rjri WHEN 'RJ' THEN rjh.rj_status WHEN 'UGD' THEN ugh.rj_status WHEN 'RI' THEN rih.ri_status END as trx_status"),
            ])
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('o.labout_price');
        if ($unit !== '') {
            $luar->where('h.status_rjri', $unit);
        }
        if ($kwUp !== null) {
            $luar->whereRaw('UPPER(o.labout_desc) LIKE ?', [$kwUp]);
        }

        // Filter Jenis → pilih subquery; kosong = gabung dua-duanya.
        if ($jenis === 'DALAM') {
            $union = $dalam;
        } elseif ($jenis === 'LUAR') {
            $union = $luar;
        } else {
            $union = $dalam->unionAll($luar);
        }

        return DB::query()->fromSub($union, 'u')->orderByDesc('u.tgl_sort');
    }

    /**
     * REKAP total periode: jumlah pemeriksaan + revenue, dipisah Dalam vs Luar.
     */
    protected function recapDalamLuar($start, $end): array
    {
        $dalam = DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price')
            ->selectRaw('COUNT(*) as jml, NVL(SUM(d.price), 0) as revenue')
            ->first();

        $luar = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'o.checkup_no')
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('o.labout_price')
            ->selectRaw('COUNT(*) as jml, NVL(SUM(o.labout_price), 0) as revenue')
            ->first();

        $dJml = (int) ($dalam->jml ?? 0);
        $dRev = (float) ($dalam->revenue ?? 0);
        $lJml = (int) ($luar->jml ?? 0);
        $lRev = (float) ($luar->revenue ?? 0);

        return [
            'dalam' => ['jml' => $dJml, 'revenue' => $dRev],
            'luar'  => ['jml' => $lJml, 'revenue' => $lRev],
            'total' => ['jml' => $dJml + $lJml, 'revenue' => $dRev + $lRev],
        ];
    }

    /**
     * Rekap PER JENIS pemeriksaan Lab DALAM (RS sendiri): nama, jml, total.
     * Semua jenis, urut jumlah terbanyak.
     */
    protected function perJenisDalam($start, $end)
    {
        return DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->leftJoin('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->select([
                DB::raw('TRIM(MAX(m.clabitem_desc)) as nama'),
                DB::raw('COUNT(*) as jml'),
                DB::raw('NVL(SUM(d.price), 0) as total'),
            ])
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price')
            ->groupBy('d.clabitem_id')
            ->orderByDesc('jml')
            ->get();
    }

    /**
     * Rekap PER JENIS pemeriksaan Lab LUAR (rujukan): nama, jml, total.
     * labout_desc free-text → group by teks dinormalisasi (UPPER+TRIM).
     */
    protected function perJenisLuar($start, $end)
    {
        return DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'o.checkup_no')
            ->select([
                DB::raw('TRIM(MAX(o.labout_desc)) as nama'),
                DB::raw('COUNT(*) as jml'),
                DB::raw('NVL(SUM(o.labout_price), 0) as total'),
            ])
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('o.labout_price')
            ->groupBy(DB::raw('UPPER(TRIM(o.labout_desc))'))
            ->orderByDesc('jml')
            ->get();
    }
}
