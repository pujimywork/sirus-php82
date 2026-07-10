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
     * Tambah LEFT JOIN header induk (RJ/UGD/RI) ke query yang punya alias "h"
     * (lbtxn_checkuphdrs). Dipakai untuk ambil/filter status transaksi induk.
     */
    protected function joinParentHeaders($query)
    {
        return $query
            ->leftJoin('rstxn_rjhdrs as rjh', function ($join) {
                $join->on('rjh.rj_no', '=', 'h.ref_no')->where('h.status_rjri', 'RJ');
            })
            ->leftJoin('rstxn_ugdhdrs as ugh', function ($join) {
                $join->on('ugh.rj_no', '=', 'h.ref_no')->where('h.status_rjri', 'UGD');
            })
            ->leftJoin('rstxn_rihdrs as rih', function ($join) {
                $join->on('rih.rihdr_no', '=', 'h.ref_no')->where('h.status_rjri', 'RI');
            });
    }

    /**
     * Hanya hitung transaksi yang SUDAH SELESAI di induk:
     *   RJ/UGD → rj_status 'L' (Selesai) atau 'I' (Transfer — unit sumber selesai,
     *            pasien pindah: RJ→UGD / UGD→Inap) ;
     *   RI     → ri_status 'P' (Pulang).
     * Yang masih Antrian (A), Dirawat (RI 'I'), Batal (F), atau induk tak ditemukan
     * TIDAK dihitung. Query wajib sudah join header (joinParentHeaders).
     */
    protected function whereFinishedParent($query)
    {
        return $query->whereRaw("(
            (h.status_rjri = 'RJ'  AND rjh.rj_status IN ('L', 'I')) OR
            (h.status_rjri = 'UGD' AND ugh.rj_status IN ('L', 'I')) OR
            (h.status_rjri = 'RI'  AND rih.ri_status = 'P')
        )");
    }

    /**
     * Query DETAIL per item (UNION dalam + luar), siap dipaginate.
     * Union dibungkus fromSub supaya ROWNUM pagination Oracle benar
     * (paginate langsung di atas UNION menghitung total salah).
     */
    protected function detailDalamLuarQuery($start, $end, array $filters = [])
    {
        $jenis = $filters['jenis'] ?? '';
        $unit = $filters['unit'] ?? '';
        $keyword = trim($filters['pemeriksaan'] ?? '');
        $keywordUpper = $keyword !== '' ? '%' . mb_strtoupper($keyword) . '%' : null;

        $dalam = DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->tap($this->joinParentHeaders(...))
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
        $this->whereFinishedParent($dalam);
        if ($unit !== '') {
            $dalam->where('h.status_rjri', $unit);
        }
        if ($keywordUpper !== null) {
            $dalam->whereRaw('UPPER(m.clabitem_desc) LIKE ?', [$keywordUpper]);
        }

        $luar = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'o.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->tap($this->joinParentHeaders(...))
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
        $this->whereFinishedParent($luar);
        if ($unit !== '') {
            $luar->where('h.status_rjri', $unit);
        }
        if ($keywordUpper !== null) {
            $luar->whereRaw('UPPER(o.labout_desc) LIKE ?', [$keywordUpper]);
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
            ->tap($this->joinParentHeaders(...))
            ->tap($this->whereFinishedParent(...))
            ->whereBetween('h.checkup_date', [$start, $end])
            ->whereNotNull('d.price')
            ->selectRaw('COUNT(*) as jml, NVL(SUM(d.price), 0) as revenue')
            ->first();

        $luar = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'o.checkup_no')
            ->tap($this->joinParentHeaders(...))
            ->tap($this->whereFinishedParent(...))
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
            ->tap($this->joinParentHeaders(...))
            ->tap($this->whereFinishedParent(...))
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
            ->tap($this->joinParentHeaders(...))
            ->tap($this->whereFinishedParent(...))
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
