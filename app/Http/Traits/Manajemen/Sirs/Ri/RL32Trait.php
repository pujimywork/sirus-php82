<?php

namespace App\Http\Traits\Manajemen\Sirs\Ri;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.2 Rawat Inap (SIRS Online Kemenkes).
 *
 * Mapping Jenis Pelayanan: DPJP **Utama** dari JSON `datadaftarri_json` path
 * `pengkajianAwalPasienRawatInap.levelingDokter[]` dengan `levelDokter='Utama'`
 * → poli (rsmst_doctors.poli_id → rsmst_polis.poli_desc) → 36 jenis pelayanan
 * SIRS pakai keyword match. Fallback: kalau leveling JSON kosong, pakai
 * `rstxn_rihdrs.dr_id` (dokter pendaftar). Poli yang tidak match keyword apa
 * pun → bucket "Tidak Ada Data" (id 100).
 *
 * Konvensi:
 *   - Periode: kalender bulan tunggal.
 *   - Filter `klaim_id <> 'KR'` (Kronis di-exclude) & `ri_status <> 'F'` (batal di-exclude).
 *   - LOS = exit_date - entry_date (Oracle date arithmetic, hari sebagai NUMBER).
 *   - Meninggal: SNOMED 419099009 di JSON datadaftarri_json (pencarian string PHP).
 *   - Gender: rsmst_pasiens.sex ('L' Pria, 'P' Wanita).
 *   - Kelas: rsmst_class.class_desc dipattern-match ke VVIP/VIP/1/2/3/Khusus.
 *   - Alokasi TT Awal Bulan: total bed RS — di-pin ke bucket "Tidak Ada Data"
 *     (mapping bangsal→specialty butuh DDL terpisah).
 *   - Pasien Pindahan/Dipindahkan (intra-RS antar specialty): tidak diimplementasikan
 *     MVP, tetap 0.
 */
trait RL32Trait
{
    private const DEATH_PATTERN = '"tindakLanjutKode":"419099009"';

    /**
     * Mapping eksplisit poli_id (rsmst_polis) → SIRS specialty ID (RL 3.2).
     * Disusun berdasarkan daftar 25 poli aktual RSI Madinah. Untuk poli_id
     * yang tidak ada di map ini, fallback ke keyword match di poli_desc.
     *
     * Kalau ada poli baru ditambahkan, append di sini.
     */
    private const POLI_TO_SIRS = [
        '1'  => 1,    // POLI UMUM         → Umum
        '2'  => 34,   // POLI GIGI         → Gigi dan Mulut
        '3'  => 2,    // POLI DALAM        → Penyakit Dalam
        '4'  => 11,   // POLI SYARAF       → Saraf
        '5'  => 7,    // POLI BEDAH        → Bedah
        '6'  => 5,    // POLI OBGIN        → Obstetri (default; ginekologi=6 jika diubah)
        '7'  => 8,    // POLI ORTHOPEDI    → Bedah Orthopedi
        '8'  => 100,  // POLI AKUPUNTUR    → Tidak Ada Data (tidak ada bucket RL 3.2)
        '9'  => 35,   // UGD               → Pelayanan Rawat Darurat
        '10' => 100,  // OK                → Tidak Ada Data (ruang operasi, bukan specialty)
        '11' => 3,    // POLI ANAK         → Kesehatan Anak
        '12' => 26,   // POLI FISIOTERAPY  → Rehabilitasi Medik
        '13' => 100,  // POLI GIZI         → Tidak Ada Data
        '14' => 12,   // POLI PSIKIATRI    → Jiwa
        '15' => 100,  // POLI RADIOLOGI    → Tidak Ada Data (diagnostic, bukan radioterapi)
        '16' => 18,   // POLI JANTUNG      → Kardiologi
        '17' => 100,  // OBAT KRONIS/PRB   → Tidak Ada Data
        '18' => 19,   // POLI TB           → Paru (TB primarily lung)
        '19' => 5,    // POLI KIA / KB     → Obstetri
        '20' => 26,   // POLI REHAB MEDIS  → Rehabilitasi Medik
        '21' => 100,  // POLI IMUNISASI    → Tidak Ada Data
        '22' => 100,  // LABORATORIUM      → Tidak Ada Data
        '23' => 100,  // RONTGEN           → Tidak Ada Data
        '24' => 19,   // POLI PARU         → Paru
        '25' => 16,   // POLI MATA         → Mata
    ];

    /** Daftar Jenis Pelayanan RL 3.2 SIRS Kemenkes (urutan & ID resmi). */
    public const JENIS_PELAYANAN_LIST = [
        ['id' => 1,   'nama' => 'Umum'],
        ['id' => 2,   'nama' => 'Penyakit Dalam'],
        ['id' => 3,   'nama' => 'Kesehatan Anak'],
        ['id' => 4,   'nama' => 'Kesehatan Remaja'],
        ['id' => 5,   'nama' => 'Obstetri'],
        ['id' => 6,   'nama' => 'Ginekologi'],
        ['id' => 7,   'nama' => 'Bedah'],
        ['id' => 8,   'nama' => 'Bedah Orthopedi'],
        ['id' => 9,   'nama' => 'Bedah Saraf'],
        ['id' => 10,  'nama' => 'Luka Bakar'],
        ['id' => 11,  'nama' => 'Saraf'],
        ['id' => 12,  'nama' => 'Jiwa'],
        ['id' => 13,  'nama' => 'Psikologi'],
        ['id' => 14,  'nama' => 'Penatalaksana Penyalahgunaan NAPZA'],
        ['id' => 15,  'nama' => 'THT'],
        ['id' => 16,  'nama' => 'Mata'],
        ['id' => 17,  'nama' => 'Kulit dan Kelamin'],
        ['id' => 18,  'nama' => 'Kardiologi'],
        ['id' => 19,  'nama' => 'Paru'],
        ['id' => 20,  'nama' => 'Kanker'],
        ['id' => 21,  'nama' => 'Uronefrogi'],
        ['id' => 22,  'nama' => 'Geriatri'],
        ['id' => 23,  'nama' => 'Kusta'],
        ['id' => 24,  'nama' => 'Radioterapi'],
        ['id' => 25,  'nama' => 'Kedokteran Nuklir'],
        ['id' => 26,  'nama' => 'Rehabilitasi Medik'],
        ['id' => 27,  'nama' => 'ICU'],
        ['id' => 28,  'nama' => 'HCU'],
        ['id' => 29,  'nama' => 'ICCU/ICVCU'],
        ['id' => 30,  'nama' => 'RICU'],
        ['id' => 31,  'nama' => 'NICU'],
        ['id' => 32,  'nama' => 'PICU'],
        ['id' => 33,  'nama' => 'Isolasi'],
        ['id' => 34,  'nama' => 'Gigi dan Mulut'],
        ['id' => 35,  'nama' => 'Pelayanan Rawat Darurat'],
        ['id' => 36,  'nama' => 'Perinatologi'],
        ['id' => 100, 'nama' => 'Tidak Ada Data'],
    ];

    protected function computeRL32(int $bulan, int $tahun): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $next  = (clone $start)->addMonth();

        $startTs = $start->getTimestamp();
        $endTs   = $end->getTimestamp();
        $nextTs  = $next->getTimestamp();
        $oneDay  = 86400;

        $buckets       = $this->initRl32Buckets();
        $doctorMap     = $this->loadDoctorPoliMap();
        $roomClassMap  = $this->loadRoomClassMap();

        // Single fetch — semua admisi yang OVERLAP periode.
        // Cakup: Awal Bulan, Masuk, Keluar, Akhir Bulan, Hari Perawatan,
        // Hari per Kelas. Filter loose: entry < next AND (exit NULL OR exit >= start).
        $rows = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->where('h.klaim_id', '!=', 'KR')
            ->where(fn($q) => $q->whereNull('h.ri_status')->orWhere('h.ri_status', '!=', 'F'))
            ->where('h.entry_date', '<', $next)
            ->where(fn($q) => $q->whereNull('h.exit_date')->orWhere('h.exit_date', '>=', $start))
            ->select([
                'h.rihdr_no',
                'h.dr_id as fallback_dr_id',
                'h.room_id',
                'h.entry_date',
                'h.exit_date',
                'h.datadaftarri_json',
                'p.sex',
            ])
            ->get();

        foreach ($rows as $r) {
            $json = $this->decodeRl32Json($r->datadaftarri_json);

            $drId    = $this->extractDpjpUtama($json) ?? (string) ($r->fallback_dr_id ?? '');
            $drInfo  = $doctorMap[$drId] ?? ['poli_id' => '', 'poli_desc' => ''];
            $sirsId  = $this->rl32ClassifySpecialty($drInfo['poli_id'], $drInfo['poli_desc']);

            $entryTs = $r->entry_date ? Carbon::parse($r->entry_date)->getTimestamp() : null;
            $exitTs  = $r->exit_date  ? Carbon::parse($r->exit_date)->getTimestamp()  : null;
            if ($entryTs === null) {
                continue;
            }

            // Pasien Awal Bulan: entry < start AND (exit NULL OR exit >= start)
            if ($entryTs < $startTs && ($exitTs === null || $exitTs >= $startTs)) {
                $buckets[$sirsId]['pasien_awal_bulan']++;
            }

            // Pasien Masuk: entry in [start, end]
            if ($entryTs >= $startTs && $entryTs <= $endTs) {
                $buckets[$sirsId]['pasien_masuk']++;
            }

            // Pasien Akhir Bulan: entry <= end AND (exit NULL OR exit > end)
            if ($entryTs <= $endTs && ($exitTs === null || $exitTs > $endTs)) {
                $buckets[$sirsId]['pasien_akhir_bulan']++;
            }

            // Hari Perawatan: overlap [entry, exit||next] ∩ [start, next]
            $effEntry = max($entryTs, $startTs);
            $effExit  = min($exitTs ?? $nextTs, $nextTs);
            $hari     = $effExit > $effEntry ? (int) round(($effExit - $effEntry) / $oneDay) : 0;
            $buckets[$sirsId]['jumlah_hari_perawatan'] += $hari;

            // Hari per Kelas — pakai class_desc room saat ini
            if ($hari > 0 && $r->room_id) {
                $classDesc = $roomClassMap[$r->room_id] ?? '';
                $kelasField = $this->kelasFieldOf($this->rl32ClassifyKelas($classDesc));
                $buckets[$sirsId][$kelasField] += $hari;
            }

            // Pasien Keluar (exit in [start, end]) — keluar hidup / mati per gender × LOS bracket
            if ($exitTs !== null && $exitTs >= $startTs && $exitTs <= $endTs) {
                $isMeninggal = is_string($r->datadaftarri_json) && str_contains($r->datadaftarri_json, self::DEATH_PATTERN);
                $losDays     = (int) max(0, ($exitTs - $entryTs) / $oneDay);
                $buckets[$sirsId]['jumlah_lama_dirawat'] += $losDays;

                if (!$isMeninggal) {
                    $buckets[$sirsId]['pasien_keluar_hidup']++;
                } else {
                    $sex = (string) ($r->sex ?? '');
                    $isLt48 = $losDays < 2;
                    if ($sex === 'L') {
                        $buckets[$sirsId][$isLt48 ? 'pria_mati_lt48' : 'pria_mati_ge48']++;
                    } elseif ($sex === 'P') {
                        $buckets[$sirsId][$isLt48 ? 'wanita_mati_lt48' : 'wanita_mati_ge48']++;
                    }
                }
            }
        }

        // Alokasi TT total — pin ke bucket 100 (mapping bangsal→specialty butuh DDL)
        $buckets[100]['alokasi_tt_awal'] = $this->rl32AlokasiTT();

        $out = [];
        foreach (self::JENIS_PELAYANAN_LIST as $jp) {
            $out[] = ['id' => $jp['id'], 'nama' => $jp['nama']] + $buckets[$jp['id']];
        }
        return $out;
    }

    private function initRl32Buckets(): array
    {
        $template = [
            'pasien_awal_bulan'     => 0,
            'pasien_masuk'          => 0,
            'pasien_pindahan'       => 0,
            'pasien_dipindahkan'    => 0,
            'pasien_keluar_hidup'   => 0,
            'pria_mati_lt48'        => 0,
            'pria_mati_ge48'        => 0,
            'wanita_mati_lt48'      => 0,
            'wanita_mati_ge48'      => 0,
            'jumlah_lama_dirawat'   => 0,
            'pasien_akhir_bulan'    => 0,
            'jumlah_hari_perawatan' => 0,
            'kelas_vvip'            => 0,
            'kelas_vip'             => 0,
            'kelas_1'               => 0,
            'kelas_2'               => 0,
            'kelas_3'               => 0,
            'kelas_khusus'          => 0,
            'alokasi_tt_awal'       => 0,
        ];
        $buckets = [];
        foreach (self::JENIS_PELAYANAN_LIST as $jp) {
            $buckets[$jp['id']] = $template;
        }
        return $buckets;
    }

    /**
     * Lookup dr_id → ['poli_id' => string, 'poli_desc' => string].
     */
    private function loadDoctorPoliMap(): array
    {
        return DB::table('rsmst_doctors as d')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'd.poli_id')
            ->select('d.dr_id', 'd.poli_id', 'p.poli_desc')
            ->get()
            ->mapWithKeys(fn($r) => [
                (string) $r->dr_id => [
                    'poli_id'   => (string) ($r->poli_id ?? ''),
                    'poli_desc' => (string) ($r->poli_desc ?? ''),
                ],
            ])
            ->all();
    }

    private function loadRoomClassMap(): array
    {
        return DB::table('rsmst_rooms as r')
            ->leftJoin('rsmst_class as c', 'c.class_id', '=', 'r.class_id')
            ->select('r.room_id', 'c.class_desc')
            ->get()
            ->mapWithKeys(fn($r) => [(string) $r->room_id => (string) ($r->class_desc ?? '')])
            ->all();
    }

    /**
     * Decode datadaftarri_json — return [] kalau invalid/null.
     */
    private function decodeRl32Json($raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Cari DPJP Utama dari JSON path:
     *   pengkajianAwalPasienRawatInap.levelingDokter[*].drId
     *   where levelDokter ≈ 'Utama' (case-insensitive)
     * Return null kalau tidak ada — caller fallback ke rstxn_rihdrs.dr_id.
     */
    private function extractDpjpUtama(array $json): ?string
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

    private function rl32AlokasiTT(): int
    {
        return (int) DB::table('rsmst_beds')->count();
    }

    private function kelasFieldOf(string $bucket): string
    {
        return [
            'VVIP'   => 'kelas_vvip',
            'VIP'    => 'kelas_vip',
            '1'      => 'kelas_1',
            '2'      => 'kelas_2',
            '3'      => 'kelas_3',
            'Khusus' => 'kelas_khusus',
        ][$bucket] ?? 'kelas_khusus';
    }

    /**
     * Pemetaan ke SIRS specialty ID (1-36, fallback 100).
     * Strategi 2 lapis:
     *   1. Lookup eksplisit di POLI_TO_SIRS by poli_id (override pasti).
     *   2. Fallback keyword match di poli_desc (untuk poli baru yang belum
     *      ditambahkan di POLI_TO_SIRS).
     * Pattern keyword dicek berurutan; yang paling spesifik harus didahulukan
     * (mis. "BEDAH ORTHOPEDI" sebelum "BEDAH").
     */
    protected function rl32ClassifySpecialty(?string $poliId, ?string $poliDesc): int
    {
        $pid = trim((string) $poliId);
        if ($pid !== '' && isset(self::POLI_TO_SIRS[$pid])) {
            return self::POLI_TO_SIRS[$pid];
        }

        $u = mb_strtoupper(trim((string) $poliDesc));
        if ($u === '') {
            return 100;
        }

        $patterns = [
            // ICU family (sebelum "ICU" sendiri)
            36 => ['PERINATOLOGI', 'PERINATAL', 'NEONATUS'],
            31 => ['NICU'],
            32 => ['PICU'],
            29 => ['ICCU', 'ICVCU'],
            30 => ['RICU'],
            28 => ['HCU'],
            27 => ['ICU'],
            // Bedah family (sebelum "BEDAH" sendiri)
             8 => ['BEDAH ORTHOPEDI', 'BEDAH ORTOPEDI', 'ORTHOPEDI', 'ORTOPEDI'],
             9 => ['BEDAH SARAF', 'BEDAH NEUROLOGI'],
             7 => ['BEDAH'],
            10 => ['LUKA BAKAR'],
            11 => ['SARAF', 'NEUROLOGI'],
             2 => ['PENYAKIT DALAM', 'INTERNA', 'INTERNIST'],
             3 => ['ANAK', 'PEDIATRI'],
             4 => ['REMAJA'],
             5 => ['OBSTETRI', 'KEBIDANAN', 'OBSGYN', 'OBGYN'],
             6 => ['GINEKOLOGI', 'KANDUNGAN'],
            12 => ['JIWA', 'PSIKIATRI'],
            13 => ['PSIKOLOGI'],
            14 => ['NAPZA'],
            15 => ['THT'],
            16 => ['MATA', 'OFTALMOLOGI'],
            17 => ['KULIT', 'KELAMIN', 'DERMATO', 'VENERO'],
            18 => ['JANTUNG', 'KARDIOLOGI', 'KARDIO'],
            19 => ['PARU', 'PULMONOLOGI'],
            20 => ['KANKER', 'ONKOLOGI'],
            21 => ['URO', 'NEFRO'],
            22 => ['GERIATRI', 'LANSIA'],
            23 => ['KUSTA', 'LEPRA'],
            24 => ['RADIOTERAPI'],
            25 => ['KEDOKTERAN NUKLIR', 'NUKLIR'],
            26 => ['REHAB', 'FISIOTERAPI'],
            33 => ['ISOLASI'],
            34 => ['GIGI', 'MULUT', 'DENTAL'],
            35 => ['GAWAT DARURAT', 'RAWAT DARURAT', 'IGD', 'UGD', 'EMERGENCY'],
             1 => ['UMUM', 'GENERAL', 'POLIKLINIK UMUM'],
        ];

        foreach ($patterns as $id => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($u, $kw)) {
                    return $id;
                }
            }
        }
        return 100;
    }

    /**
     * Pemetaan class_desc → bucket RL 3.2 (VVIP/VIP/1/2/3/Khusus).
     * VVIP harus dicek SEBELUM VIP (substring overlap).
     */
    protected function rl32ClassifyKelas(string $desc): string
    {
        $u = mb_strtoupper($desc);
        if (str_contains($u, 'VVIP')) {
            return 'VVIP';
        }
        if (str_contains($u, 'VIP')) {
            return 'VIP';
        }
        if (preg_match('/(KELAS\s*1|^1$|^I$)/u', $u)) {
            return '1';
        }
        if (preg_match('/(KELAS\s*2|^2$|^II$)/u', $u)) {
            return '2';
        }
        if (preg_match('/(KELAS\s*3|^3$|^III$)/u', $u)) {
            return '3';
        }
        return 'Khusus';
    }
}
