<?php

namespace App\Support;

/**
 * Sumber tunggal peta label opsi Formulir Permintaan Darah (transfusi).
 * Dipakai form (actions), cetak PDF, dan viewer Rekam Medis — jangan diduplikasi.
 */
class PermintaanDarahOptions
{
    /** Jenis komponen darah (key = kode, value = label cetak). */
    public const JENIS = [
        'wb' => 'Whole Blood (WB)',
        'prc' => 'Packed Red Cell (PRC)',
        'ffp' => 'Fresh Frozen Plasma (FFP)',
        'lainnya' => 'Lainnya',
    ];

    public const GOLONGAN = ['A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O'];

    public const RHESUS = ['+' => 'Positif (+)', '-' => 'Negatif (−)'];

    public const SATUAN = ['Unit' => 'Unit', 'cc' => 'cc'];

    public const TRANSFUSI = ['belum' => 'Belum pernah', 'pernah' => 'Pernah'];

    /** Dipakai blade cetak sebagai $data['opsiLabel']. */
    public static function labels(): array
    {
        return [
            'jenis' => self::JENIS,
            'golongan' => self::GOLONGAN,
            'rhesus' => self::RHESUS,
            'satuan' => self::SATUAN,
            'transfusi' => self::TRANSFUSI,
        ];
    }
}
