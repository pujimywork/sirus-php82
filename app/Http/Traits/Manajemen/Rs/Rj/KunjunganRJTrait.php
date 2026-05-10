<?php

namespace App\Http\Traits\Manajemen\Rs\Rj;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Kunjungan RJ (rj-bulanan & rj-tahunan).
 *
 * Konvensi:
 *   - Pasien Kronis (klaim_id='KR') di-exclude dari hitungan kunjungan.
 *   - rj_status='I' dipetakan sebagai "Transfer UGD" (transfer internal, bukan rujuk eksternal).
 *   - Pasien Baru = pass_status='N', Pasien Lama = pass_status='O' atau NULL.
 *   - BPJS = klaim_status='BPJS' OR klaim_id='JM' (JKN Mobile).
 */
trait KunjunganRJTrait
{
    /**
     * Aggregate query — group by ekspresi yang dikirim caller.
     * Return Collection ter-keyed by 'periode'.
     *
     * @param  string  $groupSql Oracle expression yang juga dipakai sebagai SELECT alias 'periode'
     */
    protected function buildKunjunganRJAggregate($start, $end, string $groupSql)
    {
        return DB::table('rstxn_rjhdrs as h')
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
                DB::raw("SUM(CASE WHEN h.rj_status='I' THEN 1 ELSE 0 END) as transfer_ugd"),
                DB::raw("SUM(CASE WHEN h.rj_status='A' OR h.rj_status IS NULL THEN 1 ELSE 0 END) as antrian"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy(DB::raw($groupSql))
            ->orderBy(DB::raw($groupSql))
            ->get()
            ->keyBy('periode');
    }

    /**
     * Pasien unik global di rentang periode (distinct reg_no, non-Kronis).
     */
    protected function pasienUnikGlobalRJ($start, $end): int
    {
        return DB::table('rstxn_rjhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where('klaim_id', '!=', 'KR')
            ->distinct()
            ->count('reg_no');
    }

    /**
     * Breakdown per poli — semua poli, sorted desc by total.
     */
    protected function poliBreakdownRJ($start, $end)
    {
        return DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'h.poli_id')
            ->select([
                'h.poli_id',
                DB::raw('MAX(p.poli_desc) as poli_desc'),
                DB::raw('COUNT(DISTINCT h.rj_no) as total'),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->groupBy('h.poli_id')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Fill 1 row dari aggregate hasil buildKunjunganRJAggregate.
     * Pakai null-coalescing supaya periode tanpa data tetap 0 (bukan null).
     */
    protected function fillKunjunganRow(?object $r, string $label, string $short): array
    {
        return [
            'periode_label' => $label,
            'periode_short' => $short,
            'total'         => (int) ($r->total ?? 0),
            'pasien_unik'   => (int) ($r->pasien_unik ?? 0),
            'bpjs'          => (int) ($r->bpjs ?? 0),
            'umum'          => (int) ($r->umum ?? 0),
            'baru'          => (int) ($r->baru ?? 0),
            'lama'          => (int) ($r->lama ?? 0),
            'selesai'       => (int) ($r->selesai ?? 0),
            'batal'         => (int) ($r->batal ?? 0),
            'transfer_ugd'  => (int) ($r->transfer_ugd ?? 0),
            'antrian'       => (int) ($r->antrian ?? 0),
        ];
    }

    /**
     * Sum semua kolom di rows → total summary.
     * Catatan: 'pasien_unik' di sini adalah penjumlahan baris (bisa over-count
     * pasien yang berkunjung di banyak periode). Untuk angka akurat global,
     * panggil pasienUnikGlobalRJ() terpisah.
     */
    protected function totalsKunjungan(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'total'        => $sum('total'),
            'pasien_unik'  => $sum('pasien_unik'),
            'bpjs'         => $sum('bpjs'),
            'umum'         => $sum('umum'),
            'baru'         => $sum('baru'),
            'lama'         => $sum('lama'),
            'selesai'      => $sum('selesai'),
            'batal'        => $sum('batal'),
            'transfer_ugd' => $sum('transfer_ugd'),
            'antrian'      => $sum('antrian'),
        ];
    }

    /**
     * Data untuk Chart.js — array struktur seragam, dipakai oleh window.chartKunjunganRJ().
     */
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
