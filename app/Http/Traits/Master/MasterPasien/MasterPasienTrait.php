<?php

namespace App\Http\Traits\Master\MasterPasien;

use Carbon\Carbon;
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
        // 1. Ambil JSON dari DB
        $row = DB::table('rsmst_pasiens')
            ->select([
                'reg_no',
                'reg_name',
                'meta_data_pasien_json',
                DB::raw("to_char(reg_date,'dd/mm/yyyy hh24:mi:ss') as reg_date"),
                DB::raw("to_char(reg_date,'yyyymmddhh24miss') as reg_date1"),
                'nokartu_bpjs',
                'nik_bpjs',
                'sex',
                DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"),
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
            ])
            ->join('rsmst_religions', 'rsmst_religions.rel_id', '=', 'rsmst_pasiens.rel_id')
            ->join('rsmst_educations', 'rsmst_educations.edu_id', '=', 'rsmst_pasiens.edu_id')
            ->join('rsmst_jobs', 'rsmst_jobs.job_id', '=', 'rsmst_pasiens.job_id')
            ->join('rsmst_desas', 'rsmst_desas.des_id', '=', 'rsmst_pasiens.des_id')
            ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', '=', 'rsmst_pasiens.kec_id')
            ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', '=', 'rsmst_pasiens.kab_id')
            ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', '=', 'rsmst_pasiens.prop_id')
            ->where('reg_no', $regNo)
            ->first();

        $json = $row->meta_data_pasien_json ?? null;

        // 2. Jika JSON valid, langsung return
        if ($json && $this->isValidMasterPasienJson($json, $regNo)) {
            $dataPasien = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            // Update dengan data terkini dari database
            $this->populateFromDatabaseMasterPasien($dataPasien, $row);

            return $dataPasien;
        }

        // 3. Jika JSON tidak ada/invalid, build dari DB
        $builtData = $this->getDefaultPasienTemplate();

        if ($row) {
            $this->populateFromDatabaseMasterPasien($builtData, $row);
        }
        // 4. Jika build dari DB gagal (return default), kembalikan default
        return $builtData;
    }

    /**
     * Validate Master Pasien JSON structure and reg_no
     */
    private function isValidMasterPasienJson(?string $json, string $expectedRegNo): bool
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
     * Populate data from database query result
     */
    private function populateFromDatabaseMasterPasien(array &$dataPasien, object $row): void
    {
        // Basic patient info
        $dataPasien['pasien']['regNo'] = $row->reg_no ?? '';
        $dataPasien['pasien']['regName'] = $row->reg_name ?? '';
        $dataPasien['pasien']['regDate'] = $row->reg_date ?? '';

        // Identity information
        $dataPasien['pasien']['identitas']['idbpjs'] = $row->nokartu_bpjs ?? '';
        $dataPasien['pasien']['identitas']['nik'] = $row->nik_bpjs ?? '';
        $dataPasien['pasien']['identitas']['alamat'] = $row->address ?? '';

        $dataPasien['pasien']['identitas']['desaId'] = $row->des_id ?? '';
        $dataPasien['pasien']['identitas']['desaName'] = $row->des_name ?? '';
        $dataPasien['pasien']['identitas']['rt'] = $row->rt ?? '';
        $dataPasien['pasien']['identitas']['rw'] = $row->rw ?? '';

        $dataPasien['pasien']['identitas']['kecamatanId'] = $row->kec_id ?? '';
        $dataPasien['pasien']['identitas']['kecamatanName'] = $row->kec_name ?? '';

        $dataPasien['pasien']['identitas']['kotaId'] = $row->kab_id ?? '';
        $dataPasien['pasien']['identitas']['kotaName'] = $row->kab_name ?? '';

        $dataPasien['pasien']['identitas']['propinsiId'] = $row->prop_id ?? '';
        $dataPasien['pasien']['identitas']['propinsiName'] = $row->prop_name ?? '';

        // Gender
        $isMale = (($row->sex ?? '') === 'L');
        $dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] = $isMale ? 1 : 2;
        $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] = $isMale ? 'Laki-laki' : 'Perempuan';

        // Birth data — umur dihitung on-the-fly dari birth_date.
        // Kolom stored rsmst_pasiens.thn/bln/hari adalah snapshot saat registrasi
        // dan tidak pernah refresh, sehingga selalu basi.
        $dataPasien['pasien']['tglLahir'] = $row->birth_date ?? '';
        [$thn, $bln, $hari] = $this->computeUmurFromBirthDate($row->birth_date ?? null);
        $dataPasien['pasien']['thn'] = $thn;
        $dataPasien['pasien']['bln'] = $bln;
        $dataPasien['pasien']['hari'] = $hari;
        $dataPasien['pasien']['tempatLahir'] = $row->birth_place ?? '';

        // Religion, education, occupation
        $dataPasien['pasien']['agama']['agamaId'] = $row->rel_id ?? '1';
        $dataPasien['pasien']['agama']['agamaDesc'] = $row->rel_desc ?? 'Islam';

        $dataPasien['pasien']['pendidikan']['pendidikanId'] = $row->edu_id ?? '3';
        $dataPasien['pasien']['pendidikan']['pendidikanDesc'] = $row->edu_desc ?? 'SLTA Sederajat';

        $dataPasien['pasien']['pekerjaan']['pekerjaanId'] = $row->job_id ?? '4';
        $dataPasien['pasien']['pekerjaan']['pekerjaanDesc'] = $row->job_name ?? 'Pegawai Swasta/ Wiraswasta';

        // Contact
        $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] = $row->phone ?? '';

        // Family relations
        $dataPasien['pasien']['hubungan']['namaPenanggungJawab'] = $row->kk ?? '';
        $dataPasien['pasien']['hubungan']['namaIbu'] = $row->nyonya ?? '';

        // Map additional fields if they exist
        $this->mapAdditionalFields($dataPasien, $row);
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
     * Hitung umur (tahun, bulan, hari) dari tgl lahir format dd/mm/yyyy.
     */
    protected function computeUmurFromBirthDate(?string $birthDate): array
    {
        if (empty($birthDate)) {
            return [0, 0, 0];
        }

        try {
            $diff = Carbon::createFromFormat('d/m/Y', $birthDate)->diff(Carbon::now());
            return [(int) $diff->y, (int) $diff->m, (int) $diff->d];
        } catch (Throwable $e) {
            return [0, 0, 0];
        }
    }

    /**
     * Map additional fields (blood type, marital status)
     */
    private function mapAdditionalFields(array &$dataPasien, object $row): void
    {
        // Map blood type if exists
        if (isset($row->blood) && $row->blood) {
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

            if (isset($bloodMap[$row->blood])) {
                $dataPasien['pasien']['golonganDarah']['golonganDarahId'] = (string)$bloodMap[$row->blood];
                $dataPasien['pasien']['golonganDarah']['golonganDarahDesc'] = $row->blood;
            }
        }

        // Map marital status if exists
        if (isset($row->marital_status) && $row->marital_status) {
            $maritalMap = [
                'S' => 1, // Single/Belum Kawin
                'M' => 2, // Married/Kawin
                'D' => 3, // Divorced/Cerai Hidup
                'W' => 4, // Widowed/Cerai Mati
            ];

            if (isset($maritalMap[$row->marital_status])) {
                $dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] = (string)$maritalMap[$row->marital_status];
                $dataPasien['pasien']['statusPerkawinan']['statusPerkawinanDesc'] =
                    $this->getMaritalDescription($maritalMap[$row->marital_status]);
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
            if (!isset($payload['pasien']['regNo']) || $payload['pasien']['regNo'] !== $regNo) {
                throw new \RuntimeException("regNo dalam payload tidak sesuai dengan parameter");
            }

            DB::table('rsmst_pasiens')
                ->where('reg_no', $regNo)
                ->update([
                    'meta_data_pasien_json' => json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    )
                ]);
        }, 3);
    }
}
