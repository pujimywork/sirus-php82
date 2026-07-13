<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Lab;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic Laporan Nilai Kritis Laboratorium.
 *
 * Nilai kritis = hasil pemeriksaan yang MELEWATI AMBANG KRITIS (critical_low/high
 * limit, dipilih per jenis kelamin) pada item yang auto-alert-nya AKTIF
 * (lbmst_clabitems.nilai_kritis='Y'):
 *   - hasil numerik >= critical_high → Tinggi
 *   - hasil numerik <= critical_low  → Rendah
 *
 * THRESHOLD-ONLY (tanpa fallback): item yang ambang kritisnya BELUM diisi (kedua
 * kolom critical NULL untuk gender itu) TIDAK muncul di laporan — laporan hanya
 * memuat hasil yang benar-benar melewati ambang yang sudah dikonfigurasi. (Beda
 * dari badge KRITIS di tampilan/cetak hasil lab yang masih pakai fallback flag.)
 *
 * Sumber: lbtxn_checkupdtls (hasil) + lbtxn_checkuphdrs (checkup_date, status_rjri)
 *         + lbmst_clabitems (flag kritis, ambang, satuan) + rsmst_pasiens (gender).
 * Periode di-anchor ke checkup_date (tgl pemeriksaan).
 */
trait NilaiKritisLabTrait
{
    /** Hasil sebagai angka murni (NULL bila bukan numerik). CASE short-circuit → TO_NUMBER aman. */
    private function numResultSql(): string
    {
        return "CASE WHEN REGEXP_LIKE(TRIM(d.lab_result), '^-?[0-9]+(\.[0-9]+)?$') THEN TO_NUMBER(TRIM(d.lab_result)) END";
    }

    /** Ambang kritis atas/bawah sesuai jenis kelamin pasien. */
    private function critHighSql(): string
    {
        return "(CASE WHEN p.sex = 'P' THEN m.critical_high_f ELSE m.critical_high_m END)";
    }

    private function critLowSql(): string
    {
        return "(CASE WHEN p.sex = 'P' THEN m.critical_low_f ELSE m.critical_low_m END)";
    }

    /** Predikat Tinggi: hasil numerik >= critical_high (ambang WAJIB terisi; tanpa fallback). */
    private function tinggiSql(): string
    {
        $numericResultSql = $this->numResultSql();
        $criticalHighSql = $this->critHighSql();
        return "({$criticalHighSql} IS NOT NULL AND {$numericResultSql} IS NOT NULL AND {$numericResultSql} >= {$criticalHighSql})";
    }

    /** Predikat Rendah: hasil numerik <= critical_low (ambang WAJIB terisi; tanpa fallback). */
    private function rendahSql(): string
    {
        $numericResultSql = $this->numResultSql();
        $criticalLowSql = $this->critLowSql();
        return "({$criticalLowSql} IS NOT NULL AND {$numericResultSql} IS NOT NULL AND {$numericResultSql} <= {$criticalLowSql})";
    }

    /** Predikat kritis = Tinggi ATAU Rendah. */
    private function kritisSql(): string
    {
        return '(' . $this->tinggiSql() . ' OR ' . $this->rendahSql() . ')';
    }

    /** Base query hasil kritis (item aktif + lewat ambang kritis) di periode. */
    private function baseKritis($start, $end)
    {
        return DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->join('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.checkup_date', [$start, $end])
            ->where('m.nilai_kritis', 'Y')
            ->whereRaw($this->kritisSql());
    }

    /**
     * Query DETAIL per hasil kritis, siap dipaginate (fromSub → ROWNUM benar).
     */
    protected function detailKritisQuery($start, $end, array $filters = [])
    {
        $unit = $filters['unit'] ?? '';
        $status = $filters['status'] ?? ''; // '' | 'H' | 'L'
        $keyword = trim($filters['pemeriksaan'] ?? '');
        $keywordUpper = $keyword !== '' ? '%' . mb_strtoupper($keyword) . '%' : null;

        $query = $this->baseKritis($start, $end)
            ->select([
                DB::raw("to_char(h.checkup_date, 'YYYY-MM-DD HH24:MI:SS') as tgl_sort"),
                DB::raw("to_char(h.checkup_date, 'DD/MM/YYYY') as tgl"),
                DB::raw('h.reg_no as reg_no'),
                DB::raw('p.reg_name as nama'),
                DB::raw('p.sex as sex'),
                DB::raw('TRIM(m.clabitem_desc) as pemeriksaan'),
                DB::raw('d.lab_result as hasil'),
                DB::raw('m.unit_desc as satuan'),
                DB::raw('m.unit_convert as unit_convert'),
                DB::raw('m.lowhigh_status as lowhigh_status'),
                DB::raw($this->critLowSql() . ' as crit_low'),
                DB::raw($this->critHighSql() . ' as crit_high'),
                DB::raw("CASE WHEN " . $this->tinggiSql() . " THEN 'H' ELSE 'L' END as flag"),
                DB::raw("NVL(h.status_rjri, '-') as unit"),
            ]);

        if ($unit !== '') {
            $query->where('h.status_rjri', $unit);
        }
        if ($status === 'H') {
            $query->whereRaw($this->tinggiSql());
        }
        if ($status === 'L') {
            $query->whereRaw($this->rendahSql());
        }
        if ($keywordUpper !== null) {
            $query->whereRaw('UPPER(m.clabitem_desc) LIKE ?', [$keywordUpper]);
        }

        return DB::query()->fromSub($query, 'u')->orderByDesc('u.tgl_sort');
    }

    /**
     * REKAP: total + Tinggi/Rendah + per unit (RJ/UGD/RI).
     */
    protected function recapKritis($start, $end): array
    {
        $row = $this->baseKritis($start, $end)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN ' . $this->tinggiSql() . ' THEN 1 ELSE 0 END) as tinggi,
                SUM(CASE WHEN ' . $this->rendahSql() . ' THEN 1 ELSE 0 END) as rendah,
                SUM(CASE WHEN h.status_rjri = \'RJ\' THEN 1 ELSE 0 END) as rj,
                SUM(CASE WHEN h.status_rjri = \'UGD\' THEN 1 ELSE 0 END) as ugd,
                SUM(CASE WHEN h.status_rjri = \'RI\' THEN 1 ELSE 0 END) as ri
            ')
            ->first();

        return [
            'total'  => (int) ($row->total ?? 0),
            'tinggi' => (int) ($row->tinggi ?? 0),
            'rendah' => (int) ($row->rendah ?? 0),
            'rj'     => (int) ($row->rj ?? 0),
            'ugd'    => (int) ($row->ugd ?? 0),
            'ri'     => (int) ($row->ri ?? 0),
        ];
    }

    /**
     * Rekap PER JENIS pemeriksaan yang kritis (jml + Tinggi/Rendah), urut terbanyak.
     */
    protected function perJenisKritis($start, $end)
    {
        return $this->baseKritis($start, $end)
            ->select([
                'd.clabitem_id',
                DB::raw('TRIM(MAX(m.clabitem_desc)) as nama'),
                DB::raw('COUNT(*) as jml'),
                DB::raw('SUM(CASE WHEN ' . $this->tinggiSql() . ' THEN 1 ELSE 0 END) as tinggi'),
                DB::raw('SUM(CASE WHEN ' . $this->rendahSql() . ' THEN 1 ELSE 0 END) as rendah'),
            ])
            ->groupBy('d.clabitem_id')
            ->orderByDesc('jml')
            ->get();
    }
}
