<?php

namespace App\Http\Traits\Manajemen\Rs\Penunjang\Lab;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic Laporan Nilai Kritis Laboratorium.
 *
 * Nilai kritis = hasil pemeriksaan yang MELEWATI AMBANG (low/high limit) pada item
 * yang auto-alert-nya AKTIF (lbmst_clabitems.nilai_kritis='Y'):
 *   - status hasil H/HH/HIGH → di atas ambang (Tinggi)
 *   - status hasil L/LL/LOW  → di bawah ambang (Rendah)
 *
 * Sumber: lbtxn_checkupdtls (hasil) + lbtxn_checkuphdrs (checkup_date, status_rjri)
 *         + lbmst_clabitems (flag kritis, limit, satuan) + rsmst_pasiens (gender).
 * Periode di-anchor ke checkup_date (tgl pemeriksaan).
 */
trait NilaiKritisLabTrait
{
    /** SQL predikat hasil di luar ambang (Tinggi atau Rendah). */
    private const KRITIS_WHERE = "UPPER(d.lab_result_status) IN ('H', 'HH', 'HIGH', 'L', 'LL', 'LOW')";
    private const TINGGI_WHERE = "UPPER(d.lab_result_status) IN ('H', 'HH', 'HIGH')";
    private const RENDAH_WHERE = "UPPER(d.lab_result_status) IN ('L', 'LL', 'LOW')";

    /** Base query hasil kritis (item aktif + di luar ambang) di periode. */
    private function baseKritis($start, $end)
    {
        return DB::table('lbtxn_checkupdtls as d')
            ->join('lbtxn_checkuphdrs as h', 'h.checkup_no', '=', 'd.checkup_no')
            ->join('lbmst_clabitems as m', 'm.clabitem_id', '=', 'd.clabitem_id')
            ->whereBetween('h.checkup_date', [$start, $end])
            ->where('m.nilai_kritis', 'Y')
            ->whereRaw(self::KRITIS_WHERE);
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
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
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
                DB::raw("CASE WHEN p.sex = 'P' THEN m.low_limit_f ELSE m.low_limit_m END as low_limit"),
                DB::raw("CASE WHEN p.sex = 'P' THEN m.high_limit_f ELSE m.high_limit_m END as high_limit"),
                DB::raw('UPPER(d.lab_result_status) as flag'),
                DB::raw("NVL(h.status_rjri, '-') as unit"),
            ]);

        if ($unit !== '') {
            $query->where('h.status_rjri', $unit);
        }
        if ($status === 'H') {
            $query->whereRaw(self::TINGGI_WHERE);
        }
        if ($status === 'L') {
            $query->whereRaw(self::RENDAH_WHERE);
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
                SUM(CASE WHEN ' . self::TINGGI_WHERE . ' THEN 1 ELSE 0 END) as tinggi,
                SUM(CASE WHEN ' . self::RENDAH_WHERE . ' THEN 1 ELSE 0 END) as rendah,
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
                DB::raw('SUM(CASE WHEN ' . self::TINGGI_WHERE . ' THEN 1 ELSE 0 END) as tinggi'),
                DB::raw('SUM(CASE WHEN ' . self::RENDAH_WHERE . ' THEN 1 ELSE 0 END) as rendah'),
            ])
            ->groupBy('d.clabitem_id')
            ->orderByDesc('jml')
            ->get();
    }
}
