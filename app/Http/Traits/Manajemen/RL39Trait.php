<?php

namespace App\Http\Traits\Manajemen;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.9 Radiologi (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ STRUKTUR LAPORAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ 19 jenis kegiatan resmi SIRS dibagi 4 grup (Foto/Imaging,           │
 * │ Radioterapi, Kedokteran Nuklir, Imaging Tambahan) + 1 fallback      │
 * │ "0 - Tidak Ada Data".                                               │
 * │                                                                     │
 * │ 1 kolom data: Jumlah (per row, tanpa breakdown gender — beda dari   │
 * │ RL 3.5 / RL 3.8).                                                   │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ SUMBER DATA                                                         │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Order-level rad dari 3 tabel:                                       │
 * │   - rstxn_rjrads      JOIN rstxn_rjhdrs   (filter rj_date)          │
 * │   - rstxn_ugdrads     JOIN rstxn_ugdhdrs  (filter rj_date)          │
 * │   - rstxn_riradiologs JOIN rstxn_rihdrs   (filter exit_date)        │
 * │ Master rad: rsmst_radiologis (rad_id → rad_desc)                    │
 * │                                                                     │
 * │ Filter exclude:                                                     │
 * │   - klaim_id = 'KR' (Kronis exclude, konvensi repo)                 │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ MAPPING (KEYWORD-BASED)                                             │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ classifyRl39Item(rad_desc) — keyword match. Pattern dicek           │
 * │ berurutan; spesifik dulu (mis. "C.T. SCAN" sebelum scan generic).   │
 * │                                                                     │
 * │ Yang dimapping:                                                     │
 * │   1.1 Foto tanpa kontras  : THORAX, BOF, CERVICAL, PELVIS, FEMUR,   │
 * │                              SKULL, MANUS, PEDIS, CRURIS, GENU,     │
 * │                              ELBOW, WRIST, SHOULDER, HUMERUS,       │
 * │                              ANTEBRACHII, ANKLE, MANDIBULA,         │
 * │                              MASTOIS, NASAL, ODONTOID, COCCYGEUS,   │
 * │                              SACRUM, BENDING, DYNAMIC, TMJ, dll.    │
 * │   1.2 Foto dengan kontras : IVP, HSG, COLON IN LOOP, URETHROGRAFI,  │
 * │                              SISTOGRAFI, UPPER GI, OESOPHAGOGRAFI,  │
 * │                              APPENDICOGRAFI, BARIUM,                │
 * │                              HISTEROSALPINGOGRAFI                   │
 * │   1.5 Foto Gigi           : FOTO GIGI, DENTAL                       │
 * │   1.6 C.T. Scan           : CT SCAN, C.T. SCAN, CT-SCAN             │
 * │   1.9 Lain-Lain (Foto)    : BACAAN, RADIOLOGI LAIN-LAIN             │
 * │   4.1 USG                 : USG (semua varian)                      │
 * │   4.2 MRI                 : MRI                                     │
 * │   0   Tidak Ada Data      : KIRIM KE LUAR, USG KE LUAR (referral),  │
 * │                              dan rad_desc yang tidak match keyword  │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL39Trait
{
    /** Daftar Jenis Kegiatan RL 3.9 SIRS Kemenkes + "0 Tidak Ada Data". */
    public const JENIS_KEGIATAN_LIST = [
        // ── Grup 1: Foto / Radiologi Konvensional ────────────────────
        ['id' => '1.1', 'grup' => 'Radiologi Konvensional', 'nama' => 'Foto tanpa bahan kontras'],
        ['id' => '1.2', 'grup' => 'Radiologi Konvensional', 'nama' => 'Foto dengan bahan kontras'],
        ['id' => '1.3', 'grup' => 'Radiologi Konvensional', 'nama' => 'Foto dengan rol film'],
        ['id' => '1.4', 'grup' => 'Radiologi Konvensional', 'nama' => 'Flouroskopi'],
        ['id' => '1.5', 'grup' => 'Radiologi Konvensional', 'nama' => 'Foto Gigi'],
        ['id' => '1.6', 'grup' => 'Radiologi Konvensional', 'nama' => 'C.T. Scan'],
        ['id' => '1.7', 'grup' => 'Radiologi Konvensional', 'nama' => 'Lymphografi'],
        ['id' => '1.8', 'grup' => 'Radiologi Konvensional', 'nama' => 'Angiograpi'],
        ['id' => '1.9', 'grup' => 'Radiologi Konvensional', 'nama' => 'Lain-Lain'],

        // ── Grup 2: Radioterapi ──────────────────────────────────────
        ['id' => '2.1', 'grup' => 'Radioterapi', 'nama' => 'Radioterapi dengan Linac'],
        ['id' => '2.2', 'grup' => 'Radioterapi', 'nama' => 'Radioterapi dengan Cobalt'],
        ['id' => '2.3', 'grup' => 'Radioterapi', 'nama' => 'Radioterapi dengan Brakhiterapi'],
        ['id' => '2.4', 'grup' => 'Radioterapi', 'nama' => 'Lain-Lain'],

        // ── Grup 3: Kedokteran Nuklir ────────────────────────────────
        ['id' => '3.1', 'grup' => 'Kedokteran Nuklir', 'nama' => 'Diagnostik'],
        ['id' => '3.2', 'grup' => 'Kedokteran Nuklir', 'nama' => 'Therapi'],
        ['id' => '3.3', 'grup' => 'Kedokteran Nuklir', 'nama' => 'Lain-Lain'],

        // ── Grup 4: Imaging Tambahan ─────────────────────────────────
        ['id' => '4.1', 'grup' => 'Imaging Tambahan', 'nama' => 'USG'],
        ['id' => '4.2', 'grup' => 'Imaging Tambahan', 'nama' => 'MRI'],
        ['id' => '4.3', 'grup' => 'Imaging Tambahan', 'nama' => 'Lain-lain'],

        // ── Fallback ─────────────────────────────────────────────────
        ['id' => '0',   'grup' => '-',                'nama' => 'Tidak Ada Data'],
    ];

    /**
     * Compute 1 bulan laporan. Output: 20 row × {jumlah}.
     */
    protected function computeRL39(int $bulan, int $tahun): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();

        // Inisialisasi bucket per row
        $buckets = [];
        foreach (self::JENIS_KEGIATAN_LIST as $jp) {
            $buckets[$jp['id']] = 0;
        }

        // ── Source 1: RJ rad ─────────────────────────────────────────
        $rjRows = DB::table('rstxn_rjrads as l')
            ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'l.rad_id')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->select(['l.rad_id', DB::raw('MAX(m.rad_desc) as rad_desc'), DB::raw('COUNT(*) as cnt')])
            ->groupBy('l.rad_id')
            ->get();

        foreach ($rjRows as $r) {
            $sirsId = $this->classifyRl39Item((string) ($r->rad_desc ?? ''));
            $buckets[$sirsId] += (int) $r->cnt;
        }

        // ── Source 2: UGD rad ────────────────────────────────────────
        $ugdRows = DB::table('rstxn_ugdrads as l')
            ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'l.rj_no')
            ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'l.rad_id')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->select(['l.rad_id', DB::raw('MAX(m.rad_desc) as rad_desc'), DB::raw('COUNT(*) as cnt')])
            ->groupBy('l.rad_id')
            ->get();

        foreach ($ugdRows as $r) {
            $sirsId = $this->classifyRl39Item((string) ($r->rad_desc ?? ''));
            $buckets[$sirsId] += (int) $r->cnt;
        }

        // ── Source 3: RI rad (filter exit_date sesuai konvensi RI) ────
        // Note: tabel-nya rstxn_riradiologs (bukan riads), kolom rad price-nya rirad_price
        $riRows = DB::table('rstxn_riradiologs as l')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'l.rihdr_no')
            ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'l.rad_id')
            ->whereBetween('h.exit_date', [$start, $end])
            ->whereNotNull('h.exit_date')
            ->where('h.klaim_id', '!=', 'KR')
            ->select(['l.rad_id', DB::raw('MAX(m.rad_desc) as rad_desc'), DB::raw('COUNT(*) as cnt')])
            ->groupBy('l.rad_id')
            ->get();

        foreach ($riRows as $r) {
            $sirsId = $this->classifyRl39Item((string) ($r->rad_desc ?? ''));
            $buckets[$sirsId] += (int) $r->cnt;
        }

        // Build flat output
        $out = [];
        foreach (self::JENIS_KEGIATAN_LIST as $jp) {
            $out[] = [
                'id'     => $jp['id'],
                'grup'   => $jp['grup'],
                'nama'   => $jp['nama'],
                'jumlah' => $buckets[$jp['id']],
            ];
        }
        return $out;
    }

    /**
     * Klasifikasi rad_desc → SIRS RL 3.9 row ID.
     * Pattern dicek berurutan; specific dulu.
     */
    protected function classifyRl39Item(string $desc): string
    {
        $u = mb_strtoupper(trim($desc));
        if ($u === '') {
            return '0';
        }

        // ─── Referral ke luar RS — bukan kegiatan radiologi internal ──
        if (str_contains($u, 'KIRIM KE') || str_contains($u, 'KE LUAR') || str_contains($u, 'RAD LUAR')) {
            return '0';
        }

        // ─── Imaging Tambahan ────────────────────────────────────────
        if (str_contains($u, 'MRI')) {
            return '4.2';
        }
        if (str_contains($u, 'USG')) {
            return '4.1';
        }

        // ─── C.T. Scan ───────────────────────────────────────────────
        if (str_contains($u, 'CT SCAN') || str_contains($u, 'CT-SCAN') || str_contains($u, 'C.T. SCAN') || str_contains($u, 'C.T.SCAN')) {
            return '1.6';
        }

        // ─── Foto Gigi ───────────────────────────────────────────────
        if (str_contains($u, 'FOTO GIGI') || str_contains($u, 'DENTAL')) {
            return '1.5';
        }

        // ─── Foto dengan bahan kontras ───────────────────────────────
        // (cek SEBELUM "Foto tanpa kontras" karena overlap dengan IVP/HSG/dll)
        if (str_contains($u, 'IVP') ||
            str_contains($u, 'HSG') ||
            str_contains($u, 'HISTEROSALPINGOGRAFI') ||
            str_contains($u, 'COLON IN LOOP') ||
            str_contains($u, 'URETHROGRAFI') ||
            str_contains($u, 'URETHRO-SISTOGRAFI') ||
            str_contains($u, 'SISTOGRAFI') ||
            str_contains($u, 'UPPER GI') ||
            str_contains($u, 'OESOPHAGOGRAFI') ||
            str_contains($u, 'APPENDICOGRAFI') ||
            str_contains($u, 'BARIUM') ||
            str_contains($u, 'KONTRAS')
        ) {
            return '1.2';
        }

        // ─── Lain-Lain Foto (BACAAN luar / RADIOLOGI LAIN-LAIN) ──────
        if (str_contains($u, 'BACAAN') || str_contains($u, 'RADIOLOGI LAIN')) {
            return '1.9';
        }

        // ─── Angiografi ──────────────────────────────────────────────
        if (str_contains($u, 'ANGIOGRAFI') || str_contains($u, 'ANGIOGRAPI')) {
            return '1.8';
        }

        // ─── Lymphografi ─────────────────────────────────────────────
        if (str_contains($u, 'LYMPHOGRAFI') || str_contains($u, 'LIMFOGRAFI')) {
            return '1.7';
        }

        // ─── Flouroskopi ─────────────────────────────────────────────
        if (str_contains($u, 'FLOUROSKOPI') || str_contains($u, 'FLUOROSCOPY')) {
            return '1.4';
        }

        // ─── Radioterapi ─────────────────────────────────────────────
        if (str_contains($u, 'LINAC')) {
            return '2.1';
        }
        if (str_contains($u, 'COBALT')) {
            return '2.2';
        }
        if (str_contains($u, 'BRAKHITERAPI') || str_contains($u, 'BRAKITERAPI') || str_contains($u, 'BRACHYTHERAPY')) {
            return '2.3';
        }
        if (str_contains($u, 'RADIOTERAPI')) {
            return '2.4'; // generic radioterapi tanpa spesifikasi modalitas
        }

        // ─── Foto tanpa kontras (default radiologi konvensional) ─────
        // List keyword untuk anatomical regions yang umum di-foto polos
        $fotoTanpaKontrasKeywords = [
            'THORAX', 'BOF', 'CERVICAL', 'PELVIS', 'MANDIBULA', 'MASTOIS',
            'FEMUR', 'GENU', 'CRURIS', 'ANKLE', 'PEDIS', 'HUMERUS',
            'ANTEBRACHII', 'ELBOW', 'WRIST', 'MANUS', 'SKULL', "WATER'S",
            'WATERS', 'BASIS CRANII', 'CLAVICULA', 'SHOULDER', 'VERT',
            'BABYGRAM', 'NASAL', 'ODONTOID', 'COCCYGEUS', 'SACRUM',
            'BENDING', 'DYNAMIC', 'TMJ', 'SACRO-COCCYGEUS', 'STEVENVER',
            'PROC. ODONTOIDEUS', 'ART. COXAE', 'CALCANEUS', 'HIP JOINT',
            'PENIS', 'SKYLINE', 'OBLIQUE', 'AP/LAT', 'AP / LAT', 'LATERAL',
            'OBLIQUE D', 'OBLIQUE S', 'TOP LORDOTIC', 'INLET', 'OUTLET',
            'FROG POSITON', 'FROG POSITION',
        ];

        foreach ($fotoTanpaKontrasKeywords as $kw) {
            if (str_contains($u, $kw)) {
                return '1.1';
            }
        }

        // ─── Default ─────────────────────────────────────────────────
        return '0'; // Tidak Ada Data
    }
}
