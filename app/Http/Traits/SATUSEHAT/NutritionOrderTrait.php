<?php

namespace App\Http\Traits\SATUSEHAT;

trait NutritionOrderTrait
{
    use SatuSehatTrait;

    /**
     * Kirim Instruksi Gizi / order diet ke SATUSEHAT (NutritionOrder).
     *
     * @param array $data
     *   - patientId    (string) IHS pasien           [wajib]
     *   - encounterId  (string) IHS encounter         [wajib]
     *   - ordererId    (string) IHS Practitioner      [wajib]
     *   - dietText     (string) teks diet             [wajib]
     *   - dietCode     (string) kode SNOMED diet      (opsional — coding dilewati bila kosong)
     *   - dietDisplay  (string) display kode          (opsional)
     *   - instruction  (string) instruksi tambahan    (opsional)
     *   - dateTime     (string) ISO8601               (opsional, default now)
     *   - status       (string)                       (opsional, default 'active')
     *
     * Catatan: bila `dietCode` kosong, oralDiet.type dikirim TEXT-ONLY (tanpa coding SNOMED).
     * FHIR mengizinkan CodeableConcept hanya text; upgrade ke coding saat master diet berkode ada.
     *
     * @return array
     */
    public function createNutritionOrder(array $data): array
    {
        foreach (['patientId', 'encounterId', 'ordererId', 'dietText'] as $req) {
            if (empty($data[$req])) {
                throw new \InvalidArgumentException("Field '{$req}' wajib diset untuk NutritionOrder.");
            }
        }

        $type = ['text' => $data['dietText']];
        if (!empty($data['dietCode'])) {
            $type['coding'] = [[
                'system'  => 'http://snomed.info/sct',
                'code'    => $data['dietCode'],
                'display' => $data['dietDisplay'] ?? $data['dietText'],
            ]];
        }

        $oralDiet = ['type' => [$type]];
        if (!empty($data['instruction'])) {
            $oralDiet['instruction'] = $data['instruction'];
        }

        $payload = [
            'resourceType' => 'NutritionOrder',
            'status'       => $data['status'] ?? 'active',
            'intent'       => 'order',
            'patient'      => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'    => ['reference' => 'Encounter/' . $data['encounterId']],
            'dateTime'     => $data['dateTime'] ?? now()->toIso8601String(),
            'orderer'      => ['reference' => 'Practitioner/' . $data['ordererId']],
            'oralDiet'     => $oralDiet,
        ];

        return $this->makeRequest('post', '/NutritionOrder', $payload);
    }
}
