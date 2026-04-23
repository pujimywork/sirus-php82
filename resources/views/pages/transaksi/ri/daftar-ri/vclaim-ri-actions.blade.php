<?php
// resources/views/pages/transaksi/ri/daftar-ri/vclaim-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use VclaimTrait, EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'form-sep', 'form-spri', 'info-pasien'];

    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public ?string $drId = null;
    public ?string $drDesc = null;
    public ?string $poliId = null;
    public ?string $poliDesc = null;
    public ?string $kdpolibpjs = null;
    public ?string $noReferensi = null;
    public ?string $diagnosaId = null;

    public string $formMode = 'create';
    public bool $isFormLocked = false;
    public string $activeTab = 'spri';
    public array $dataPasien = [];

    /* ============================
     | SEP FORM
     | tglRujukan disimpan d/m/Y untuk display,
     | dikonversi ke Y-m-d hanya di buildSEPRequest()
     ============================ */
    public array $SEPForm = [
        'noKartu' => '',
        'tglSep' => '',
        'ppkPelayanan' => '0184R006',
        'jnsPelayanan' => '1', // RI: rawat inap (fixed)
        'klsRawat' => [
            'klsRawatHak' => '',
            'klsRawatNaik' => '',
            'pembiayaan' => '',
            'penanggungJawab' => '',
        ],
        'noMR' => '',
        'rujukan' => [
            'asalRujukan' => '2', // RI: default FKTL (RS sendiri)
            'asalRujukanNama' => 'Faskes Tingkat 2 (RS)',
            'tglRujukan' => '', // format d/m/Y untuk display
            'noRujukan' => '',
            'ppkRujukan' => '0184R006', // RI: default RS sendiri
            'ppkRujukanNama' => 'RSI Madinah',
        ],
        'catatan' => '',
        'diagAwal' => '',
        'poli' => ['tujuan' => '', 'eksekutif' => '0'],
        'cob' => ['cob' => '0'],
        'katarak' => ['katarak' => '0'],
        'jaminan' => [
            'lakaLantas' => '0',
            'noLP' => '',
            'penjamin' => [
                'tglKejadian' => '',
                'keterangan' => '',
                'suplesi' => [
                    'suplesi' => '0',
                    'noSepSuplesi' => '',
                    'lokasiLaka' => ['kdPropinsi' => '', 'kdKabupaten' => '', 'kdKecamatan' => ''],
                ],
            ],
        ],
        'tujuanKunj' => '0',
        'flagProcedure' => '',
        'kdPenunjang' => '',
        'assesmentPel' => '',
        'skdp' => ['noSurat' => '', 'kodeDPJP' => ''],
        'dpjpLayan' => '',
        'noTelp' => '',
        'user' => 'sirus App',
    ];

    /* ============================
     | SPRI FORM
     ============================ */
    public array $SPRIForm = [
        'noKontrolRS' => '',
        'noSPRIBPJS' => '',
        'noAntrian' => '',
        'tglKontrol' => '', // d/m/Y untuk display
        'poliKontrol' => '',
        'poliKontrolBPJS' => '',
        'poliKontrolDesc' => '',
        'drKontrol' => '',
        'drKontrolBPJS' => '',
        'drKontrolDesc' => '',
        'noKartu' => '',
        'catatan' => '',
    ];

    public array $sepData = ['noSep' => '', 'reqSep' => [], 'resSep' => []];
    public array $spriData = [];

    public array $asalRujukanOptions = [['id' => '1', 'name' => '1 — Faskes Tingkat 1 (FKTP)'], ['id' => '2', 'name' => '2 — Faskes Tingkat 2 (RS / FKRTL)'], ['id' => '3', 'name' => '3 — Luar Negeri / Khusus']];
    public array $tujuanKunjOptions = [['id' => '0', 'name' => 'Normal'], ['id' => '1', 'name' => 'Prosedur'], ['id' => '2', 'name' => 'Konsul Dokter']];
    public array $flagProcedureOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '0', 'name' => 'Prosedur Tidak Berkelanjutan'], ['id' => '1', 'name' => 'Prosedur dan Terapi Berkelanjutan']];
    public array $kdPenunjangOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Radioterapi'], ['id' => '2', 'name' => 'Kemoterapi'], ['id' => '3', 'name' => 'Rehabilitasi Medik'], ['id' => '4', 'name' => 'Rehabilitasi Psikososial'], ['id' => '5', 'name' => 'Transfusi Darah'], ['id' => '6', 'name' => 'Pelayanan Gigi'], ['id' => '7', 'name' => 'Laboratorium'], ['id' => '8', 'name' => 'USG'], ['id' => '9', 'name' => 'Farmasi'], ['id' => '10', 'name' => 'Lain-Lain'], ['id' => '11', 'name' => 'MRI'], ['id' => '12', 'name' => 'HEMODIALISA']];
    public array $assesmentPelOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Poli spesialis tidak tersedia pada hari sebelumnya'], ['id' => '2', 'name' => 'Jam Poli telah berakhir pada hari sebelumnya'], ['id' => '3', 'name' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya'], ['id' => '4', 'name' => 'Atas Instruksi RS'], ['id' => '5', 'name' => 'Tujuan Kontrol']];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->SPRIForm['tglKontrol'] = Carbon::now()->format('d/m/Y');
        $this->registerAreas(['modal', 'form-sep', 'form-spri', 'info-pasien']);
    }

    /* ===============================
     | OPEN dari parent daftar-ri-actions
     =============================== */
    #[On('open-vclaim-modal-ri')]
    public function handleOpenVclaimModal(?string $riHdrNo = null, ?string $regNo = null, ?string $drId = null, ?string $drDesc = null, ?string $poliId = null, ?string $poliDesc = null, ?string $kdpolibpjs = null, ?string $noReferensi = null, array $sepData = [], array $spriData = []): void
    {
        // Reset form dulu agar tidak ada sisa state dari sesi sebelumnya
        $this->resetFormData();

        $this->riHdrNo = $riHdrNo;
        $this->regNo = $regNo;
        $this->poliId = $poliId;
        $this->poliDesc = $poliDesc;
        $this->noReferensi = $noReferensi;
        $this->formMode = $riHdrNo ? 'edit' : 'create';

        // Baca fresh dari DB — agar data SPRI/SEP yang sudah tersimpan selalu ter-load
        if ($riHdrNo) {
            $fresh = $this->findDataRI($riHdrNo);
            if (!empty($fresh['spri'])) {
                $spriData = $fresh['spri'];
            }
            if (!empty($fresh['sep'])) {
                $sepData = $fresh['sep'];
            }
            if (!empty($fresh['noReferensi']) && empty($noReferensi)) {
                $this->noReferensi = $fresh['noReferensi'];
                $noReferensi = $fresh['noReferensi'];
            }
            if (!empty($fresh['kdpolibpjs']) && empty($kdpolibpjs)) {
                $kdpolibpjs = $fresh['kdpolibpjs'];
            }
        }
        /* ---- Restore DPJP dari reqSep (edit mode) ---- */
        // Prioritas: SEP tersimpan → SPRI tersimpan (dokter kontrol = DPJP RI) → argument parent
        $tSep = $sepData['reqSep']['request']['t_sep'] ?? [];
        if (!empty($tSep['dpjpLayan'])) {
            $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $tSep['dpjpLayan'])->select('dr_id', 'dr_name')->first();
            $this->drId = $dokter->dr_id ?? $drId;
            $this->drDesc = $dokter->dr_name ?? $drDesc;
        } elseif (!empty($spriData['drKontrol'])) {
            $this->drId = $spriData['drKontrol'];
            $this->drDesc = $spriData['drKontrolDesc'] ?? $drDesc;
        } else {
            $this->drId = $drId;
            $this->drDesc = $drDesc;
        }

        $this->loadDataPasien($regNo);

        /* ---- Restore SEP ---- */
        if (!empty($sepData)) {
            $this->sepData = $sepData;
            if (!empty($sepData['noSep'])) {
                $this->isFormLocked = true;
            }
            if (!empty($tSep)) {
                $this->SEPForm = array_replace_recursive($this->SEPForm, $tSep);
            }
            // tglSep: Y-m-d dari storage → d/m/Y untuk display
            if (!empty($tSep['tglSep'])) {
                $this->SEPForm['tglSep'] = Carbon::parse($tSep['tglSep'])->format('d/m/Y');
            }
            // tglRujukan: Y-m-d dari storage → d/m/Y untuk display
            if (!empty($tSep['rujukan']['tglRujukan'])) {
                $this->SEPForm['rujukan']['tglRujukan'] = Carbon::parse($tSep['rujukan']['tglRujukan'])->format('d/m/Y');
            }
            if (!empty($tSep['diagAwal'])) {
                $this->diagnosaId = $tSep['diagAwal'];
            }
            if (!empty($tSep['poli']['tujuan'])) {
                $this->SEPForm['poli']['tujuan'] = $tSep['poli']['tujuan'];
            } elseif ($kdpolibpjs) {
                $this->SEPForm['poli']['tujuan'] = $kdpolibpjs;
            }
        }

        /* ---- Restore SPRI ---- */
        if (!empty($spriData)) {
            $this->spriData = $spriData;
            $this->SPRIForm = array_replace_recursive($this->SPRIForm, $spriData);
        }

        /* ---- Default noRujukan dari noReferensi ---- */
        if (!empty($noReferensi) && empty($this->SEPForm['rujukan']['noRujukan'])) {
            $this->SEPForm['rujukan']['noRujukan'] = $noReferensi;
        }

        /* ---- Sync SKDP & SEP dari SPRI yang sudah ada ---- */
        // Hanya sync jika SEP belum terbit — kalau SEP sudah ada, data sudah tersimpan di reqSep
        if (!empty($spriData['noSPRIBPJS']) && empty($sepData['noSep'])) {
            $this->SEPForm['skdp']['noSurat'] = $spriData['noSPRIBPJS'];
            $this->SEPForm['skdp']['kodeDPJP'] = $spriData['drKontrolBPJS'] ?? '';
            $this->SEPForm['rujukan']['noRujukan'] = $spriData['noSPRIBPJS'];
            $this->SEPForm['rujukan']['tglRujukan'] = $spriData['tglKontrol'] ?? '';
            // tglSep = tglKontrol SPRI
            if (!empty($spriData['tglKontrol'])) {
                $this->SEPForm['tglSep'] = $spriData['tglKontrol'];
            }

            if (empty($this->SEPForm['rujukan']['ppkRujukan'])) {
                $this->SEPForm['rujukan']['ppkRujukan'] = '0184R006';
                $this->SEPForm['rujukan']['ppkRujukanNama'] = 'RSI Madinah';
            }

            if (empty($this->SEPForm['dpjpLayan']) && !empty($spriData['drKontrolBPJS'])) {
                $this->SEPForm['dpjpLayan'] = $spriData['drKontrolBPJS'];
            }
            if (empty($this->SEPForm['poli']['tujuan']) && !empty($spriData['poliKontrolBPJS'])) {
                $this->SEPForm['poli']['tujuan'] = $spriData['poliKontrolBPJS'];
            }
        }

        $this->activeTab = !empty($spriData['noSPRIBPJS']) ? 'sep' : 'spri';

        $this->resetVersion();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'vclaim-ri-actions');

        /* ---- Auto-fetch klsRawatHak: deferred agar modal buka dulu ---- */
        if (!$this->isFormLocked && !empty($this->SEPForm['noKartu']) && empty($this->SEPForm['klsRawat']['klsRawatHak'])) {
            $this->dispatch('vclaim-ri.auto-fetch-kelas');
        }
    }

    /* ---- Load data pasien ---- */
    private function loadDataPasien(?string $regNo): void
    {
        if (!$regNo) {
            return;
        }
        $data = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->first();
        if (!$data) {
            return;
        }

        $this->dataPasien = [
            'pasien' => [
                'regNo' => $data->reg_no,
                'regName' => $data->reg_name,
                'identitas' => ['idbpjs' => $data->nokartu_bpjs ?? '', 'nik' => $data->nik_bpjs ?? ''],
                'kontak' => ['nomerTelponSelulerPasien' => $data->phone ?? ''],
            ],
        ];

        if (empty($this->SEPForm['noKartu'])) {
            $this->SEPForm['noKartu'] = $data->nokartu_bpjs ?? '';
        }
        if (empty($this->SEPForm['noMR'])) {
            $this->SEPForm['noMR'] = $data->reg_no;
        }
        if (empty($this->SEPForm['noTelp'])) {
            $this->SEPForm['noTelp'] = $data->phone ?? '';
        }
        if (empty($this->SPRIForm['noKartu'])) {
            $this->SPRIForm['noKartu'] = $data->nokartu_bpjs ?? '';
        }
    }

    /* ===============================
     | BPJS: fetch kelas rawat peserta
     | FIX: $this->peserta_nomorkartu() bukan VclaimTrait::peserta_nomorkartu()
     =============================== */
    public function fetchKlasRawat(): void
    {
        if (empty($this->SEPForm['noKartu'])) {
            $this->dispatch('toast', type: 'error', message: 'Nomor Kartu BPJS kosong.');
            return;
        }
        try {
            $tgl = !empty($this->SEPForm['tglSep']) ? Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d') : Carbon::now()->format('Y-m-d');

            // FIX: instance call
            $response = $this->peserta_nomorkartu($this->SEPForm['noKartu'], $tgl)->getOriginalContent();
            if (($response['metadata']['code'] ?? 500) == 200) {
                $peserta = $response['response']['peserta'] ?? [];
                $hakKelas = $peserta['hakKelas']['kode'] ?? '';
                $this->SEPForm['klsRawat']['klsRawatHak'] = $hakKelas;
                $this->dispatch('toast', type: 'success', message: 'Kelas rawat hak: ' . ($peserta['hakKelas']['keterangan'] ?? $hakKelas));
                $this->incrementVersion('form-sep');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Gagal fetch peserta: ' . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    /* ---- Deferred auto-fetch: dipanggil setelah modal buka ---- */
    #[On('vclaim-ri.auto-fetch-kelas')]
    public function autoFetchKlasRawat(): void
    {
        if (!$this->isFormLocked && !empty($this->SEPForm['noKartu']) && empty($this->SEPForm['klsRawat']['klsRawatHak'])) {
            $this->fetchKlasRawat();
        }
    }

    /* ===============================
     | BPJS: fetch data SPRI existing
     | FIX: $this->suratkontrol_nomor() bukan VclaimTrait::suratkontrol_nomor()
     | FIX: tglRujukan → convert Y-m-d dari API ke d/m/Y untuk display
     =============================== */
    public function fetchDataSPRI(): void
    {
        $noSPRI = trim($this->SPRIForm['noSPRIBPJS'] ?? '');
        if (empty($noSPRI)) {
            $this->dispatch('toast', type: 'error', message: 'Isi Nomor SPRI BPJS terlebih dahulu.');
            return;
        }
        try {
            // FIX: instance call
            $response = $this->suratkontrol_nomor($noSPRI)->getOriginalContent();
            if (($response['metadata']['code'] ?? 500) == 200) {
                $data = $response['response'] ?? [];

                // tglRencanaKontrol dari API: Y-m-d → d/m/Y untuk display
                $tglDariAPI = $data['tglRencanaKontrol'] ?? null;
                if ($tglDariAPI) {
                    $tglDisplay = Carbon::parse($tglDariAPI)->format('d/m/Y');
                    $this->SPRIForm['tglKontrol'] = $tglDisplay;
                    // Sync ke SEPForm.rujukan.tglRujukan juga dalam format d/m/Y
                    $this->SEPForm['rujukan']['tglRujukan'] = $tglDisplay;
                }

                $this->SPRIForm['drKontrolBPJS'] = $data['kodeDokter'] ?? '';
                $this->SEPForm['rujukan']['noRujukan'] = $data['noSuratKontrol'] ?? $noSPRI;
                $this->SEPForm['skdp']['noSurat'] = $data['noSuratKontrol'] ?? $noSPRI;
                $this->SEPForm['skdp']['kodeDPJP'] = $data['kodeDokter'] ?? '';

                $this->dispatch('toast', type: 'success', message: 'Data SPRI berhasil dimuat dari BPJS.');
                $this->incrementVersion('form-sep');
                $this->incrementVersion('form-spri');
            } else {
                $this->dispatch('toast', type: 'error', message: 'SPRI tidak ditemukan: ' . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error fetch SPRI: ' . $e->getMessage());
        }
    }

    public function updatedSEPFormTujuanKunj(string $value): void
    {
        if ($value === '0') {
            $this->SEPForm['flagProcedure'] = '';
            $this->SEPForm['kdPenunjang'] = '';
            $this->SEPForm['assesmentPel'] = '';
        }
        if ($value !== '2') {
            $this->SEPForm['assesmentPel'] = '';
        }
        $this->incrementVersion('form-sep');
    }

    /* ===============================
     | SIMPAN SPRI — push ke BPJS
     | FIX: $this->spri_insert() / $this->spri_update() bukan VclaimTrait::
     | FIX: tglRujukan disimpan d/m/Y di SEPForm
     =============================== */
    public function simpanSPRI(): void
    {
        $isUpdate = !empty($this->SPRIForm['noSPRIBPJS']);

        // Cegah update SPRI jika SEP masih ada — hapus SEP dulu
        if ($isUpdate && !empty($this->sepData['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Hapus SEP terlebih dahulu sebelum mengupdate SPRI.');
            return;
        }

        $this->validateSPRIForm();
        $this->setDataPrimerSPRI();

        try {
            // FIX: instance call
            $response = $isUpdate ? $this->spri_update($this->SPRIForm)->getOriginalContent() : $this->spri_insert($this->SPRIForm)->getOriginalContent();

            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '';

            if ($code == 200) {
                if (!$isUpdate) {
                    $this->SPRIForm['noSPRIBPJS'] = $response['response']['noSPRI'] ?? '';
                }

                /* ====================================================
                 * AUTO-SYNC noSPRI → SEP form
                 * tglRujukan disimpan d/m/Y (konsisten dengan pola RJ/UGD)
                 * buildSEPRequest() akan convert ke Y-m-d saat kirim ke API
                 * ==================================================== */
                $this->SEPForm['skdp']['noSurat'] = $this->SPRIForm['noSPRIBPJS'];
                $this->SEPForm['skdp']['kodeDPJP'] = $this->SPRIForm['drKontrolBPJS'] ?? '';
                $this->SEPForm['rujukan']['noRujukan'] = $this->SPRIForm['noSPRIBPJS'];
                // Simpan d/m/Y — buildSEPRequest() convert ke Y-m-d
                $this->SEPForm['rujukan']['tglRujukan'] = $this->SPRIForm['tglKontrol'];
                // tglSep = tglKontrol SPRI (RI: SPRI dulu → SEP, tanggal harus sama)
                $this->SEPForm['tglSep'] = $this->SPRIForm['tglKontrol'];

                if (empty($this->SEPForm['rujukan']['ppkRujukan'])) {
                    $this->SEPForm['rujukan']['ppkRujukan'] = '0184R006';
                    $this->SEPForm['rujukan']['ppkRujukanNama'] = 'RSI Madinah';
                }

                if (empty($this->SEPForm['dpjpLayan']) && !empty($this->SPRIForm['drKontrolBPJS'])) {
                    $this->SEPForm['dpjpLayan'] = $this->SPRIForm['drKontrolBPJS'];
                }
                if (empty($this->SEPForm['poli']['tujuan']) && !empty($this->SPRIForm['poliKontrolBPJS'])) {
                    $this->SEPForm['poli']['tujuan'] = $this->SPRIForm['poliKontrolBPJS'];
                }

                // Persist SPRI langsung ke JSON DB
                $this->syncVclaimJson(spriData: $this->SPRIForm);

                $this->dispatch('spri-generated-ri', spriData: $this->SPRIForm);
                $this->dispatch('toast', type: 'success', message: ($isUpdate ? 'Update' : 'Insert') . " SPRI berhasil ({$code}): {$msg}");

                $this->activeTab = 'sep';
                $this->incrementVersion('form-sep');
                $this->incrementVersion('form-spri');
                $this->incrementVersion('info-pasien');
            } else {
                $this->dispatch('toast', type: 'error', message: ($isUpdate ? 'Update' : 'Insert') . " SPRI gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error SPRI: ' . $e->getMessage());
        }
    }

    private function validateSPRIForm(): void
    {
        $this->validate(
            [
                'SPRIForm.tglKontrol' => 'required|date_format:d/m/Y',
                'SPRIForm.drKontrol' => 'required',
                'SPRIForm.drKontrolDesc' => 'required',
                'SPRIForm.drKontrolBPJS' => 'required',
                'SPRIForm.poliKontrol' => 'required',
                'SPRIForm.poliKontrolDesc' => 'required',
                'SPRIForm.noKartu' => 'required',
            ],
            [
                'SPRIForm.tglKontrol.required' => 'Tanggal kontrol harus diisi.',
                'SPRIForm.tglKontrol.date_format' => 'Format tanggal kontrol harus dd/mm/yyyy.',
                'SPRIForm.drKontrol.required' => 'Dokter kontrol harus dipilih via LOV.',
                'SPRIForm.drKontrolDesc.required' => 'Nama dokter kontrol tidak boleh kosong.',
                'SPRIForm.drKontrolBPJS.required' => 'Kode BPJS dokter tidak boleh kosong (pilih lewat LOV).',
                'SPRIForm.poliKontrol.required' => 'Poli kontrol harus dipilih.',
                'SPRIForm.poliKontrolDesc.required' => 'Nama poli kontrol tidak boleh kosong.',
                'SPRIForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi.',
            ],
        );
    }

    private function setDataPrimerSPRI(): void
    {
        if (empty($this->SPRIForm['noKontrolRS'])) {
            $tgl = Carbon::createFromFormat('d/m/Y', $this->SPRIForm['tglKontrol'])->format('dmY');
            $this->SPRIForm['noKontrolRS'] = $tgl . ($this->SPRIForm['drKontrol'] ?? '') . ($this->SPRIForm['poliKontrol'] ?? '');
        }
    }

    /* ===============================
     | BUAT SEP — push langsung ke BPJS API
     =============================== */
    public function generateSEP(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'warning', message: 'SEP sudah terbentuk, tidak dapat diubah.');
            return;
        }
        $this->validateSEPForm();
        $request = $this->buildSEPRequest();

        try {
            $response = $this->sep_insert($request)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;
            if ($code == 200 || ($code == 201 && !empty($response['response']['sep']['noSep']))) {
                $sepData = $response['response']['sep'] ?? [];
                $noSep = $sepData['noSep'] ?? '';

                // Persist SEP langsung ke JSON DB
                $this->syncVclaimJson(
                    sepData: [
                        'noSep' => $noSep,
                        'reqSep' => $request,
                        'resSep' => $sepData,
                    ],
                );

                $this->dispatch('sep-generated-ri', reqSep: $request, noSep: $noSep, resSep: $sepData);
                $this->dispatch('toast', type: 'success', message: "SEP RI berhasil dibuat: {$noSep}");
                if ($code == 201) {
                    $this->dispatch('toast', type: 'info', message: $response['metadata']['message'] ?? '');
                }
                $this->closeModal();
            } else {
                $this->dispatch('toast', type: 'error', message: "Buat SEP gagal ({$code}): " . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error buat SEP: ' . $e->getMessage());
        }
    }

    private function validateSEPForm(): void
    {
        $this->validate(
            [
                'SEPForm.noKartu' => 'required',
                'SEPForm.tglSep' => 'required|date_format:d/m/Y',
                'SEPForm.noMR' => 'required',
                'SEPForm.diagAwal' => 'required',
                'SEPForm.klsRawat.klsRawatHak' => 'required',
                'SEPForm.noTelp' => 'required',
            ],
            [
                'SEPForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi.',
                'SEPForm.tglSep.required' => 'Tanggal SEP wajib diisi.',
                'SEPForm.tglSep.date_format' => 'Format Tanggal SEP harus dd/mm/yyyy.',
                'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi.',
                'SEPForm.klsRawat.klsRawatHak.required' => 'Kelas rawat hak belum dimuat. Klik tombol "↺ Muat Kelas Rawat".',
                'SEPForm.noTelp.required' => 'No. telepon pasien harus diisi.',
            ],
        );
    }

    private function buildSEPRequest(): array
    {
        // tglSep: d/m/Y → Y-m-d untuk API
        $tglSepFormatted = Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d');

        // tglRujukan: d/m/Y → Y-m-d untuk API, fallback ke tglSep jika kosong
        $tglRujukanRaw = $this->SEPForm['rujukan']['tglRujukan'] ?? '';
        $tglRujukan = !empty($tglRujukanRaw) ? Carbon::createFromFormat('d/m/Y', $tglRujukanRaw)->format('Y-m-d') : $tglSepFormatted;

        return [
            'request' => [
                't_sep' => [
                    'noKartu' => $this->SEPForm['noKartu'] ?? '',
                    'tglSep' => $tglSepFormatted,
                    'ppkPelayanan' => $this->SEPForm['ppkPelayanan'] ?? '0184R006',
                    'jnsPelayanan' => '1', // RI: rawat inap — fixed
                    'klsRawat' => [
                        'klsRawatHak' => $this->SEPForm['klsRawat']['klsRawatHak'] ?? '',
                        'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $this->SEPForm['noMR'] ?? '',
                    'rujukan' => [
                        'asalRujukan' => $this->SEPForm['rujukan']['asalRujukan'] ?? '2',
                        'tglRujukan' => $tglRujukan,
                        'noRujukan' => $this->SEPForm['rujukan']['noRujukan'] ?? '',
                        'ppkRujukan' => $this->SEPForm['rujukan']['ppkRujukan'] ?: '0184R006',
                        'ppkRujukanNama' => $this->SEPForm['rujukan']['ppkRujukanNama'] ?: 'RSI Madinah',
                    ],
                    'catatan' => $this->SEPForm['catatan'] ?: '-',
                    'diagAwal' => $this->SEPForm['diagAwal'] ?? '',
                    'poli' => [
                        'tujuan' => '', // kosong untuk RANAP (jnsPelayanan=1)
                        'eksekutif' => $this->SEPForm['poli']['eksekutif'] ?? '0',
                    ],
                    'cob' => ['cob' => $this->SEPForm['cob']['cob'] ?? '0'],
                    'katarak' => ['katarak' => $this->SEPForm['katarak']['katarak'] ?? '0'],
                    'jaminan' => $this->buildJaminan(),
                    'tujuanKunj' => (string) ($this->SEPForm['tujuanKunj'] ?? '0'),
                    'flagProcedure' => $this->SEPForm['flagProcedure'] ?? '',
                    'kdPenunjang' => $this->SEPForm['kdPenunjang'] ?? '',
                    'assesmentPel' => $this->SEPForm['assesmentPel'] ?? '',
                    'skdp' => [
                        'noSurat' => $this->SEPForm['skdp']['noSurat'] ?? '',
                        'kodeDPJP' => $this->SEPForm['skdp']['kodeDPJP'] ?? '',
                    ],
                    'dpjpLayan' => '', // kosong untuk RANAP (jnsPelayanan=1)
                    'noTelp' => $this->SEPForm['noTelp'] ?? '',
                    'user' => 'sirus App',
                ],
            ],
        ];
    }

    private function buildJaminan(): array
    {
        $jaminan = [
            'lakaLantas' => $this->SEPForm['jaminan']['lakaLantas'] ?? '0',
            'noLP' => $this->SEPForm['jaminan']['noLP'] ?? '',
            'penjamin' => [
                'tglKejadian' => '',
                'keterangan' => '',
                'suplesi' => [
                    'suplesi' => '0',
                    'noSepSuplesi' => '',
                    'lokasiLaka' => ['kdPropinsi' => '', 'kdKabupaten' => '', 'kdKecamatan' => ''],
                ],
            ],
        ];
        if (($this->SEPForm['jaminan']['lakaLantas'] ?? '0') !== '0') {
            $p = $this->SEPForm['jaminan']['penjamin'] ?? [];
            $s = $p['suplesi'] ?? [];
            $l = $s['lokasiLaka'] ?? [];
            $jaminan['penjamin'] = [
                'tglKejadian' => $p['tglKejadian'] ?? '',
                'keterangan' => $p['keterangan'] ?? '',
                'suplesi' => [
                    'suplesi' => $s['suplesi'] ?? '0',
                    'noSepSuplesi' => $s['noSepSuplesi'] ?? '',
                    'lokasiLaka' => [
                        'kdPropinsi' => $l['kdPropinsi'] ?? '',
                        'kdKabupaten' => $l['kdKabupaten'] ?? '',
                        'kdKecamatan' => $l['kdKecamatan'] ?? '',
                    ],
                ],
            ];
        }
        return $jaminan;
    }

    /* ===============================
     | DELETE SEP dari BPJS
     | FIX: $this->sep_delete() bukan VclaimTrait::sep_delete()
     =============================== */
    public function deleteSEP(): void
    {
        if (empty($this->sepData['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk dihapus.');
            return;
        }
        try {
            // FIX: instance call
            $response = $this->sep_delete($this->sepData['noSep'])->getOriginalContent();
            $code = data_get($response, 'metadata.code');
            $msg = data_get($response, 'metadata.message', 'Tidak ada pesan');
            if (in_array($code, [200, 201])) {
                $this->sepData = ['noSep' => '', 'reqSep' => [], 'resSep' => []];
                $this->isFormLocked = false;

                // Persist hapus SEP ke JSON DB
                $this->syncVclaimJson(sepData: ['noSep' => '', 'reqSep' => [], 'resSep' => []]);

                $this->dispatch('sep-generated-ri', reqSep: []);
                $this->dispatch('toast', type: 'success', message: "SEP berhasil dihapus ({$code}): {$msg}");
                $this->incrementVersion('form-sep');
                $this->incrementVersion('info-pasien');
            } else {
                $this->dispatch('toast', type: 'error', message: "Hapus SEP gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error hapus SEP: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE SEP
     =============================== */
    public function updateSEP(): void
    {
        if (empty($this->sepData['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk diupdate.');
            return;
        }

        $this->validateSEPForm();
        $request = $this->buildSEPRequest();

        // Tambahkan noSep ke request untuk update
        $request['request']['t_sep']['noSep'] = $this->sepData['noSep'];

        try {
            $response = $this->sep_update($request)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '-';

            if ($code == 200) {
                // Persist update SEP ke JSON DB
                $this->syncVclaimJson(
                    sepData: [
                        'noSep' => $this->sepData['noSep'],
                        'reqSep' => $request,
                        'resSep' => $this->sepData['resSep'] ?? [],
                    ],
                );

                $this->dispatch('sep-generated-ri', reqSep: $request, noSep: $this->sepData['noSep'], resSep: $this->sepData['resSep'] ?? []);
                $this->dispatch('toast', type: 'success', message: "SEP berhasil diupdate ({$code}): {$msg}");
                $this->isFormLocked = true;
                $this->incrementVersion('form-sep');
                $this->incrementVersion('info-pasien');
            } else {
                $this->dispatch('toast', type: 'error', message: "Update SEP gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error update SEP: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV LISTENERS
     =============================== */
    #[On('lov.selected.riFormDokterVclaim')]
    public function riFormDokterVclaim(string $target, array $payload): void
    {
        $this->drId = $payload['dr_id'] ?? null;
        $this->drDesc = $payload['dr_name'] ?? '';
        $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';
        $this->SEPForm['poli']['tujuan'] = $payload['kd_poli_bpjs'] ?? $this->SEPForm['poli']['tujuan'];
        $this->SEPForm['skdp']['kodeDPJP'] = $payload['kd_dr_bpjs'] ?? '';
        $this->incrementVersion('form-sep');
        $this->dispatch('focus-vclaim-ri-diagnosa');
    }

    #[On('lov.selected.riFormDiagnosaVclaim')]
    public function riFormDiagnosaVclaim(string $target, array $payload): void
    {
        $this->diagnosaId = $payload['icdx'] ?? null;
        $this->SEPForm['diagAwal'] = $payload['icdx'] ?? '';
        $this->incrementVersion('form-sep');
        $this->dispatch('focus-vclaim-ri-simpan');
    }

    #[On('lov.selected.riFormDokterSPRI')]
    public function riFormDokterSPRI(string $target, array $payload): void
    {
        $this->SPRIForm['drKontrol'] = $payload['dr_id'] ?? '';
        $this->SPRIForm['drKontrolDesc'] = $payload['dr_name'] ?? '';
        $this->SPRIForm['drKontrolBPJS'] = $payload['kd_dr_bpjs'] ?? '';
        $this->SPRIForm['poliKontrol'] = $payload['poli_id'] ?? '';
        $this->SPRIForm['poliKontrolDesc'] = $payload['poli_desc'] ?? '';
        $this->SPRIForm['poliKontrolBPJS'] = $payload['kd_poli_bpjs'] ?? '';

        // Dokter kontrol SPRI = DPJP yang Melayani RI — selalu sinkron selama SEP belum terbit.
        // drId + drDesc di-set agar LOV Dokter di tab SEP (#[Reactive] initialDrId) ikut re-render.
        if (!$this->isFormLocked) {
            $this->drId = $payload['dr_id'] ?? null;
            $this->drDesc = $payload['dr_name'] ?? '';
            $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';
            $this->SEPForm['skdp']['kodeDPJP'] = $payload['kd_dr_bpjs'] ?? '';
            $this->SEPForm['poli']['tujuan'] = $payload['kd_poli_bpjs'] ?? '';
        }
        $this->incrementVersion('form-spri');
        $this->incrementVersion('form-sep');
    }

    /* ===============================
     | PERSIST JSON — simpan langsung ke DB via EmrRITrait
     =============================== */
    private function syncVclaimJson(array $spriData = [], array $sepData = []): void
    {
        if (!$this->riHdrNo) {
            return;
        }

        DB::transaction(function () use ($spriData, $sepData) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo);

            if (!empty($spriData)) {
                $fresh['spri'] = $spriData;
            }
            if (!empty($sepData)) {
                $fresh['sep'] = array_merge($fresh['sep'] ?? [], $sepData);
            }
            if (!empty($this->noReferensi)) {
                $fresh['noReferensi'] = $this->noReferensi;
            }

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);

            // Sync kolom vno_sep agar konsisten dengan JSON — dipakai report & findDataRI fallback.
            // RJ melakukan ini via buildPayload saat save; RI perlu update terpisah karena SEP
            // dibuat dari modal setelah RI tersimpan.
            if (!empty($sepData['noSep'])) {
                DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->update(['vno_sep' => $sepData['noSep']]);
            } elseif (array_key_exists('noSep', $sepData) && $sepData['noSep'] === '') {
                // Delete SEP — reset vno_sep juga
                DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->update(['vno_sep' => null]);
            }
        });
    }

    /* ===============================
     | Reset & Close
     =============================== */
    private function resetFormData(): void
    {
        $this->reset(['SEPForm', 'SPRIForm', 'diagnosaId', 'dataPasien', 'sepData', 'spriData']);
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->SEPForm['jnsPelayanan'] = '1';
        $this->SPRIForm['tglKontrol'] = Carbon::now()->format('d/m/Y');
        $this->isFormLocked = false;
        $this->activeTab = 'spri';
    }

    #[On('close-vclaim-modal-ri')]
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'vclaim-ri-actions');
        $this->resetFormData();
        $this->resetVersion();
    }
};
?>

{{-- Blade template sama dengan versi asli — tidak ada perubahan pada HTML --}}
{{-- Copy paste blade HTML dari file asli setelah baris ini --}}
<div>
    <x-modal name="vclaim-ri-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-500/10">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Kelola SPRI & SEP —
                                    Rawat Inap</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">SPRI → SEP (jnsPelayanan: 1 —
                                    Rawat Inap)</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <x-badge
                                :variant="$formMode === 'edit' ? 'warning' : 'success'">{{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Buat' }}</x-badge>
                            @if (!empty($SPRIForm['noSPRIBPJS']))
                                <x-badge variant="warning">SPRI: {{ $SPRIForm['noSPRIBPJS'] }}</x-badge>
                            @endif
                            @if (!empty($sepData['noSep']))
                                <x-badge variant="success">SEP: {{ $sepData['noSep'] }}</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">SEP Aktif — Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-vclaim-ri-diagnosa.window="$nextTick(() => setTimeout(() => $refs.lovDiagnosaVclaim?.querySelector('input')?.focus(), 150))"
                x-on:focus-vclaim-ri-simpan.window="$nextTick(() => setTimeout(() => $refs.btnSimpanSEP?.focus(), 150))">

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">

                    {{-- ============================================================
                         PANEL KIRI: Info Pasien + Status
                         ============================================================ --}}
                    <div wire:key="{{ $this->renderKey('info-pasien', $regNo ?? '') }}" class="lg:col-span-1 space-y-4">

                        {{-- Info Pasien --}}
                        <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800">
                            <h3
                                class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Informasi Pasien
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. RM</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['regNo'] ?? '-' }}</p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">Nama</span>
                                    <p class="font-semibold">{{ $dataPasien['pasien']['regName'] ?? '-' }}</p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. Kartu BPJS</span>
                                    <p class="font-mono font-medium">
                                        {{ $dataPasien['pasien']['identitas']['idbpjs'] ?? '-' }}</p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. Telepon</span>
                                    <p class="font-medium">
                                        {{ $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '-' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Status SPRI --}}
                        <div
                            class="p-4 rounded-xl border {{ !empty($SPRIForm['noSPRIBPJS']) ? 'bg-purple-50 border-purple-200 dark:bg-purple-900/20 dark:border-purple-800' : 'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="w-2 h-2 rounded-full {{ !empty($SPRIForm['noSPRIBPJS']) ? 'bg-purple-500' : 'bg-gray-300' }}">
                                </div>
                                <span
                                    class="text-xs font-semibold {{ !empty($SPRIForm['noSPRIBPJS']) ? 'text-purple-700 dark:text-purple-300' : 'text-gray-500' }}">
                                    SPRI {{ !empty($SPRIForm['noSPRIBPJS']) ? 'Aktif' : 'Belum Ada' }}
                                </span>
                            </div>
                            @if (!empty($SPRIForm['noSPRIBPJS']))
                                <p class="font-mono text-sm font-semibold text-purple-800 dark:text-purple-200">
                                    {{ $SPRIForm['noSPRIBPJS'] }}</p>
                                <div class="mt-1 text-xs text-purple-600 dark:text-purple-400 space-y-0.5">
                                    <p>Dr: {{ $SPRIForm['drKontrolDesc'] ?? '-' }}</p>
                                    <p>Poli: {{ $SPRIForm['poliKontrolDesc'] ?? '-' }}</p>
                                    <p>Tgl: {{ $SPRIForm['tglKontrol'] ?? '-' }}</p>
                                </div>
                            @else
                                <p class="text-xs text-gray-400">Buat SPRI di tab SPRI terlebih dahulu.</p>
                            @endif
                        </div>

                        {{-- Status SEP --}}
                        <div
                            class="p-4 rounded-xl border {{ !empty($sepData['noSep']) ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800' : 'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="w-2 h-2 rounded-full {{ !empty($sepData['noSep']) ? 'bg-green-500' : 'bg-gray-300' }}">
                                </div>
                                <span
                                    class="text-xs font-semibold {{ !empty($sepData['noSep']) ? 'text-green-700 dark:text-green-300' : 'text-gray-500' }}">
                                    SEP {{ !empty($sepData['noSep']) ? 'Aktif' : 'Belum Ada' }}
                                </span>
                            </div>
                            @if (!empty($sepData['noSep']))
                                <p class="font-mono text-sm font-semibold text-green-800 dark:text-green-200">
                                    {{ $sepData['noSep'] }}</p>
                                @if (!empty($sepData['resSep']['tglSEP']))
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">Tgl:
                                        {{ Carbon::parse($sepData['resSep']['tglSEP'])->format('d/m/Y') }}</p>
                                @endif
                                <div class="mt-2">
                                    <x-confirm-button variant="danger" action="deleteSEP()"
                                        title="Hapus SEP BPJS"
                                        message="Yakin hapus SEP ini dari server BPJS? Tindakan ini tidak dapat dibatalkan."
                                        confirmText="Ya, hapus SEP" cancelText="Batal"
                                        class="w-full text-xs gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Hapus SEP dari BPJS
                                    </x-confirm-button>
                                </div>
                            @else
                                <p class="text-xs text-gray-400">Buat SEP setelah SPRI selesai.</p>
                            @endif
                        </div>

                        {{-- Alur RI --}}
                        <div
                            class="p-3 text-xs bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-800 text-blue-700 dark:text-blue-300 space-y-1">
                            <p class="font-semibold">Alur SEP Rawat Inap:</p>
                            <div class="flex items-center gap-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold {{ !empty($SPRIForm['noSPRIBPJS']) ? 'bg-purple-500' : 'bg-gray-300' }}">1</span>
                                <span>Buat SPRI → push ke BPJS</span>
                                @if (!empty($SPRIForm['noSPRIBPJS']))
                                    <span class="text-green-600">✓</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold {{ !empty($SEPForm['diagAwal']) ? 'bg-blue-500' : 'bg-gray-300' }}">2</span>
                                <span>Isi form SEP (Diagnosa, DPJP, dll)</span>
                                @if (!empty($SEPForm['diagAwal']))
                                    <span class="text-green-600">✓</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold {{ !empty($sepData['noSep']) ? 'bg-green-500' : 'bg-gray-300' }}">3</span>
                                <span>Klik Buat SEP → SEP langsung dikirim ke BPJS</span>
                                @if (!empty($sepData['noSep']))
                                    <span class="text-green-600">✓</span>
                                @endif
                            </div>
                        </div>

                    </div>

                    {{-- ============================================================
                         PANEL KANAN: Tab SPRI & SEP
                         ============================================================ --}}
                    <div class="lg:col-span-3 space-y-0">

                        {{-- Tab Navigation --}}
                        <div
                            class="flex border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
                            <button type="button" wire:click="$set('activeTab', 'spri')"
                                class="flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                    {{ $activeTab === 'spri' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                SPRI (Surat Perintah Rawat Inap)
                                @if (!empty($SPRIForm['noSPRIBPJS']))
                                    <span class="w-2 h-2 rounded-full bg-purple-500 shrink-0"></span>
                                @endif
                            </button>
                            <button type="button" wire:click="$set('activeTab', 'sep')"
                                class="flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                    {{ $activeTab === 'sep' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                SEP (Surat Eligibilitas Peserta)
                                @if (!empty($sepData['noSep']))
                                    <span class="w-2 h-2 rounded-full bg-green-500 shrink-0"></span>
                                @elseif (!empty($SEPForm['diagAwal']))
                                    <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                                @endif
                            </button>
                        </div>

                        {{-- ====================================================
                             TAB SPRI — urutan sesuai VClaim BPJS (Image 1)
                             1. Tgl. Rencana Kontrol / Inap
                             2. Pelayanan (fixed: Rawat Inap)
                             3. No. Surat Kontrol
                             4. Spesialis/SubSpesialis (LOV Dokter → poli)
                             5. DPJP Tujuan Kontrol / Inap
                             ==================================================== --}}
                        @if ($activeTab === 'spri')
                            <div wire:key="{{ $this->renderKey('form-spri', []) }}"
                                class="p-5 bg-white rounded-b-xl shadow dark:bg-gray-800">

                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        Surat Perintah Rawat Inap (SPRI) BPJS
                                    </h3>
                                    <div class="flex items-center gap-2">
                                        <x-badge :variant="!empty($SPRIForm['noSPRIBPJS']) ? 'warning' : 'success'">
                                            {{ !empty($SPRIForm['noSPRIBPJS']) ? 'Update Mode' : 'Insert Mode' }}
                                        </x-badge>
                                        {{-- Fetch SPRI existing --}}
                                        @if (!empty($SPRIForm['noSPRIBPJS']))
                                            <x-secondary-button type="button" wire:click="fetchDataSPRI"
                                                class="text-xs gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Fetch dari BPJS
                                            </x-secondary-button>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                    {{-- 1. Tgl. Rencana Kontrol / Inap --}}
                                    <div>
                                        <x-input-label value="Tgl. Rencana Kontrol / Inap (dd/mm/yyyy) *" />
                                        <x-text-input wire:model="SPRIForm.tglKontrol" class="w-full mt-1"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('SPRIForm.tglKontrol')" />
                                        <x-input-error :messages="$errors->get('SPRIForm.tglKontrol')" class="mt-1" />
                                    </div>

                                    {{-- 2. Pelayanan (fixed Rawat Inap) --}}
                                    <div>
                                        <x-input-label value="Pelayanan" />
                                        <x-text-input class="w-full mt-1 bg-gray-50" :disabled="true"
                                            value="Rawat Inap" />
                                        <p class="mt-1 text-xs text-gray-400">jnsPelayanan = 1 (fixed untuk RI)</p>
                                    </div>

                                    {{-- 3. No. Surat Kontrol (No. SPRI BPJS) --}}
                                    <div>
                                        <x-input-label value="No. Surat Kontrol (No. SPRI BPJS)" />
                                        <div class="flex gap-2 mt-1">
                                            <x-text-input wire:model="SPRIForm.noSPRIBPJS" class="flex-1"
                                                :disabled="true"
                                                placeholder="Otomatis terisi setelah Insert SPRI" />
                                            @if (!empty($SPRIForm['noSPRIBPJS']))
                                                <x-secondary-button type="button" wire:click="fetchDataSPRI"
                                                    title="Fetch data SPRI dari BPJS" class="shrink-0 px-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </x-secondary-button>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-gray-400">
                                            @if (empty($SPRIForm['noSPRIBPJS']))
                                                Kosong — klik "Insert SPRI ke BPJS" untuk membuat baru.
                                            @else
                                                SPRI sudah terbit. Klik ↺ untuk refresh data dari BPJS.
                                            @endif
                                        </p>
                                    </div>

                                    {{-- No Kartu (hidden tapi penting) --}}
                                    <div>
                                        <x-input-label value="No. Kartu BPJS Pasien" />
                                        <x-text-input wire:model="SPRIForm.noKartu" class="w-full mt-1"
                                            :disabled="true" />
                                        <x-input-error :messages="$errors->get('SPRIForm.noKartu')" class="mt-1" />
                                    </div>

                                    {{-- 4. Spesialis/SubSpesialis — via LOV Dokter (mengisi poli + dokter sekaligus) --}}
                                    <div class="md:col-span-2">
                                        <livewire:lov.dokter.lov-dokter
                                            label="Spesialis / Sub Spesialis * (Cari Dokter Kontrol)"
                                            target="riFormDokterSPRI" :initialDrId="$SPRIForm['drKontrol'] ?? null" />
                                        <p class="mt-1 text-xs text-gray-400">Memilih dokter otomatis mengisi
                                            Spesialis/SubSpesialis dan DPJP.</p>
                                    </div>

                                    {{-- Hasil LOV: Spesialis/SubSpesialis (poli) --}}
                                    <div>
                                        <x-input-label value="Spesialis / Sub Spesialis *" />
                                        <x-text-input wire:model="SPRIForm.poliKontrolDesc" class="w-full mt-1"
                                            :disabled="true" placeholder="Otomatis dari LOV Dokter" />
                                        <x-input-error :messages="$errors->get('SPRIForm.poliKontrolDesc')" class="mt-1" />
                                        @if (!empty($SPRIForm['poliKontrolBPJS']))
                                            <p class="mt-1 text-xs text-gray-400">Kode BPJS: <span
                                                    class="font-mono">{{ $SPRIForm['poliKontrolBPJS'] }}</span></p>
                                        @endif
                                    </div>

                                    {{-- 5. DPJP Tujuan Kontrol / Inap --}}
                                    <div>
                                        <x-input-label value="DPJP Tujuan Kontrol / Inap *" />
                                        <x-text-input wire:model="SPRIForm.drKontrolDesc" class="w-full mt-1"
                                            :disabled="true" placeholder="Otomatis dari LOV Dokter" />
                                        <x-input-error :messages="$errors->get('SPRIForm.drKontrolDesc')" class="mt-1" />
                                        @if (!empty($SPRIForm['drKontrolBPJS']))
                                            <p class="mt-1 text-xs text-gray-400">Kode BPJS: <span
                                                    class="font-mono">{{ $SPRIForm['drKontrolBPJS'] }}</span></p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SPRIForm.drKontrolBPJS')" class="mt-1" />
                                    </div>

                                    {{-- Catatan --}}
                                    <div class="md:col-span-2">
                                        <x-input-label value="Catatan (opsional)" />
                                        <x-text-input wire:model="SPRIForm.catatan" class="w-full mt-1"
                                            placeholder="Catatan rencana kontrol" />
                                    </div>

                                    {{-- Preview sync --}}
                                    @if (!empty($SPRIForm['drKontrol']))
                                        <div class="md:col-span-2">
                                            <div
                                                class="px-3 py-2 text-xs border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 text-blue-700 dark:text-blue-300">
                                                <span class="font-semibold">Setelah simpan, otomatis mengisi form
                                                    SEP:</span>
                                                <span class="ml-2">
                                                    skdp.noSurat =
                                                    <strong>{{ $SPRIForm['noSPRIBPJS'] ?: '(no SPRI baru)' }}</strong>
                                                    &nbsp;·&nbsp; kodeDPJP =
                                                    <strong>{{ $SPRIForm['drKontrolBPJS'] ?: '-' }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                    @endif

                                </div>
                            </div>
                        @endif

                        {{-- ====================================================
                             TAB SEP — urutan sesuai VClaim BPJS SEP RI (Image 2)
                             1.  Asal Rujukan
                             2.  PPK Asal Rujukan
                             3.  (yyyy-mm-dd) Tgl. Rujukan
                             4.  No. Rujukan *
                             5.  No. SPRI * (skdp.noSurat)
                             6.  DPJP Pemberi Surat SKDP/SPRI * (skdp.kodeDPJP)
                             7.  (yyyy-mm-dd) Tgl. SEP
                             8.  No. MR + Peserta COB
                             9.  Kelas Rawat (auto dari BPJS)
                             10. Diagnosa (LOV)
                             11. No. Telepon
                             12. Catatan
                             13. Katarak (toggle)
                             14. Status Kecelakaan (KLL)
                             ==================================================== --}}
                        @if ($activeTab === 'sep')
                            <div wire:key="{{ $this->renderKey('form-sep', [$formMode]) }}"
                                class="p-5 bg-white rounded-b-xl shadow dark:bg-gray-800">

                                <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    SEP — Rawat Inap (jnsPelayanan: 1)
                                </h3>

                                @if (empty($SPRIForm['noSPRIBPJS']))
                                    <div
                                        class="mb-4 px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                                        ⚠ SPRI belum ada. Disarankan buat SPRI dahulu agar No. SPRI, SKDP, dan DPJP
                                        terisi otomatis.
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

                                    {{-- 1. Asal Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Asal Rujukan" />
                                        <x-select-input wire:model.live="SEPForm.rujukan.asalRujukan" class="w-full"
                                            :disabled="$isFormLocked">
                                            @foreach ($asalRujukanOptions as $opt)
                                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    {{-- 2. PPK Asal Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="PPK Asal Rujukan *" />
                                        <x-text-input wire:model="SEPForm.rujukan.ppkRujukan" class="w-full"
                                            :disabled="true" placeholder="Kode PPK perujuk" />
                                        @if (!empty($SEPForm['rujukan']['ppkRujukanNama']))
                                            <p class="mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                                                {{ $SEPForm['rujukan']['ppkRujukanNama'] }}</p>
                                        @endif
                                    </div>

                                    {{-- 3. Tgl. Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="(dd/mm/yyyy) Tgl. Rujukan" />
                                        <x-text-input wire:model="SEPForm.rujukan.tglRujukan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="dd/mm/yyyy (auto dari SPRI)" />
                                    </div>

                                    {{-- 4. No. Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. Rujukan *" />
                                        <x-text-input wire:model="SEPForm.rujukan.noRujukan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Nomor rujukan" />
                                    </div>

                                    {{-- 5. No. SPRI --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. SPRI *" />
                                        <x-text-input wire:model="SEPForm.skdp.noSurat" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Auto dari SPRI" />
                                    </div>

                                    {{-- 6. DPJP Pemberi Surat SKDP/SPRI --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="DPJP Pemberi Surat SKDP/SPRI *" />
                                        <x-text-input wire:model="SEPForm.skdp.kodeDPJP" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Auto dari SPRI / LOV Dokter" />
                                    </div>

                                    {{-- 7. Tgl. SEP --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="(dd/mm/yyyy) Tgl. SEP" />
                                        <x-text-input wire:model="SEPForm.tglSep" class="w-full" :disabled="$isFormLocked"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('SEPForm.tglSep')" />
                                        <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                    </div>

                                    {{-- Tombol Muat Kelas Rawat --}}
                                    <div class="lg:col-span-2 flex items-end">
                                        <x-secondary-button type="button" wire:click="fetchKlasRawat"
                                            :disabled="$isFormLocked" class="w-full text-xs gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            ↺ Muat Kelas Rawat dari BPJS
                                        </x-secondary-button>
                                    </div>

                                    {{-- 8. No. MR + Peserta COB --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. MR *" />
                                        <div class="flex items-center gap-3 mt-1">
                                            <x-text-input wire:model="SEPForm.noMR" class="flex-1"
                                                :disabled="true" />
                                            <label class="inline-flex items-center gap-2 cursor-pointer whitespace-nowrap">
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Peserta COB</span>
                                                <button type="button" role="switch"
                                                    aria-checked="{{ $SEPForm['cob']['cob'] == '1' ? 'true' : 'false' }}"
                                                    wire:click="$set('SEPForm.cob.cob', '{{ $SEPForm['cob']['cob'] == '1' ? '0' : '1' }}')"
                                                    {{ $isFormLocked ? 'disabled' : '' }}
                                                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $SEPForm['cob']['cob'] == '1' ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600' }} {{ $isFormLocked ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $SEPForm['cob']['cob'] == '1' ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                </button>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- 9. Kelas Rawat (auto dari BPJS) --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Kelas Rawat Hak *" />
                                        <x-select-input wire:model="SEPForm.klsRawat.klsRawatHak" class="w-full"
                                            :disabled="true" :error="$errors->has('SEPForm.klsRawat.klsRawatHak')">
                                            <option value="">-- Memuat... --</option>
                                            <option value="1">Kelas 1</option>
                                            <option value="2">Kelas 2</option>
                                            <option value="3">Kelas 3</option>
                                        </x-select-input>
                                        @if (empty($SEPForm['klsRawat']['klsRawatHak']))
                                            <p class="mt-1 text-xs text-amber-500">
                                                <span wire:loading wire:target="fetchKlasRawat">Sedang memuat kelas
                                                    rawat...</span>
                                                <span wire:loading.remove wire:target="fetchKlasRawat">Belum termuat.
                                                    Klik tombol "↺ Muat Kelas Rawat".</span>
                                            </p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SEPForm.klsRawat.klsRawatHak')" class="mt-1" />
                                    </div>

                                    {{-- LOV Dokter DPJP --}}
                                    <div class="lg:col-span-4">
                                        <livewire:lov.dokter.lov-dokter label="DPJP yang Melayani (Rawat Inap) *"
                                            target="riFormDokterVclaim" :initialDrId="$drId ?? null" :disabled="$isFormLocked" />
                                        @if (!empty($SEPForm['dpjpLayan']))
                                            <p class="mt-1 text-xs text-gray-400">Kode DPJP BPJS: <span
                                                    class="font-mono font-semibold">{{ $SEPForm['dpjpLayan'] }}</span>
                                            </p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                    </div>

                                    {{-- Kode Poli BPJS (read only) --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Kode Poli BPJS" />
                                        <x-text-input wire:model="SEPForm.poli.tujuan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Auto dari LOV Dokter / SPRI"
                                            :error="$errors->has('SEPForm.poli.tujuan')" />
                                        <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                    </div>

                                    <div class="lg:col-span-2 flex items-end pb-1">
                                        <x-toggle wire:model="SEPForm.poli.eksekutif" trueValue="1" falseValue="0"
                                            label="Poli Eksekutif" :disabled="$isFormLocked" />
                                    </div>

                                    {{-- 10. Diagnosa (LOV) --}}
                                    <div class="lg:col-span-4" x-ref="lovDiagnosaVclaim">
                                        <livewire:lov.diagnosa.lov-diagnosa label="Diagnosa *"
                                            target="riFormDiagnosaVclaim" :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked" />
                                        @if (!empty($SEPForm['diagAwal']))
                                            <p class="mt-1 text-xs text-gray-400">Kode ICD-10: <span
                                                    class="font-mono font-semibold">{{ $SEPForm['diagAwal'] }}</span>
                                            </p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                    </div>

                                    {{-- 11. No. Telepon --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. Telepon *" />
                                        <x-text-input wire:model="SEPForm.noTelp" class="w-full" :disabled="$isFormLocked"
                                            placeholder="08xxxx" :error="$errors->has('SEPForm.noTelp')" />
                                        <x-input-error :messages="$errors->get('SEPForm.noTelp')" class="mt-1" />
                                    </div>

                                    {{-- 12. Catatan --}}
                                    <div class="lg:col-span-4">
                                        <x-input-label value="Catatan" />
                                        <x-textarea wire:model="SEPForm.catatan" class="w-full" rows="2"
                                            :disabled="$isFormLocked" placeholder="Catatan (opsional)" />
                                    </div>

                                    {{-- 13. Katarak (toggle sesuai VClaim) --}}
                                    <div
                                        class="lg:col-span-2 flex items-center gap-3 p-3 border rounded-lg bg-gray-50 dark:bg-gray-700/30">
                                        <x-toggle wire:model="SEPForm.katarak.katarak" trueValue="1" falseValue="0"
                                            label="Katarak" :disabled="$isFormLocked" />
                                        <p class="text-xs text-gray-400">Centang jika peserta mendapatkan Surat
                                            Perjanjian Katarak</p>
                                    </div>

                                    {{-- Kelas Rawat Naik + Pembiayaan (accordion) --}}
                                    <div class="lg:col-span-4" x-data="{ open: false }">
                                        <button type="button" @click="open = !open"
                                            class="flex items-center justify-between w-full px-3 py-2 text-xs font-medium text-left text-gray-600 bg-gray-100 rounded dark:bg-gray-700/50 dark:text-gray-300">
                                            <span>Kelas Rawat Naik & Pembiayaan (opsional)</span>
                                            <svg x-bind:class="open ? 'rotate-180' : ''"
                                                class="w-3 h-3 transition-transform" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="open" x-collapse
                                            class="grid grid-cols-2 gap-3 p-3 lg:grid-cols-4">
                                            <div>
                                                <x-input-label value="Kelas Rawat Naik" />
                                                <x-select-input wire:model="SEPForm.klsRawat.klsRawatNaik"
                                                    class="w-full" :disabled="$isFormLocked">
                                                    <option value="">Tidak Naik</option>
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
                                            <div>
                                                <x-input-label value="Pembiayaan" />
                                                <x-select-input wire:model="SEPForm.klsRawat.pembiayaan"
                                                    class="w-full" :disabled="$isFormLocked">
                                                    <option value="">Pilih</option>
                                                    <option value="1">Pribadi</option>
                                                    <option value="2">Pemberi Kerja</option>
                                                    <option value="3">Asuransi Tambahan</option>
                                                </x-select-input>
                                            </div>
                                            <div class="lg:col-span-2">
                                                <x-input-label value="Penanggung Jawab" />
                                                <x-text-input wire:model="SEPForm.klsRawat.penanggungJawab"
                                                    class="w-full" :disabled="$isFormLocked" />
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 14. Status Kecelakaan (KLL) --}}
                                    <div class="lg:col-span-4 p-3 border rounded-lg bg-gray-50 dark:bg-gray-700/30">
                                        <h4 class="flex items-center gap-2 mb-3 text-sm font-medium">
                                            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            Status Kecelakaan *
                                        </h4>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                            <div>
                                                <x-input-label value="Laka Lantas" />
                                                <x-select-input wire:model.live="SEPForm.jaminan.lakaLantas"
                                                    class="w-full" :disabled="$isFormLocked">
                                                    <option value="0">Bukan KLL</option>
                                                    <option value="1">KLL bukan Kecelakaan Kerja</option>
                                                    <option value="2">KLL dan KK</option>
                                                    <option value="3">KK</option>
                                                </x-select-input>
                                            </div>
                                            @if ($SEPForm['jaminan']['lakaLantas'] !== '0')
                                                <div>
                                                    <x-input-label value="No. LP" />
                                                    <x-text-input wire:model="SEPForm.jaminan.noLP" class="w-full"
                                                        :disabled="$isFormLocked" />
                                                </div>
                                                <div>
                                                    <x-input-label value="Tgl Kejadian (yyyy-mm-dd)" />
                                                    <x-text-input wire:model="SEPForm.jaminan.penjamin.tglKejadian"
                                                        class="w-full" placeholder="yyyy-mm-dd" :disabled="$isFormLocked" />
                                                </div>
                                                <div>
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
                                                    <x-input-label value="Lokasi Kejadian *" />
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

                                    {{-- Tujuan Kunjungan + Flag --}}
                                    <div>
                                        <x-input-label value="Tujuan Kunjungan" />
                                        <x-select-input wire:model.live="SEPForm.tujuanKunj" class="w-full"
                                            :disabled="$isFormLocked">
                                            @foreach ($tujuanKunjOptions as $opt)
                                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                    @if ($SEPForm['tujuanKunj'] !== '0')
                                        <div>
                                            <x-input-label value="Flag Procedure" />
                                            <x-select-input wire:model="SEPForm.flagProcedure" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($flagProcedureOptions as $opt)
                                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Kode Penunjang" />
                                            <x-select-input wire:model="SEPForm.kdPenunjang" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($kdPenunjangOptions as $opt)
                                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif
                                    @if ($SEPForm['tujuanKunj'] === '2')
                                        <div class="lg:col-span-2">
                                            <x-input-label value="Assesment Pelayanan" />
                                            <x-select-input wire:model="SEPForm.assesmentPel" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($assesmentPelOptions as $opt)
                                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif

                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($activeTab === 'spri')
                            SPRI: Push langsung ke BPJS. Nomor SPRI otomatis mengisi No. SPRI & SKDP di form SEP.
                        @else
                            SEP: Disimpan ke form pendaftaran. Dikirim ke BPJS saat klik <strong>Simpan</strong>
                            pendaftaran.
                        @endif
                    </p>
                    <div class="flex gap-2 shrink-0">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        @if ($activeTab === 'spri')
                            <x-primary-button type="button" wire:click="simpanSPRI" wire:loading.attr="disabled"
                                class="!bg-purple-600 hover:!bg-purple-700 focus:!ring-purple-500">
                                <span wire:loading.remove>
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    {{ empty($SPRIForm['noSPRIBPJS']) ? 'Insert SPRI ke BPJS' : 'Update SPRI ke BPJS' }}
                                </span>
                                <span wire:loading><x-loading /> Memproses...</span>
                            </x-primary-button>
                        @else
                            @if ($isFormLocked)
                                {{-- SEP sudah ada: tombol Edit & Update --}}
                                <x-secondary-button type="button" wire:click="$set('isFormLocked', false)">
                                    Edit SEP
                                </x-secondary-button>
                            @else
                                @if (!empty($sepData['noSep']))
                                    {{-- Mode edit SEP: update ke BPJS --}}
                                    <x-primary-button type="button" wire:click="updateSEP"
                                        wire:loading.attr="disabled" x-ref="btnSimpanSEP">
                                        <span wire:loading.remove>Update SEP ke BPJS</span>
                                        <span wire:loading><x-loading /> Mengupdate...</span>
                                    </x-primary-button>
                                @else
                                    {{-- Belum ada SEP: buat baru --}}
                                    <x-primary-button type="button" wire:click="generateSEP"
                                        wire:loading.attr="disabled" x-ref="btnSimpanSEP">
                                        <span wire:loading.remove>
                                            <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                            </svg>
                                            Buat SEP
                                        </span>
                                        <span wire:loading><x-loading /> Mengirim ke BPJS...</span>
                                    </x-primary-button>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
