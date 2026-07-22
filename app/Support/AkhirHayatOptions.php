<?php

namespace App\Support;

/**
 * Sumber tunggal peta label opsi Pengkajian Akhir Hayat (KARS + RM.RI.62).
 *
 * Dipakai oleh cetak PDF (opsiLabel) di komponen viewer Rekam Medis RI/UGD, supaya
 * label checklist/skala tidak perlu diduplikasi di tiap viewer. Komponen form
 * (actions) tetap punya properti publiknya sendiri karena di-bind ke UI, namun
 * ISI-nya harus identik dengan konstanta di sini.
 */
class AkhirHayatOptions
{
    public const SKALA = [
        'tidakAda' => 'Tidak ada',
        'ringan' => 'Ringan',
        'sedang' => 'Sedang',
        'berat' => 'Berat',
    ];

    public const REAKSI_PASIEN = [
        'menyangkalMarah' => 'Menyangkal / marah',
        'sedihMenangis' => 'Sedih / menangis',
        'takutCemas' => 'Takut / cemas',
        'bersalahTakBerdaya' => 'Rasa bersalah / tidak berdaya',
    ];

    public const MASALAH_PASIEN = [
        'anxietas' => 'Cemas / ansietas menjelang kematian',
        'distressSpiritual' => 'Distres spiritual',
    ];

    public const KONDISI_KELUARGA = [
        'marahBersalah' => 'Marah / rasa bersalah',
        'depresiSedih' => 'Depresi / sedih & menangis',
        'letihGangguanTidur' => 'Letih / lelah & gangguan tidur',
        'konsentrasiKomunikasi' => 'Penurunan konsentrasi / komunikasi terganggu',
        'peranKeputusan' => 'Sulit menjalankan peran & terlibat keputusan perawatan',
    ];

    public const MASALAH_KELUARGA = [
        'kopingTidakEfektif' => 'Koping keluarga tidak efektif',
        'distressSpiritual' => 'Distres spiritual',
        'perubahanProsesKeluarga' => 'Perubahan proses keluarga',
    ];

    public const DUKUNGAN = [
        'roomingIn' => 'Keluarga boleh menunggu 24 jam',
        'keluargaKunjungLuarJam' => 'Keluarga boleh berkunjung di luar jam besuk',
        'sahabatKunjungLuarJam' => 'Sahabat boleh berkunjung di luar jam besuk',
    ];

    public const INTERVENSI_KEPERAWATAN = [
        'higienePersonalMata' => 'Kebersihan diri & perawatan mata',
        'posisiReposisi' => 'Posisi tidur nyaman & reposisi tiap 2 jam',
        'suctionSekret' => 'Pengisapan lendir bila menumpuk',
        'nutrisiCairan' => 'Pemenuhan nutrisi & cairan sesuai program',
        'manajemenNyeri' => 'Penanganan nyeri yang memadai',
        'dukunganKeluarga' => 'Pendampingan & empati kepada keluarga berduka',
    ];

    public const INTERVENSI_MEDIS = [
        'rjpo' => 'Resusitasi Jantung Paru Otak (RJPO)',
        'ventilator' => 'Alat bantu napas (ventilator)',
        'feedingTube' => 'Pemberian makan lewat selang',
        'parenteralNutrition' => 'Pemberian nutrisi lewat infus',
        'dialisis' => 'Cuci darah (dialisis)',
        'dnr' => 'Tidak dilakukan resusitasi (DNR)',
    ];

    public const PROGNOSIS = [
        'bonam' => 'Cenderung membaik (dubia ad bonam)',
        'malam' => 'Cenderung memburuk (dubia ad malam)',
        'terminal' => 'Buruk / terminal (malam)',
    ];

    public const HUBUNGAN = [
        'pasien' => 'Pasien Sendiri',
        'suami' => 'Suami',
        'istri' => 'Istri',
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
        'anak' => 'Anak',
        'saudara' => 'Saudara',
        'wali_hukum' => 'Wali Hukum',
        'lainnya' => 'Lainnya',
    ];

    /** Peta label yang dipakai blade cetak sebagai $data['opsiLabel']. */
    public static function labels(): array
    {
        return [
            'skala' => self::SKALA,
            'reaksiPasien' => self::REAKSI_PASIEN,
            'masalahPasien' => self::MASALAH_PASIEN,
            'kondisiKeluarga' => self::KONDISI_KELUARGA,
            'masalahKeluarga' => self::MASALAH_KELUARGA,
            'dukungan' => self::DUKUNGAN,
            'intervensiKeperawatan' => self::INTERVENSI_KEPERAWATAN,
            'intervensiMedis' => self::INTERVENSI_MEDIS,
            'prognosis' => self::PROGNOSIS,
            'hubungan' => self::HUBUNGAN,
        ];
    }
}
