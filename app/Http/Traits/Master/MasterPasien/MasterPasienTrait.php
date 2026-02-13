<?php

namespace App\Http\Traits\Master\MasterPasien;

use Illuminate\Support\Facades\DB;
use Throwable;

trait MasterPasienTrait
{
    /**
     * Find master patient data with cache-first logic
     * - If meta_data_pasien_json exists & valid: use it directly
     * - If null/invalid: fallback to database query (once)
     * - Validate reg_no: if not found or mismatched, return error
     */
    protected function findDataMasterPasien(string $regNo): array
    {

        try {
            // 1. Check if JSON exists (cache-first pattern)
            $row = DB::table('rsmst_pasiens')
                ->select('meta_data_pasien_json')
                ->where('reg_no', $regNo)
                ->first();

            if (!$row) {
                return $this->buildDefaultData($regNo, "Pasien tidak ditemukan untuk reg_no: {$regNo}");
            }

            $json = $row->meta_data_pasien_json ?? null;

            // 2. If JSON exists & valid, return immediately
            if ($json && $this->isValidJson($json, $regNo)) {
                return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            }

            // 3. If JSON doesn't exist/invalid, build from database
            return $this->buildDataFromDatabase($regNo);
        } catch (Throwable $e) {
            return $this->buildDefaultData($regNo, $e->getMessage());
        }
    }

    /**
     * Build data from database (only called if JSON is missing)
     */
    private function buildDataFromDatabase(string $regNo): array
    {
        // Start with default template
        $dataPasien = $this->getDefaultPasienTemplate();
        $dataPasien['pasien']['regNo'] = $regNo;

        // Query from database with JOINs
        $findData = DB::table('rsmst_pasiens')
            ->select(
                DB::raw("to_char(reg_date,'dd/mm/yyyy hh24:mi:ss') as reg_date"),
                DB::raw("to_char(reg_date,'yyyymmddhh24miss') as reg_date1"),
                'reg_no',
                'reg_name',
                DB::raw("nvl(nokartu_bpjs,'-') as nokartu_bpjs"),
                DB::raw("nvl(nik_bpjs,'-') as nik_bpjs"),
                'sex',
                DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"),
                DB::raw("(select trunc( months_between( sysdate, birth_date ) /12 ) from dual) as thn"),
                'bln',
                'hari',
                'birth_place',
                'blood',
                'marital_status',
                'rsmst_religions.rel_id as rel_id',
                'rel_desc',
                'rsmst_educations.edu_id as edu_id',
                'edu_desc',
                'rsmst_jobs.job_id as job_id',
                'job_name',
                'kk',
                'nyonya',
                'no_kk',
                'address',
                'rsmst_desas.des_id as des_id',
                'des_name',
                'rt',
                'rw',
                'rsmst_kecamatans.kec_id as kec_id',
                'kec_name',
                'rsmst_kabupatens.kab_id as kab_id',
                'kab_name',
                'rsmst_propinsis.prop_id as prop_id',
                'prop_name',
                'phone'
            )
            ->join('rsmst_religions', 'rsmst_religions.rel_id', '=', 'rsmst_pasiens.rel_id')
            ->join('rsmst_educations', 'rsmst_educations.edu_id', '=', 'rsmst_pasiens.edu_id')
            ->join('rsmst_jobs', 'rsmst_jobs.job_id', '=', 'rsmst_pasiens.job_id')
            ->join('rsmst_desas', 'rsmst_desas.des_id', '=', 'rsmst_pasiens.des_id')
            ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', '=', 'rsmst_pasiens.kec_id')
            ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', '=', 'rsmst_pasiens.kab_id')
            ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', '=', 'rsmst_pasiens.prop_id')
            ->where('reg_no', $regNo)
            ->first();

        if (!$findData) {
            return $this->buildDefaultData($regNo, "Data detail pasien tidak ditemukan di database");
        }

        // Populate data from database
        $this->populateFromDatabase($dataPasien, $findData);

        // Auto-save to JSON for next requests
        $this->autoSaveToJson($regNo, $dataPasien);

        return $dataPasien;
    }

    /**
     * Populate data from database query result
     */
    private function populateFromDatabase(array &$dataPasien, object $findData): void
    {
        // Basic patient info
        $dataPasien['pasien']['regDate'] = $findData->reg_date ?? '';
        $dataPasien['pasien']['regName'] = $findData->reg_name ?? '';

        // Identity information
        $dataPasien['pasien']['identitas']['idbpjs'] = $findData->nokartu_bpjs ?? '-';
        $dataPasien['pasien']['identitas']['nik'] = $findData->nik_bpjs ?? '-';
        $dataPasien['pasien']['identitas']['alamat'] = $findData->address ?? '';

        $dataPasien['pasien']['identitas']['desaId'] = $findData->des_id ?? '';
        $dataPasien['pasien']['identitas']['desaName'] = $findData->des_name ?? '';
        $dataPasien['pasien']['identitas']['rt'] = $findData->rt ?? '';
        $dataPasien['pasien']['identitas']['rw'] = $findData->rw ?? '';

        $dataPasien['pasien']['identitas']['kecamatanId'] = $findData->kec_id ?? '';
        $dataPasien['pasien']['identitas']['kecamatanName'] = $findData->kec_name ?? '';

        $dataPasien['pasien']['identitas']['kotaId'] = $findData->kab_id ?? '';
        $dataPasien['pasien']['identitas']['kotaName'] = $findData->kab_name ?? '';

        $dataPasien['pasien']['identitas']['propinsiId'] = $findData->prop_id ?? '';
        $dataPasien['pasien']['identitas']['propinsiName'] = $findData->prop_name ?? '';

        // Gender
        $isMale = (($findData->sex ?? '') === 'L');
        $dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] = $isMale ? 1 : 2;
        $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] = $isMale ? 'Laki-laki' : 'Perempuan';

        // Birth data
        $dataPasien['pasien']['tglLahir'] = $findData->birth_date ?? '';
        $dataPasien['pasien']['thn'] = $findData->thn ?? '';
        $dataPasien['pasien']['bln'] = $findData->bln ?? '';
        $dataPasien['pasien']['hari'] = $findData->hari ?? '';
        $dataPasien['pasien']['tempatLahir'] = $findData->birth_place ?? '';

        // Religion, education, occupation
        $dataPasien['pasien']['agama']['agamaId'] = $findData->rel_id ?? '1';
        $dataPasien['pasien']['agama']['agamaDesc'] = $findData->rel_desc ?? 'Islam';

        $dataPasien['pasien']['pendidikan']['pendidikanId'] = $findData->edu_id ?? '3';
        $dataPasien['pasien']['pendidikan']['pendidikanDesc'] = $findData->edu_desc ?? 'SLTA Sederajat';

        $dataPasien['pasien']['pekerjaan']['pekerjaanId'] = $findData->job_id ?? '4';
        $dataPasien['pasien']['pekerjaan']['pekerjaanDesc'] = $findData->job_name ?? 'Pegawai Swasta/ Wiraswasta';

        // Contact
        $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] = $findData->phone ?? '';

        // Family relations
        $dataPasien['pasien']['hubungan']['namaPenanggungJawab'] = $findData->kk ?? '';
        $dataPasien['pasien']['hubungan']['namaIbu'] = $findData->nyonya ?? '';

        // Map additional fields if they exist
        $this->mapAdditionalFields($dataPasien, $findData);
    }

    /**
     * Validate JSON structure and reg_no
     */
    private function isValidJson(?string $json, string $expectedRegNo): bool
    {
        if (!$json || trim($json) === '') {
            return false;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            // Check if it's an array and has 'pasien' key
            if (!is_array($decoded) || !isset($decoded['pasien'])) {
                return false;
            }

            // Validate reg_no matches
            return isset($decoded['pasien']['regNo']) &&
                $decoded['pasien']['regNo'] === $expectedRegNo;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Auto-save to JSON (optimization for next requests)
     */
    private function autoSaveToJson(string $regNo, array $data): void
    {
        try {
            DB::table('rsmst_pasiens')
                ->where('reg_no', $regNo)
                ->update([
                    'meta_data_pasien_json' => json_encode(
                        $data,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ]);
        } catch (Throwable $e) {
            // Silent fail - auto-save is not critical
        }
    }

    /**
     * Build default data with error message
     */
    private function buildDefaultData(string $regNo, string $errorMessage = ''): array
    {
        $dataPasien = $this->getDefaultPasienTemplate();
        $dataPasien['pasien']['regNo'] = $regNo;
        $dataPasien['pasien']['regName'] = 'DATA TIDAK DITEMUKAN';
        $dataPasien['pasien']['regDate'] = date('d/m/Y H:i:s');

        if ($errorMessage) {
            $dataPasien['metadata'] = [
                'error' => $errorMessage,
                'source' => 'default_template',
                'timestamp' => date('c')
            ];
        }

        return $dataPasien;
    }

    /**
     * Get default patient template
     */
    private function getDefaultPasienTemplate(): array
    {
        return [
            "pasien" => [
                "pasientidakdikenal" => [],
                "regNo" => "",
                "gelarDepan" => "",
                "regName" => "",
                "gelarBelakang" => "",
                "namaPanggilan" => "",
                "tempatLahir" => "",
                "tglLahir" => "",
                "thn" => "",
                "bln" => "",
                "hari" => "",
                "jenisKelamin" => [
                    "jenisKelaminId" => 1,
                    "jenisKelaminDesc" => "Laki-laki",
                    "jenisKelaminOptions" => [
                        ["jenisKelaminId" => 0, "jenisKelaminDesc" => "Tidak diketaui"],
                        ["jenisKelaminId" => 1, "jenisKelaminDesc" => "Laki-laki"],
                        ["jenisKelaminId" => 2, "jenisKelaminDesc" => "Perempuan"],
                        ["jenisKelaminId" => 3, "jenisKelaminDesc" => "Tidak dapat di tentukan"],
                        ["jenisKelaminId" => 4, "jenisKelaminDesc" => "Tidak Mengisi"],
                    ],
                ],
                "agama" => [
                    "agamaId" => "1",
                    "agamaDesc" => "Islam",
                    "agamaOptions" => [
                        ["agamaId" => 1, "agamaDesc" => "Islam"],
                        ["agamaId" => 2, "agamaDesc" => "Kristen (Protestan)"],
                        ["agamaId" => 3, "agamaDesc" => "Katolik"],
                        ["agamaId" => 4, "agamaDesc" => "Hindu"],
                        ["agamaId" => 5, "agamaDesc" => "Budha"],
                        ["agamaId" => 6, "agamaDesc" => "Konghucu"],
                        ["agamaId" => 7, "agamaDesc" => "Penghayat"],
                        ["agamaId" => 8, "agamaDesc" => "Lain-lain"],
                    ],
                ],
                "statusPerkawinan" => [
                    "statusPerkawinanId" => "1",
                    "statusPerkawinanDesc" => "Belum Kawin",
                    "statusPerkawinanOptions" => [
                        ["statusPerkawinanId" => 1, "statusPerkawinanDesc" => "Belum Kawin"],
                        ["statusPerkawinanId" => 2, "statusPerkawinanDesc" => "Kawin"],
                        ["statusPerkawinanId" => 3, "statusPerkawinanDesc" => "Cerai Hidup"],
                        ["statusPerkawinanId" => 4, "statusPerkawinanDesc" => "Cerai Mati"],
                    ],
                ],
                "pendidikan" => [
                    "pendidikanId" => "3",
                    "pendidikanDesc" => "SLTA Sederajat",
                    "pendidikanOptions" => [
                        ["pendidikanId" => 0, "pendidikanDesc" => "Tidak Sekolah"],
                        ["pendidikanId" => 1, "pendidikanDesc" => "SD"],
                        ["pendidikanId" => 2, "pendidikanDesc" => "SLTP Sederajat"],
                        ["pendidikanId" => 3, "pendidikanDesc" => "SLTA Sederajat"],
                        ["pendidikanId" => 4, "pendidikanDesc" => "D1-D3"],
                        ["pendidikanId" => 5, "pendidikanDesc" => "D4"],
                        ["pendidikanId" => 6, "pendidikanDesc" => "S1"],
                        ["pendidikanId" => 7, "pendidikanDesc" => "S2"],
                        ["pendidikanId" => 8, "pendidikanDesc" => "S3"],
                    ],
                ],
                "pekerjaan" => [
                    "pekerjaanId" => "4",
                    "pekerjaanDesc" => "Pegawai Swasta/ Wiraswasta",
                    "pekerjaanOptions" => [
                        ["pekerjaanId" => 0, "pekerjaanDesc" => "Tidak Bekerja"],
                        ["pekerjaanId" => 1, "pekerjaanDesc" => "PNS"],
                        ["pekerjaanId" => 2, "pekerjaanDesc" => "TNI/POLRI"],
                        ["pekerjaanId" => 3, "pekerjaanDesc" => "BUMN"],
                        ["pekerjaanId" => 4, "pekerjaanDesc" => "Pegawai Swasta/ Wiraswasta"],
                        ["pekerjaanId" => 5, "pekerjaanDesc" => "Lain-Lain"],
                    ],
                ],
                "golonganDarah" => [
                    "golonganDarahId" => "13",
                    "golonganDarahDesc" => "Tidak Tahu",
                    "golonganDarahOptions" => [
                        ["golonganDarahId" => 1, "golonganDarahDesc" => "A"],
                        ["golonganDarahId" => 2, "golonganDarahDesc" => "B"],
                        ["golonganDarahId" => 3, "golonganDarahDesc" => "AB"],
                        ["golonganDarahId" => 4, "golonganDarahDesc" => "O"],
                        ["golonganDarahId" => 5, "golonganDarahDesc" => "A+"],
                        ["golonganDarahId" => 6, "golonganDarahDesc" => "A-"],
                        ["golonganDarahId" => 7, "golonganDarahDesc" => "B+"],
                        ["golonganDarahId" => 8, "golonganDarahDesc" => "B-"],
                        ["golonganDarahId" => 9, "golonganDarahDesc" => "AB+"],
                        ["golonganDarahId" => 10, "golonganDarahDesc" => "AB-"],
                        ["golonganDarahId" => 11, "golonganDarahDesc" => "O+"],
                        ["golonganDarahId" => 12, "golonganDarahDesc" => "O-"],
                        ["golonganDarahId" => 13, "golonganDarahDesc" => "Tidak Tahu"],
                        ["golonganDarahId" => 14, "golonganDarahDesc" => "O Rhesus"],
                        ["golonganDarahId" => 15, "golonganDarahDesc" => "#"],
                    ],
                ],
                "kewarganegaraan" => 'INDONESIA',
                "suku" => 'Jawa',
                "bahasa" => 'Indonesia / Jawa',
                "status" => [
                    "statusId" => "1",
                    "statusDesc" => "Aktif / Hidup",
                    "statusOptions" => [
                        ["statusId" => 0, "statusDesc" => "Tidak Aktif / Batal"],
                        ["statusId" => 1, "statusDesc" => "Aktif / Hidup"],
                        ["statusId" => 2, "statusDesc" => "Meninggal"],
                    ]
                ],
                "domisil" => [
                    "samadgnidentitas" => [],
                    "alamat" => "",
                    "rt" => "",
                    "rw" => "",
                    "kodepos" => "",
                    "desaId" => "",
                    "kecamatanId" => "",
                    "kotaId" => "3504",
                    "propinsiId" => "35",
                    "desaName" => "",
                    "kecamatanName" => "",
                    "kotaName" => "TULUNGAGUNG",
                    "propinsiName" => "JAWA TIMUR",
                    "negara" => "ID"
                ],
                "identitas" => [
                    "nik" => "",
                    "idbpjs" => "",
                    "patientUuid" => "",
                    "pasport" => "",
                    "alamat" => "",
                    "rt" => "",
                    "rw" => "",
                    "kodepos" => "",
                    "desaId" => "",
                    "kecamatanId" => "",
                    "kotaId" => "3504",
                    "propinsiId" => "35",
                    "desaName" => "",
                    "kecamatanName" => "",
                    "kotaName" => "TULUNGAGUNG",
                    "propinsiName" => "JAWA TIMUR",
                    "negara" => "ID"
                ],
                "kontak" => [
                    "kodenegara" => "62",
                    "nomerTelponSelulerPasien" => "",
                    "nomerTelponLain" => ""
                ],
                "hubungan" => [
                    "namaAyah" => "",
                    "kodenegaraAyah" => "62",
                    "nomerTelponSelulerAyah" => "",
                    "namaIbu" => "",
                    "kodenegaraIbu" => "62",
                    "nomerTelponSelulerIbu" => "",
                    "namaPenanggungJawab" => "",
                    "kodenegaraPenanggungJawab" => "62",
                    "nomerTelponSelulerPenanggungJawab" => "",
                    "hubunganDgnPasien" => [
                        "hubunganDgnPasienId" => 5,
                        "hubunganDgnPasienDesc" => "Kerabat / Saudara",
                        "hubunganDgnPasienOptions" => [
                            ["hubunganDgnPasienId" => 1, "hubunganDgnPasienDesc" => "Diri Sendiri"],
                            ["hubunganDgnPasienId" => 2, "hubunganDgnPasienDesc" => "Orang Tua"],
                            ["hubunganDgnPasienId" => 3, "hubunganDgnPasienDesc" => "Anak"],
                            ["hubunganDgnPasienId" => 4, "hubunganDgnPasienDesc" => "Suami / Istri"],
                            ["hubunganDgnPasienId" => 5, "hubunganDgnPasienDesc" => "Kerabaat / Saudara"],
                            ["hubunganDgnPasienId" => 6, "hubunganDgnPasienDesc" => "Lain-lain"]
                        ]
                    ]
                ],
            ]
        ];
    }

    /**
     * Map additional fields (blood type, marital status)
     */
    private function mapAdditionalFields(array &$dataPasien, object $findData): void
    {
        // Map blood type if exists
        if (isset($findData->blood) && $findData->blood) {
            $bloodMap = [
                'A' => 1,
                'B' => 2,
                'AB' => 3,
                'O' => 4,
                'A+' => 5,
                'A-' => 6,
                'B+' => 7,
                'B-' => 8,
                'AB+' => 9,
                'AB-' => 10,
                'O+' => 11,
                'O-' => 12,
                'O Rhesus' => 14,
                '#' => 15
            ];

            if (isset($bloodMap[$findData->blood])) {
                $dataPasien['pasien']['golonganDarah']['golonganDarahId'] = (string)$bloodMap[$findData->blood];
                $dataPasien['pasien']['golonganDarah']['golonganDarahDesc'] = $findData->blood;
            }
        }

        // Map marital status if exists
        if (isset($findData->marital_status) && $findData->marital_status) {
            $maritalMap = [
                'S' => 1, // Single/Belum Kawin
                'M' => 2, // Married/Kawin
                'D' => 3, // Divorced/Cerai Hidup
                'W' => 4, // Widowed/Cerai Mati
            ];

            if (isset($maritalMap[$findData->marital_status])) {
                $dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] = (string)$maritalMap[$findData->marital_status];
                $dataPasien['pasien']['statusPerkawinan']['statusPerkawinanDesc'] =
                    $this->getMaritalDescription($maritalMap[$findData->marital_status]);
            }
        }
    }

    private function getMaritalDescription(int $id): string
    {
        $descriptions = [
            1 => 'Belum Kawin',
            2 => 'Kawin',
            3 => 'Cerai Hidup',
            4 => 'Cerai Mati',
        ];

        return $descriptions[$id] ?? 'Belum Kawin';
    }

    /**
     * Update JSON master patient with validation
     */
    public static function updateJsonMasterPasien(string $regNo, array $payload): void
    {
        DB::transaction(function () use ($regNo, $payload) {
            // Validate payload has correct regNo
            if (!isset($payload['pasien']['regNo']) || $payload['pasien']['regNo'] !== $regNo) {
                throw new \RuntimeException("regNo dalam payload tidak sesuai dengan parameter");
            }

            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            DB::table('rsmst_pasiens')
                ->where('reg_no', $regNo)
                ->update([
                    'meta_data_pasien_json' => $json,
                ]);
        }, 3);
    }

    /**
     * Check if patient has JSON data
     */
    protected function checkMasterPasienStatus(string $regNo): bool
    {
        return DB::table('rsmst_pasiens')
            ->where('reg_no', $regNo)
            ->whereNotNull('meta_data_pasien_json')
            ->exists();
    }

    /**
     * Enhanced method with strict validation
     */
    protected function findDataMasterPasienStrict(string $regNo): array
    {
        // Validate reg_no format
        if (empty($regNo) || !$this->validateRegNo($regNo)) {
            return $this->buildDefaultData($regNo, "Format reg_no tidak valid");
        }

        $data = $this->findDataMasterPasien($regNo);

        // Additional validation for returned data
        if (isset($data['pasien']['regNo']) && $data['pasien']['regNo'] !== $regNo) {
            // Force correction
            $data['pasien']['regNo'] = $regNo;
            $data['metadata']['corrected_regNo'] = true;
        }

        return $data;
    }

    /**
     * Validate reg_no format
     */
    private function validateRegNo(string $regNo): bool
    {
        // Basic validation - adjust as needed
        return !empty($regNo) && strlen($regNo) <= 50;
    }
}
