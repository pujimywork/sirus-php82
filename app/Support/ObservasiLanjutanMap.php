<?php

namespace App\Support;

/**
 * Pemetaan Observasi Lanjutan RI (EMR) → payload FHIR SATUSEHAT.
 *
 *   observasi.obatDanCairan.pemberianObatDanCairan[] → MedicationAdministration
 *   observasi.pemakaianOksigen.pemakaianOksigenData[] → Observation (terapi oksigen)
 *   observasi.pengeluaranCairan.pengeluaranCairan[]   → Observation (output cairan)
 *
 * (observasi.observasiLanjutan.tandaVital[] sudah ditangani sender Observation kartu 5.)
 *
 * Kode LOINC & SNOMED di bawah diverifikasi via terminology server tx.fhir.org —
 * jangan mengganti dari hafalan, pakai App\Http\Traits\SATUSEHAT\LoincTrait untuk cek ulang.
 */
class ObservasiLanjutanMap
{
    /** LOINC: alat/metode pemberian oksigen (jawaban berupa teks pilihan). */
    private const LOINC_ALAT_OKSIGEN = ['code' => '107117-4', 'display' => 'Method of oxygen delivery'];

    /** LOINC: laju aliran oksigen. Nilainya rentang → dikirim sebagai valueRange. */
    private const LOINC_FLOW_OKSIGEN = ['code' => '3151-8', 'display' => 'Inhaled oxygen flow rate'];

    /** LOINC: volume urine keluar. */
    private const LOINC_URINE_OUTPUT = ['code' => '9187-6', 'display' => 'Urine output'];

    /**
     * Rute pemberian → SNOMED (valueset route-codes).
     *
     * Data lapangan berisi 63 varian teks bebas termasuk salah ketik ('inheler'),
     * jadi pencocokan dilakukan atas teks yang sudah dinormalkan (huruf kecil, tanpa spasi).
     * Yang tidak dikenal SENGAJA tidak dipetakan — lebih baik tanpa route daripada salah kode.
     */
    private const RUTE_SNOMED = [
        'iv'          => ['47625008', 'Intravenous route'],
        'intravena'   => ['47625008', 'Intravenous route'],
        'intravenous' => ['47625008', 'Intravenous route'],
        'ivline'      => ['47625008', 'Intravenous route'],
        'drip'        => ['47625008', 'Intravenous route'],
        'sc'          => ['34206005', 'Subcutaneous route'],
        'subcutan'    => ['34206005', 'Subcutaneous route'],
        'subkutan'    => ['34206005', 'Subcutaneous route'],
        'im'          => ['78421000', 'Intramuscular route'],
        'intramuskular' => ['78421000', 'Intramuscular route'],
        'po'          => ['26643006', 'Oral route'],
        'oral'        => ['26643006', 'Oral route'],
        'peroral'     => ['26643006', 'Oral route'],
        'inhalasi'    => ['447694001', 'Respiratory tract route'],
        'inhaler'     => ['447694001', 'Respiratory tract route'],
        'inheler'     => ['447694001', 'Respiratory tract route'], // salah ketik yang lazim di data
        'nebul'       => ['447694001', 'Respiratory tract route'],
        'nebulizer'   => ['447694001', 'Respiratory tract route'],
        'nasal'       => ['46713006', 'Nasal route'],
    ];

    /**
     * Satuan dosis EMR → UCUM.
     *
     * ⚠️ JEBAKAN: di data, "gr"/"GR" berarti **gram** (mis. "BACTRAZ INJ 1 GR"), sedangkan
     * di UCUM `gr` berarti **grain** (≈0,065 g). Mengirim apa adanya = salah dosis ~15×.
     * Karena itu gr/gram/g DIPAKSA ke UCUM 'g'. Satuan non-UCUM (amp, tab, flash) memakai
     * anotasi kurung kurawal — sah di UCUM & jujur (dimensionless, bukan mengaku massa).
     * Satuan tak dikenal → null → dosage DIBUANG (mad-1), bukan ditebak.
     */
    private const SATUAN_UCUM = [
        'mg'    => ['mg', 'mg'],
        'gr'    => ['g', 'g'],       // "gr" Indonesia = gram, BUKAN grain
        'gram'  => ['g', 'g'],
        'g'     => ['g', 'g'],
        'mcg'   => ['ug', 'ug'],
        'ug'    => ['ug', 'ug'],
        'ml'    => ['mL', 'mL'],
        'cc'    => ['mL', 'mL'],
        'l'     => ['L', 'L'],
        'unit'  => ['unit', '{unit}'],
        'iu'    => ['iU', '[iU]'],
        'ui'    => ['iU', '[iU]'],
        'amp'   => ['ampul', '{ampul}'],
        'ampul' => ['ampul', '{ampul}'],
        'tab'   => ['tablet', '{tablet}'],
        'flash' => ['flash', '{flash}'],
        'fls'   => ['flash', '{flash}'],
    ];

    /** Normalkan teks bebas: huruf kecil, buang spasi & titik. */
    private static function norm(string $s): string
    {
        return preg_replace('/[\s.]+/', '', mb_strtolower(trim($s))) ?? '';
    }

    /**
     * Rute teks bebas → ['code','display'] SNOMED, atau null bila tak dikenal.
     */
    public static function rute(?string $teks): ?array
    {
        $k = self::norm((string) $teks);
        if ($k === '' || !isset(self::RUTE_SNOMED[$k])) {
            return null;
        }
        [$code, $display] = self::RUTE_SNOMED[$k];

        return ['code' => $code, 'display' => $display];
    }

    /**
     * Dosis teks ("1 GR", "50mg", "10 unit", "1") → Quantity UCUM, atau null bila tak terurai.
     *
     * Pecahan seperti "1/3 AMP" (±1,8% data) TIDAK diurai — nilai pecahan ampul tak bisa
     * dipastikan tanpa tahu isi ampulnya, jadi lebih baik dosage dibuang.
     *
     * @return array{value: float, unit: string|null, code: string|null}|null
     */
    public static function dosis(?string $teks): ?array
    {
        $s = trim((string) $teks);
        if ($s === '') {
            return null;
        }

        if (!preg_match('/^\s*([0-9]+(?:[.,][0-9]+)?)\s*([a-zA-Z]*)\s*$/', $s, $m)) {
            return null; // pecahan / teks campur → jangan ditebak
        }

        $value = (float) str_replace(',', '.', $m[1]);
        $satuan = self::norm($m[2] ?? '');

        if ($satuan === '') {
            return ['value' => $value, 'unit' => null, 'code' => null]; // angka telanjang, tetap sah
        }
        if (!isset(self::SATUAN_UCUM[$satuan])) {
            return null; // satuan tak dikenal → dosage dibuang, jangan salah kode
        }
        [$unit, $ucum] = self::SATUAN_UCUM[$satuan];

        return ['value' => $value, 'unit' => $unit, 'code' => $ucum];
    }

    /**
     * Entri pemakaian oksigen → 0..2 potongan payload Observation.
     * Alat (teks pilihan) + laju aliran (rentang, mis. "3-4 L/menit").
     *
     * @return array<int, array>
     */
    public static function oksigen(array $entry): array
    {
        $out = [];

        $alat = trim((string) ($entry['jenisAlatOksigen'] ?? ''));
        if ($alat !== '') {
            if (strcasecmp($alat, 'Lainnya') === 0 && trim((string) ($entry['jenisAlatOksigenDetail'] ?? '')) !== '') {
                $alat = trim((string) $entry['jenisAlatOksigenDetail']);
            }
            $out[] = [
                'code' => ['system' => 'http://loinc.org'] + self::LOINC_ALAT_OKSIGEN,
                'valueString' => $alat,
            ];
        }

        $flow = self::rentangFlow((string) ($entry['dosisOksigen'] ?? ''));
        if ($flow !== null) {
            $out[] = [
                'code' => ['system' => 'http://loinc.org'] + self::LOINC_FLOW_OKSIGEN,
                'valueRange' => $flow,
            ];
        }

        return $out;
    }

    /**
     * "3-4 L/menit" / "5-10 L/menit (Masker)" → Range low/high L/min.
     * "Lainnya" atau format lain → null (jangan mengarang angka).
     *
     * @return array{low: array, high: array}|null
     */
    private static function rentangFlow(string $teks): ?array
    {
        if (!preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*-\s*([0-9]+(?:[.,][0-9]+)?)/', $teks, $m)) {
            return null;
        }
        $low = (float) str_replace(',', '.', $m[1]);
        $high = (float) str_replace(',', '.', $m[2]);
        if ($high < $low) {
            return null;
        }

        return [
            'low'  => ['value' => $low,  'unit' => 'L/min', 'code' => 'L/min'],
            'high' => ['value' => $high, 'unit' => 'L/min', 'code' => 'L/min'],
        ];
    }

    /**
     * Entri pengeluaran cairan → 0..1 potongan payload Observation.
     * Data lapangan semuanya urine tapi ejaannya beragam (Urine/urine/urin/URINE).
     * Jenis output selain urine belum dipetakan → dilewati (bukan dipaksa ke kode urine).
     *
     * @return array<int, array>
     */
    public static function pengeluaran(array $entry): array
    {
        $jenis = self::norm((string) ($entry['jenisOutput'] ?? ''));
        $volume = $entry['volume'] ?? null;

        if (!is_numeric($volume) || (float) $volume <= 0) {
            return [];
        }
        if (!in_array($jenis, ['urine', 'urin', 'urin e'], true)) {
            return []; // muntah/drain/BAB dsb. butuh kode sendiri — jangan dipaksa
        }

        return [[
            'code' => ['system' => 'http://loinc.org'] + self::LOINC_URINE_OUTPUT,
            'valueQuantity' => ['value' => (float) $volume, 'unit' => 'mL', 'system' => 'http://unitsofmeasure.org', 'code' => 'mL'],
        ]];
    }
}
