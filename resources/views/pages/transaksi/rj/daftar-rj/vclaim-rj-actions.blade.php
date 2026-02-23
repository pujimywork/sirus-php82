<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use VclaimTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'lov-rujukan', 'form-sep', 'info-pasien'];

    // Data dari parent
    public ?string $rjNo = null;
    public ?string $regNo = null;
    public ?string $drId = null;
    public ?string $drDesc = null;
    public ?string $poliId = null;
    public ?string $poliDesc = null;
    public ?string $kdpolibpjs = null;
    public ?string $kunjunganId = null;
    public ?string $kontrol12 = null;
    public ?string $internal12 = null;
    public $postInap = false;
    public ?string $noReferensi = null;

    // State
    public string $formMode = 'create'; // create|edit
    public bool $isFormLocked = false;
    public bool $showRujukanLov = false;
    public array $dataRujukan = [];
    public array $selectedRujukan = [];
    public array $dataPasien = [];

    // SEP Form - Struktur sesuai format BPJS
    public array $SEPForm = [
        'noKartu' => '',
        'tglSep' => '',
        'ppkPelayanan' => '0184R006',
        'jnsPelayanan' => '2',
        'klsRawat' => [
            'klsRawatHak' => '',
            'klsRawatNaik' => '',
            'pembiayaan' => '',
            'penanggungJawab' => '',
        ],
        'noMR' => '',
        'rujukan' => [
            'asalRujukan' => '',
            'tglRujukan' => '',
            'noRujukan' => '',
            'ppkRujukan' => '',
        ],
        'catatan' => '',
        'diagAwal' => '',
        'poli' => [
            'tujuan' => '',
            'eksekutif' => '0',
        ],
        'cob' => [
            'cob' => '0',
        ],
        'katarak' => [
            'katarak' => '0',
        ],
        'jaminan' => [
            'lakaLantas' => '0',
            'noLP' => '',
            'penjamin' => [
                'tglKejadian' => '',
                'keterangan' => '',
                'suplesi' => [
                    'suplesi' => '0',
                    'noSepSuplesi' => '',
                    'lokasiLaka' => [
                        'kdPropinsi' => '',
                        'kdKabupaten' => '',
                        'kdKecamatan' => '',
                    ],
                ],
            ],
        ],
        'tujuanKunj' => '0',
        'flagProcedure' => '',
        'kdPenunjang' => '',
        'assesmentPel' => '',
        'skdp' => [
            'noSurat' => '',
            'kodeDPJP' => '',
        ],
        'dpjpLayan' => '',
        'noTelp' => '',
        'user' => 'sirus App',
    ];

    // Data SEP yang sudah terbentuk (dikirim ke parent)
    public array $sepData = [
        'noSep' => '',
        'reqSep' => [],
        'resSep' => [],
    ];

    // Options dropdown
    public array $tujuanKunjOptions = [['id' => '0', 'name' => 'Normal'], ['id' => '1', 'name' => 'Prosedur'], ['id' => '2', 'name' => 'Konsul Dokter']];

    public array $flagProcedureOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '0', 'name' => 'Prosedur Tidak Berkelanjutan'], ['id' => '1', 'name' => 'Prosedur dan Terapi Berkelanjutan']];

    public array $kdPenunjangOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Radioterapi'], ['id' => '2', 'name' => 'Kemoterapi'], ['id' => '3', 'name' => 'Rehabilitasi Medik'], ['id' => '4', 'name' => 'Rehabilitasi Psikososial'], ['id' => '5', 'name' => 'Transfusi Darah'], ['id' => '6', 'name' => 'Pelayanan Gigi'], ['id' => '7', 'name' => 'Laboratorium'], ['id' => '8', 'name' => 'USG'], ['id' => '9', 'name' => 'Farmasi'], ['id' => '10', 'name' => 'Lain-Lain'], ['id' => '11', 'name' => 'MRI'], ['id' => '12', 'name' => 'HEMODIALISA']];

    public array $assesmentPelOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Poli spesialis tidak tersedia pada hari sebelumnya'], ['id' => '2', 'name' => 'Jam Poli telah berakhir pada hari sebelumnya'], ['id' => '3', 'name' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya'], ['id' => '4', 'name' => 'Atas Instruksi RS'], ['id' => '5', 'name' => 'Tujuan Kontrol']];
    /**
     * Handle event dari parent
     */
    #[On('open-vclaim-modal')]
    public function handleOpenVclaimModal($rjNo = null, $regNo = null, $drId = null, $drDesc = null, $poliId = null, $poliDesc = null, $kdpolibpjs = null, $kunjunganId = null, $kontrol12 = null, $internal12 = null, $postInap, $noReferensi = null, $sepData = [])
    {
        // Set semua data dari parent
        $this->rjNo = $rjNo;
        $this->regNo = $regNo;
        $this->drId = $drId;
        $this->drDesc = $drDesc;
        $this->poliId = $poliId;
        $this->poliDesc = $poliDesc;
        $this->kdpolibpjs = $kdpolibpjs;
        $this->kunjunganId = $kunjunganId;
        $this->kontrol12 = $kontrol12;
        $this->internal12 = $internal12;
        $this->postInap = $postInap;
        $this->noReferensi = $noReferensi;

        // Set form mode
        $this->formMode = $rjNo ? 'edit' : 'create';
        // LOAD DATA PASIEN (default)
        $this->loadDataPasien($regNo);

        // CEK APAKAH SUDAH ADA SEP
        if (!empty($sepData)) {
            $this->sepData = $sepData;
            $this->noSep = $sepData['noSep'] ?? null;

            // CEK APAKAH noSep SUDAH TERBENTUK
            if (!empty($this->noSep)) {
                $this->isFormLocked = true;
            }

            // SIMPLE: Timpa SEPForm dengan data dari reqSep jika ada
            if (!empty($sepData['reqSep']['request']['t_sep'])) {
                $this->SEPForm = array_replace_recursive($this->SEPForm, $sepData['reqSep']['request']['t_sep']);
            }

            if (!empty($sepData['reqSep']['request']['t_sep']['tglSep'])) {
                $this->SEPForm['tglSep'] = Carbon::parse($sepData['reqSep']['request']['t_sep']['tglSep'])->format('d/m/Y');
            }

            // Load selected rujukan jika ada
            if (!empty($sepData['reqSep']['request']['t_sep']['rujukan']['noRujukan'])) {
                $this->selectedRujukan = [
                    'noKunjungan' => $sepData['reqSep']['request']['t_sep']['rujukan']['noRujukan'],
                ];
            }
        }

        // Buka modal
        $this->resetVersion();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'vclaim-rj-actions');
    }

    /**
     * Load data pasien dari database
     */
    private function loadDataPasien($regNo)
    {
        $data = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->first();

        if ($data) {
            $this->dataPasien = [
                'pasien' => [
                    'identitas' => [
                        'idbpjs' => $data->nokartu_bpjs ?? '',
                        'nik' => $data->nik_bpjs ?? '',
                    ],
                    'kontak' => [
                        'nomerTelponSelulerPasien' => $data->phone ?? '',
                    ],
                    'regNo' => $data->reg_no,
                    'regName' => $data->reg_name,
                ],
            ];

            // Set default nilai SEP Form dari data pasien
            $this->SEPForm['noKartu'] = $data->nokartu_bpjs ?? '';
            $this->SEPForm['noMR'] = $data->reg_no;
            $this->SEPForm['noTelp'] = $data->phone ?? '';

            // Set dpjpLayan dari data dokter
            $this->SEPForm['dpjpLayan'] = $this->getKdDrBpjs($this->drId);

            // Set poli tujuan
            $this->SEPForm['poli']['tujuan'] = $this->kdpolibpjs ?? '';

            // Set asal rujukan berdasarkan jenis kunjungan
            $this->SEPForm['rujukan']['asalRujukan'] = $this->getAsalRujukan();
        }
    }

    /**
     * Get kode dokter BPJS
     */
    private function getKdDrBpjs($drId)
    {
        if (!$drId) {
            return '';
        }

        return DB::table('rsmst_doctors')->where('dr_id', $drId)->value('kd_dr_bpjs') ?? '';
    }

    /**
     * Get asal rujukan berdasarkan jenis kunjungan
     */
    private function getAsalRujukan()
    {
        switch ($this->kunjunganId) {
            case '1':
                return '1';
            case '2':
                return $this->internal12 ?? '1';
            case '3':
                return $this->postInap ? '2' : $this->kontrol12 ?? '1';
            case '4':
                return '2';
            default:
                return '1';
        }
    }

    /**
     * Cari rujukan peserta
     */
    public function cariRujukan()
    {
        $idBpjs = $this->dataPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (empty($idBpjs)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Nomor BPJS tidak ditemukan']);
            return;
        }

        $this->showRujukanLov = true;
        $this->dataRujukan = [];

        // Panggil API sesuai jenis kunjungan
        switch ($this->kunjunganId) {
            case '1': // Rujukan FKTP
                $this->cariRujukanFKTP($idBpjs);
                break;
            case '2': // Rujukan Internal
                $this->internal12 == '1' ? $this->cariRujukanFKTP($idBpjs) : $this->cariRujukanFKTL($idBpjs);
                break;
            case '3': // Kontrol
                if (!empty($this->postInap)) {
                    $this->cariDataPeserta($idBpjs);
                } else {
                    $this->kontrol12 == '1' ? $this->cariRujukanFKTP($idBpjs) : $this->cariRujukanFKTL($idBpjs);
                }
                break;
            case '4': // Rujukan Antar RS
                $this->cariRujukanFKTL($idBpjs);
                break;
            default:
                $this->cariRujukanFKTP($idBpjs);
                break;
        }
    }

    private function cariRujukanFKTP($idBpjs)
    {
        $response = VclaimTrait::rujukan_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $this->dataRujukan = $response['response']['rujukan'] ?? [];
            $this->incrementVersion('lov-rujukan');
            $this->incrementVersion('modal');
            if (empty($this->dataRujukan)) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'Tidak ada data rujukan FKTP']);
            }
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $response['metadata']['message'] ?? 'Gagal']);
        }
    }

    private function cariRujukanFKTL($idBpjs)
    {
        $response = VclaimTrait::rujukan_rs_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $this->dataRujukan = $response['response']['rujukan'] ?? [];
            $this->incrementVersion('lov-rujukan');
            $this->incrementVersion('modal');
            if (empty($this->dataRujukan)) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'Tidak ada data rujukan FKTL']);
            }
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $response['metadata']['message'] ?? 'Gagal']);
        }
    }

    private function cariDataPeserta($idBpjs)
    {
        $tglSep = Carbon::now()->format('Y-m-d');
        $response = VclaimTrait::peserta_nomorkartu($idBpjs, $tglSep)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $peserta = $response['response']['peserta'] ?? [];
            if (!empty($peserta)) {
                $this->setSEPFormPostInap($peserta);
                $this->showRujukanLov = false;
                $this->incrementVersion('form-sep');
                $this->incrementVersion('modal');
            }
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $response['metadata']['message'] ?? 'Gagal']);
        }
    }

    public function pilihRujukan($index)
    {
        $rujukan = $this->dataRujukan[$index];
        $this->selectedRujukan = $rujukan;
        $this->setSEPFormFromRujukan($rujukan);
        $this->showRujukanLov = false;
        $this->incrementVersion('form-sep');
        $this->incrementVersion('modal');

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Rujukan dipilih']);
    }

    private function setSEPFormFromRujukan($rujukan)
    {
        $peserta = $rujukan['peserta'] ?? [];

        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => $this->getAsalRujukan(),
                'tglRujukan' => Carbon::parse($rujukan['tglKunjungan'])->format('Y-m-d'),
                'noRujukan' => $rujukan['noKunjungan'] ?? '',
                'ppkRujukan' => $rujukan['provPerujuk']['kode'] ?? '',
            ],
            'diagAwal' => $rujukan['diagnosa']['kode'] ?? '',
            'poli' => [
                'tujuan' => $rujukan['poliRujukan']['kode'] ?? $this->SEPForm['poli']['tujuan'],
                'eksekutif' => '0',
            ],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
        ]);

        // Set skdp untuk kontrol
        if ($this->kunjunganId == '3' && !$this->postInap) {
            $this->SEPForm['skdp'] = [
                'noSurat' => $rujukan['noKunjungan'] ?? '',
                'kodeDPJP' => $this->SEPForm['dpjpLayan'] ?? '',
            ];
        }
    }

    private function setSEPFormPostInap($peserta)
    {
        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => '2', // Faskes 2 (RS) untuk post inap
                'tglRujukan' => Carbon::now()->format('Y-m-d'),
                'noRujukan' => '',
                'ppkRujukan' => '0184R006', // Kode RS sendiri
            ],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
                'klsRawatNaik' => '',
                'pembiayaan' => '',
                'penanggungJawab' => '',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
            'skdp' => [
                // Reset skdp untuk post inap
                'noSurat' => '',
                'kodeDPJP' => '',
            ],
        ]);
    }

    public function updatedSEPFormTujuanKunj($value)
    {
        // Reset flagProcedure dan kdPenunjang jika tujuanKunj = 0
        if ($value == '0') {
            $this->SEPForm['flagProcedure'] = '';
            $this->SEPForm['kdPenunjang'] = '';
            $this->SEPForm['assesmentPel'] = '';
        }

        // Reset assesmentPel jika bukan konsul dokter
        if ($value != '2') {
            $this->SEPForm['assesmentPel'] = '';
        }

        $this->incrementVersion('form-sep');
        $this->incrementVersion('modal');
    }

    /**
     * Generate SEP - hasilnya dikirim ke parent
     */
    public function generateSEP()
    {
        // Jika sudah locked (sudah ada SEP), tidak bisa generate ulang
        if ($this->isFormLocked) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'SEP sudah terbentuk, tidak dapat diubah']);
            return;
        }

        // Validasi form
        $this->validateSEPForm();
        // Build request dari form
        $request = $this->buildSEPRequest();
        // KIRIM LANGSUNG reqSep KE PARENT (bukan sepData)
        $this->dispatch('sep-generated', reqSep: $request);

        // Notifikasi sukses
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Data SEP berhasil disimpan',
        ]);

        $this->showRujukanLov = false;

        $this->closeModal();
    }

    /**
     * Validasi form SEP
     */
    private function validateSEPForm()
    {
        $rules = [
            'SEPForm.noKartu' => 'required',
            'SEPForm.tglSep' => 'required|date_format:d/m/Y',
            'SEPForm.noMR' => 'required',
            'SEPForm.diagAwal' => 'required',
            'SEPForm.poli.tujuan' => 'required',
            'SEPForm.dpjpLayan' => 'required',
        ];

        if ($this->kunjunganId == '3' && !$this->postInap) {
            $rules['SEPForm.skdp.noSurat'] = 'required';
            $rules['SEPForm.skdp.kodeDPJP'] = 'required';
        }

        $messages = [
            'SEPForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi',
            'SEPForm.tglSep.required' => 'Tanggal SEP wajib diisi.',
            'SEPForm.tglSep.date_format' => 'Format Tanggal SEP harus DD/MM/YYYY (contoh: 31/01/2024).',
            'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi',
            'SEPForm.poli.tujuan.required' => 'Poli tujuan harus diisi',
            'SEPForm.dpjpLayan.required' => 'DPJP harus diisi',
        ];

        $this->validate($rules, $messages);
    }

    /**
     * Build request SEP sesuai format BPJS
     */
    private function buildSEPRequest()
    {
        // Validasi tambahan untuk kunjungan kontrol post inap
        if ($this->kunjunganId == '3' && !empty($this->postInap)) {
            // Untuk post inap, SKDP dikosongkan (tetap dalam struktur)
            $this->SEPForm['skdp'] = [
                'noSurat' => '',
                'kodeDPJP' => '',
            ];
        }

        // Set flagProcedure dan kdPenunjang berdasarkan tujuanKunj
        $flagProcedure = $this->SEPForm['flagProcedure'] ?? '';
        $kdPenunjang = $this->SEPForm['kdPenunjang'] ?? '';

        // Set assesmentPel berdasarkan tujuanKunj
        $assesmentPel = $this->SEPForm['assesmentPel'] ?? '';

        // Untuk kasus post inap, asalRujukan harus '2'
        $asalRujukan = $this->SEPForm['rujukan']['asalRujukan'] ?? '1';
        if ($this->kunjunganId == '3' && !empty($this->postInap)) {
            $asalRujukan = '2';
        }

        $request = [
            'request' => [
                't_sep' => [
                    'noKartu' => $this->SEPForm['noKartu'] ?? '',
                    'tglSep' => isset($this->SEPForm['tglSep']) ? Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d') : '',
                    'ppkPelayanan' => $this->SEPForm['ppkPelayanan'] ?? '',
                    'jnsPelayanan' => $this->SEPForm['jnsPelayanan'] ?? '2',
                    'klsRawat' => [
                        'klsRawatHak' => $this->SEPForm['klsRawat']['klsRawatHak'] ?? '',
                        'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $this->SEPForm['noMR'] ?? '',
                    'rujukan' => [
                        'asalRujukan' => $asalRujukan,
                        'tglRujukan' => $this->SEPForm['rujukan']['tglRujukan'] ?? '',
                        'noRujukan' => $this->SEPForm['rujukan']['noRujukan'] ?? '',
                        'ppkRujukan' => $this->SEPForm['rujukan']['ppkRujukan'] ?? '',
                    ],
                    'catatan' => $this->SEPForm['catatan'] ?? '' ?: '-',
                    'diagAwal' => $this->SEPForm['diagAwal'] ?? '',
                    'poli' => [
                        'tujuan' => $this->SEPForm['poli']['tujuan'] ?? '',
                        'eksekutif' => $this->SEPForm['poli']['eksekutif'] ?? '0',
                    ],
                    'cob' => [
                        'cob' => $this->SEPForm['cob']['cob'] ?? '0',
                    ],
                    'katarak' => [
                        'katarak' => $this->SEPForm['katarak']['katarak'] ?? '0',
                    ],
                    'jaminan' => $this->buildJaminan(),
                    'tujuanKunj' => (string) ($this->SEPForm['tujuanKunj'] ?? '0'),
                    'flagProcedure' => $flagProcedure,
                    'kdPenunjang' => $kdPenunjang,
                    'assesmentPel' => $assesmentPel,
                    'skdp' => [
                        'noSurat' => $this->SEPForm['skdp']['noSurat'] ?? '',
                        'kodeDPJP' => $this->SEPForm['skdp']['kodeDPJP'] ?? '',
                    ],
                    'dpjpLayan' => $this->SEPForm['dpjpLayan'] ?? '',
                    'noTelp' => $this->SEPForm['noTelp'] ?? '',
                    'user' => $this->SEPForm['user'] ?? 'sirus App',
                ],
            ],
        ];

        // Hapus dpjpLayan hanya jika jnsPelayanan = "1" (RANAP)
        if (($this->SEPForm['jnsPelayanan'] ?? '2') == '1') {
            $request['request']['t_sep']['dpjpLayan'] = '';
        }

        return $request;
    }

    private function buildJaminan()
    {
        // SELALU sertakan semua field dalam struktur yang konsisten
        $jaminan = [
            'lakaLantas' => $this->SEPForm['jaminan']['lakaLantas'] ?? '0',
            'noLP' => $this->SEPForm['jaminan']['noLP'] ?? '', // SELALU sertakan noLP
        ];

        // Struktur penjamin HARUS konsisten, TERMASUK untuk lakaLantas=0
        $jaminan['penjamin'] = [
            'tglKejadian' => '',
            'keterangan' => '',
            'suplesi' => [
                'suplesi' => '0',
                'noSepSuplesi' => '',
                'lokasiLaka' => [
                    'kdPropinsi' => '',
                    'kdKabupaten' => '',
                    'kdKecamatan' => '',
                ],
            ],
        ];

        // Jika KLL (lakaLantas != 0), isi dengan data yang ada
        if (($this->SEPForm['jaminan']['lakaLantas'] ?? '0') != '0') {
            $jaminan['noLP'] = $this->SEPForm['jaminan']['noLP'] ?? '';

            // Isi data penjamin jika ada
            if (isset($this->SEPForm['jaminan']['penjamin'])) {
                $jaminan['penjamin']['tglKejadian'] = $this->SEPForm['jaminan']['penjamin']['tglKejadian'] ?? '';
                $jaminan['penjamin']['keterangan'] = $this->SEPForm['jaminan']['penjamin']['keterangan'] ?? '';

                if (isset($this->SEPForm['jaminan']['penjamin']['suplesi'])) {
                    $jaminan['penjamin']['suplesi']['suplesi'] = $this->SEPForm['jaminan']['penjamin']['suplesi']['suplesi'] ?? '0';
                    $jaminan['penjamin']['suplesi']['noSepSuplesi'] = $this->SEPForm['jaminan']['penjamin']['suplesi']['noSepSuplesi'] ?? '';

                    if (isset($this->SEPForm['jaminan']['penjamin']['suplesi']['lokasiLaka'])) {
                        $jaminan['penjamin']['suplesi']['lokasiLaka']['kdPropinsi'] = $this->SEPForm['jaminan']['penjamin']['suplesi']['lokasiLaka']['kdPropinsi'] ?? '';
                        $jaminan['penjamin']['suplesi']['lokasiLaka']['kdKabupaten'] = $this->SEPForm['jaminan']['penjamin']['suplesi']['lokasiLaka']['kdKabupaten'] ?? '';
                        $jaminan['penjamin']['suplesi']['lokasiLaka']['kdKecamatan'] = $this->SEPForm['jaminan']['penjamin']['suplesi']['lokasiLaka']['kdKecamatan'] ?? '';
                    }
                }
            }
        }

        return $jaminan;
    }

    /**
     * Reset form
     */
    private function resetForm()
    {
        $this->reset('SEPForm', 'selectedRujukan', 'showRujukanLov', 'dataRujukan');
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->isFormLocked = false;
    }

    /**
     * Close modal
     */
    #[On('close-vclaim-modal')]
    public function closeModal()
    {
        $this->dispatch('close-modal', name: 'vclaim-rj-actions');
        $this->resetForm();
        $this->resetVersion();
    }

    /**
     * Handle event dari LOV Dokter
     * Update data dokter dan poli yang dipilih
     */
    #[On('lov.selected.rjFormDokterVclaim')]
    public function rjFormDokterVclaim(string $target, array $payload): void
    {
        // Update data dokter
        $this->drId = $payload['dr_id'] ?? null;
        $this->drDesc = $payload['dr_name'] ?? '';
        $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';

        // Update data poli dari dokter yang dipilih
        $this->poliId = $payload['poli_id'] ?? null;
        $this->poliDesc = $payload['poli_desc'] ?? '';
        $this->SEPForm['poli']['tujuan'] = $payload['kd_poli_bpjs'] ?? ($this->kdpolibpjs ?? '');

        // Update informasi tambahan untuk SKDP jika diperlukan (kontrol)
        if ($this->kunjunganId == '3' && !$this->postInap) {
            $this->SEPForm['skdp']['kodeDPJP'] = $payload['kd_dr_bpjs'] ?? '';
        } else {
            $this->SEPForm['skdp']['kodeDPJP'] = '';
        }

        // Trigger render ulang
        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
    }

    public function mount()
    {
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->registerAreas(['modal', 'lov-rujukan', 'form-sep', 'info-pasien']);
    }
};
?>


<div>
    <x-modal name="vclaim-rj-actions" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA MODAL --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            {{-- Icon --}}
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            {{-- Title & subtitle --}}
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data SEP' : 'Buat SEP Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola data SEP (Surat Eligibilitas Peserta) BPJS.
                                </p>
                            </div>
                        </div>

                        {{-- Badge mode --}}
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit SEP' : 'Mode: Buat SEP' }}
                            </x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">
                                    Read Only
                                </x-badge>
                            @endif
                        </div>
                    </div>

                    {{-- Close button --}}
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                {{-- TOMBOL AKSI --}}
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <x-secondary-button type="button" wire:click="cariRujukan" class="gap-2" :disabled="$isFormLocked">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Cari Rujukan BPJS
                    </x-secondary-button>

                    @if (!empty($selectedRujukan))
                        <x-badge variant="info" class="gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Rujukan: {{ $selectedRujukan['noKunjungan'] ?? '-' }}
                        </x-badge>
                    @endif
                </div>

                {{-- LOV Rujukan --}}
                @if ($showRujukanLov)
                    <div wire:key="{{ $this->renderKey('lov-rujukan') }}"
                        class="mb-4 overflow-hidden bg-white border rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Pilih Rujukan
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="space-y-2 overflow-y-auto max-h-60">
                                @forelse($dataRujukan as $index => $rujukan)
                                    <div wire:key="rujukan-item-{{ $index }}"
                                        class="p-3 transition-colors border rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                        wire:click="pilihRujukan({{ $index }})">
                                        <div class="flex justify-between">
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">No Rujukan:</span>
                                                <span
                                                    class="ml-1 text-sm font-semibold">{{ $rujukan['noKunjungan'] ?? '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Tgl:</span>
                                                <span
                                                    class="ml-1 text-sm">{{ Carbon::parse($rujukan['tglKunjungan'])->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Asal Rujukan:</span>
                                                <span
                                                    class="block text-sm">{{ $rujukan['provPerujuk']['nama'] ?? '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Poli Tujuan:</span>
                                                <span
                                                    class="block text-sm">{{ $rujukan['poliRujukan']['nama'] ?? '-' }}</span>
                                            </div>
                                            <div class="col-span-2">
                                                <span class="text-xs font-medium text-gray-500">Diagnosa:</span>
                                                <span
                                                    class="block text-sm">{{ $rujukan['diagnosa']['nama'] ?? '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-4 text-sm text-center text-gray-500">Tidak ada data rujukan</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/50">
                            <x-secondary-button type="button" wire:click="$set('showRujukanLov', false)"
                                class="justify-center w-full">
                                Tutup
                            </x-secondary-button>
                        </div>
                    </div>
                @endif

                {{-- MAIN CONTENT: Informasi Pasien & Form SEP --}}
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                    {{-- Data Pasien --}}
                    <div wire:key="{{ $this->renderKey('info-pasien', $regNo ?? '') }}" class="lg:col-span-1">
                        <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                            <h3
                                class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Informasi Pasien
                            </h3>

                            <div class="space-y-3">
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. RM</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['regNo'] ?? '-' }}</p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">Nama Pasien</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['regName'] ?? '-' }}</p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. BPJS</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['identitas']['idbpjs'] ?? '-' }}
                                    </p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. Telepon</span>
                                    <p class="font-medium">
                                        {{ $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '-' }}</p>
                                </div>

                                {{-- Status Kunjungan --}}
                                @php
                                    $isPostInap = !empty($this->postInap);
                                    $jenisRujukanLabels = [
                                        '1' => 'Rujukan FKTP',
                                        '2' => 'Rujukan Internal',
                                        '3' => 'Kontrol',
                                        '4' => 'Rujukan Antar RS',
                                    ];
                                    $faskesLabels = [
                                        '1' => 'Faskes Tingkat 1',
                                        '2' => 'Faskes Tingkat 2 RS',
                                    ];
                                    $asalRujukan = $this->getAsalRujukan();
                                @endphp

                                <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                                    <div class="space-y-2">
                                        <div>
                                            <span class="text-xs font-medium text-gray-500">Jenis Rujukan:</span>
                                            <div class="mt-1">
                                                <span
                                                    class="px-2 py-1 text-xs rounded-full {{ $asalRujukan == '1' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                    {{ $jenisRujukanLabels[$this->kunjunganId] ?? 'Internal' }}
                                                </span>
                                            </div>
                                        </div>

                                        @if ($this->kunjunganId == '2')
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Internal:</span>
                                                <div class="mt-1">
                                                    <span
                                                        class="px-2 py-1 text-xs text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-200">
                                                        {{ $faskesLabels[$this->internal12] ?? 'Faskes Tingkat 1' }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($this->kunjunganId == '3')
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Post Inap:</span>
                                                <div class="mt-1">
                                                    <span
                                                        class="px-2 py-1 text-xs rounded-full {{ $isPostInap ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                        {{ $isPostInap ? 'Ya' : 'Tidak' }}
                                                    </span>
                                                </div>
                                            </div>
                                            @if (!$isPostInap)
                                                <div>
                                                    <span class="text-xs font-medium text-gray-500">Kontrol ke:</span>
                                                    <div class="mt-1">
                                                        <span
                                                            class="px-2 py-1 text-xs text-purple-800 bg-purple-100 rounded-full dark:bg-purple-900 dark:text-purple-200">
                                                            {{ $faskesLabels[$this->kontrol12] ?? 'Faskes Tingkat 1' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Form SEP --}}
                    {{-- Form SEP --}}
                    @if (!empty($selectedRujukan) || (($kunjunganId ?? '1') == '3' && ($postInap ?? false)))
                        <div wire:key="{{ $this->renderKey('form-sep', [$formMode, $selectedRujukan['noKunjungan'] ?? '']) }}"
                            class="space-y-4 lg:col-span-3">

                            {{-- FORM UTAMA SEP --}}
                            <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                                <h3
                                    class="flex items-center gap-2 mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Form SEP
                                </h3>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {{-- No Kartu --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="No. Kartu BPJS" required />
                                        <x-text-input wire:model="SEPForm.noKartu" class="w-full" :error="$errors->has('SEPForm.noKartu')"
                                            :disabled="$isFormLocked" placeholder="0000000000000" />
                                        <x-input-error :messages="$errors->get('SEPForm.noKartu')" class="mt-1" />
                                    </div>

                                    {{-- Tgl SEP --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Tanggal SEP" required />
                                        <x-text-input wire:model="SEPForm.tglSep" class="w-full" :error="$errors->has('SEPForm.tglSep')"
                                            :disabled="$isFormLocked" placeholder="dd/mm/yyyy" />
                                        <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                    </div>

                                    {{-- No MR --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="No. MR" required />
                                        <x-text-input wire:model="SEPForm.noMR" class="w-full" :error="$errors->has('SEPForm.noMR')"
                                            :disabled="$isFormLocked" />
                                        <x-input-error :messages="$errors->get('SEPForm.noMR')" class="mt-1" />
                                    </div>

                                    {{-- NO RUJUKAN - Ditambahkan di sini --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="No. Rujukan" required />
                                        <x-text-input wire:model="SEPForm.rujukan.noRujukan" class="w-full"
                                            :error="$errors->has('SEPForm.rujukan.noRujukan')" :disabled="$isFormLocked" placeholder="Nomor rujukan" />
                                        <x-input-error :messages="$errors->get('SEPForm.rujukan.noRujukan')" class="mt-1" />
                                    </div>

                                    {{-- Tgl Rujukan --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Tgl Rujukan" required />
                                        <x-text-input wire:model="SEPForm.rujukan.tglRujukan" class="w-full"
                                            :error="$errors->has('SEPForm.rujukan.tglRujukan')" :disabled="$isFormLocked" placeholder="yyyy-mm-dd" />
                                        <x-input-error :messages="$errors->get('SEPForm.rujukan.tglRujukan')" class="mt-1" />
                                    </div>

                                    {{-- PPK Rujukan --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="PPK Rujukan" required />
                                        <x-text-input wire:model="SEPForm.rujukan.ppkRujukan" class="w-full"
                                            :error="$errors->has('SEPForm.rujukan.ppkRujukan')" :disabled="$isFormLocked" placeholder="Kode faskes rujukan" />
                                        <x-input-error :messages="$errors->get('SEPForm.rujukan.ppkRujukan')" class="mt-1" />
                                    </div>

                                    {{-- Asal Rujukan --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Asal Rujukan" required />
                                        <x-select-input wire:model="SEPForm.rujukan.asalRujukan" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="">Pilih</option>
                                            <option value="1">Faskes Tingkat 1</option>
                                            <option value="2">Faskes Tingkat 2 (RS)</option>
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('SEPForm.rujukan.asalRujukan')" class="mt-1" />
                                    </div>

                                    {{-- Kelas Rawat Hak --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Kelas Rawat Hak" required />
                                        <x-select-input wire:model="SEPForm.klsRawat.klsRawatHak" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="">Pilih Kelas</option>
                                            <option value="1">Kelas 1</option>
                                            <option value="2">Kelas 2</option>
                                            <option value="3">Kelas 3</option>
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('SEPForm.klsRawat.klsRawatHak')" class="mt-1" />
                                    </div>

                                    {{-- Kelas Rawat Naik --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Kelas Rawat Naik" />
                                        <x-select-input wire:model="SEPForm.klsRawat.klsRawatNaik" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="">Pilih Kelas</option>
                                            <option value="1">VVIP</option>
                                            <option value="2">VIP</option>
                                            <option value="3">Kelas 1</option>
                                            <option value="4">Kelas 2</option>
                                            <option value="5">Kelas 3</option>
                                            <option value="6">ICCU</option>
                                            <option value="7">ICU</option>
                                            <option value="8">Diatas Kelas 1</option>
                                        </x-select-input>
                                    </div>

                                    {{-- Pembiayaan --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Pembiayaan" />
                                        <x-select-input wire:model="SEPForm.klsRawat.pembiayaan" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="">Pilih</option>
                                            <option value="1">Pribadi</option>
                                            <option value="2">Pemberi Kerja</option>
                                            <option value="3">Asuransi Kesehatan Tambahan</option>
                                        </x-select-input>
                                    </div>

                                    {{-- Penanggung Jawab --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Penanggung Jawab" />
                                        <x-text-input wire:model="SEPForm.klsRawat.penanggungJawab" class="w-full"
                                            :disabled="$isFormLocked" />
                                    </div>

                                    {{-- Diagnosa Awal --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Diagnosa Awal (ICD 10)" required />
                                        <div class="flex gap-2">
                                            <x-text-input wire:model="SEPForm.diagAwal" class="flex-1"
                                                placeholder="Kode ICD 10" :error="$errors->has('SEPForm.diagAwal')" :disabled="$isFormLocked" />
                                            {{-- Tombol cari diagnosa bisa ditambahkan di sini --}}
                                        </div>
                                        <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                    </div>

                                    {{-- Poli Tujuan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Poli Tujuan" required />
                                        <div class="flex gap-2">
                                            <x-text-input wire:model="SEPForm.poli.tujuan" class="flex-1"
                                                placeholder="Kode Poli" :error="$errors->has('SEPForm.poli.tujuan')" :disabled="$isFormLocked" />
                                            {{-- Tombol cari poli bisa ditambahkan di sini --}}
                                        </div>
                                        <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                    </div>

                                    {{-- Poli Eksekutif --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Poli Eksekutif" />
                                        <x-select-input wire:model="SEPForm.poli.eksekutif" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="0">Tidak</option>
                                            <option value="1">Ya</option>
                                        </x-select-input>
                                    </div>

                                    {{-- DPJP Layan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="DPJP" required />
                                        <div class="flex gap-2">
                                            <x-text-input wire:model="SEPForm.dpjpLayan" class="flex-1"
                                                placeholder="Kode DPJP" :error="$errors->has('SEPForm.dpjpLayan')" :disabled="$isFormLocked" />
                                        </div>
                                        <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                    </div>

                                    {{-- LOV Dokter --}}
                                    <div class="lg:col-span-3">
                                        <livewire:lov.dokter.lov-dokter label="Cari Dokter DPJP"
                                            target="rjFormDokterVclaim" :initialDrId="$drId ?? null" :disabled="$isFormLocked" />
                                        @if ($SEPForm['dpjpLayan'])
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Kode DPJP BPJS: <span
                                                    class="font-medium text-brand-green dark:text-brand-lime">{{ $SEPForm['dpjpLayan'] }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Tujuan Kunjungan --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Tujuan Kunjungan" />
                                        <x-select-input wire:model.live="SEPForm.tujuanKunj" class="w-full"
                                            :disabled="$isFormLocked">
                                            @foreach ($tujuanKunjOptions as $option)
                                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    {{-- Flag Procedure --}}
                                    @if ($SEPForm['tujuanKunj'] != '0')
                                        <div class="lg:col-span-1">
                                            <x-input-label value="Flag Procedure" />
                                            <x-select-input wire:model="SEPForm.flagProcedure" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($flagProcedureOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>

                                        <div class="lg:col-span-1">
                                            <x-input-label value="Kode Penunjang" />
                                            <x-select-input wire:model="SEPForm.kdPenunjang" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($kdPenunjangOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif

                                    {{-- Assesment Pelayanan --}}
                                    @if ($SEPForm['tujuanKunj'] == '2')
                                        <div class="lg:col-span-2">
                                            <x-input-label value="Assesment Pelayanan" />
                                            <x-select-input wire:model="SEPForm.assesmentPel" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($assesmentPelOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif

                                    {{-- Catatan --}}
                                    <div class="lg:col-span-3">
                                        <x-input-label value="Catatan" />
                                        <x-textarea wire:model="SEPForm.catatan" class="w-full" rows="2"
                                            :disabled="$isFormLocked" placeholder="Catatan (opsional)" />
                                    </div>

                                    {{-- COB --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="COB" />
                                        <x-select-input wire:model="SEPForm.cob.cob" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="0">Tidak</option>
                                            <option value="1">Ya</option>
                                        </x-select-input>
                                    </div>

                                    {{-- Katarak --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Katarak" />
                                        <x-select-input wire:model="SEPForm.katarak.katarak" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="0">Tidak</option>
                                            <option value="1">Ya</option>
                                        </x-select-input>
                                    </div>

                                    {{-- No Telepon --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="No. Telepon" />
                                        <x-text-input wire:model="SEPForm.noTelp" class="w-full" :disabled="$isFormLocked"
                                            placeholder="08xxxx" />
                                    </div>

                                    {{-- JAMINAN - LAKA LANTAS --}}
                                    <div class="p-3 border rounded lg:col-span-3 bg-gray-50 dark:bg-gray-700/30">
                                        <h4 class="flex items-center gap-2 mb-3 text-sm font-medium">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            Jaminan KLL (Kecelakaan Lalu Lintas)
                                        </h4>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                            <div>
                                                <x-input-label value="Laka Lantas" />
                                                <x-select-input wire:model.live="SEPForm.jaminan.lakaLantas"
                                                    class="w-full" :disabled="$isFormLocked">
                                                    <option value="0">Bukan KLL</option>
                                                    <option value="1">KLL dan bukan kecelakaan Kerja</option>
                                                    <option value="2">KLL dan KK</option>
                                                    <option value="3">KK</option>
                                                </x-select-input>
                                            </div>

                                            @if ($SEPForm['jaminan']['lakaLantas'] != '0')
                                                <div>
                                                    <x-input-label value="No. LP" />
                                                    <x-text-input wire:model="SEPForm.jaminan.noLP" class="w-full"
                                                        :disabled="$isFormLocked" />
                                                </div>
                                                <div>
                                                    <x-input-label value="Tgl Kejadian" />
                                                    <x-text-input wire:model="SEPForm.jaminan.penjamin.tglKejadian"
                                                        class="w-full" placeholder="yyyy-mm-dd" :disabled="$isFormLocked" />
                                                </div>
                                                <div class="md:col-span-1">
                                                    <x-input-label value="Keterangan" />
                                                    <x-text-input wire:model="SEPForm.jaminan.penjamin.keterangan"
                                                        class="w-full" :disabled="$isFormLocked" />
                                                </div>
                                                <div class="md:col-span-2">
                                                    <x-input-label value="Suplesi" />
                                                    <div class="grid grid-cols-3 gap-2">
                                                        <x-select-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.suplesi"
                                                            :disabled="$isFormLocked">
                                                            <option value="0">Tidak</option>
                                                            <option value="1">Ya</option>
                                                        </x-select-input>
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.noSepSuplesi"
                                                            class="col-span-2" placeholder="No. SEP Suplesi"
                                                            :disabled="$isFormLocked" />
                                                    </div>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <x-input-label value="Lokasi Kejadian" />
                                                    <div class="grid grid-cols-3 gap-2">
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi"
                                                            placeholder="Propinsi" :disabled="$isFormLocked" />
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten"
                                                            placeholder="Kabupaten" :disabled="$isFormLocked" />
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan"
                                                            placeholder="Kecamatan" :disabled="$isFormLocked" />
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- SKDP untuk Kontrol --}}
                                    @if (($kunjunganId ?? '1') == '3' && !($postInap ?? false))
                                        <div class="p-3 border rounded lg:col-span-3 bg-gray-50 dark:bg-gray-700/30">
                                            <h4 class="flex items-center gap-2 mb-3 text-sm font-medium">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linecap="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                Data Kontrol
                                            </h4>
                                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <div>
                                                    <x-input-label value="No. Surat Kontrol" required />
                                                    <x-text-input wire:model="SEPForm.skdp.noSurat" class="w-full"
                                                        :disabled="$isFormLocked" />
                                                    <x-input-error :messages="$errors->get('SEPForm.skdp.noSurat')" class="mt-1" />
                                                </div>
                                                <div>
                                                    <x-input-label value="Kode DPJP Kontrol" required />
                                                    <x-text-input wire:model="SEPForm.skdp.kodeDPJP" class="w-full"
                                                        :disabled="$isFormLocked" />
                                                    <x-input-error :messages="$errors->get('SEPForm.skdp.kodeDPJP')" class="mt-1" />
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- INFORMASI RUJUKAN TERPILIH --}}
                            @if (!empty($selectedRujukan))
                                <div
                                    class="p-4 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                    <h4
                                        class="flex items-center gap-2 mb-2 text-sm font-medium text-blue-800 dark:text-blue-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Data Rujukan Terpilih
                                    </h4>
                                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">No. Rujukan:</span>
                                            <p class="font-medium">{{ $selectedRujukan['noKunjungan'] ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">Tgl Rujukan:</span>
                                            <p class="font-medium">
                                                {{ isset($selectedRujukan['tglKunjungan']) ? Carbon::parse($selectedRujukan['tglKunjungan'])->format('d/m/Y') : '-' }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">Asal Rujukan:</span>
                                            <p class="font-medium">
                                                {{ $selectedRujukan['provPerujuk']['nama'] ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">Poli Tujuan:</span>
                                            <p class="font-medium">
                                                {{ $selectedRujukan['poliRujukan']['nama'] ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Tampilkan SEP yang sudah dibuat --}}
                @if (!empty($noSep))
                    <div wire:key="{{ $this->renderKey('sep-info', $noSep ?? '') }}"
                        class="p-4 mt-4 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-green-100 rounded-full dark:bg-green-800">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-300" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-green-700 dark:text-green-300">No. SEP</span>
                                    <p class="text-lg font-semibold text-green-800 dark:text-green-200">
                                        {{ $noSep }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <x-secondary-button type="button" wire:click="cetakSEP" size="sm">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak SEP
                                </x-secondary-button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">
                        Batal
                    </x-secondary-button>
                    <x-primary-button type="button" wire:click="generateSEP" wire:loading.attr="disabled"
                        :disabled="$isFormLocked">
                        <span wire:loading.remove>
                            <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                            </svg>
                            Simpan SEP
                        </span>
                        <span wire:loading>
                            <x-loading />
                            Menyimpan...
                        </span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
