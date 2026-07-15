<?php

namespace App\Http\Traits\SATUSEHAT;


trait ObservationTrait
{
    use SatuSehatTrait;

    /**
     * Generic FHIR Observation creator
     *
     * @param array $data
     *   - patientId       (string)  required
     *   - encounterId     (string)  required
     *   - performerId     (string)  optional
     *   - effectiveDate   (string)  ISO8601 optional (default: now)
     *   - category        (array)   FHIR category coding array (default: vital-signs)
     *   - code            (array)   ['system'=>..., 'code'=>..., 'display'=>...] required
     *   - valueQuantity   (array)   ['value'=>float|int,'unit'=>string,'system'=>string,'code'=>string]
     *   - valueString     (string)  optional
     *   - valueCodeableConcept (array) ['system'=>...,'code'=>...,'display'=>...] optional
     *   - components      (array)   list of buildVitalSignComponent(...) items
     *
     * @return array  decoded JSON response
     * @throws \Exception on HTTP error
     */
    public function createObservation(array $data): array
    {
        // Basic payload
        $payload = [
            'resourceType'      => 'Observation',
            'status'            => $data['status']       ?? 'final',
            'category'          => $data['category']     ?? [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                    'code'    => 'vital-signs',
                    'display' => 'Vital Signs',
                ]],
            ]],
            'code'              => [
                'coding' => [[
                    'system'  => $data['code']['system'],
                    'code'    => $data['code']['code'],
                    'display' => $data['code']['display'],
                ]],
                'text' => $data['code']['display'],
            ],
            'subject'           => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'effectiveDateTime' => $data['effectiveDate'] ?? now()->toIso8601String(),
        ];

        // Optional performer
        if (!empty($data['performerId'])) {
            $payload['performer'] = [[
                'reference' => 'Practitioner/' . $data['performerId']
            ]];
        }

        // Either a single valueQuantity...
        if (!empty($data['valueQuantity']) && is_array($data['valueQuantity'])) {
            $payload['valueQuantity'] = [
                'value'  => $data['valueQuantity']['value'],
                'unit'   => $data['valueQuantity']['unit'],
                'system' => $data['valueQuantity']['system'],
                'code'   => $data['valueQuantity']['code'],
            ];
        }
        // ...or a simple string
        elseif (isset($data['valueString'])) {
            $payload['valueString'] = $data['valueString'];
        }
        // ...or a coded answer (mis. LOINC answer list LA* untuk skala survey)
        elseif (!empty($data['valueCodeableConcept']) && is_array($data['valueCodeableConcept'])) {
            $payload['valueCodeableConcept'] = [
                'coding' => [[
                    'system'  => $data['valueCodeableConcept']['system'],
                    'code'    => $data['valueCodeableConcept']['code'],
                    'display' => $data['valueCodeableConcept']['display'],
                ]],
                'text' => $data['valueCodeableConcept']['display'],
            ];
        }
        // ...or a range (mis. dosis oksigen "3-4 L/menit" — rentang pilihan, BUKAN hasil ukur;
        // jangan dipaksa jadi angka tunggal karena itu mengarang presisi yang tak pernah diukur)
        elseif (!empty($data['valueRange']) && is_array($data['valueRange'])) {
            $range = [];
            foreach (['low', 'high'] as $sisi) {
                if (isset($data['valueRange'][$sisi])) {
                    $range[$sisi] = [
                        'value'  => $data['valueRange'][$sisi]['value'],
                        'unit'   => $data['valueRange'][$sisi]['unit'],
                        'system' => 'http://unitsofmeasure.org',
                        'code'   => $data['valueRange'][$sisi]['code'],
                    ];
                }
            }
            if ($range !== []) {
                $payload['valueRange'] = $range;
            }
        }
        // ...or multiple components
        elseif (!empty($data['components']) && is_array($data['components'])) {
            $payload['component'] = $data['components'];
        }
        // dd($payload);
        return $this->makeRequest('post', '/Observation', $payload);
    }


    /**
     * Cari semua Observation untuk sebuah encounter.
     *
     * @param string $encounterId
     * @return array
     * @throws \Exception on HTTP error
     */
    public function searchObservationsByEncounter(string $encounterId): array
    {
        // Inisialisasi request ke endpoint Observation dengan filter encounter
        return $this->makeRequest('get', "/Observation?encounter={$encounterId}");
    }
}
