<?php

namespace App\Support;

/**
 * Pemetaan entri Penilaian EMR → payload FHIR Observation (SATUSEHAT).
 *
 * Dipakai bersama oleh sender RI (kirim-penilaian) dan UGD (kirim-penilaian):
 * struktur node JSON penilaian di kedua modul IDENTIK, hanya trait/PK/event yang beda.
 * Ditaruh sebagai helper statis (bukan trait) supaya tidak menambah risiko tabrakan
 * nama method di komponen EMR yang sudah menumpuk banyak trait.
 *
 * Struktur entri (bersarang ganda, sesuai writer rm-penilaian-*-actions):
 *   penilaian.resikoJatuh[] → { tglPenilaian, resikoJatuh: { kategoriResiko,
 *                                resikoJatuhMetode: { resikoJatuhMetode, resikoJatuhMetodeScore } } }
 *   penilaian.gizi[]        → { tglPenilaian, gizi: { beratBadan, tinggiBadan, imt,
 *                                skorSkrining, kategoriGizi } }
 *
 * Kode LOINC di bawah SEMUA diverifikasi via terminology server tx.fhir.org
 * (CodeSystem/$lookup + ValueSet/$expand). Jangan mengganti dari hafalan —
 * pakai App\Http\Traits\SATUSEHAT\LoincTrait untuk cek ulang.
 */
class PenilaianObservationMap
{
    /** Kategori repo → answer list LOINC LL905-1 (milik kode 59461-4). */
    private const MORSE_LEVEL = [
        'rendah' => ['code' => 'LA13038-7', 'display' => 'Low Risk (MFS Score 0 - 24)'],
        'sedang' => ['code' => 'LA13039-5', 'display' => 'Moderate Risk (MFS Score 25 - 45)'],
        'tinggi' => ['code' => 'LA13040-3', 'display' => 'High Risk (MFS Score 50+)'],
    ];

    /** Category FHIR untuk observasi skala/kuesioner (LOINC CLASSTYPE=4 Surveys). */
    public static function surveyCategory(): array
    {
        return [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code'    => 'survey',
                'display' => 'Survey',
            ]],
        ]];
    }

    /**
     * Satu entri risiko jatuh → 0..2 potongan payload Observation.
     *
     * Morse punya LOINC penuh (skor total + level ber-answer-list).
     * Humpty Dumpty TIDAK punya padanan LOINC → kode generik 73830-2 + nilai teks.
     *
     * Entri TANPA metode DAN tanpa kategori tidak menghasilkan apa-apa. Ini pola dominan
     * di RJ (nilai default form: resikoJatuh='Tidak', metode='', skor=0, kategori='') —
     * artinya tidak ada skala yang dipakai, jadi tidak ada temuan untuk dilaporkan.
     * Jangan gantungkan guard pada skor: skor 0 itu default sekaligus nilai Morse yang sah.
     * Field resikoJatuh ('Ya'/'Tidak') juga tidak dipakai sebagai penentu karena 'Tidak'
     * adalah nilai default — entri yang tersimpan apa adanya tak bisa dibedakan dari
     * pernyataan "tidak berisiko" yang disengaja.
     *
     * @return array<int, array>
     */
    public static function resikoJatuh(array $entry): array
    {
        $node = $entry['resikoJatuh'] ?? [];
        $metode = trim((string) ($node['resikoJatuhMetode']['resikoJatuhMetode'] ?? ''));
        $skor = $node['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? null;
        $kategori = trim((string) ($node['kategoriResiko'] ?? ''));

        if ($metode === '' && $kategori === '') {
            return [];
        }

        $adaSkor = is_numeric($skor);
        $out = [];

        if (stripos($metode, 'morse') !== false) {
            if ($adaSkor) {
                $out[] = [
                    'category' => self::surveyCategory(),
                    'code' => ['system' => 'http://loinc.org', 'code' => '59460-6', 'display' => 'Fall risk total [Morse Fall Scale]'],
                    'valueQuantity' => ['value' => (float) $skor, 'unit' => '{score}', 'system' => 'http://unitsofmeasure.org', 'code' => '{score}'],
                ];
            }
            $level = self::MORSE_LEVEL[strtolower($kategori)] ?? null;
            if ($level !== null) {
                $out[] = [
                    'category' => self::surveyCategory(),
                    'code' => ['system' => 'http://loinc.org', 'code' => '59461-4', 'display' => 'Fall risk level [Morse Fall Scale]'],
                    'valueCodeableConcept' => ['system' => 'http://loinc.org', 'code' => $level['code'], 'display' => $level['display']],
                ];
            }

            return $out;
        }

        // Humpty Dumpty / metode lain: tanpa padanan LOINC → kode generik + nilai teks.
        $label = $kategori !== '' ? $kategori : 'Tidak diketahui';
        if ($metode !== '') {
            $label .= ' (' . $metode . ($adaSkor ? ', skor ' . $skor : '') . ')';
        }
        $out[] = [
            'category' => self::surveyCategory(),
            'code' => ['system' => 'http://loinc.org', 'code' => '73830-2', 'display' => 'Fall risk assessment'],
            'valueString' => $label,
        ];

        return $out;
    }

    /**
     * Satu entri gizi → 0..3 potongan payload Observation antropometri (category default vital-signs).
     *
     * skorSkrining/kategoriGizi SENGAJA dilewati: skrining gizi di repo ini skala custom
     * 3-item (bukan MST/MUST/Strong-Kids) sehingga tidak punya padanan LOINC.
     *
     * Nilai di luar batas kewajaran DILEWATI. Form gizi tidak punya validasi rentang,
     * dan data nyata sempat berisi bb=1 kg / tb=1 cm → imt=10000 kg/m2; angka mustahil
     * seperti itu tidak boleh lolos ke SATUSEHAT sebagai data klinis. Batas dibuat lebar
     * (neonatus s/d dewasa obes) supaya hanya menyaring yang jelas mustahil, bukan ekstrem.
     *
     * @return array<int, array>
     */
    public static function gizi(array $entry): array
    {
        $node = $entry['gizi'] ?? [];

        $items = [
            ['val' => $node['beratBadan'] ?? null,  'loinc' => '29463-7', 'display' => 'Body weight',                  'unit' => 'kg',    'ucum' => 'kg',    'min' => 0.3, 'max' => 500.0],
            ['val' => $node['tinggiBadan'] ?? null, 'loinc' => '8302-2',  'display' => 'Body height',                  'unit' => 'cm',    'ucum' => 'cm',    'min' => 20.0, 'max' => 260.0],
            ['val' => $node['imt'] ?? null,         'loinc' => '39156-5', 'display' => 'Body mass index (BMI) [Ratio]', 'unit' => 'kg/m2', 'ucum' => 'kg/m2', 'min' => 5.0,  'max' => 200.0],
        ];

        $out = [];
        foreach ($items as $v) {
            if ($v['val'] === null || $v['val'] === '' || !is_numeric($v['val'])) {
                continue;
            }
            $num = (float) $v['val'];
            if ($num < $v['min'] || $num > $v['max']) {
                continue;
            }
            $out[] = [
                'code' => ['system' => 'http://loinc.org', 'code' => $v['loinc'], 'display' => $v['display']],
                'valueQuantity' => ['value' => $num, 'unit' => $v['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $v['ucum']],
            ];
        }

        return $out;
    }
}
