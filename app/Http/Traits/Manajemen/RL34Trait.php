<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.4 Pengunjung (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ KONSEP                                                              │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ "Pengunjung" ≠ "Kunjungan":                                         │
 * │   - 1 pasien bisa punya banyak kunjungan dalam 1 bulan              │
 * │   - 1 pasien dihitung SEKALI sebagai pengunjung per periode         │
 * │     (DISTINCT reg_no)                                               │
 * │                                                                     │
 * │ Pengunjung Baru:                                                    │
 * │   Pasien yg KUNJUNGAN PERTAMA di RS (across RJ+UGD+RI, sepanjang    │
 * │   sejarah) jatuh di periode laporan. Artinya pasien tsb pertama     │
 * │   kali ke RS bulan ini.                                             │
 * │                                                                     │
 * │ Pengunjung Lama:                                                    │
 * │   Pasien yg punya kunjungan di periode tapi pernah ke RS sebelum    │
 * │   periode start (ada kunjungan dengan tanggal < startOfMonth).      │
 * │                                                                     │
 * │ Tidak Ada Data:                                                     │
 * │   Edge case — pasien yg tidak bisa dicari first-visit-nya (data     │
 * │   inkonsisten). Idealnya 0.                                         │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ SUMBER DATA & FILTER                                                │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Tabel kunjungan (3):                                                │
 * │   - rstxn_rjhdrs  (RJ)  : tgl kunjungan = rj_date                   │
 * │   - rstxn_ugdhdrs (UGD) : tgl kunjungan = rj_date                   │
 * │   - rstxn_rihdrs  (RI)  : tgl kunjungan = entry_date                │
 * │                                                                     │
 * │ Filter exclude (status admisi tidak valid sbg pengunjung):          │
 * │   - RJ/UGD : rj_status IN ('A','F') exclude                         │
 * │              ('A'=Antrian belum dilayani, 'F'=Batal)                │
 * │   - RI     : ri_status IN ('I','F') exclude                         │
 * │              ('I'=masih Dirawat / belum complete, 'F'=Batal).       │
 * │              Pengunjung RI dihitung HANYA setelah admisi selesai    │
 * │              (ri_status='L'). Pasien aktif yg masih dirawat tidak   │
 * │              count di bulan tsb.                                    │
 * │   - klaim_id = 'KR' (Kronis exclude, konvensi laporan repo)         │
 * │                                                                     │
 * │ Algoritma 2 step:                                                   │
 * │   1. periodRegs = DISTINCT reg_no yang ada visit di [start, end]    │
 * │      lintas 3 tabel                                                 │
 * │   2. firstVisit[reg_no] = MIN(visit_date) seumur hidup pasien       │
 * │      (lintas 3 tabel, semua periode)                                │
 * │   3. Klasifikasi tiap reg_no di periodRegs:                         │
 * │      - firstVisit[reg_no] >= startOfMonth → Baru                    │
 * │      - firstVisit[reg_no] <  startOfMonth → Lama                    │
 * │      - firstVisit[reg_no] null/missing    → Tidak Ada Data          │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL34Trait
{
    /** Daftar Jenis Pengunjung RL 3.4 SIRS Kemenkes. */
    public const JENIS_PENGUNJUNG_LIST = [
        ['id' => 1, 'no' => '1', 'nama' => 'Pengunjung Baru'],
        ['id' => 2, 'no' => '2', 'nama' => 'Pengunjung Lama'],
        ['id' => 3, 'no' => '3', 'nama' => 'Tidak Ada Data'],
    ];

    /**
     * Compute 1 bulan laporan. Output: 3 row × kolom 'jumlah'.
     */
    protected function computeRL34(int $bulan, int $tahun): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();

        // Step 1: kumpulkan reg_no pasien yang ada visit di periode
        // (lintas RJ + UGD + RI). Pakai 3 query terpisah lalu merge unique
        // di PHP — lebih portable drpd UNION SQL Oracle.
        // Status valid: RJ/UGD exclude 'A' (Antrian) & 'F' (Batal). RI exclude
        // 'I' (Dirawat — belum selesai) & 'F' (Batal). NULL diperlakukan sbg
        // valid (data lama yg tidak punya status).
        $regsRJ = DB::table('rstxn_rjhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
            ->where('klaim_id', '!=', 'KR')
            ->distinct()
            ->pluck('reg_no');

        $regsUGD = DB::table('rstxn_ugdhdrs')
            ->whereBetween('rj_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
            ->where('klaim_id', '!=', 'KR')
            ->distinct()
            ->pluck('reg_no');

        $regsRI = DB::table('rstxn_rihdrs')
            ->whereBetween('entry_date', [$start, $end])
            ->where(fn($q) => $q->whereNull('ri_status')->orWhereNotIn('ri_status', ['I', 'F']))
            ->where('klaim_id', '!=', 'KR')
            ->distinct()
            ->pluck('reg_no');

        $periodRegs = $regsRJ
            ->merge($regsUGD)
            ->merge($regsRI)
            ->map(fn($v) => (string) $v)
            ->unique()
            ->values();

        if ($periodRegs->isEmpty()) {
            return $this->buildRl34Output(0, 0, 0);
        }

        // Step 2: cari MIN(visit_date) seumur hidup tiap pasien (lintas
        // 3 tabel, tanpa filter periode). Pakai UNION ALL + GROUP BY.
        // Whereln di-chunk 500 untuk menghindari Oracle limit 1000.
        $firstVisits = $this->loadFirstVisitMap($periodRegs);

        // Step 3: klasifikasi
        $baru = 0; $lama = 0; $noData = 0;
        foreach ($periodRegs as $reg) {
            $first = $firstVisits[$reg] ?? null;
            if ($first === null) {
                $noData++;
                continue;
            }
            try {
                $firstTs = Carbon::parse($first)->getTimestamp();
            } catch (\Throwable $e) {
                $noData++;
                continue;
            }
            if ($firstTs >= $start->getTimestamp()) {
                $baru++;
            } else {
                $lama++;
            }
        }

        return $this->buildRl34Output($baru, $lama, $noData);
    }

    /**
     * Cari first visit ever per reg_no, lintas RJ + UGD + RI.
     * Chunk whereIn ke 500 untuk batas Oracle (max 1000 per IN list).
     */
    private function loadFirstVisitMap($periodRegs): array
    {
        $out = [];
        foreach ($periodRegs->chunk(500) as $chunk) {
            $regsArr = $chunk->all();

            // 3 query MIN per tabel — gabung di PHP. Lebih simple drpd
            // UNION SQL yg sintaks-nya berbeda di Laravel/Oracle.
            $minRJ = DB::table('rstxn_rjhdrs')
                ->whereIn('reg_no', $regsArr)
                ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
                ->where('klaim_id', '!=', 'KR')
                ->groupBy('reg_no')
                ->select('reg_no', DB::raw('MIN(rj_date) as first_visit'))
                ->get();

            $minUGD = DB::table('rstxn_ugdhdrs')
                ->whereIn('reg_no', $regsArr)
                ->where(fn($q) => $q->whereNull('rj_status')->orWhereNotIn('rj_status', ['A', 'F']))
                ->where('klaim_id', '!=', 'KR')
                ->groupBy('reg_no')
                ->select('reg_no', DB::raw('MIN(rj_date) as first_visit'))
                ->get();

            $minRI = DB::table('rstxn_rihdrs')
                ->whereIn('reg_no', $regsArr)
                ->where(fn($q) => $q->whereNull('ri_status')->orWhereNotIn('ri_status', ['I', 'F']))
                ->where('klaim_id', '!=', 'KR')
                ->groupBy('reg_no')
                ->select('reg_no', DB::raw('MIN(entry_date) as first_visit'))
                ->get();

            // Merge: ambil MIN dari ketiga sumber per reg_no
            foreach ([$minRJ, $minUGD, $minRI] as $set) {
                foreach ($set as $row) {
                    $reg = (string) $row->reg_no;
                    $val = $row->first_visit;
                    if ($val === null) {
                        continue;
                    }
                    $existing = $out[$reg] ?? null;
                    if ($existing === null || strcmp((string) $val, (string) $existing) < 0) {
                        $out[$reg] = $val;
                    }
                }
            }
        }
        return $out;
    }

    private function buildRl34Output(int $baru, int $lama, int $noData): array
    {
        $counts = [1 => $baru, 2 => $lama, 3 => $noData];
        $out = [];
        foreach (self::JENIS_PENGUNJUNG_LIST as $jp) {
            $out[] = [
                'id'     => $jp['id'],
                'no'     => $jp['no'],
                'nama'   => $jp['nama'],
                'jumlah' => $counts[$jp['id']] ?? 0,
            ];
        }
        return $out;
    }
}
