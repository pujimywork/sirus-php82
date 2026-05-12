<?php

namespace App\Http\Traits\Manajemen\Rs\Tu;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trait untuk laporan Pendapatan RS Keseluruhan — split BPJS vs UMUM untuk evaluasi.
 *
 * Sumber data: tabel administrasi RJ/UGD/RI yang sudah diisi kasir.
 * Filter status (konsisten dgn RSVIEW_NEWDOCSALARIES):
 *   - RJ : rj_status='L' (Selesai), tanggal pakai rj_date
 *   - UGD: rj_status='L' (Selesai), tanggal pakai rj_date
 *   - RI : ri_status='P' (Pulang), tanggal pakai exit_date
 */
trait PendapatanRsTrait
{
    /**
     * Bangun matriks pendapatan per periode × modul × klaim.
     *
     * @param Carbon $start  awal periode (inclusive)
     * @param Carbon $end    akhir periode (inclusive)
     * @param string $oracleFmt  Oracle to_char format, mis. 'MM' atau 'YYYY'
     * @return array  [periode => ['rj_bpjs'=>..., 'rj_umum'=>..., 'ugd_bpjs'=>..., 'ugd_umum'=>..., 'ri_bpjs'=>..., 'ri_umum'=>..., 'bpjs'=>..., 'umum'=>..., 'total'=>...]]
     */
    protected function buildPendapatanRsAggregate(Carbon $start, Carbon $end, string $oracleFmt): array
    {
        $rj  = $this->aggregateRj($start, $end, $oracleFmt);
        $ugd = $this->aggregateUgd($start, $end, $oracleFmt);
        $ri  = $this->aggregateRi($start, $end, $oracleFmt);

        $periodes = collect()
            ->merge($rj->keys())
            ->merge($ugd->keys())
            ->merge($ri->keys())
            ->unique()
            ->values();

        $result = [];
        foreach ($periodes as $p) {
            $rjB = (int) ($rj[$p]['bpjs']  ?? 0);
            $rjU = (int) ($rj[$p]['umum']  ?? 0);
            $ugB = (int) ($ugd[$p]['bpjs'] ?? 0);
            $ugU = (int) ($ugd[$p]['umum'] ?? 0);
            $riB = (int) ($ri[$p]['bpjs']  ?? 0);
            $riU = (int) ($ri[$p]['umum']  ?? 0);
            $bpjs = $rjB + $ugB + $riB;
            $umum = $rjU + $ugU + $riU;
            $result[$p] = [
                'rj_bpjs'  => $rjB, 'rj_umum'  => $rjU,
                'ugd_bpjs' => $ugB, 'ugd_umum' => $ugU,
                'ri_bpjs'  => $riB, 'ri_umum'  => $riU,
                'bpjs'     => $bpjs,
                'umum'     => $umum,
                'total'    => $bpjs + $umum,
            ];
        }
        return $result;
    }

    private function aggregateRj(Carbon $start, Carbon $end, string $fmt): Collection
    {
        $periodeExpr = "to_char(h.rj_date, '{$fmt}')";

        $perRjExpr = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                      + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(obt.v,0)  + NVL(lab.v,0)  + NVL(rad.v,0) + NVL(oth.v,0)";

        return DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub(DB::table('rstxn_rjactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->groupBy('rj_no'),     'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->groupBy('rj_no'),   'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->groupBy('rj_no'),   'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->groupBy('rj_no'),      'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rj_no'),         'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->groupBy('rj_no'),         'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rj_no'),     'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->where('h.rj_status', 'L')
            ->whereBetween('h.rj_date', [$start, $end])
            ->selectRaw("{$periodeExpr} as periode,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRjExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRjExpr}) ELSE 0 END) as umum")
            ->groupBy(DB::raw($periodeExpr))
            ->get()
            ->keyBy('periode')
            ->map(fn($r) => ['bpjs' => (int) $r->bpjs, 'umum' => (int) $r->umum]);
    }

    private function aggregateUgd(Carbon $start, Carbon $end, string $fmt): Collection
    {
        $periodeExpr = "to_char(h.rj_date, '{$fmt}')";

        $perRjExpr = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                      + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(obt.v,0)  + NVL(lab.v,0)  + NVL(rad.v,0) + NVL(oth.v,0)";

        return DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub(DB::table('rstxn_ugdactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->groupBy('rj_no'),    'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->groupBy('rj_no'),  'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->groupBy('rj_no'),  'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->groupBy('rj_no'),     'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rj_no'),        'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->groupBy('rj_no'),        'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rj_no'),    'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->where('h.rj_status', 'L')
            ->whereBetween('h.rj_date', [$start, $end])
            ->selectRaw("{$periodeExpr} as periode,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRjExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRjExpr}) ELSE 0 END) as umum")
            ->groupBy(DB::raw($periodeExpr))
            ->get()
            ->keyBy('periode')
            ->map(fn($r) => ['bpjs' => (int) $r->bpjs, 'umum' => (int) $r->umum]);
    }

    private function aggregateRi(Carbon $start, Carbon $end, string $fmt): Collection
    {
        $periodeExpr = "to_char(h.exit_date, '{$fmt}')";

        $perRiExpr = "NVL(h.admin_age,0) + NVL(h.admin_status,0)
                      + NVL(vis.v,0) + NVL(kon.v,0)
                      + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(lab.v,0) + NVL(rad.v,0)
                      + NVL(oth.v,0) + NVL(ok.v,0)
                      + NVL(rm.v,0)  + NVL(trf.v,0)";

        $roomSub = DB::table('rsmst_trfrooms')
            ->select('rihdr_no', DB::raw(
                "NVL(SUM((NVL(room_price,0)+NVL(common_service,0)+NVL(perawatan_price,0))
                  * ROUND(NVL(day, NVL(end_date,sysdate+1)-NVL(start_date,sysdate)))),0) as v"
            ))
            ->groupBy('rihdr_no');

        $tempadmSub = DB::table('rstxn_ritempadmins')
            ->select('rihdr_no', DB::raw(
                "NVL(SUM(NVL(rj_admin,0)+NVL(poli_price,0)+NVL(acte_price,0)+NVL(actp_price,0)
                       +NVL(actd_price,0)+NVL(obat,0)+NVL(rad,0)+NVL(lab,0)
                       +NVL(other,0)+NVL(rs_admin,0)),0) as v"
            ))
            ->groupBy('rihdr_no');

        return DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub(DB::table('rstxn_rivisits')->select('rihdr_no', DB::raw('NVL(SUM(visit_price),0) as v'))->groupBy('rihdr_no'),                                          'vis',  'vis.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rikonsuls')->select('rihdr_no', DB::raw('NVL(SUM(konsul_price),0) as v'))->groupBy('rihdr_no'),                                        'kon',  'kon.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riactparams')->select('rihdr_no', DB::raw('NVL(SUM(actp_price * actp_qty),0) as v'))->groupBy('rihdr_no'),                              'actp', 'actp.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riactdocs')->select('rihdr_no', DB::raw('NVL(SUM(actd_price * actd_qty),0) as v'))->groupBy('rihdr_no'),                                'actd', 'actd.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rilabs')->select('rihdr_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rihdr_no'),                                              'lab',  'lab.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riradiologs')->select('rihdr_no', DB::raw('NVL(SUM(rirad_price),0) as v'))->groupBy('rihdr_no'),                                       'rad',  'rad.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riothers')->select('rihdr_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rihdr_no'),                                          'oth',  'oth.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rioks')->select('rihdr_no', DB::raw('NVL(SUM(ok_price),0) as v'))->groupBy('rihdr_no'),                                                'ok',   'ok.rihdr_no',   '=', 'h.rihdr_no')
            ->leftJoinSub($roomSub, 'rm',   'rm.rihdr_no',   '=', 'h.rihdr_no')
            ->leftJoinSub($tempadmSub, 'trf', 'trf.rihdr_no', '=', 'h.rihdr_no')
            ->where('h.ri_status', 'P')
            ->whereBetween('h.exit_date', [$start, $end])
            ->selectRaw("{$periodeExpr} as periode,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRiExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRiExpr}) ELSE 0 END) as umum")
            ->groupBy(DB::raw($periodeExpr))
            ->get()
            ->keyBy('periode')
            ->map(fn($r) => ['bpjs' => (int) $r->bpjs, 'umum' => (int) $r->umum]);
    }

    protected function chartDataPendapatanRs(array $rows): array
    {
        return [
            'labels'   => array_column($rows, 'label'),
            'rj_bpjs'  => array_map(fn($r) => (int) $r['rj_bpjs'],  $rows),
            'rj_umum'  => array_map(fn($r) => (int) $r['rj_umum'],  $rows),
            'ugd_bpjs' => array_map(fn($r) => (int) $r['ugd_bpjs'], $rows),
            'ugd_umum' => array_map(fn($r) => (int) $r['ugd_umum'], $rows),
            'ri_bpjs'  => array_map(fn($r) => (int) $r['ri_bpjs'],  $rows),
            'ri_umum'  => array_map(fn($r) => (int) $r['ri_umum'],  $rows),
            'bpjs'     => array_map(fn($r) => (int) $r['bpjs'],     $rows),
            'umum'     => array_map(fn($r) => (int) $r['umum'],     $rows),
            'total'    => array_map(fn($r) => (int) $r['total'],    $rows),
        ];
    }

    protected function totalsPendapatanRs(array $rows): array
    {
        return [
            'rj_bpjs'  => array_sum(array_column($rows, 'rj_bpjs')),
            'rj_umum'  => array_sum(array_column($rows, 'rj_umum')),
            'ugd_bpjs' => array_sum(array_column($rows, 'ugd_bpjs')),
            'ugd_umum' => array_sum(array_column($rows, 'ugd_umum')),
            'ri_bpjs'  => array_sum(array_column($rows, 'ri_bpjs')),
            'ri_umum'  => array_sum(array_column($rows, 'ri_umum')),
            'bpjs'     => array_sum(array_column($rows, 'bpjs')),
            'umum'     => array_sum(array_column($rows, 'umum')),
            'total'    => array_sum(array_column($rows, 'total')),
        ];
    }

    protected function bulanLabelPendapatan(int $m): string
    {
        return ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][$m - 1] ?? (string) $m;
    }

    /* ───────────────────────────────────────────────────────────────
       BREAKDOWN per dokter (3 sumber: RJ/UGD/RI)
       ─────────────────────────────────────────────────────────────── */

    /**
     * RJ: per dokter × poli, count distinct rj_no + sum pendapatan (BPJS/UMUM/total).
     */
    protected function dokterBreakdownRj(Carbon $start, Carbon $end): array
    {
        $perRjExpr = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                      + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(obt.v,0)  + NVL(lab.v,0)  + NVL(rad.v,0) + NVL(oth.v,0)";

        $rows = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'h.poli_id')
            ->leftJoinSub(DB::table('rstxn_rjactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->groupBy('rj_no'),     'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->groupBy('rj_no'),   'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->groupBy('rj_no'),   'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->groupBy('rj_no'),      'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rj_no'),         'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->groupBy('rj_no'),         'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rj_no'),     'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->where('h.rj_status', 'L')
            ->whereBetween('h.rj_date', [$start, $end])
            ->selectRaw("h.dr_id as dr_id,
                d.dr_name as dr_name,
                h.poli_id as poli_id,
                p.poli_desc as poli_desc,
                COUNT(DISTINCT h.rj_no) as jumlah,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRjExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRjExpr}) ELSE 0 END) as umum")
            ->groupBy('h.dr_id', 'd.dr_name', 'h.poli_id', 'p.poli_desc')
            ->get();

        return $rows->map(fn($r) => [
            'dr_id'     => $r->dr_id !== null && $r->dr_id !== '' ? (string) $r->dr_id : '__NONE__',
            'dr_name'   => $r->dr_name ?: '(Tidak Ada Kategori)',
            'poli_desc' => $r->poli_desc ?: '(Tidak Ada Kategori)',
            'jumlah'    => (int) $r->jumlah,
            'bpjs'      => (int) $r->bpjs,
            'umum'      => (int) $r->umum,
            'total'     => (int) $r->bpjs + (int) $r->umum,
        ])->sortByDesc('total')->values()->all();
    }

    /**
     * UGD: per dokter, count distinct rj_no + sum pendapatan (BPJS/UMUM/total).
     */
    protected function dokterBreakdownUgd(Carbon $start, Carbon $end): array
    {
        $perRjExpr = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                      + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(obt.v,0)  + NVL(lab.v,0)  + NVL(rad.v,0) + NVL(oth.v,0)";

        $rows = DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoinSub(DB::table('rstxn_ugdactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->groupBy('rj_no'),    'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->groupBy('rj_no'),  'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->groupBy('rj_no'),  'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->groupBy('rj_no'),     'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rj_no'),        'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->groupBy('rj_no'),        'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rj_no'),    'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->where('h.rj_status', 'L')
            ->whereBetween('h.rj_date', [$start, $end])
            ->selectRaw("h.dr_id as dr_id,
                d.dr_name as dr_name,
                COUNT(DISTINCT h.rj_no) as jumlah,
                SUM(CASE WHEN k.klaim_status = 'BPJS' THEN ({$perRjExpr}) ELSE 0 END) as bpjs,
                SUM(CASE WHEN k.klaim_status != 'BPJS' OR k.klaim_status IS NULL THEN ({$perRjExpr}) ELSE 0 END) as umum")
            ->groupBy('h.dr_id', 'd.dr_name')
            ->get();

        return $rows->map(fn($r) => [
            'dr_id'   => $r->dr_id !== null && $r->dr_id !== '' ? (string) $r->dr_id : '__NONE__',
            'dr_name' => $r->dr_name ?: '(Tidak Ada Kategori)',
            'jumlah'  => (int) $r->jumlah,
            'bpjs'    => (int) $r->bpjs,
            'umum'    => (int) $r->umum,
            'total'   => (int) $r->bpjs + (int) $r->umum,
        ])->sortByDesc('total')->values()->all();
    }

    /**
     * RI: per DPJP Utama (dari JSON pengkajianAwalPasienRawatInap.levelingDokter[*]
     * where levelDokter='Utama'), fallback "Tidak Ada Kategori" kalau JSON kosong.
     * Tidak bisa pakai JSON_VALUE Oracle — extract di PHP.
     */
    protected function dokterBreakdownRi(Carbon $start, Carbon $end): array
    {
        $perRiExpr = "NVL(h.admin_age,0) + NVL(h.admin_status,0)
                      + NVL(vis.v,0) + NVL(kon.v,0)
                      + NVL(actp.v,0) + NVL(actd.v,0)
                      + NVL(lab.v,0) + NVL(rad.v,0)
                      + NVL(oth.v,0) + NVL(ok.v,0)
                      + NVL(rm.v,0)  + NVL(trf.v,0)";

        $roomSub = DB::table('rsmst_trfrooms')
            ->select('rihdr_no', DB::raw(
                "NVL(SUM((NVL(room_price,0)+NVL(common_service,0)+NVL(perawatan_price,0))
                  * ROUND(NVL(day, NVL(end_date,sysdate+1)-NVL(start_date,sysdate)))),0) as v"
            ))
            ->groupBy('rihdr_no');

        $tempadmSub = DB::table('rstxn_ritempadmins')
            ->select('rihdr_no', DB::raw(
                "NVL(SUM(NVL(rj_admin,0)+NVL(poli_price,0)+NVL(acte_price,0)+NVL(actp_price,0)
                       +NVL(actd_price,0)+NVL(obat,0)+NVL(rad,0)+NVL(lab,0)
                       +NVL(other,0)+NVL(rs_admin,0)),0) as v"
            ))
            ->groupBy('rihdr_no');

        $rows = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub(DB::table('rstxn_rivisits')->select('rihdr_no', DB::raw('NVL(SUM(visit_price),0) as v'))->groupBy('rihdr_no'),                          'vis',  'vis.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rikonsuls')->select('rihdr_no', DB::raw('NVL(SUM(konsul_price),0) as v'))->groupBy('rihdr_no'),                        'kon',  'kon.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riactparams')->select('rihdr_no', DB::raw('NVL(SUM(actp_price * actp_qty),0) as v'))->groupBy('rihdr_no'),              'actp', 'actp.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riactdocs')->select('rihdr_no', DB::raw('NVL(SUM(actd_price * actd_qty),0) as v'))->groupBy('rihdr_no'),                'actd', 'actd.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rilabs')->select('rihdr_no', DB::raw('NVL(SUM(lab_price),0) as v'))->groupBy('rihdr_no'),                              'lab',  'lab.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riradiologs')->select('rihdr_no', DB::raw('NVL(SUM(rirad_price),0) as v'))->groupBy('rihdr_no'),                       'rad',  'rad.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riothers')->select('rihdr_no', DB::raw('NVL(SUM(other_price),0) as v'))->groupBy('rihdr_no'),                          'oth',  'oth.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rioks')->select('rihdr_no', DB::raw('NVL(SUM(ok_price),0) as v'))->groupBy('rihdr_no'),                                'ok',   'ok.rihdr_no',   '=', 'h.rihdr_no')
            ->leftJoinSub($roomSub, 'rm',   'rm.rihdr_no',   '=', 'h.rihdr_no')
            ->leftJoinSub($tempadmSub, 'trf', 'trf.rihdr_no', '=', 'h.rihdr_no')
            ->where('h.ri_status', 'P')
            ->whereBetween('h.exit_date', [$start, $end])
            ->selectRaw("h.rihdr_no, h.datadaftarri_json as json,
                k.klaim_status,
                ({$perRiExpr}) as total")
            ->get();

        // Extract DPJP Utama di PHP (Oracle JSON_VALUE tidak tersedia).
        $byDokter = [];
        foreach ($rows as $r) {
            $json = is_string($r->json) ? (json_decode($r->json, true) ?: []) : [];
            $drId = $this->extractDpjpUtamaPendapatan($json) ?? '__NONE__';
            $klaim = ($r->klaim_status ?? '') === 'BPJS' ? 'bpjs' : 'umum';
            $total = (int) $r->total;

            if (!isset($byDokter[$drId])) {
                $byDokter[$drId] = ['dr_id' => $drId, 'jumlah' => 0, 'bpjs' => 0, 'umum' => 0, 'total' => 0];
            }
            $byDokter[$drId]['jumlah']++;
            $byDokter[$drId][$klaim] += $total;
            $byDokter[$drId]['total'] += $total;
        }

        // Lookup nama dokter
        $drIds = array_filter(array_keys($byDokter), fn($id) => $id !== '__NONE__');
        $drMap = $drIds
            ? DB::table('rsmst_doctors')->whereIn('dr_id', $drIds)->pluck('dr_name', 'dr_id')->toArray()
            : [];

        foreach ($byDokter as $drId => &$row) {
            $row['dr_name'] = $drId === '__NONE__'
                ? '(Tidak Ada Kategori)'
                : ($drMap[$drId] ?? "(Dokter {$drId} tidak ditemukan)");
        }

        usort($byDokter, fn($a, $b) => $b['total'] <=> $a['total']);
        return array_values($byDokter);
    }

    /**
     * Inline extraction agar tidak konflik dgn trait lain (mis. RL32Trait).
     */
    private function extractDpjpUtamaPendapatan(array $json): ?string
    {
        $list = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
        if (!is_array($list)) {
            return null;
        }
        foreach ($list as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $level = (string) ($entry['levelDokter'] ?? '');
            if (strcasecmp($level, 'Utama') === 0) {
                $drId = (string) ($entry['drId'] ?? '');
                return $drId !== '' ? $drId : null;
            }
        }
        return null;
    }
}
