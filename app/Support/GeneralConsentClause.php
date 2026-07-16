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

        // Hak & Tanggung Jawab pasien — redaksi dipulihkan dari general-consent-body.blade.php
        // (dihapus d660a2e1). Ini daftar UTAMA; 'points' hanya memuat sisa yang tidak
        // terwakili di sini — jangan ulangi isi Hak/TJ di points (dulu tumpang tindih 5 poin).
        // Beda RJ/UGD vs RI hanya pada 1 poin hak & 1 poin tanggung jawab (varian rawat inap).
        $buildHak = fn(string $poinMenolak) => [
            'Mendapatkan informasi yang jelas tentang peraturan rumah sakit dan hak serta tanggung jawab saya sebagai pasien.',
            'Mendapatkan layanan kesehatan yang baik, tanpa diskriminasi dan sesuai dengan standar profesional.',
            'Memilih dokter dan jenis perawatan yang saya inginkan, sesuai ketentuan rumah sakit.',
            'Mendapatkan informasi tentang diagnosis, prosedur medis, tujuan, risiko, dan alternatif tindakan medis.',
            $poinMenolak,
            'Mendapatkan privasi dan kerahasiaan terkait penyakit dan data medis saya.',
            'Mengajukan keluhan atau saran mengenai pelayanan rumah sakit yang saya terima.',
            'Meminta konsultasi (<em>second opinion</em>) dengan dokter lain yang berizin jika diperlukan.',
        ];

        $buildTanggungJawab = fn(string $poinBiaya) => [
            'Mematuhi peraturan rumah sakit dan menggunakan fasilitas dengan bertanggung jawab.',
            'Memberikan informasi yang akurat dan lengkap tentang kondisi kesehatan saya.',
            'Mematuhi rencana terapi yang disarankan oleh tenaga medis setelah mendapatkan penjelasan.',
            $poinBiaya,
            'Menghormati hak pasien lain dan petugas medis yang memberikan pelayanan.',
        ];

        $hakPasien = $buildHak(
            'Memberikan persetujuan atau menolak tindakan medis yang akan dilakukan oleh tenaga kesehatan, termasuk hak untuk menolak/menghentikan terapi serta menolak pelayanan resusitasi.',
        );
        $tanggungJawabPasien = $buildTanggungJawab('Menanggung biaya pengobatan yang saya terima sesuai ketentuan yang berlaku.');

        // Varian RI — dua nuansa khas rawat inap yang dulu hidup di 'points'
        // (terapi penunjang kehidupan, biaya kamar); dipindah ke sini agar tidak hilang.
        $hakPasienRI = $buildHak(
            'Memberikan persetujuan atau menolak tindakan medis yang akan dilakukan oleh tenaga kesehatan, termasuk hak untuk menolak/menghentikan terapi, pelayanan resusitasi, serta terapi penunjang kehidupan.',
        );
        $tanggungJawabPasienRI = $buildTanggungJawab(
            'Menanggung biaya pengobatan yang saya terima sesuai ketentuan yang berlaku, termasuk biaya kamar dan tindakan yang dilakukan.',
        );

        $pointICTerpisah = 'Untuk tindakan invasif, pembedahan, anestesi, transfusi darah, dan tindakan berisiko tinggi akan diminta <em>persetujuan tindakan (informed consent)</em> tersendiri.';

        return [
            'v1' => [
                'rj' => [
                    'subtitle' => 'Pelayanan Rawat Jalan',
                    'introTemplate' => $introTpl('di'),
                    'hakPasien' => $hakPasien,
                    'tanggungJawabPasien' => $tanggungJawabPasien,
                    'agreePre' => $agreePre,
                    'agreePost' => ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini.',
                    'points' => [
                        $pointICTerpisah,
                    ],
                ],
                'ugd' => [
                    'subtitle' => 'Pelayanan Unit Gawat Darurat',
                    'introTemplate' => $introTpl('di'),
                    'hakPasien' => $hakPasien,
                    'tanggungJawabPasien' => $tanggungJawabPasien,
                    'agreePre' => $agreePre,
                    'agreePost' => ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini.',
                    'points' => [
                        'Untuk tindakan invasif, pembedahan, anestesi, transfusi darah, dan tindakan berisiko tinggi akan diminta <em>persetujuan tindakan (informed consent)</em> tersendiri. Dalam keadaan darurat yang mengancam nyawa, tindakan penyelamatan dapat dilakukan sebelum persetujuan diperoleh.',
                    ],
                ],
                'ri' => [
                    'subtitle' => 'Pelayanan Rawat Inap',
                    'introTemplate' => $introTpl('selama rawat inap di'),
                    'hakPasien' => $hakPasienRI,
                    'tanggungJawabPasien' => $tanggungJawabPasienRI,
                    'agreePre' => $agreePre,
                    'agreePost' => ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini selama menjalani rawat inap.',
                    'points' => [
                        'Saya berhak didampingi keluarga, terutama dalam keadaan kritis.',
                        $pointICTerpisah,
                        'Rumah sakit tidak bertanggung jawab atas kehilangan atau kerusakan barang berharga yang saya bawa sendiri.',
                    ],
                ],
            ],
        ];
    }
}
