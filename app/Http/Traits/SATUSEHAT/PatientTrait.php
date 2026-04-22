<?php
// app/Http/Traits/SATUSEHAT/PatientTrait.php

namespace App\Http\Traits\SATUSEHAT;

trait PatientTrait
{
    use SatuSehatTrait;

    /**
     * Mendaftarkan pasien baru
     */
    public function createPatient(array $data): array
    {
        $payload = $this->buildPatientPayload($data);
        return $this->makeRequest('post', '/Patient', $payload);
    }

    /**
     * Update data pasien
     */
    public function updatePatient(string $patientId, array $data): array
    {
        $payload = $this->buildPatientPayload($data, $patientId);
        return $this->makeRequest('put', "/Patient/{$patientId}", $payload);
    }

    /**
     * Bangun payload Patient untuk Create atau Update
     *
     * @param  array        $data
     * @param  string|null  $id    null untuk create, string untuk update
     * @return array
     */
    private function buildPatientPayload(array $data, ?string $id = null): array
    {
        // Normalize address: FHIR Patient.address = array of address objects.
        // Toleran kalau caller kirim single assoc array — kita wrap jadi list.
        $address = $data['address'] ?? [];
        if (!empty($address) && !array_is_list($address)) {
            $address = [$address];
        }

        // Identifier NIK & BPJS — SATUSEHAT strict validator, skip yang format salah.
        $identifier = [];
        $nik = trim((string) ($data['nik'] ?? ''));
        if ($nik !== '' && strlen($nik) === 16 && ctype_digit($nik)) {
            $identifier[] = [
                'use'    => 'official',
                'system' => 'https://fhir.kemkes.go.id/id/nik',
                'value'  => $nik,
            ];
        }
        $bpjs = trim((string) ($data['bpjs_number'] ?? ''));
        if ($bpjs !== '' && strlen($bpjs) === 13 && ctype_digit($bpjs)) {
            $identifier[] = [
                'use'    => 'official',
                'system' => 'https://fhir.kemkes.go.id/id/bpjs',
                'value'  => $bpjs,
            ];
        }

        $maritalCode = $data['marital_status'] ?? 'U';
        $maritalDisplay = $this->getMaritalStatusDisplay($maritalCode);

        // Struktur sesuai referensi SATUSEHAT /Patient (FHIR R4 Indonesia Core IG).
        $payload = [
            'resourceType' => 'Patient',
            'meta'         => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Patient'],
            ],
            // id hanya untuk update (PUT /Patient/{id}); create tidak kirim id.
            'id'           => $id,
            'identifier'   => $identifier,
            'active'       => true,
            'name'         => [[
                'use'  => 'official',
                'text' => $data['name'] ?? '',
            ]],
            'telecom'      => [[
                'system' => 'phone',
                'value'  => $data['phone'] ?? '',
                'use'    => 'mobile',
            ]],
            'gender'       => $data['gender']     ?? 'unknown',
            'birthDate'    => $data['birth_date'] ?? null,
            'deceasedBoolean' => (bool) ($data['deceased'] ?? false),
            'address'      => $address,
            'maritalStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v3-MaritalStatus',
                    'code'    => $maritalCode,
                    'display' => $maritalDisplay,
                ]],
                'text' => $maritalDisplay,
            ],
            // FHIR Indonesia Core IG wajib salah satu dari multipleBirthBoolean /
            // multipleBirthInteger. Reference SATUSEHAT pakai Integer (0 = single).
            'multipleBirthInteger' => (int) ($data['multiple_birth'] ?? 0),
            'communication' => [[
                'language' => [
                    'coding' => [[
                        'system'  => 'urn:ietf:bcp:47',
                        'code'    => 'id-ID',
                        'display' => 'Indonesian',
                    ]],
                    'text' => 'Indonesian',
                ],
                'preferred' => true,
            ]],
        ];

        return $payload;
    }

    private function getMaritalStatusDisplay(string $code): string
    {
        $map = [
            'A' => 'Annulled',
            'D' => 'Divorced',
            'I' => 'Interlocutory',
            'L' => 'Legally Separated',
            'M' => 'Married',
            'P' => 'Polygamous',
            'S' => 'Never Married',
            'T' => 'Domestic Partner',
            'U' => 'Unmarried',
            'W' => 'Widowed',
        ];
        return $map[$code] ?? 'Unknown';
    }

    public function searchPatient(array $searchCriteria): array
    {
        // Daftar filter yang diperbolehkan
        $allowedFilters   = ['identifier', 'name', 'birthdate', 'gender', 'nik', 'bpjs', 'mother_nik'];
        $queryParameters  = [];

        foreach ($searchCriteria as $filterKey => $filterValue) {
            // Lewati jika bukan filter yang valid atau nilainya kosong/null
            if (
                ! in_array($filterKey, $allowedFilters, true)
                || $filterValue === null
                || $filterValue === ''
            ) {
                continue;
            }

            // Mapping khusus untuk NIK pasien / BPJS
            if ($filterKey === 'nik' || $filterKey === 'bpjs') {
                $identifierSystem = $filterKey === 'nik'
                    ? 'https://fhir.kemkes.go.id/id/nik'
                    : 'https://fhir.kemkes.go.id/id/bpjs';
                $queryParameters['identifier'] = "{$identifierSystem}|{$filterValue}";
            }
            // Mapping untuk NIK ibu (bayi baru lahir)
            elseif ($filterKey === 'mother_nik') {
                $identifierSystem = 'https://fhir.kemkes.go.id/id/nik-ibu';
                $queryParameters['identifier'] = "{$identifierSystem}|{$filterValue}";
            }
            // Filter lain langsung jadi query parameter
            else {
                $queryParameters[$filterKey] = $filterValue;
            }
        }

        // Bangun query string
        $queryString = http_build_query(
            $queryParameters,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        return $this->makeRequest('get', '/Patient?' . $queryString);
    }
}
