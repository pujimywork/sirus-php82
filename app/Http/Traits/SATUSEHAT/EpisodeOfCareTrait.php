<?php

namespace App\Http\Traits\SATUSEHAT;

trait EpisodeOfCareTrait
{
    use SatuSehatTrait;

    /**
     * Buat EpisodeOfCare (1 rawat inap = 1 episode).
     *
     * @param array $data
     *   - episodeNo     (string) rihdr_no             [wajib]
     *   - patientId     (string) IHS pasien           [wajib]
     *   - careManagerId (string) IHS Practitioner DPJP [wajib]
     *   - start         (string) ISO8601 tgl masuk    (opsional, default now)
     *   - end           (string) ISO8601 tgl pulang   (opsional)
     *   - status        (string) active|finished|cancelled (opsional, default active)
     *   - typeCode/typeDisplay (string) override type coding (opsional)
     */
    public function createEpisodeOfCare(array $data): array
    {
        foreach (['episodeNo', 'patientId', 'careManagerId'] as $req) {
            if (empty($data[$req])) {
                throw new \InvalidArgumentException("Field '{$req}' wajib diset untuk EpisodeOfCare.");
            }
        }

        return $this->makeRequest('post', '/EpisodeOfCare', $this->buildEpisodeOfCarePayload($data));
    }

    /**
     * Update EpisodeOfCare (mis. finish saat pasien pulang: status finished + period.end).
     */
    public function updateEpisodeOfCare(string $id, array $data): array
    {
        $payload = $this->buildEpisodeOfCarePayload($data);
        $payload['id'] = $id;
        return $this->makeRequest('put', "/EpisodeOfCare/{$id}", $payload);
    }

    protected function buildEpisodeOfCarePayload(array $data): array
    {
        return [
            'resourceType' => 'EpisodeOfCare',
            'identifier'   => [[
                'system' => 'http://sys-ids.kemkes.go.id/episodeofcare/' . $this->organizationId,
                'value'  => (string) $data['episodeNo'],
            ]],
            'status' => $data['status'] ?? 'active',
            'type'   => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/episodeofcare-type',
                    'code'    => $data['typeCode'] ?? 'hacc',
                    'display' => $data['typeDisplay'] ?? 'Home and Community Care',
                ]],
            ]],
            'patient'              => ['reference' => 'Patient/' . $data['patientId']],
            'managingOrganization' => ['reference' => 'Organization/' . $this->organizationId],
            'period' => array_filter([
                'start' => $data['start'] ?? now()->toIso8601String(),
                'end'   => $data['end'] ?? null,
            ]),
            'careManager' => ['reference' => 'Practitioner/' . $data['careManagerId']],
        ];
    }
}
