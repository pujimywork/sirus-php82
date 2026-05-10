<?php

namespace App\Http\Traits\Manajemen\Sirs\Rj;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.15 Kesehatan Jiwa (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ 8 jenis kegiatan resmi SIRS + 1 fallback "Tidak Ada Data".          │
 * │ Periode: TAHUNAN (1 Januari - 31 Desember).                         │
 * │ Kolom: Laki-Laki, Perempuan, Jumlah (auto sum L+P).                 │
 * │                                                                     │
 * │ Source: rstxn_rjhdrs (RAWAT JALAN saja) JOIN rsmst_doctors di mana  │
 * │ d.poli_id = '14' (POLI PSIKIATRI) JOIN rsmst_pasiens for sex.       │
 * │ UGD/RI tidak termasuk (RL 3.15 spesifik untuk pelayanan jiwa rawat  │
 * │ jalan; admisi UGD/RI psikiatri masuk ke laporan UGD/RI masing2).    │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ MAPPING JENIS KEGIATAN (MVP)                                        │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Sistem belum tracking per-aktivitas psikiatri (Pemeriksaan vs       │
 * │ Psikoterapi vs Konseling vs Medikamentosa vs ...). 1 kunjungan      │
 * │ poli psikiatri pasti minimal melibatkan "Pemeriksaan Psikiatri",    │
 * │ jadi MVP: SEMUA kunjungan poli psikiatri → row 1 (Pemeriksaan       │
 * │ Psikiatri).                                                         │
 * │                                                                     │
 * │ Row 2-8 (Medikamentosa, Psikoterapi, Konseling, Elektro Medik,      │
 * │ Terapi Perilaku, Rehab Medik Psikiatrik, Assessment) di-render 0    │
 * │ sampai ada tracking EMR per-aktivitas. Untuk aktivasi nanti:        │
 * │ butuh field aktivitas di JSON datadaftarpolirj_json atau master     │
 * │ tindakan psikiatri.                                                 │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * Filter: rj_status NOT IN ('A','F') (exclude Antrian & Batal),
 *         klaim_id != 'KR'.
 */
trait RL315Trait
{
    /** poli_id POLI PSIKIATRI di rsmst_polis. */
    private const POLI_PSIKIATRI_ID = '14';

    /** Daftar Jenis Kegiatan RL 3.15 SIRS Kemenkes + "Tidak Ada Data". */
    public const JENIS_KEGIATAN_LIST = [
        ['id' => 1, 'no' => '1', 'nama' => 'Pemeriksaan Psikiatri'],
        ['id' => 2, 'no' => '2', 'nama' => 'Penatalaksanaan Medikamentosa'],
        ['id' => 3, 'no' => '3', 'nama' => 'Psikoterapi'],
        ['id' => 4, 'no' => '4', 'nama' => 'Konseling'],
        ['id' => 5, 'no' => '5', 'nama' => 'Elektro Medik'],
        ['id' => 6, 'no' => '6', 'nama' => 'Terapi Perilaku'],
        ['id' => 7, 'no' => '7', 'nama' => 'Rehabilitasi Medik Psikiatrik'],
        ['id' => 8, 'no' => '8', 'nama' => 'Assessment'],
        ['id' => 0, 'no' => '0', 'nama' => 'Tidak Ada Data'],
    ];

    /**
     * Compute 1 tahun laporan. Output: 9 row × {laki, perempuan, jumlah}.
     */
    protected function computeRL315(int $tahun): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        // Inisialisasi bucket per row
        $buckets = [];
        foreach (self::JENIS_KEGIATAN_LIST as $jp) {
            $buckets[$jp['id']] = ['L' => 0, 'P' => 0];
        }

        // Single fetch: count visits poli psikiatri di tahun tsb, group by sex
        $rows = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhereNotIn('h.rj_status', ['A', 'F']))
            ->where('d.poli_id', self::POLI_PSIKIATRI_ID)
            ->select('p.sex', DB::raw('COUNT(*) as cnt'))
            ->groupBy('p.sex')
            ->get();

        foreach ($rows as $r) {
            $sex = (string) ($r->sex ?? '');
            $cnt = (int) $r->cnt;
            if ($sex === 'L') {
                // Default: semua kunjungan poli psikiatri → row 1 Pemeriksaan Psikiatri
                $buckets[1]['L'] += $cnt;
            } elseif ($sex === 'P') {
                $buckets[1]['P'] += $cnt;
            } else {
                // Sex tidak valid (null/lain) → row 0 Tidak Ada Data
                $buckets[0]['L'] += $cnt; // tetap masuk kolom L sebagai default karena unknown
            }
        }

        // Build flat output
        $out = [];
        foreach (self::JENIS_KEGIATAN_LIST as $jp) {
            $l = $buckets[$jp['id']]['L'];
            $p = $buckets[$jp['id']]['P'];
            $out[] = [
                'id'        => $jp['id'],
                'no'        => $jp['no'],
                'nama'      => $jp['nama'],
                'laki'      => $l,
                'perempuan' => $p,
                'jumlah'    => $l + $p,
            ];
        }
        return $out;
    }
}
