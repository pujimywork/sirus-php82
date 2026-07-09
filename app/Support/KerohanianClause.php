<?php

namespace App\Support;

/**
 * Registry TEKS KLAUSUL Formulir Permintaan Pelayanan Kerohaniawan (RI) per-VERSI — SUMBER TUNGGAL.
 *
 * Pola sama dengan App\Support\GeneralConsentClause / PenjaminanClause
 * (lihat docs/clause-versioning.md + skill clause-versioning): dokumen bertanda tangan
 * harus bisa dicetak ulang dengan redaksi SAAT DITANDATANGANI walau teks diubah kemudian.
 *
 * Saat teks/kebijakan berubah:
 *   1. TAMBAH key versi baru (mis. 'v2'), JANGAN ubah versi lama.
 *   2. Naikkan CURRENT.
 * Record baru menstempel CURRENT; record lama tetap render versi tersimpannya.
 *
 * Placeholder statementPre/Post membungkus nilai Agama (diisi komponen cetak).
 */
class KerohanianClause
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
                'salamTitle' => 'Yth. Petugas Bimbingan Rohani RS Islam Madinah',
                'salamBody' => 'Mohon diberikan Bimbingan Rohani sebagai Pasien Rawat Inap.',
                'pemohonIntro' => 'Yang bertanda tangan di bawah ini:',
                // Statement mengapit Agama: "...Agama/Kepercayaan <AGAMA> kepada RS ... di bawah ini:"
                'statementPre' => 'Dengan ini menyatakan permintaan pendampingan pelayanan kerohanian Agama/Kepercayaan',
                'statementPost' => 'kepada Rumah Sakit Islam Madinah terhadap pasien di bawah ini:',
                'penutup' => 'Demikian surat permohonan permintaan pelayanan kerohaniawan ini saya buat sebagaimana mestinya.',
            ],
        ];
    }
}
