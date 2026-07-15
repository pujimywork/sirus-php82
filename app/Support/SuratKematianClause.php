<?php

namespace App\Support;

/**
 * Registry TEKS KLAUSUL Surat Keterangan Kematian (UGD) per-VERSI — SUMBER TUNGGAL.
 *
 * Pola sama dengan App\Support\GeneralConsentClause / KerohanianClause
 * (lihat docs/clause-versioning.md + skill clause-versioning): dokumen bertanda tangan
 * harus bisa dicetak ulang dengan redaksi SAAT DITANDATANGANI walau teks diubah kemudian.
 *
 * Saat teks/kebijakan berubah:
 *   1. TAMBAH key versi baru (mis. 'v2'), JANGAN ubah versi lama — versi lama = arsip legal.
 *   2. Naikkan CURRENT.
 * Record baru menstempel CURRENT; record lama tetap render versi tersimpannya.
 *
 * Placeholder %RS% diinterpolasi komponen cetak (strtr + e()), TIDAK disimpan di registry.
 */
class SuratKematianClause
{
    /** Versi teks yang distempel untuk record BARU. */
    public const CURRENT = 'v1';

    public static function get(?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;
        return $reg[$ver] ?? $reg[self::CURRENT];
    }

    private static function registry(): array
    {
        return [
            'v1' => [
                'intro' => 'Yang bertanda tangan di bawah ini, dokter pada %RS%, dengan ini menerangkan bahwa:',
                'statement' => 'Telah meninggal dunia pada:',
                'penutup' => 'Demikian surat keterangan kematian ini dibuat dengan sebenarnya, berdasarkan pemeriksaan yang telah dilakukan, untuk dapat dipergunakan sebagaimana mestinya.',
                // Pembatas hukum: dokumen RS bukan pengganti akta kematian Dukcapil.
                'catatanHukum' => 'Surat keterangan ini diterbitkan oleh rumah sakit dan BUKAN merupakan Akta Kematian. Akta Kematian diterbitkan oleh Dinas Kependudukan dan Pencatatan Sipil berdasarkan surat keterangan ini.',
            ],
        ];
    }
}
