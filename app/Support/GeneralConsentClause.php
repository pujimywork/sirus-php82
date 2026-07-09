<?php

namespace App\Support;

/**
 * Registry TEKS KLAUSUL General Consent per-VERSI (RJ/UGD/RI) — SUMBER TUNGGAL.
 *
 * Tujuan: dokumen bertanda tangan harus bisa dicetak ulang dengan redaksi
 * SAAT DITANDATANGANI, walau klausul diubah karena kebijakan baru kemudian.
 *
 * Cara pakai saat kebijakan/teks berubah:
 *   1. TAMBAH key versi baru (mis. 'v2') di registry() dengan teks baru.
 *   2. JANGAN ubah/hapus versi lama (arsip legal record yang sudah TTD).
 *   3. Naikkan konstanta CURRENT ke versi baru.
 * Record baru menstempel CURRENT; record lama tetap render versi tersimpannya.
 *
 * Placeholder introTemplate: %WALI%, %HUB%, %RS% (di-escape & diisi komponen).
 */
class GeneralConsentClause
{
    /** Versi teks yang distempel untuk record BARU. */
    public const CURRENT = 'v1';

    public static function get(string $context, ?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;
        return $reg[$ver][$context] ?? ($reg[self::CURRENT][$context] ?? []);
    }

    private static function registry(): array
    {
        $introTpl = fn(string $lokasi) => 'Saya yang bertanda tangan di bawah ini, <strong>%WALI%</strong> (sebagai <strong>%HUB%</strong> pasien), menyatakan bahwa saya telah mendapat penjelasan yang cukup mengenai tujuan, prosedur, risiko, dan manfaat dari pelayanan medis yang akan diberikan ' . $lokasi . ' <strong>%RS%</strong>, dengan bahasa yang saya pahami.';

        $agreePre = 'Dengan ini saya menyatakan ';

        $pointInfo = 'Saya berhak mendapat informasi yang jelas mengenai kondisi kesehatan, diagnosis, prosedur, risiko, dan alternatif tindakan.';
        $pointSecondOpinion = 'Saya berhak meminta konsultasi dokter lain (<em>second opinion</em>) bila diperlukan.';
        $pointRahasia = 'Rumah sakit menjaga kerahasiaan informasi medis saya sesuai ketentuan yang berlaku.';
        $pointICTerpisah = 'Untuk tindakan invasif, pembedahan, anestesi, transfusi darah, dan tindakan berisiko tinggi akan diminta <em>persetujuan tindakan (informed consent)</em> tersendiri.';

        return [
            'v1' => [
                'rj' => [
                    'subtitle' => 'Pelayanan Rawat Jalan',
                    'introTemplate' => $introTpl('di'),
                    'agreePre' => $agreePre,
                    'agreePost' => ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini.',
                    'points' => [
                        $pointInfo,
                        'Saya berhak menolak/menghentikan tindakan, termasuk pelayanan resusitasi, setelah mendapat penjelasan.',
                        $pointSecondOpinion,
                        $pointRahasia,
                        'Saya bertanggung jawab atas biaya pelayanan sesuai ketentuan rumah sakit.',
                        $pointICTerpisah,
                    ],
                ],
                'ugd' => [
                    'subtitle' => 'Pelayanan Unit Gawat Darurat',
                    'introTemplate' => $introTpl('di'),
                    'agreePre' => $agreePre,
                    'agreePost' => ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini.',
                    'points' => [
                        $pointInfo,
                        'Saya berhak menolak/menghentikan tindakan, termasuk pelayanan resusitasi, setelah mendapat penjelasan.',
                        $pointSecondOpinion,
                        $pointRahasia,
                        'Saya bertanggung jawab atas biaya pelayanan sesuai ketentuan rumah sakit.',
                        'Untuk tindakan invasif, pembedahan, anestesi, transfusi darah, dan tindakan berisiko tinggi akan diminta <em>persetujuan tindakan (informed consent)</em> tersendiri. Dalam keadaan darurat yang mengancam nyawa, tindakan penyelamatan dapat dilakukan sebelum persetujuan diperoleh.',
                    ],
                ],
                'ri' => [
                    'subtitle' => 'Pelayanan Rawat Inap',
                    'introTemplate' => $introTpl('selama rawat inap di'),
                    'agreePre' => $agreePre,
                    'agreePost' => ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini selama menjalani rawat inap.',
                    'points' => [
                        $pointInfo,
                        'Saya berhak menolak/menghentikan tindakan, termasuk pelayanan resusitasi dan terapi penunjang kehidupan, setelah mendapat penjelasan.',
                        $pointSecondOpinion,
                        'Saya berhak didampingi keluarga, terutama dalam keadaan kritis.',
                        $pointRahasia,
                        'Saya bertanggung jawab atas biaya pelayanan rawat inap sesuai ketentuan rumah sakit, termasuk biaya kamar dan tindakan yang dilakukan.',
                        $pointICTerpisah,
                        'Rumah sakit tidak bertanggung jawab atas kehilangan atau kerusakan barang berharga yang saya bawa sendiri.',
                    ],
                ],
            ],
        ];
    }
}
