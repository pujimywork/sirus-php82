<?php

namespace App\Support;

/**
 * Opsi Discharge Planning RI (pelayanan berkelanjutan & alat bantu) + kode SNOMED-nya.
 *
 * Dipakai form `rm-perencanaan-ri-actions` (picklist) dan nanti sender CarePlan
 * (`activity.detail.code`). Kode disimpan PER ENTRI di JSON supaya cetak/kirim record
 * lama tetap memakai kode saat itu, walau daftar opsi berubah.
 *
 * ⚠️ SEMUA kode di bawah diverifikasi via terminology server tx.fhir.org
 * (CodeSystem/$lookup). JANGAN menambah/mengubah kode dari hafalan — daftar
 * `tindakLanjutOptions` di modul yang sama membuktikan bahayanya: 2 dari 6 kodenya
 * salah arti, salah satunya melaporkan pasien pulang hidup sebagai MENINGGAL
 * (lihat App\Support\DischargeDisposition).
 *
 * Catatan: "Tongkat" dan "Walker" SENGAJA dipisah — SNOMED membedakannya
 * (Walking stick vs Walking frame); digabung berarti salah satunya pasti salah kode.
 */
class DischargePlanningOptions
{
    /** Jenis pelayanan berkelanjutan → SNOMED (konsep prosedur/layanan). */
    public const PELAYANAN = [
        ['label' => 'Perawatan luka',           'code' => '225358003', 'display' => 'Wound care'],
        ['label' => 'Fisioterapi/rehabilitasi', 'code' => '52052004',  'display' => 'Rehabilitation therapy'],
        ['label' => 'Homecare',                 'code' => '60689008',  'display' => 'Home care of patient'],
        ['label' => 'Perawatan kateter',        'code' => '410253009', 'display' => 'Urinary catheter care management'],
        ['label' => 'Perawatan NGT',            'code' => '736165005', 'display' => 'Nasogastric tube care management'],
        ['label' => 'Hemodialisa',              'code' => '302497006', 'display' => 'Hemodialysis procedure'],
        ['label' => 'Rujuk balik FKTP',         'code' => '703978000', 'display' => 'Referral to primary care service'],
        ['label' => 'Lainnya',                  'code' => '74964007',  'display' => 'Other'],
    ];

    /** Jenis alat bantu → SNOMED (konsep alat/device). */
    public const ALAT_BANTU = [
        ['label' => 'Kursi roda',    'code' => '58938008',  'display' => 'WC - Wheelchair'],
        ['label' => 'Tongkat',       'code' => '360006004', 'display' => 'Walking stick'],
        ['label' => 'Walker',        'code' => '266731002', 'display' => 'Walking frame'],
        ['label' => 'Kruk',          'code' => '74566002',  'display' => 'Crutch'],
        ['label' => 'Oksigen',       'code' => '336621006', 'display' => 'Oxygen concentrator'],
        ['label' => 'Kateter urin',  'code' => '20568009',  'display' => 'Urinary catheter'],
        ['label' => 'NGT',           'code' => '17102003',  'display' => 'Nasogastric tube'],
        ['label' => 'Kolostomi bag', 'code' => '339648008', 'display' => 'Colostomy bag'],
        ['label' => 'Korset/brace',  'code' => '224898003', 'display' => 'Orthotic device'],
        ['label' => 'Lainnya',       'code' => '74964007',  'display' => 'Other'],
    ];

    /** Sumber alat bantu (tanpa kode — keperluan internal, bukan pelaporan). */
    public const SUMBER_ALAT = ['Pinjam RS', 'Milik sendiri', 'Beli'];

    /** @return array{code: string, display: string}|null */
    public static function pelayanan(?string $label): ?array
    {
        return self::cari(self::PELAYANAN, $label);
    }

    /** @return array{code: string, display: string}|null */
    public static function alatBantu(?string $label): ?array
    {
        return self::cari(self::ALAT_BANTU, $label);
    }

    /** @return array{code: string, display: string}|null */
    private static function cari(array $opsi, ?string $label): ?array
    {
        $l = trim((string) $label);
        foreach ($opsi as $o) {
            if ($o['label'] === $l) {
                return ['code' => $o['code'], 'display' => $o['display']];
            }
        }

        return null;
    }
}
