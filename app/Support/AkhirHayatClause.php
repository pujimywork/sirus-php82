<?php

namespace App\Support;

/**
 * Registry TEKS PERNYATAAN Pengkajian Akhir Hayat (RI) per-VERSI — SUMBER TUNGGAL.
 *
 * Pola sama dengan App\Support\GeneralConsentClause
 * (lihat docs/clause-versioning.md + skill clause-versioning): dokumen bertanda tangan
 * harus bisa dicetak ulang dengan redaksi SAAT DITANDATANGANI walau teks diubah kemudian.
 *
 * Saat teks/kebijakan berubah:
 *   1. TAMBAH key versi baru (mis. 'v2'), JANGAN ubah versi lama.
 *   2. Naikkan CURRENT.
 * Record baru menstempel CURRENT; record lama tetap dicetak dengan versi tersimpannya.
 */
class AkhirHayatClause
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
                'persetujuan' =>
                    'Saya yang bertanda tangan di bawah ini menyatakan bahwa saya telah membaca dan memahami ' .
                    'semua informasi di atas, dan menyetujui rencana perawatan yang telah disusun.',
            ],
        ];
    }
}
