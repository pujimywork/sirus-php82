<?php

namespace App\Http\Traits\SATUSEHAT;

/**
 * FHIR MedicationAdministration (SATUSEHAT) — obat/cairan yang BENAR-BENAR DIBERIKAN
 * ke pasien (beda dari MedicationRequest = diresepkan, MedicationDispense = diserahkan).
 *
 * Wajib menurut profil SATUSEHAT: status (1..1), medication[x] (1..1), subject (1..1),
 * effective[x] (1..1). Opsional: context (Encounter), performer, dosage.
 *
 * PENTING — `dosage` punya constraint FHIR **mad-1**: kalau `dosage` disertakan, ia WAJIB
 * punya `dose` atau `rate`. Jadi bila dosis tak bisa diurai jadi Quantity yang sah,
 * `dosage` harus DIHILANGKAN seluruhnya (termasuk route) — bukan dikirim setengah.
 *
 * Pola contained Medication + KFA + extension MedicationType meniru MedicationDispenseTrait.
 */
trait MedicationAdministrationTrait
{
    use SatuSehatTrait;

    /**
     * @param array $data
     *   - medContainedId    (string)  required — id contained Medication (mis. "medadm-1")
     *   - orgId             (string)  required
     *   - medicationCode    (string)  required — KFA
     *   - medicationDisplay (string)  required
     *   - patientId         (string)  required
     *   - patientName       (string)  optional
     *   - encounterId       (string)  optional → context
     *   - effectiveDate     (string)  required — ISO8601
     *   - performerId       (string)  optional — Practitioner IHS
     *   - status            (string)  optional — default 'completed'
     *   - dose              (array)   optional — ['value'=>float,'unit'=>string,'code'=>string(UCUM)]
     *   - routeCode         (string)  optional — SNOMED route (butuh dose juga, lihat mad-1)
     *   - routeDisplay      (string)  optional
     *   - dosageText        (string)  optional — teks dosis asli dari EMR
     *   - note             (string)  optional
     */
    public function createMedicationAdministration(array $data): array
    {
        $med = [
            'resourceType' => 'Medication',
            'id'           => $data['medContainedId'],
            'meta'         => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication'],
            ],
            'code' => [
                'coding' => [[
                    'system'  => 'http://sys-ids.kemkes.go.id/kfa',
                    'code'    => $data['medicationCode'],
                    'display' => $data['medicationDisplay'],
                ]],
            ],
            'status' => 'active',
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                'valueCodeableConcept' => [
                    'coding' => [[
                        'system'  => 'http://terminology.kemkes.go.id/CodeSystem/medication-type',
                        'code'    => 'NC',
                        'display' => 'Non-compound',
                    ]],
                ],
            ]],
        ];

        if (!empty($data['orgId'])) {
            $med['manufacturer'] = ['reference' => 'Organization/' . $data['orgId']];
        }

        $payload = [
            'resourceType' => 'MedicationAdministration',
            'contained'    => [$med],
            'status'       => $data['status'] ?? 'completed',
            'medicationReference' => ['reference' => '#' . $data['medContainedId']],
            'subject' => array_filter([
                'reference' => 'Patient/' . $data['patientId'],
                'display'   => $data['patientName'] ?? null,
            ]),
            'effectiveDateTime' => $data['effectiveDate'],
        ];

        if (!empty($data['encounterId'])) {
            $payload['context'] = ['reference' => 'Encounter/' . $data['encounterId']];
        }

        if (!empty($data['performerId'])) {
            $payload['performer'] = [[
                'actor' => ['reference' => 'Practitioner/' . $data['performerId']],
            ]];
        }

        // mad-1: dosage hanya boleh dikirim bila ADA dose. Tanpa dose → seluruh dosage
        // (termasuk route) dibuang, supaya payload tetap valid.
        if (!empty($data['dose']) && is_array($data['dose']) && isset($data['dose']['value'])) {
            $dosage = [
                'dose' => array_filter([
                    'value'  => $data['dose']['value'],
                    'unit'   => $data['dose']['unit'] ?? null,
                    'system' => !empty($data['dose']['code']) ? 'http://unitsofmeasure.org' : null,
                    'code'   => $data['dose']['code'] ?? null,
                ], fn ($v) => $v !== null),
            ];

            if (!empty($data['dosageText'])) {
                $dosage['text'] = $data['dosageText'];
            }

            if (!empty($data['routeCode'])) {
                $dosage['route'] = [
                    'coding' => [[
                        'system'  => 'http://snomed.info/sct',
                        'code'    => $data['routeCode'],
                        'display' => $data['routeDisplay'] ?? '',
                    ]],
                ];
            }

            $payload['dosage'] = $dosage;
        }

        if (!empty($data['note'])) {
            $payload['note'] = [['text' => $data['note']]];
        }

        return $this->makeRequest('post', '/MedicationAdministration', $payload);
    }
}
