<?php

namespace App\Support;

/**
 * Kode SNOMED "tidak ada alergi" untuk anamnesa RJ/UGD (AllergyIntolerance SATUSEHAT).
 *
 * Semua kode diverifikasi via terminology server tx.fhir.org (CodeSystem/$lookup) —
 * jangan menambah/mengubah dari hafalan.
 *
 * DEFAULT: node alergi yang MASIH KOSONG saat anamnesa dibuka diisi "Tidak ada alergi"
 * (716186003). Keputusan user 2026-07-15, dengan risiko yang disadari:
 *
 *   Default ini adalah PERNYATAAN KLINIS. Kalau dokter tak pernah menanyakan alergi dan
 *   tetap menyimpan anamnesa, sistem melaporkan "pasien tidak punya alergi" ke SATUSEHAT
 *   padahal tak ada yang pernah memastikannya. Sebelum ini, 90 dari 397 record RJ
 *   alerginya KOSONG — kosong itu jujur ("kami tidak tahu"), default itu klaim.
 *   Mitigasi: default hanya dipasang saat form DIBUKA (dokter melihat & bisa mengubah),
 *   bukan disisipkan diam-diam saat simpan.
 *
 * Kenapa default ini tetap masuk akal: ~75% entri lama memang diketik "tidak ada" dengan
 * lima ejaan berbeda (tidak ada / TIDAK ADA / tidaka da / TIDAKADA / tridak ada /
 * disangkal), dan snomedCode terisi 0 dari 397. Default menghapus varian salah ketik itu
 * sekaligus mengisi kode yang selama ini kosong.
 */
class AlergiSnomed
{
    /** Tidak ada alergi (umum) — dipakai sebagai default. */
    public const TIDAK_ADA = [
        'alergi' => 'Tidak ada alergi',
        'snomedCode' => '716186003',
        'snomedDisplayEn' => 'No known allergy',
        'snomedDisplayId' => 'Tidak ada alergi yang diketahui',
    ];

    /** Varian per kategori — belum dipakai UI, disiapkan bila nanti perlu dibedakan. */
    public const TIDAK_ADA_OBAT = [
        'alergi' => 'Tidak ada alergi obat',
        'snomedCode' => '409137002',
        'snomedDisplayEn' => 'No known history of drug allergy',
        'snomedDisplayId' => 'Tidak ada riwayat alergi obat',
    ];

    public const TIDAK_ADA_MAKANAN = [
        'alergi' => 'Tidak ada alergi makanan',
        'snomedCode' => '429625007',
        'snomedDisplayEn' => 'No known food allergy',
        'snomedDisplayId' => 'Tidak ada alergi makanan yang diketahui',
    ];

    public const TIDAK_ADA_LINGKUNGAN = [
        'alergi' => 'Tidak ada alergi lingkungan',
        'snomedCode' => '428607008',
        'snomedDisplayEn' => 'No known environmental allergy',
        'snomedDisplayId' => 'Tidak ada alergi lingkungan yang diketahui',
    ];

    /**
     * Ejaan "tidak ada alergi" yang ditemukan di data lama (397 record RJ) — dipakai untuk
     * menurunkan `adaAlergi` pada record yang belum punya key itu, supaya tak perlu migrasi
     * data. Dicocokkan atas teks ternormalkan (huruf kecil, tanpa spasi/tanda baca).
     */
    private const TEKS_TIDAK_ADA = [
        'tidakada', 'tidakadaalergi', 'tidakadaalergiyangdiketahui',
        'tidaka', 'tidakda', 'tridakada', 'tdkada', 'tidak',
        'disangkal', 'nihil', 'none', '-',
    ];

    /**
     * Apakah kode ini pernyataan "tidak ada alergi" (bukan zat penyebab)?
     *
     * Dipakai sender SATUSEHAT: AllergyIntolerance untuk "no known allergy" TIDAK boleh
     * membawa `category`/`criticality`/`type` — itu atribut alergi yang ADA. Mengirim
     * category='medication' bersama 716186003 malah kontradiktif: "tidak ada alergi obat"
     * punya kodenya sendiri (409137002), bukan 716186003.
     */
    public static function adalahTidakAdaAlergi(?string $kode): bool
    {
        $k = trim((string) $kode);
        if ($k === '') {
            return false;
        }

        return in_array($k, [
            self::TIDAK_ADA['snomedCode'],
            self::TIDAK_ADA_OBAT['snomedCode'],
            self::TIDAK_ADA_MAKANAN['snomedCode'],
            self::TIDAK_ADA_LINGKUNGAN['snomedCode'],
        ], true);
    }

    /** Normalkan teks bebas: huruf kecil, buang spasi & tanda baca. */
    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($s))) ?? '';
    }

    /**
     * Nama key per modul — RJ/UGD dan RI menyimpan alergi dengan bentuk BERBEDA
     * (lihat [[feedback_ugd_rj_struktur_beda]]), tapi logikanya sama sehingga tetap
     * satu sumber. `ada` sengaja sama supaya UI-nya seragam.
     */
    private const KEYS_RJ_UGD = [
        'ada' => 'adaAlergi', 'teks' => 'alergi',
        'code' => 'snomedCode', 'en' => 'snomedDisplayEn', 'id' => 'snomedDisplayId',
    ];

    private const KEYS_RI = [
        'ada' => 'adaAlergi', 'teks' => 'jenisAlergi',
        'code' => 'jenisAlergiSnomedCode', 'en' => 'jenisAlergiSnomedDisplayEn', 'id' => 'jenisAlergiSnomedDisplayId',
    ];

    /**
     * Seragamkan node alergi RJ/UGD (`anamnesa.alergi`) + turunkan `adaAlergi`.
     *
     * @param  array $node  node anamnesa.alergi
     */
    public static function normalisasi(array $node): array
    {
        return self::terapkan($node, self::KEYS_RJ_UGD);
    }

    /**
     * Seragamkan alergi RI (`pengkajianDokter.anamnesa`) — key-nya datar (`jenisAlergi`),
     * bukan node tersendiri, jadi HANYA key alergi yang disentuh; sisanya utuh.
     *
     * @param  array $anamnesa  node pengkajianDokter.anamnesa
     */
    public static function normalisasiRi(array $anamnesa): array
    {
        return self::terapkan($anamnesa, self::KEYS_RI);
    }

    /**
     * Inti logika (dipakai ketiga modul). Dipanggil saat form DIBUKA & saat radio diubah.
     *
     *   - Sudah punya `adaAlergi` -> hormati apa adanya (jawaban petugas).
     *     ⚠️ Karena itu struktur DEFAULT modul TIDAK BOLEH preset `adaAlergi => 'Tidak'`:
     *     nilai itu tak bisa dibedakan dari jawaban asli, sehingga akan MENGHAPUS alergi
     *     yang baru di-prefill dari master pasien (mis. "allopurinol" jadi "Tidak ada
     *     alergi"). Biarkan kosong — biar diturunkan dari teks. Jebakan yang sama pernah
     *     terjadi pada resikoJatuh='Tidak' (lihat PenilaianObservationMap).
     *   - Belum punya (record lama / pasien baru) -> turunkan dari teks:
     *       teks kosong / salah satu ejaan "tidak ada" -> 'Tidak'
     *       teks lain (mis. "allopurinol")            -> 'Ya'
     *   - 'Tidak' -> teks & kode dipaksa ke TIDAK_ADA (716186003), kode zat dibuang.
     *   - 'Ya'    -> teks & kode zat dibiarkan; kalau teksnya masih berbunyi "tidak ada",
     *                dikosongkan supaya petugas mengisi zat yang sebenarnya.
     *
     * CATATAN: 716186003 adalah konsep SNOMED *situation*, BUKAN zat — jadi ia TIDAK
     * boleh masuk LOV `substance-code` (terbukti ditolak $validate-code). Karena itu saat
     * 'Tidak' LOV zat disembunyikan & dikosongkan, dan kodenya diisi di sini.
     *
     * @param  array<string,string> $k  peta nama key modul terkait
     */
    private static function terapkan(array $node, array $k): array
    {
        $teks = trim((string) ($node[$k['teks']] ?? ''));
        $ada = trim((string) ($node[$k['ada']] ?? ''));

        if ($ada !== 'Ya' && $ada !== 'Tidak') {
            $ada = ($teks === '' || in_array(self::norm($teks), self::TEKS_TIDAK_ADA, true)) ? 'Tidak' : 'Ya';
        }

        if ($ada === 'Tidak') {
            return [...$node, ...self::sebagai(self::TIDAK_ADA, $k), $k['ada'] => 'Tidak'];
        }

        // 'Ya' tapi teksnya masih "tidak ada" (mis. petugas baru mengubah radio) -> kosongkan
        // supaya tak ada kontradiksi "ada alergi = tidak ada alergi".
        if ($teks === '' || in_array(self::norm($teks), self::TEKS_TIDAK_ADA, true)) {
            return [...$node, $k['ada'] => 'Ya', $k['teks'] => '', $k['code'] => '', $k['en'] => '', $k['id'] => ''];
        }

        return [...$node, $k['ada'] => 'Ya'];
    }

    /**
     * Teks alergi untuk DISPLAY / CETAK (read-only) — RJ/UGD.
     *
     * Cetakan membedakan keadaan; sebelumnya semuanya jadi "-" sehingga pembaca tak bisa
     * tahu pasien memang tak punya alergi ATAU perawat lupa mengisi:
     *   1. dikaji, tidak ada alergi   -> "Tidak ada alergi"
     *   2. dikaji, ada alergi         -> teksnya
     *   3. dikaji "Ya" tapi tak dirinci -> "Ada (belum dirinci)"
     *   4. record lama, teks terisi   -> teksnya apa adanya (JANGAN ditafsir)
     *   5. record lama, KOSONG        -> "Tidak ada alergi"
     *
     * Keadaan 5 = keputusan user 2026-07-15 atas dasar KONVENSI LAMA: di sistem lama,
     * alergi kosong memang DIPERSEPSIKAN "tidak ada alergi" — perawat sudah bertanya lalu
     * mengosongkannya. Jadi menulis "Belum dikaji" justru salah ke arah sebaliknya
     * (seolah tak pernah ditanyakan).
     *
     * ⚠️ Risiko yang disadari: kalau ternyata ada record yang benar-benar terlewat, ia akan
     * tercetak "Tidak ada alergi" — klaim yang tak pernah dibuat siapa pun. Sinyal yang
     * berlawanan dgn konvensi itu: dari 397 record RJ, 294 MENGETIK "tidak ada"/"disangkal"
     * secara eksplisit sementara 90 dibiarkan kosong — kalau kosong sudah berarti "tidak
     * ada", 294 orang itu tak perlu repot mengetik. Bila kelak diputuskan sebaliknya, ganti
     * fallback ini ke 'Belum dikaji' (satu baris, di bawah).
     */
    public static function untukCetak(array $node): string
    {
        return self::teksCetak($node, self::KEYS_RJ_UGD);
    }

    /** Idem untuk RI (`pengkajianDokter.anamnesa`, key datar `jenisAlergi`). */
    public static function untukCetakRi(array $anamnesa): string
    {
        return self::teksCetak($anamnesa, self::KEYS_RI);
    }

    /** @param array<string,string> $k */
    private static function teksCetak(array $node, array $k): string
    {
        $teks = trim((string) ($node[$k['teks']] ?? ''));
        $ada = trim((string) ($node[$k['ada']] ?? ''));

        if ($ada === 'Tidak') {
            return self::TIDAK_ADA['alergi'];
        }

        if ($ada === 'Ya') {
            return $teks !== '' ? $teks : 'Ada (belum dirinci)';
        }

        // Record lama (belum ada jawaban Ya/Tidak). Teks lama ditampilkan APA ADANYA —
        // jangan dinormalkan: cetakan harus memperlihatkan yang benar-benar dicatat waktu
        // itu, bukan tafsiran kita. Kosong -> "Tidak ada alergi" mengikuti konvensi lama
        // (lihat catatan keadaan 5 di docblock).
        return $teks !== '' ? $teks : self::TIDAK_ADA['alergi'];
    }

    /**
     * Petakan konstanta (berkey RJ/UGD) ke nama key modul tujuan.
     *
     * @param  array<string,string> $k
     * @return array<string,string>
     */
    private static function sebagai(array $preset, array $k): array
    {
        return [
            $k['teks'] => $preset['alergi'],
            $k['code'] => $preset['snomedCode'],
            $k['en'] => $preset['snomedDisplayEn'],
            $k['id'] => $preset['snomedDisplayId'],
        ];
    }
}
