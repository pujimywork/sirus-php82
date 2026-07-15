<?php

namespace App\Http\Traits\Manajemen\Sirs\Ugd;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic untuk Laporan RL 3.3 Rawat Darurat (SIRS Online Kemenkes).
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ ANATOMI LAPORAN                                                     │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Periode: 1 kalender bulan (Bulan + Tahun, default = bulan & tahun   │
 * │ saat ini).                                                          │
 * │ Sumber data: rstxn_ugdhdrs (UGD/IGD), filter `rj_date` antara       │
 * │ startOfMonth..endOfMonth.                                           │
 * │ Output: 13 row jenis pelayanan × 12 metrik (lihat initRl33Buckets). │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ MAPPING JENIS PELAYANAN (priority chain, FIRST MATCH WINS)          │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ Lihat classifyRl33Specialty() — input: poli_desc DPJP (UPPER) &     │
 * │ umur pasien (tahun). Output: SIRS specialty ID (1-13).              │
 * │                                                                     │
 * │ #1 poli  PSIKIATRI / JIWA                       → id 10 Psikiatrik  │
 * │ #2 poli  OBGIN / KEBIDANAN / KIA / OBSTETRI /                       │
 * │          KANDUNGAN                              → id 9  Kebidanan   │
 * │ #3 umur  < 1 tahun                              → id 11 Bayi        │
 * │ #4 umur  >= 60 tahun                            → id 13 Geriatri    │
 * │ #5 umur  1-17 tahun                             → id 12 Anak        │
 * │ #6 DEFAULT (sisanya)                            → id 8  Non bedah   │
 * │                                                       lainnya       │
 * │                                                                     │
 * │ Catatan: poli specialty MENANG atas umur (mis. bayi DPJP psikiatri  │
 * │ tetap masuk Psikiatrik, bukan Bayi).                                │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ KATEGORI YG BELUM DIIMPLEMENTASIKAN (selalu 0 di output)            │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ id 1 (1.1) Kecelakaan lalu lintas darat   — butuh ICD V01-V89       │
 * │ id 2 (1.2) Kecelakaan lalu lintas perairan— butuh ICD V90-V94       │
 * │ id 3 (1.3) Kecelakaan lalu lintas udara   — butuh ICD V95-V97       │
 * │ id 4 (1.4) Bedah lainnya (non kecelakaan) — butuh ICD trauma non-V  │
 * │ id 5 (2.1) Kekerasan thd Perempuan (>17)  — ICD T74/Y07 + filter    │
 * │                                             gender=P + umur>17      │
 * │ id 6 (2.2) Kekerasan thd Anak (<18)       — ICD T74/Y07 + umur<18   │
 * │ id 7 (2.3) Kekerasan lainnya              — ICD violence residual   │
 * │                                                                     │
 * │ Untuk aktifkan: parse JSON datadaftarugd_json path `diagnosis[]`    │
 * │ atau kolom diagnosa langsung, pattern match prefix ICD-10. Cek      │
 * │ ICD HARUS DI AWAL priority chain (sebelum #1 poli) karena lebih     │
 * │ spesifik dari klasifikasi by poli/umur.                             │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ METRIK & SUMBER DATA (per row)                                      │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ rujukan          : rsmst_entryugds.rujukan_status='Y' (lookup map   │
 * │                    di-preload via loadRl33EntryStatusMap)           │
 * │ non_rujukan      : selain itu                                       │
 * │ dirawat          : rj_status='I' (transfer ke RI — sudah ada flag)  │
 * │ dirujuk          : JSON perencanaan.tindakLanjut.tindakLanjut       │
 * │                    = 'Rujuk' (string match str_contains)            │
 * │ pulang           : rj_status='L' AND bukan dirujuk                  │
 * │ mati_igd_l/p     : death_on_igd_status='Y' × p.sex (L/P)            │
 * │ doa_l/p          : triase 'P0' (Hitam) + death_on_igd_status='Y'    │
 * │                    × p.sex. Asumsi: pasien tiba sudah meninggal     │
 * │                    biasanya di-tag triase P0 saat input perawat     │
 * │ luka_l/p         : 0 (butuh ICD trauma matching, ditangguhkan)      │
 * │ false_emergency  : triase 'P4' (Hijau-Biru, non-urgent)             │
 * │                                                                     │
 * │ Filter umum: klaim_id <> 'KR' (Kronis exclude — safety, jarang di   │
 * │ UGD) AND rj_status <> 'F' (admisi batal exclude).                   │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ NUANSA & CATATAN                                                    │
 * ├─────────────────────────────────────────────────────────────────────┤
 * │ • Triase value di JSON: tingkatKegawatan = 'P0' (Hitam/Meninggal),  │
 * │   'P1' (Merah/Resusitasi), 'P2' (Kuning/Emergent), 'P3' (Hijau/     │
 * │   Urgent), 'P4' (Biru/Non-urgent). Lihat                            │
 * │   pages/transaksi/ugd/emr-ugd/anamnesa/tabs/                        │
 * │   pengkajian-perawatan-tab-dokter-view.blade.php.                   │
 * │                                                                     │
 * │ • Tindak lanjut UGD di JSON pakai string label, BUKAN SNOMED        │
 * │   (berbeda dari RI). Options: 'MRS', 'Kontrol', 'Rujuk',            │
 * │   'Perawatan Selesai', 'PRB', 'Lain-lain'. Lihat                    │
 * │   pages/transaksi/ugd/emr-ugd/perencanaan/                          │
 * │   rm-perencanaan-ugd-actions.blade.php → getDefaultPerencanaan().   │
 * │                                                                     │
 * │ • rj_status enum: I=transfer ke RI, L=Selesai, F=Batal, A=Antrian.  │
 * │   "Dirawat" SIRS pakai rj_status='I' (sudah ada flag transfer ke    │
 * │   RI), bukan dari JSON tindak_lanjut='MRS' (yang adalah niat,       │
 * │   bukan eksekusi). Konsisten dengan bagaimana KunjunganUGDTrait     │
 * │   menghitung "transfer_ri".                                         │
 * │                                                                     │
 * │ • Carbon::diffInYears() di Carbon 3 sign-nya bisa kebalik (lihat    │
 * │   memory `carbon3-diff-signed.md`). Untuk umur, urutan yang dipakai │
 * │   $birthDate->diffInYears($referenceDate) menghasilkan positif jika │
 * │   reference > birth (umur normal).                                  │
 * │                                                                     │
 * │ • Oracle DB di repo ini tidak support JSON_VALUE — semua parsing    │
 * │   JSON pakai INSTR/str_contains (PHP-side string match).            │
 * └─────────────────────────────────────────────────────────────────────┘
 */
trait RL33Trait
{
    /** Daftar Jenis Pelayanan RL 3.3 SIRS Kemenkes. */
    public const JENIS_PELAYANAN_LIST = [
        ['id' => 1,  'no' => '1.1', 'nama' => 'Kecelakaan lalu lintas darat'],
        ['id' => 2,  'no' => '1.2', 'nama' => 'Kecelakaan lalu lintas perairan'],
        ['id' => 3,  'no' => '1.3', 'nama' => 'Kecelakaan lalu lintas udara'],
        ['id' => 4,  'no' => '1.4', 'nama' => 'Bedah lainnya (non kecelakaan)'],
        ['id' => 5,  'no' => '2.1', 'nama' => 'Kekerasan terhadap Perempuan (>17 tahun)'],
        ['id' => 6,  'no' => '2.2', 'nama' => 'Kekerasan terhadap Anak (<18 tahun)'],
        ['id' => 7,  'no' => '2.3', 'nama' => 'Kekerasan lainnya'],
        ['id' => 8,  'no' => '2.4', 'nama' => 'Non bedah lainnya'],
        ['id' => 9,  'no' => '3',   'nama' => 'Kebidanan'],
        ['id' => 10, 'no' => '4',   'nama' => 'Psikiatrik'],
        ['id' => 11, 'no' => '5',   'nama' => 'Bayi'],
        ['id' => 12, 'no' => '6',   'nama' => 'Anak'],
        ['id' => 13, 'no' => '7',   'nama' => 'Geriatri'],
    ];

    /**
     * Compute 1 bulan laporan. Output: 13 row (sesuai JENIS_PELAYANAN_LIST)
     * × 12 metrik. Single fetch + iterate di PHP.
     */
    protected function computeRL33(int $bulan, int $tahun): array
    {
        // Periode 1 kalender bulan: [tgl-1 00:00, tgl-akhir 23:59]
        $start = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();

        // Inisialisasi 13 bucket dengan semua metrik = 0
        $buckets = $this->initRl33Buckets();

        // Pre-load lookup map (sekali query, dipakai per-row di loop)
        $doctorPoli  = $this->loadRl33DoctorPoliMap();   // dr_id      → poli_desc UPPER
        $entryStatus = $this->loadRl33EntryStatusMap();  // entry_id   → rujukan_status (Y/N)

        // Single fetch: semua admisi UGD bulan ini, exclude Kronis & Batal
        $rows = DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR') // exclude Kronis (safety, jarang di UGD)
            ->where(fn($q) => $q->whereNull('h.rj_status')->orWhere('h.rj_status', '!=', 'F')) // exclude batal
            ->select([
                'h.rj_no',
                'h.dr_id',                  // → poli (specialty mapping)
                'h.entry_id',               // → rujukan_status (Y/N)
                'h.rj_status',              // I=Dirawat ke RI, L=Selesai, A=Antrian
                'h.death_on_igd_status',    // Y/N — Mati di IGD
                'h.datadaftarugd_json',     // JSON: triase, tindak lanjut, dll
                'p.sex',                    // L=Pria, P=Wanita
                'p.birth_date',             // → umur
            ])
            ->get();

        foreach ($rows as $r) {
            // ─── KLASIFIKASI: input poli + umur → SIRS specialty ID ──────
            $poliDesc = (string) ($doctorPoli[(string) $r->dr_id] ?? '');
            $umur     = $this->ageOnDate($r->birth_date, $start);
            $sirsId   = $this->classifyRl33Specialty($poliDesc, $umur);
            // CATATAN: kategori 1.1-2.3 (kecelakaan/kekerasan) butuh ICD,
            // ditangguhkan — sekarang admisi tsb akan jatuh ke kategori
            // umur/poli atau default Non bedah lainnya.

            // ─── EKSTRAKSI FLAG dari JSON (sekali per row) ───────────────
            // ⚠️  DULU pakai str_contains('"tindakLanjut":"Rujuk"') dsb. Itu SALAH BESAR:
            //     daftar opsi ikut tersimpan di JSON tiap record
            //     ("tindakLanjutOptions":[{"tindakLanjut":"Rujuk"},...]), jadi polanya
            //     cocok di HAMPIR SEMUA record — bukan cuma yang benar-benar memilih.
            //     Terukur 2026-07-15 atas 35.794 record ber-JSON:
            //       dirujuk : terhitung 24.808, sebenarnya    441  (salah 56x)
            //       P0      : terhitung 24.461, sebenarnya     69  (salah 354x)
            //     P0 selama ini tak kelihatan salah karena DOA butuh isDeathIgd yang
            //     selalu false. Begitu deteksi kematian disambungkan, DOA akan meledak
            //     kalau pola ini tak dibetulkan.
            //     Oracle tak punya JSON_VALUE (ORA-00904) → decode di PHP, bukan SQL.
            $json = json_decode((string) ($r->datadaftarugd_json ?? ''), true) ?: [];

            $tindakLanjut = (string) data_get($json, 'perencanaan.tindakLanjut.tindakLanjut', '');
            $triase       = (string) data_get($json, 'anamnesa.pengkajianPerawatan.tingkatKegawatan', '');

            $sex       = (string) ($r->sex ?? '');
            $isRujuk   = $tindakLanjut === 'Rujuk';
            $isP0      = $triase === 'P0';
            $isP4      = $triase === 'P4';
            $isRujukan = strcasecmp((string) ($entryStatus[(string) $r->entry_id] ?? ''), 'Y') === 0;

            // Mati di IGD dibaca dari SOAP (tindak lanjut Meninggal), BUKAN dari kolom
            // rstxn_ugdhdrs.death_on_igd_status: kolom itu tak punya penulis 'Y' satu pun
            // di seluruh aplikasi, jadi Mati IGD & DOA selalu 0. Sementara SOAP sudah
            // punya datanya (97 record ber-tindakLanjut 'Meninggal' per 2026-07-15).
            // Sejalan dgn cara RI menandai kematian (status pulang BPJS 4 di Perencanaan).
            $isDeathIgd = $tindakLanjut === 'Meninggal';

            // ─── METRIK 1-2: Total Pasien (Rujukan / Non Rujukan) ────────
            // Sumber: rsmst_entryugds.rujukan_status (Y = rujukan dari faskes
            // lain, N = walk-in). Setiap admisi pasti masuk ke salah satu.
            $buckets[$sirsId][$isRujukan ? 'rujukan' : 'non_rujukan']++;

            // ─── METRIK 3-5: Tindak Lanjut Pelayanan ─────────────────────
            // Mutually exclusive — 1 admisi cuma masuk SATU dari 3 kolom ini
            // (atau tidak masuk sama sekali kalau masih A=Antrian/null).
            //
            // Prioritas: Dirawat (rj_status='I') > Dirujuk (JSON='Rujuk')
            // > Pulang (rj_status='L'). Logic ini menjamin pasien yang
            // sudah ditransfer ke RI tidak double-count di Pulang/Dirujuk.
            if ($r->rj_status === 'I') {
                $buckets[$sirsId]['dirawat']++;       // sudah pindah ke RI
            } elseif ($isRujuk) {
                $buckets[$sirsId]['dirujuk']++;       // dirujuk ke RS lain
            } elseif ($isDeathIgd) {
                // Meninggal punya kolom sendiri (Mati IGD). Pasien meninggal bukan
                // "pulang" — tanpa cabang ini dia ikut terhitung di Pulang (rj_status
                // 'L') SEKALIGUS di Mati IGD → total tindak lanjut menggelembung.
            } elseif ($r->rj_status === 'L') {
                $buckets[$sirsId]['pulang']++;        // selesai, pulang
            }
            // else: rj_status A/null → masih antrian, tidak dihitung

            // ─── METRIK 6-9: Mati di IGD (L/P) + DOA (L/P) ───────────────
            // DOA = subset dari Mati di IGD (pasien yang sudah tiba dalam
            // kondisi meninggal). Indikator: triase P0 (Hitam = Meninggal).
            // Jadi:
            //   Mati IGD count >= DOA count
            //   DOA dikenakan double-count di kolom Mati IGD juga
            //   (sesuai standar SIRS — DOA adalah breakdown dari Mati IGD).
            if ($isDeathIgd) {
                if ($sex === 'L') {
                    $buckets[$sirsId]['mati_igd_l']++;
                } elseif ($sex === 'P') {
                    $buckets[$sirsId]['mati_igd_p']++;
                }
                if ($isP0) {
                    if ($sex === 'L') {
                        $buckets[$sirsId]['doa_l']++;
                    } elseif ($sex === 'P') {
                        $buckets[$sirsId]['doa_p']++;
                    }
                }
            }

            // ─── METRIK 10-11: Luka-luka L/P ─────────────────────────────
            // BELUM DIIMPLEMENTASIKAN — butuh ICD trauma matching dari
            // diagnosis. Akan tetap 0. Lihat docblock kelas untuk detail.

            // ─── METRIK 12: False Emergency ──────────────────────────────
            // Triase P4 (non-urgent — pasien yang sebenarnya tidak emergency
            // tapi datang ke UGD). Standalone metric, tidak overlap dengan
            // tindak lanjut.
            if ($isP4) {
                $buckets[$sirsId]['false_emergency']++;
            }
        }

        // Konversi bucket associative array → flat output (urut sesuai
        // JENIS_PELAYANAN_LIST agar 13 row keluar dengan urutan SIRS resmi)
        $out = [];
        foreach (self::JENIS_PELAYANAN_LIST as $jp) {
            $out[] = ['id' => $jp['id'], 'no' => $jp['no'], 'nama' => $jp['nama']] + $buckets[$jp['id']];
        }
        return $out;
    }

    private function initRl33Buckets(): array
    {
        $template = [
            'rujukan'         => 0,
            'non_rujukan'     => 0,
            'dirawat'         => 0,
            'dirujuk'         => 0,
            'pulang'          => 0,
            'mati_igd_l'      => 0,
            'mati_igd_p'      => 0,
            'doa_l'           => 0,
            'doa_p'           => 0,
            'luka_l'          => 0,
            'luka_p'          => 0,
            'false_emergency' => 0,
        ];
        $buckets = [];
        foreach (self::JENIS_PELAYANAN_LIST as $jp) {
            $buckets[$jp['id']] = $template;
        }
        return $buckets;
    }

    /** dr_id → poli_desc (uppercase trim untuk pencocokan). */
    private function loadRl33DoctorPoliMap(): array
    {
        return DB::table('rsmst_doctors as d')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'd.poli_id')
            ->select('d.dr_id', 'p.poli_desc')
            ->get()
            ->mapWithKeys(fn($r) => [(string) $r->dr_id => mb_strtoupper(trim((string) ($r->poli_desc ?? '')))])
            ->all();
    }

    /** entry_id → rujukan_status ('Y'/'N'). */
    private function loadRl33EntryStatusMap(): array
    {
        return DB::table('rsmst_entryugds')
            ->select('entry_id', 'rujukan_status')
            ->get()
            ->mapWithKeys(fn($r) => [(string) $r->entry_id => (string) ($r->rujukan_status ?? '')])
            ->all();
    }

    /** Umur (tahun) pada tanggal referensi. Return null kalau birth_date tidak valid. */
    private function ageOnDate($birthDate, Carbon $reference): ?int
    {
        if (!$birthDate) {
            return null;
        }
        try {
            $bd = Carbon::parse($birthDate);
        } catch (\Throwable $e) {
            return null;
        }
        return (int) $bd->diffInYears($reference);
    }

    /**
     * Klasifikasi pasien UGD ke 13 kategori RL 3.3 (SIRS specialty ID).
     *
     * Priority chain — FIRST MATCH WINS, urutan dari paling spesifik:
     *
     *   ┌──────┬─────────────────────────────────────┬──────────────────┐
     *   │ Step │ Kondisi                             │ Output (id, no)  │
     *   ├──────┼─────────────────────────────────────┼──────────────────┤
     *   │  #1  │ poli_desc contains "PSIKIATRI"      │ 10  (no 4)       │
     *   │      │   atau "JIWA"                       │  Psikiatrik      │
     *   ├──────┼─────────────────────────────────────┼──────────────────┤
     *   │  #2  │ poli_desc contains salah satu dari: │  9  (no 3)       │
     *   │      │   "OBGIN" / "KEBIDANAN" / "KIA" /   │  Kebidanan       │
     *   │      │   "OBSTETRI" / "KANDUNGAN"          │                  │
     *   ├──────┼─────────────────────────────────────┼──────────────────┤
     *   │  #3  │ umur < 1 tahun                      │ 11  (no 5)       │
     *   │      │                                     │  Bayi            │
     *   ├──────┼─────────────────────────────────────┼──────────────────┤
     *   │  #4  │ umur >= 60 tahun                    │ 13  (no 7)       │
     *   │      │                                     │  Geriatri        │
     *   ├──────┼─────────────────────────────────────┼──────────────────┤
     *   │  #5  │ umur 1-17 tahun                     │ 12  (no 6)       │
     *   │      │                                     │  Anak            │
     *   ├──────┼─────────────────────────────────────┼──────────────────┤
     *   │  #6  │ DEFAULT (semua sisanya)             │  8  (no 2.4)     │
     *   │      │                                     │  Non bedah       │
     *   │      │                                     │  lainnya         │
     *   └──────┴─────────────────────────────────────┴──────────────────┘
     *
     * IMPORTANT: poli MENANG atas umur. Contoh: bayi (umur=0) dengan DPJP
     * dari poli psikiatri akan masuk #1 Psikiatrik (id 10), BUKAN #3 Bayi.
     * Asumsinya: konteks specialty lebih relevan untuk laporan SIRS daripada
     * grouping demografi.
     *
     * BELUM DIIMPLEMENTASIKAN: kategori 1.1-2.3 (Kecelakaan & Kekerasan).
     * Kalau diaktifkan nanti, harus jadi step #0 (paling awal) sebelum #1
     * karena ICD diagnosis lebih spesifik dari poli/umur.
     *
     * @param string   $poliDescUpper poli_desc DPJP, sudah di-uppercase & trim
     *                                (kosong kalau DPJP tidak punya poli)
     * @param int|null $umur          umur pasien dalam tahun (null kalau
     *                                birth_date tidak valid)
     * @return int  ID kategori 1-13. Default 8 kalau tidak match.
     */
    protected function classifyRl33Specialty(string $poliDescUpper, ?int $umur): int
    {
        // Step #1 & #2 — pakai poli (kalau ada). Cek poli MENANG atas umur.
        if ($poliDescUpper !== '') {
            // #1 Psikiatrik
            if (str_contains($poliDescUpper, 'PSIKIATRI') || str_contains($poliDescUpper, 'JIWA')) {
                return 10;
            }
            // #2 Kebidanan — gabungan obstetri + ginekologi + KIA
            // (note: SIRS RL 3.3 hanya punya 1 bucket "Kebidanan", tidak
            // separate ginekologi seperti RL 3.2)
            if (str_contains($poliDescUpper, 'OBGIN') || str_contains($poliDescUpper, 'KEBIDANAN')
                || str_contains($poliDescUpper, 'KIA') || str_contains($poliDescUpper, 'OBSTETRI')
                || str_contains($poliDescUpper, 'KANDUNGAN')) {
                return 9;
            }
        }

        // Step #3-#5 — fallback by umur
        if ($umur !== null) {
            if ($umur < 1) {
                return 11; // #3 Bayi (< 1 tahun)
            }
            if ($umur >= 60) {
                return 13; // #4 Geriatri (>= 60 tahun) — cek SEBELUM Anak
                           // karena pasien >=60 di poli umum harusnya geriatri
            }
            if ($umur >= 1 && $umur < 18) {
                return 12; // #5 Anak (1-17 tahun, exclude bayi & geriatri)
            }
        }

        // #6 DEFAULT — dewasa (18-59) di poli non-spesifik
        return 8; // Non bedah lainnya
    }
}
