<?php

namespace App\Support;

/**
 * Sumber tunggal opsi checklist Safety Plan (Stanley-Brown) pada sub-penilaian
 * Risiko Bunuh Diri RJ / UGD / RI.
 *
 * Dipakai bersama oleh:
 *   - blade partial pages/components/rekam-medis/risiko-bunuh-diri-safety-plan
 *     (untuk me-render <x-toggle>), dan
 *   - method toggleSafetyPlan($field, $index) di komponen penilaian RJ/UGD/RI
 *     (untuk memetakan indeks -> nilai saat toggle diklik).
 *
 * Wajib satu sumber supaya urutan (indeks) di blade & server selalu sinkron.
 * Toggle mengirim INDEKS + nama field (bukan nilai string) mengikuti pola
 * toggleTindakLanjutBunuhDiri, menghindari double-escape wire:click.
 */
class SafetyPlanOptions
{
    public const TANDA_BAHAYA = [
        'Pikiran hidup tidak berarti',
        'Merasa putus asa',
        'Ingin menyendiri',
        'Sulit tidur',
        'Cemas atau gelisah',
        'Marah berlebihan',
    ];

    public const STRATEGI_MANDIRI = [
        'Latihan napas',
        'Relaksasi',
        'Berdoa',
        'Mendengarkan musik',
        'Jalan santai',
        'Menulis jurnal',
        'Aktivitas hobi',
    ];

    public const AKTIVITAS_PENGALIH = [
        'Menonton film',
        'Pergi ke ruang keluarga',
        'Berjalan di lingkungan rumah',
    ];

    public const AMANKAN_LINGKUNGAN = [
        'Menyimpan obat-obatan dengan aman',
        'Menjauhkan benda tajam',
        'Mengamankan tali atau benda yang dapat digunakan untuk melukai diri',
        'Menghindari akses terhadap racun atau pestisida',
        'Tidak sendirian ketika risiko meningkat',
        'Menghubungi keluarga bila mulai muncul pikiran bunuh diri',
    ];

    /**
     * Peta field -> daftar opsi. Kunci = nama field di safetyPlan.
     */
    public static function all(): array
    {
        return [
            'tandaBahaya' => self::TANDA_BAHAYA,
            'strategiMandiri' => self::STRATEGI_MANDIRI,
            'aktivitasPengalih' => self::AKTIVITAS_PENGALIH,
            'amankanLingkungan' => self::AMANKAN_LINGKUNGAN,
        ];
    }

    /**
     * Opsi untuk satu field, atau null bila field tak dikenal.
     */
    public static function for(string $field): ?array
    {
        return self::all()[$field] ?? null;
    }
}
