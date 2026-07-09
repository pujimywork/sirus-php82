<?php

namespace App\Support;

/**
 * Registry TEKS KLAUSUL Form Penjaminan (ketentuan BPJS & selisih biaya) per-VERSI — SUMBER TUNGGAL.
 *
 * Pola sama dengan App\Support\GeneralConsentClause (lihat docs/clause-versioning.md + skill clause-versioning):
 * dokumen bertanda tangan harus bisa dicetak ulang dengan redaksi SAAT DITANDATANGANI walau
 * kebijakan berubah (mis. INA-CBG → iDRG mengubah aturan selisih biaya).
 *
 * Saat kebijakan/teks berubah:
 *   1. TAMBAH key versi baru (mis. 'v2') dengan teks baru. JANGAN ubah/hapus versi lama.
 *   2. Naikkan CURRENT.
 * Record baru menstempel CURRENT; record lama tetap render versi tersimpannya.
 *
 * Section: 'bpjs' (ketentuan penjaminan BPJS), 'selisih' (ketentuan selisih biaya naik kelas).
 */
class PenjaminanClause
{
    /** Versi teks yang distempel untuk record BARU. */
    public const CURRENT = 'v1';

    public static function get(string $section, ?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;
        return $reg[$ver][$section] ?? ($reg[self::CURRENT][$section] ?? []);
    }

    private static function registry(): array
    {
        return [
            'v1' => [
                'bpjs' => [
                    'intro' => 'BPJS Kesehatan hanya menjamin pelayanan kesehatan peserta JKN yang sesuai dengan ketentuan yang berlaku. Pelayanan yang tidak sesuai tidak menjadi tanggungan BPJS Kesehatan, antara lain:',
                    // Poin bernomor; item bertipe array = punya sub-poin (a/b).
                    'points' => [
                        'Pelayanan di luar ketentuan/prosedur yang diatur dalam Program JKN.',
                        [
                            'text' => 'Pelayanan yang tidak sesuai ketentuan:',
                            'sub' => [
                                'a. Rawat jalan/rawat inap atas permintaan sendiri (APS).',
                                'b. Penolakan/tidak mematuhi rencana terapi yang direkomendasikan (pulang APS) dan menerima segala konsekuensi atas keputusan pribadinya.',
                            ],
                        ],
                        'Pelayanan di luar lingkup penjaminan dalam Perjanjian Kerja Sama.',
                        'Pelayanan homecare di rumah (tidak dijamin dalam PKS FKRTL).',
                        'Kecelakaan lalu lintas tidak sesuai ketentuan (tidak urus LP/damai, intoksikasi miras).',
                        'Pelayanan atas instruksi dari fasilitas kesehatan yang tidak bekerja sama dengan BPJS Kesehatan.',
                        'Apabila peserta memilih pelayanan di luar ketentuan di atas, biaya menjadi tanggungan pribadi/keluarga.',
                    ],
                ],
                'selisih' => [
                    'rows' => [
                        [
                            'jenis' => 'Hak rawat kelas 2 naik ke kelas 1',
                            'ketentuan' => 'Selisih tarif INA-CBG pada kelas rawat inap kelas 1 dengan tarif INA-CBG pada kelas rawat inap kelas 2.',
                        ],
                        [
                            'jenis' => 'Hak rawat kelas 1 naik ke kelas di atas kelas 1',
                            'ketentuan' => 'Selisih tarif INA-CBG kelas 1 dengan tarif kelas di atas kelas 1, paling banyak sebesar 75% dari tarif INA-CBG kelas 1.',
                        ],
                        [
                            'jenis' => 'Hak rawat kelas 2 naik ke kelas di atas kelas 1',
                            'ketentuan' => 'Selisih tarif INA-CBG antara kelas 2 dengan kelas 1, ditambah paling banyak sebesar 75% dari tarif INA-CBG kelas 1.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
