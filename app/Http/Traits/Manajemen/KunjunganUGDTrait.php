<?php

namespace App\Http\Traits\Manajemen;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Kunjungan UGD (ugd-bulanan & ugd-tahunan).
 *
 * Konvensi:
 *   - Pasien Kronis (klaim_id='KR') di-exclude dari hitungan kunjungan.
 *   - rj_status='I' di UGD dipetakan sebagai "Transfer RI" (transfer ke Rawat Inap).
 *   - Pasien Baru = pass_status='N', Pasien Lama = pass_status='O' atau NULL.
 *   - BPJS = klaim_status='BPJS' OR klaim_id='JM' (JKN Mobile).
 *   - UGD tidak punya konsep poli — breakdown pakai Dokter.
 */
trait KunjunganUGDTrait
{
    /**
     * Aggregate query — group by ekspresi yang dikirim caller.
     *
     * @param  string  $groupSql Oracle expression yang juga dipakai sebagai SELECT alias 'periode'
     */
    protected function buildKunjunganUGDAggregate($start, $end, string $groupSql)
    {
        // Catatan: Oracle DB di lingkungan ini tidak punya JSON_VALUE (versi <12c
        // atau JSON option tidak aktif). Pakai INSTR untuk pattern-match string
        // JSON. JSON di-encode oleh PHP json_encode tanpa pretty-print, format
        // selalu kompak: "tingkatKegawatan":"P1" — aman untuk INSTR.
        $triaseInstr = fn(string $p) => "INSTR(h.datadaftarugd_json, '\"tingkatKegawatan\":\"{$p}\"')";

        return DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupSql} as periode"),
                DB::raw("COUNT(DISTINCT h.rj_no) as total"),
                DB::raw("COUNT(DISTINCT h.reg_no) as pasien_unik"),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
                DB::raw("SUM(CASE WHEN h.pass_status='N' THEN 1 ELSE 0 END) as baru"),
                DB::raw("SUM(CASE WHEN h.pass_status='O' OR h.pass_status IS NULL THEN 1 ELSE 0 END) as lama"),
                DB::raw("SUM(CASE WHEN h.rj_status='L' THEN 1 ELSE 0 END) as selesai"),
                DB::raw("SUM(CASE WHEN h.rj_status='F' THEN 1 ELSE 0 END) as batal"),
                DB::raw("SUM(CASE WHEN h.rj_status='I' THEN 1 ELSE 0 END) as transfer_ri"),
                DB::raw("SUM(CASE WHEN h.rj_status='A' OR h.rj_status IS NULL THEN 1 ELSE 0 END) as antrian"),
                // Triase (P1-P4) — INSTR pattern match
                DB::raw("SUM(CASE WHEN {$triaseInstr('P1')} > 0 THEN 1 ELSE 0 END) as p1"),
                DB::raw("SUM(CASE WHEN {$triaseInstr('P2')} > 0 THEN 1 ELSE 0 END) as p2"),
                DB::raw("SUM(CASE WHEN {$triaseInstr('P3')} > 0 THEN 1 ELSE 0 END) as p3"),
                DB::raw("SUM(CASE WHEN {$triaseInstr('P4')} > 0 THEN 1 ELSE 0 END) as p4"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupSql))
            ->orderBy(DB::raw($groupSql))
            ->get()
            ->keyBy('periode');
    }

    protected function pasienUnikGlobalUGD($start, $end): int
    {
        return DB::table('rstxn_ugdhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where('klaim_id', '!=', 'KR')
            ->distinct()
            ->count('reg_no');
    }

    /**
     * Breakdown per dokter UGD — semua dokter, sorted desc by total.
     */
    protected function dokterBreakdownUGD($start, $end)
    {
        return DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select([
                'h.dr_id',
                DB::raw('MAX(d.dr_name) as dr_name'),
                DB::raw('COUNT(DISTINCT h.rj_no) as total'),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('h.dr_id')
            ->orderByDesc('total')
            ->get();
    }

    protected function fillKunjunganRow(?object $r, string $label, string $short): array
    {
        $total = (int) ($r->total ?? 0);
        $p1 = (int) ($r->p1 ?? 0);
        $p2 = (int) ($r->p2 ?? 0);
        $p3 = (int) ($r->p3 ?? 0);
        $p4 = (int) ($r->p4 ?? 0);

        return [
            'periode_label' => $label,
            'periode_short' => $short,
            'total'         => $total,
            'pasien_unik'   => (int) ($r->pasien_unik ?? 0),
            'bpjs'          => (int) ($r->bpjs ?? 0),
            'umum'          => (int) ($r->umum ?? 0),
            'baru'          => (int) ($r->baru ?? 0),
            'lama'          => (int) ($r->lama ?? 0),
            'selesai'       => (int) ($r->selesai ?? 0),
            'batal'         => (int) ($r->batal ?? 0),
            'transfer_ri'   => (int) ($r->transfer_ri ?? 0),
            'antrian'       => (int) ($r->antrian ?? 0),
            'p1'            => $p1,
            'p2'            => $p2,
            'p3'            => $p3,
            'p4'            => $p4,
            // Triase kosong = total - jumlah yang punya label triase
            'triase_kosong' => max(0, $total - ($p1 + $p2 + $p3 + $p4)),
        ];
    }

    protected function totalsKunjungan(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'total'         => $sum('total'),
            'pasien_unik'   => $sum('pasien_unik'),
            'bpjs'          => $sum('bpjs'),
            'umum'          => $sum('umum'),
            'baru'          => $sum('baru'),
            'lama'          => $sum('lama'),
            'selesai'       => $sum('selesai'),
            'batal'         => $sum('batal'),
            'transfer_ri'   => $sum('transfer_ri'),
            'antrian'       => $sum('antrian'),
            'p1'            => $sum('p1'),
            'p2'            => $sum('p2'),
            'p3'            => $sum('p3'),
            'p4'            => $sum('p4'),
            'triase_kosong' => $sum('triase_kosong'),
        ];
    }

    protected function chartDataKunjungan(array $rows): array
    {
        return [
            'labels' => array_column($rows, 'periode_label'),
            'bpjs'   => array_column($rows, 'bpjs'),
            'umum'   => array_column($rows, 'umum'),
            'total'  => array_column($rows, 'total'),
            'baru'   => array_column($rows, 'baru'),
            'lama'   => array_column($rows, 'lama'),
        ];
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
