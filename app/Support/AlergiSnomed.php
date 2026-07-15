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
     * Isi node alergi dengan default "Tidak ada alergi" HANYA bila teksnya masih kosong.
     * Node yang sudah terisi (dari master pasien / input dokter) TIDAK disentuh.
     *
     * @param  array $node  node anamnesa.alergi
     * @return array        node (mungkin) sudah ber-default
     */
    public static function defaultBilaKosong(array $node): array
    {
        if (trim((string) ($node['alergi'] ?? '')) !== '') {
            return $node;
        }

        return [...$node, ...self::TIDAK_ADA];
    }
}
