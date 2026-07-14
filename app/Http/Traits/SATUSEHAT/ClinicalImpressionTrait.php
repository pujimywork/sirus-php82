<?php

namespace App\Http\Traits\SATUSEHAT;

trait ClinicalImpressionTrait
{
    use SatuSehatTrait;

    /**
     * Kirim Impresi Klinik / asesmen ("A" di SOAP) ke SATUSEHAT.
     *
     * @param array $data
     *   - patientId    (string) IHS pasien           [wajib]
     *   - encounterId  (string) IHS encounter         [wajib]
     *   - assessorId   (string) IHS Practitioner      [wajib]
     *   - summary      (string) teks asesmen klinis   [wajib]
     *   - description  (string) deskripsi singkat     (opsional)
     *   - effective    (string) ISO8601              (opsional, default now)
     *   - findings     (array)  list ['code'=>SNOMED,'display'=>...] (opsional)
     *
     * @return array
     */
    public function createClinicalImpression(array $data): array
    {
        foreach (['patientId', 'encounterId', 'assessorId', 'summary'] as $req) {
            if (empty($data[$req])) {
                throw new \InvalidArgumentException("Field '{$req}' wajib diset untuk ClinicalImpression.");
            }
        }

        $payload = [
            'resourceType'      => 'ClinicalImpression',
            'status'            => $data['status'] ?? 'completed',
            'description'       => $data['description'] ?? null,
            'subject'           => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'effectiveDateTime' => $data['effective'] ?? now()->toIso8601String(),
            'date'              => now()->toIso8601String(),
            'assessor'          => ['reference' => 'Practitioner/' . $data['assessorId']],
            'summary'           => $data['summary'],
        ];

        if (!empty($data['findings']) && is_array($data['findings'])) {
            $payload['finding'] = array_map(function ($f) {
                return [
                    'itemCodeableConcept' => [
                        'coding' => [[
                            'system'  => 'http://snomed.info/sct',
                            'code'    => $f['code'],
                            'display' => $f['display'] ?? '',
                        ]],
                    ],
                ];
            }, $data['findings']);
        }

        return $this->makeRequest('post', '/ClinicalImpression', $payload);
    }
}
