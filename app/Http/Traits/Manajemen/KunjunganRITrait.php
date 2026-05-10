<?php

namespace App\Http\Traits\Manajemen;

use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan Kunjungan Rawat Inap (ri-bulanan & ri-tahunan).
 *
 * Konvensi:
 *   - Filter periode pakai **exit_date** (tanggal pulang) — sesuai konvensi
 *     BPJS reporting RI yang dihitung saat pasien discharge.
 *   - Pasien Kronis (klaim_id='KR') di-exclude (tidak relevan di RI tapi safety).
 *   - Status RI:
 *       I = Dirawat (sedang inap aktif — biasanya tidak muncul di laporan
 *           periodik karena belum punya exit_date)
 *       L = Selesai (pulang)
 *       F = Batal
 *   - LOS (Length of Stay) = exit_date - entry_date (Oracle date arithmetic
 *     return NUMBER dalam hari).
 *   - ALOS = AVG(LOS), Total LOS = SUM(LOS).
 *   - BPJS = klaim_status='BPJS' OR klaim_id='JM'.
 *   - Breakdown spesifik RI: per **Bangsal** (bukan poli/dokter).
 */
trait KunjunganRITrait
{
    /**
     * Aggregate query — group by ekspresi yang dikirim caller.
     */
    protected function buildKunjunganRIAggregate($start, $end, string $groupSql)
    {
        return DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupSql} as periode"),
                DB::raw("COUNT(DISTINCT h.rihdr_no) as total"),
                DB::raw("COUNT(DISTINCT h.reg_no) as pasien_unik"),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
                DB::raw("SUM(CASE WHEN h.ri_status='L' THEN 1 ELSE 0 END) as selesai"),
                DB::raw("SUM(CASE WHEN h.ri_status='F' THEN 1 ELSE 0 END) as batal"),
                DB::raw("SUM(CASE WHEN h.ri_status='I' THEN 1 ELSE 0 END) as dirawat"),
                // LOS aggregates — Oracle date subtraction returns days as NUMBER
                DB::raw("SUM(NVL(h.exit_date - h.entry_date, 0)) as total_los"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('h.exit_date')
            ->groupBy(DB::raw($groupSql))
            ->orderBy(DB::raw($groupSql))
            ->get()
            ->keyBy('periode');
    }

    protected function pasienUnikGlobalRI($start, $end): int
    {
        return DB::table('rstxn_rihdrs')
            ->whereBetween('exit_date', [$start, $end])
            ->whereNotNull('exit_date')
            ->where('klaim_id', '!=', 'KR')
            ->distinct()
            ->count('reg_no');
    }

    /**
     * Breakdown per bangsal — semua bangsal, sorted desc by total.
     * Plus ALOS, total LOS, jumlah pasien meninggal (untuk GDR/NDR).
     *
     * Catatan: bangsal_id BUKAN kolom langsung di rstxn_rihdrs. Lookup via:
     *   rstxn_rihdrs.room_id → rsmst_rooms.bangsal_id → rsmst_bangsals.bangsal_name
     *
     * Deteksi "Meninggal" lewat JSON datadaftarri_json (path:
     *   perencanaan.tindakLanjut.tindakLanjutKode = '419099009' SNOMED-CT).
     * Pakai INSTR pattern karena Oracle DB di repo ini tidak support JSON_VALUE.
     */
    protected function bangsalBreakdownRI($start, $end)
    {
        $deathPattern = '"tindakLanjutKode":"419099009"';

        return DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->select([
                'r.bangsal_id',
                DB::raw('MAX(b.bangsal_name) as bangsal_name'),
                DB::raw('COUNT(DISTINCT h.rihdr_no) as total'),
                DB::raw('ROUND(AVG(NVL(h.exit_date - h.entry_date, 0)), 1) as alos'),
                DB::raw('SUM(NVL(h.exit_date - h.entry_date, 0)) as total_los'),
                DB::raw("SUM(CASE WHEN INSTR(h.datadaftarri_json, '{$deathPattern}') > 0 THEN 1 ELSE 0 END) as meninggal"),
                DB::raw("SUM(CASE WHEN INSTR(h.datadaftarri_json, '{$deathPattern}') > 0 AND NVL(h.exit_date - h.entry_date, 0) >= 2 THEN 1 ELSE 0 END) as meninggal48"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('h.exit_date')
            ->groupBy('r.bangsal_id')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Kapasitas TT per bangsal: COUNT bed di tiap bangsal lewat
     *   rsmst_beds.room_id → rsmst_rooms.bangsal_id.
     *
     * Return: [bangsal_id => jumlah_bed]. Bangsal tanpa bed/dengan room
     * yang punya bangsal_id NULL akan absent dari array.
     */
    protected function kapasitasTTPerBangsal(): array
    {
        return DB::table('rsmst_beds as bd')
            ->join('rsmst_rooms as r', 'r.room_id', '=', 'bd.room_id')
            ->whereNotNull('r.bangsal_id')
            ->select('r.bangsal_id', DB::raw('COUNT(*) as tt'))
            ->groupBy('r.bangsal_id')
            ->pluck('tt', 'bangsal_id')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    /**
     * Tambahkan indikator BOR/BTO/TOI/GDR/NDR ke tiap row breakdown bangsal.
     *
     * Formula:
     *   BOR = Σ LOS / (TT × hari periode) × 100%
     *   BTO = Σ pulang / TT
     *   TOI = (TT × hari − Σ LOS) / Σ pulang
     *   GDR = meninggal / Σ pulang × 1000 (per mille)
     *   NDR = meninggal_LOS≥48h / Σ pulang × 1000
     *
     * Bangsal tanpa data TT (bangsal_id NULL atau tidak ada bed) → bor/bto/toi
     * di-set null (akan ditampilkan "—" oleh view).
     *
     * @param  iterable $rows       Hasil bangsalBreakdownRI() — Collection of stdClass
     * @param  int      $totalDays  Total hari di periode laporan
     * @return array<int,array>     Array of array yang siap dirender
     */
    protected function enrichBangsalIndicators($rows, int $totalDays): array
    {
        $ttMap = $this->kapasitasTTPerBangsal();
        $out = [];

        foreach ($rows as $row) {
            $total = (int) ($row->total ?? 0);
            $totalLos = (float) ($row->total_los ?? 0);
            $meninggal = (int) ($row->meninggal ?? 0);
            $meninggal48 = (int) ($row->meninggal48 ?? 0);
            $tt = (int) ($ttMap[$row->bangsal_id] ?? 0);

            $bor = ($tt > 0 && $totalDays > 0)
                ? round($totalLos / ($tt * $totalDays) * 100, 1)
                : null;
            $bto = $tt > 0 ? round($total / $tt, 2) : null;
            $toi = ($tt > 0 && $totalDays > 0 && $total > 0)
                ? round((($tt * $totalDays) - $totalLos) / $total, 1)
                : null;
            $gdr = $total > 0 ? round($meninggal / $total * 1000, 1) : 0.0;
            $ndr = $total > 0 ? round($meninggal48 / $total * 1000, 1) : 0.0;

            $out[] = [
                'bangsal_id'   => $row->bangsal_id,
                'bangsal_name' => $row->bangsal_name,
                'total'        => $total,
                'alos'         => (float) ($row->alos ?? 0),
                'total_los'    => round($totalLos, 1),
                'tt'           => $tt,
                'meninggal'    => $meninggal,
                'meninggal48'  => $meninggal48,
                'bor'          => $bor,
                'bto'          => $bto,
                'toi'          => $toi,
                'gdr'          => $gdr,
                'ndr'          => $ndr,
            ];
        }

        return $out;
    }

    protected function fillKunjunganRow(?object $r, string $label, string $short): array
    {
        $total = (int) ($r->total ?? 0);
        $totalLos = (float) ($r->total_los ?? 0);

        return [
            'periode_label' => $label,
            'periode_short' => $short,
            'total'         => $total,
            'pasien_unik'   => (int) ($r->pasien_unik ?? 0),
            'bpjs'          => (int) ($r->bpjs ?? 0),
            'umum'          => (int) ($r->umum ?? 0),
            'selesai'       => (int) ($r->selesai ?? 0),
            'batal'         => (int) ($r->batal ?? 0),
            'dirawat'       => (int) ($r->dirawat ?? 0),
            'total_los'     => round($totalLos, 1),
            // ALOS per periode = total_los / total (weighted)
            'alos'          => $total > 0 ? round($totalLos / $total, 1) : 0.0,
        ];
    }

    protected function totalsKunjungan(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        $totalCount = $sum('total');
        $totalLos = $sum('total_los');

        return [
            'total'        => $totalCount,
            'pasien_unik'  => $sum('pasien_unik'),
            'bpjs'         => $sum('bpjs'),
            'umum'         => $sum('umum'),
            'selesai'      => $sum('selesai'),
            'batal'        => $sum('batal'),
            'dirawat'      => $sum('dirawat'),
            'total_los'    => round($totalLos, 1),
            // ALOS global = weighted (total_los / total_pasien)
            'alos'         => $totalCount > 0 ? round($totalLos / $totalCount, 1) : 0.0,
        ];
    }

    protected function chartDataKunjungan(array $rows): array
    {
        return [
            'labels'    => array_column($rows, 'periode_label'),
            'bpjs'      => array_column($rows, 'bpjs'),
            'umum'      => array_column($rows, 'umum'),
            'total'     => array_column($rows, 'total'),
            'alos'      => array_column($rows, 'alos'),
            'selesai'   => array_column($rows, 'selesai'),
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

    /**
     * Kapasitas Tempat Tidur global = jumlah bed di rsmst_beds.
     * Catatan: TT bisa berubah seiring waktu. Untuk akurasi historis penuh,
     * butuh tracking history. Versi sekarang pakai TT current — cocok kalau
     * kapasitas TT relatif stabil dalam periode laporan.
     */
    protected function kapasitasTTGlobal(): int
    {
        return (int) DB::table('rsmst_beds')->count();
    }

    /**
     * Enrich rows dengan BOR / BTO / TOI per periode.
     *
     * Formula (Kemenkes / standar Indonesia):
     *   BOR = (Σ hari rawat / (TT × hari periode)) × 100%
     *   BTO = jumlah pasien pulang / TT (rate, kali)
     *   TOI = ((TT × hari periode) − Σ hari rawat) / jumlah pasien pulang (hari)
     *
     * @param  array  $rows           Output dari fillKunjunganRow (sudah punya total_los & total)
     * @param  int    $tt             Kapasitas tempat tidur
     * @param  callable $daysGetter   Function(row) => jumlah hari di periode row tsb
     */
    protected function enrichWithBORTOIBTO(array $rows, int $tt, callable $daysGetter): array
    {
        foreach ($rows as &$r) {
            $days = (int) $daysGetter($r);
            $totalLos = (float) $r['total_los'];
            $total = (int) $r['total'];

            $r['days_in_period'] = $days;

            if ($tt > 0 && $days > 0) {
                $r['bor'] = round($totalLos / ($tt * $days) * 100, 1);
                $r['bto'] = round($total / $tt, 2);
                $r['toi'] = $total > 0
                    ? round((($tt * $days) - $totalLos) / $total, 1)
                    : null;
            } else {
                $r['bor'] = 0.0;
                $r['bto'] = 0.0;
                $r['toi'] = null;
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Hitung BOR/BTO/TOI agregat dari array rows (sudah enrichWithBORTOIBTO).
     */
    protected function totalBORTOIBTO(array $rows, int $tt): array
    {
        $totalLos = array_sum(array_column($rows, 'total_los'));
        $totalPasien = array_sum(array_column($rows, 'total'));
        $totalDays = array_sum(array_column($rows, 'days_in_period'));

        $bor = ($tt > 0 && $totalDays > 0) ? round($totalLos / ($tt * $totalDays) * 100, 1) : 0.0;
        $bto = $tt > 0 ? round($totalPasien / $tt, 2) : 0.0;
        $toi = ($totalPasien > 0 && $tt > 0 && $totalDays > 0)
            ? round((($tt * $totalDays) - $totalLos) / $totalPasien, 1)
            : null;

        return ['bor' => $bor, 'bto' => $bto, 'toi' => $toi, 'days_total' => $totalDays];
    }
}
