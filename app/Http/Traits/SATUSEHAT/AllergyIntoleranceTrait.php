<?php

namespace App\Http\Traits\SATUSEHAT;


trait AllergyIntoleranceTrait
{
    use SatuSehatTrait;

    /**
     * Mengirim data riwayat alergi pasien ke SATUSEHAT
     *
     * @param array $data
     * @return array
     */
    public function createAllergyIntolerance(array $data): array
    {
        // validasi wajib
        if (empty($data['patientId'])) {
            throw new \InvalidArgumentException('Patient ID wajib diset.');
        }
        if (empty($data['encounterId'])) {
            throw new \InvalidArgumentException('Encounter ID wajib diset.');
        }
        if (empty($data['code'])) {
            throw new \InvalidArgumentException('SNOMED code alergi wajib diset.');
        }
        if (empty($data['recorderId'])) {
            throw new \InvalidArgumentException('Recorder (Practitioner ID) wajib diset.');
        }

        $payload = [
            "resourceType"       => "AllergyIntolerance",
            "clinicalStatus"     => [
                "coding" => [[
                    "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                    "code"   => "active"
                ]]
            ],
            "verificationStatus" => [
                "coding" => [[
                    "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                    "code"   => "confirmed"
                ]]
            ],
            "code"               => [
                "coding" => [[
                    "system"  => "http://snomed.info/sct",
                    "code"    => $data['code'],
                    "display" => $data['display']
                ]],
                "text"   => $data['display']
            ],
            "patient"            => [
                "reference" => "Patient/{$data['patientId']}"
            ],
            // **wajib**: encounter reference
            "encounter"          => [
                "reference" => "Encounter/{$data['encounterId']}"
            ],
            // **wajib**: who recorded
            "recorder"           => [
                "reference" => "Practitioner/{$data['recorderId']}"
            ],
            "onsetDateTime"      => $data['onset']   ?? now()->toIso8601String(),
            "note"               => [["text" => $data['note']  ?? '']],
        ];

        // type/category/criticality SENGAJA opsional — kirim null utk MENGHILANGKANNYA.
        // Wajib dihilangkan untuk pernyataan "no known allergy" (mis. SNOMED 716186003):
        // ketiganya atribut alergi yang ADA. category='medication' bersama 716186003 malah
        // kontradiktif — "tidak ada alergi obat" punya kode sendiri (409137002). Pola ini
        // mengikuti contoh "no known allergies" (code + clinicalStatus + verificationStatus
        // saja, tanpa category/criticality/reaction).
        // Dulu ketiganya di-HARDCODE ('allergy'/'medication'/'low') sehingga setiap kiriman
        // "tidak ada alergi" membawa klaim kategori yang tak pernah dibuat siapa pun.
        if (array_key_exists('type', $data) ? $data['type'] !== null : true) {
            $payload['type'] = $data['type'] ?? 'allergy';
        }
        if (array_key_exists('category', $data) ? $data['category'] !== null : true) {
            $payload['category'] = [$data['category'] ?? 'medication'];
        }
        if (array_key_exists('criticality', $data) ? $data['criticality'] !== null : true) {
            $payload['criticality'] = $data['criticality'] ?? 'low';
        }

        return $this->makeRequest('post', '/AllergyIntolerance', $payload);
    }

    public function fetchAllergyIntoleranceByPatient(string $patientId): array
    {
        $this->initializeSatuSehat();

        // Gunakan makeRequest untuk GET dengan query patient
        $endpoint = "AllergyIntolerance?patient=Patient/{$patientId}";
        return $this->makeRequest('get', $endpoint);
    }
}
