<?php
// resources/views/pages/transaksi/ri/daftar-ri/vclaim-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use VclaimTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'form-sep', 'form-spri', 'info-pasien'];

    /* ---- State dasar ---- */
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
    public string $activeTab = 'spri'; // mulai dari SPRI dulu sesuai alur RI
    public array $dataPasien = [];

    /* ============================
     | SEP FORM
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
            'asalRujukan' => '1', // RI: default FKTP
            'asalRujukanNama' => 'Faskes Tingkat 1 (FKTP)',
            'tglRujukan' => '',
            'noRujukan' => '', // diisi dari SPRI
            'ppkRujukan' => '',
            'ppkRujukanNama' => '',
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
        'skdp' => ['noSurat' => '', 'kodeDPJP' => ''], // diisi dari SPRI
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
        'tglKontrol' => '',
        'poliKontrol' => '',
        'poliKontrolBPJS' => '',
        'poliKontrolDesc' => '',
        'drKontrol' => '',
        'drKontrolBPJS' => '',
        'drKontrolDesc' => '',
        'noKartu' => '',
        'catatan' => '',
    ];

    /* ---- Persisted data ---- */
    public array $sepData = ['noSep' => '', 'reqSep' => [], 'resSep' => []];
    public array $spriData = [];

    /* ---- Select options ---- */
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
        $this->SPRIForm['tglKontrol'] = Carbon::now()->addDays(7)->format('d/m/Y');
        $this->registerAreas(['modal', 'form-sep', 'form-spri', 'info-pasien']);
    }

    /* ===============================
     | OPEN dari parent daftar-ri-actions
     =============================== */
    #[On('open-vclaim-modal-ri')]
    public function handleOpenVclaimModal(?string $riHdrNo = null, ?string $regNo = null, ?string $drId = null, ?string $drDesc = null, ?string $poliId = null, ?string $poliDesc = null, ?string $kdpolibpjs = null, ?string $noReferensi = null, array $sepData = [], array $spriData = []): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->regNo = $regNo;
        $this->poliId = $poliId;
        $this->poliDesc = $poliDesc;
        $this->noReferensi = $noReferensi;
        $this->formMode = $riHdrNo ? 'edit' : 'create';

        /* ---- Restore DPJP dari reqSep ---- */
        $tSep = $sepData['reqSep']['request']['t_sep'] ?? [];
        if (!empty($tSep['dpjpLayan'])) {
            $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $tSep['dpjpLayan'])->select('dr_id', 'dr_name')->first();
            $this->drId = $dokter->dr_id ?? $drId;
            $this->drDesc = $dokter->dr_name ?? $drDesc;
        } else {
            $this->drId = $drId;
            $this->drDesc = $drDesc;
        }

        /* ---- Load data pasien ---- */
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
            if (!empty($tSep['tglSep'])) {
                $this->SEPForm['tglSep'] = Carbon::parse($tSep['tglSep'])->format('d/m/Y');
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

        /* ---- Sync SKDP dari SPRI yang sudah ada ---- */
        if (!empty($spriData['noSPRIBPJS'])) {
            $this->SEPForm['skdp']['noSurat'] = $spriData['noSPRIBPJS'];
            $this->SEPForm['skdp']['kodeDPJP'] = $spriData['drKontrolBPJS'] ?? '';
            $this->SEPForm['rujukan']['noRujukan'] = $spriData['noSPRIBPJS'];
        }

        /* ---- Jika SPRI sudah ada, langsung ke tab SEP ---- */
        $this->activeTab = !empty($spriData['noSPRIBPJS']) ? 'sep' : 'spri';

        $this->resetVersion();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'vclaim-ri-actions');
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
     =============================== */
    public function fetchKlasRawat(): void
    {
        if (empty($this->SEPForm['noKartu'])) {
            $this->dispatch('toast', type: 'error', message: 'Nomor Kartu BPJS kosong.');
            return;
        }
        try {
            $tgl = Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d');
            $response = VclaimTrait::peserta_nomorkartu($this->SEPForm['noKartu'], $tgl)->getOriginalContent();
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

    /* ===============================
     | BPJS: fetch data SPRI existing
     =============================== */
    public function fetchDataSPRI(): void
    {
        $noSPRI = trim($this->SPRIForm['noSPRIBPJS'] ?? '');
        if (empty($noSPRI)) {
            $this->dispatch('toast', type: 'error', message: 'Isi Nomor SPRI BPJS terlebih dahulu.');
            return;
        }
        try {
            $response = VclaimTrait::suratkontrol_nomor($noSPRI)->getOriginalContent();
            if (($response['metadata']['code'] ?? 500) == 200) {
                $data = json_decode(json_encode($response['response'], true), true);

                // Sync ke SPRI form
                $this->SPRIForm['tglKontrol'] = isset($data['tglRencanaKontrol']) ? Carbon::parse($data['tglRencanaKontrol'])->format('d/m/Y') : $this->SPRIForm['tglKontrol'];
                $this->SPRIForm['drKontrolBPJS'] = $data['kodeDokter'] ?? '';

                // Sync ke SEP form
                $this->SEPForm['rujukan']['noRujukan'] = $data['noSuratKontrol'] ?? $noSPRI;
                $this->SEPForm['rujukan']['tglRujukan'] = $data['tglRencanaKontrol'] ?? '';
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

    /* ===============================
     | Updated hooks
     =============================== */
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
     | SIMPAN SPRI — push ke BPJS & sync ke SEP form
     |
     | Insert jika noSPRIBPJS kosong, update jika sudah ada.
     =============================== */
    public function simpanSPRI(): void
    {
        $this->validateSPRIForm();
        $this->setDataPrimerSPRI();

        $isUpdate = !empty($this->SPRIForm['noSPRIBPJS']);

        try {
            $response = $isUpdate ? VclaimTrait::spri_update($this->SPRIForm)->getOriginalContent() : VclaimTrait::spri_insert($this->SPRIForm)->getOriginalContent();

            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '';

            if ($code == 200) {
                // Ambil noSPRI dari response insert
                if (!$isUpdate) {
                    $this->SPRIForm['noSPRIBPJS'] = $response['response']['noSPRI'] ?? '';
                }

                // ====================================================
                // AUTO-SYNC noSPRI → SEP form (skdp & rujukan)
                // ====================================================
                $this->SEPForm['skdp']['noSurat'] = $this->SPRIForm['noSPRIBPJS'];
                $this->SEPForm['skdp']['kodeDPJP'] = $this->SPRIForm['drKontrolBPJS'] ?? '';
                $this->SEPForm['rujukan']['noRujukan'] = $this->SPRIForm['noSPRIBPJS'];
                $this->SEPForm['rujukan']['tglRujukan'] = Carbon::createFromFormat('d/m/Y', $this->SPRIForm['tglKontrol'])->format('Y-m-d');

                // Auto-isi dpjpLayan dari dokter kontrol jika SEP belum punya DPJP
                if (empty($this->SEPForm['dpjpLayan']) && !empty($this->SPRIForm['drKontrolBPJS'])) {
                    $this->SEPForm['dpjpLayan'] = $this->SPRIForm['drKontrolBPJS'];
                }
                // Auto-isi poli.tujuan dari poli kontrol jika belum ada
                if (empty($this->SEPForm['poli']['tujuan']) && !empty($this->SPRIForm['poliKontrolBPJS'])) {
                    $this->SEPForm['poli']['tujuan'] = $this->SPRIForm['poliKontrolBPJS'];
                }

                // Dispatch ke parent agar spriData tersimpan di dataDaftarRi
                $this->dispatch('spri-generated-ri', spriData: $this->SPRIForm);
                $this->dispatch('toast', type: 'success', message: ($isUpdate ? 'Update' : 'Insert') . " SPRI berhasil ({$code}): {$msg}");

                // Pindah ke tab SEP secara otomatis
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
     | GENERATE SEP — simpan reqSep ke parent (belum push ke BPJS)
     | Push ke BPJS terjadi di daftar-ri-actions saat klik Simpan pendaftaran.
     =============================== */
    public function generateSEP(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'warning', message: 'SEP sudah terbentuk, tidak dapat diubah.');
            return;
        }
        $this->validateSEPForm();
        $request = $this->buildSEPRequest();
        $this->dispatch('sep-generated-ri', reqSep: $request);
        $this->dispatch('toast', type: 'success', message: 'Data SEP RI berhasil disimpan ke form pendaftaran.');
        $this->closeModal();
    }

    private function validateSEPForm(): void
    {
        $this->validate(
            [
                'SEPForm.noKartu' => 'required',
                'SEPForm.tglSep' => 'required|date_format:d/m/Y',
                'SEPForm.noMR' => 'required',
                'SEPForm.diagAwal' => 'required',
                'SEPForm.poli.tujuan' => 'required',
                'SEPForm.dpjpLayan' => 'required',
            ],
            [
                'SEPForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi.',
                'SEPForm.tglSep.required' => 'Tanggal SEP wajib diisi.',
                'SEPForm.tglSep.date_format' => 'Format Tanggal SEP harus dd/mm/yyyy.',
                'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi.',
                'SEPForm.poli.tujuan.required' => 'Kode poli BPJS harus diisi.',
                'SEPForm.dpjpLayan.required' => 'DPJP harus diisi.',
            ],
        );
    }

    private function buildSEPRequest(): array
    {
        return [
            'request' => [
                't_sep' => [
                    'noKartu' => $this->SEPForm['noKartu'] ?? '',
                    'tglSep' => Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d'),
                    'ppkPelayanan' => $this->SEPForm['ppkPelayanan'] ?? '0184R006',
                    'jnsPelayanan' => '1',
                    'klsRawat' => [
                        'klsRawatHak' => $this->SEPForm['klsRawat']['klsRawatHak'] ?? '',
                        'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $this->SEPForm['noMR'] ?? '',
                    'rujukan' => [
                        'asalRujukan' => $this->SEPForm['rujukan']['asalRujukan'] ?? '1',
                        'tglRujukan' => $this->SEPForm['rujukan']['tglRujukan'] ?? '',
                        'noRujukan' => $this->SEPForm['rujukan']['noRujukan'] ?? '',
                        'ppkRujukan' => $this->SEPForm['rujukan']['ppkRujukan'] ?? '',
                        'ppkRujukanNama' => $this->SEPForm['rujukan']['ppkRujukanNama'] ?? '',
                    ],
                    'catatan' => $this->SEPForm['catatan'] ?: '-',
                    'diagAwal' => $this->SEPForm['diagAwal'] ?? '',
                    'poli' => [
                        'tujuan' => $this->SEPForm['poli']['tujuan'] ?? '',
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
                    'dpjpLayan' => $this->SEPForm['dpjpLayan'] ?? '',
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
     =============================== */
    public function deleteSEP(): void
    {
        if (empty($this->sepData['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk dihapus.');
            return;
        }
        try {
            $response = VclaimTrait::sep_delete($this->sepData['noSep'])->getOriginalContent();
            $code = data_get($response, 'metadata.code');
            $msg = data_get($response, 'metadata.message', 'Tidak ada pesan');
            if (in_array($code, [200, 201])) {
                $this->sepData = ['noSep' => '', 'reqSep' => [], 'resSep' => []];
                $this->isFormLocked = false;
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
     | LOV LISTENERS
     =============================== */

    /* Dokter DPJP untuk form SEP */
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

    /* Diagnosa untuk form SEP */
    #[On('lov.selected.riFormDiagnosaVclaim')]
    public function riFormDiagnosaVclaim(string $target, array $payload): void
    {
        $this->diagnosaId = $payload['icdx'] ?? null;
        $this->SEPForm['diagAwal'] = $payload['icdx'] ?? '';
        $this->incrementVersion('form-sep');
        $this->dispatch('focus-vclaim-ri-simpan');
    }

    /* Dokter kontrol untuk form SPRI */
    #[On('lov.selected.riFormDokterSPRI')]
    public function riFormDokterSPRI(string $target, array $payload): void
    {
        $this->SPRIForm['drKontrol'] = $payload['dr_id'] ?? '';
        $this->SPRIForm['drKontrolDesc'] = $payload['dr_name'] ?? '';
        $this->SPRIForm['drKontrolBPJS'] = $payload['kd_dr_bpjs'] ?? '';
        $this->SPRIForm['poliKontrol'] = $payload['poli_id'] ?? '';
        $this->SPRIForm['poliKontrolDesc'] = $payload['poli_desc'] ?? '';
        $this->SPRIForm['poliKontrolBPJS'] = $payload['kd_poli_bpjs'] ?? '';

        // Auto-sync ke SEP form jika DPJP belum terisi
        if (empty($this->SEPForm['dpjpLayan'])) {
            $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';
            $this->SEPForm['skdp']['kodeDPJP'] = $payload['kd_dr_bpjs'] ?? '';
        }
        if (empty($this->SEPForm['poli']['tujuan'])) {
            $this->SEPForm['poli']['tujuan'] = $payload['kd_poli_bpjs'] ?? '';
        }
        $this->incrementVersion('form-spri');
        $this->incrementVersion('form-sep');
    }

    /* ===============================
     | Reset & Close
     =============================== */
    private function resetFormData(): void
    {
        $this->reset(['SEPForm', 'SPRIForm', 'diagnosaId', 'dataPasien', 'sepData', 'spriData']);
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->SEPForm['jnsPelayanan'] = '1';
        $this->SPRIForm['tglKontrol'] = Carbon::now()->addDays(7)->format('d/m/Y');
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

<div>
    <x-modal name="vclaim-ri-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $riHdrNo ?? 'new']) }}">

            {{-- ============================================================
                 HEADER
                 ============================================================ --}}
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
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Kelola SPRI & SEP — Rawat Inap
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Surat Perintah Rawat Inap → Surat Eligibilitas Peserta BPJS (jnsPelayanan: 1)
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Buat' }}
                            </x-badge>
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
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ============================================================
                 BODY
                 ============================================================ --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-vclaim-ri-diagnosa.window="$nextTick(() => setTimeout(() => $refs.lovDiagnosaVclaim?.querySelector('input')?.focus(), 150))"
                x-on:focus-vclaim-ri-simpan.window="$nextTick(() => setTimeout(() => $refs.btnSimpanSEP?.focus(), 150))">

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">

                    {{-- ============================================================
                         PANEL KIRI: Info Pasien + Ringkasan Status
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
                                    {{ $SPRIForm['noSPRIBPJS'] }}
                                </p>
                                <div class="mt-1 text-xs text-purple-600 dark:text-purple-400 space-y-0.5">
                                    <p>Dr: {{ $SPRIForm['drKontrolDesc'] ?? '-' }}</p>
                                    <p>Poli: {{ $SPRIForm['poliKontrolDesc'] ?? '-' }}</p>
                                    <p>Tgl Kontrol: {{ $SPRIForm['tglKontrol'] ?? '-' }}</p>
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
                                    {{ $sepData['noSep'] }}
                                </p>
                                @if (!empty($sepData['resSep']['tglSEP']))
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                        Tgl: {{ Carbon::parse($sepData['resSep']['tglSEP'])->format('d/m/Y') }}
                                    </p>
                                @endif
                                <div class="mt-2">
                                    <x-danger-button type="button" wire:click="deleteSEP"
                                        wire:confirm="Yakin hapus SEP ini dari server BPJS?"
                                        class="w-full text-xs gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Hapus SEP dari BPJS
                                    </x-danger-button>
                                </div>
                            @else
                                <p class="text-xs text-gray-400">Buat SEP di tab SEP setelah SPRI selesai.</p>
                            @endif
                        </div>

                        {{-- Info alur --}}
                        <div
                            class="p-3 text-xs bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-800 text-blue-700 dark:text-blue-300 space-y-1">
                            <p class="font-semibold">Alur RI:</p>
                            <div class="flex items-center gap-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold {{ !empty($SPRIForm['noSPRIBPJS']) ? 'bg-purple-500' : 'bg-gray-300' }}">1</span>
                                <span>SPRI ke BPJS</span>
                                @if (!empty($SPRIForm['noSPRIBPJS']))
                                    <span class="text-green-600">✓</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold {{ !empty($SEPForm['diagAwal']) ? 'bg-blue-500' : 'bg-gray-300' }}">2</span>
                                <span>Isi Data SEP</span>
                                @if (!empty($SEPForm['diagAwal']))
                                    <span class="text-green-600">✓</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold {{ !empty($sepData['noSep']) ? 'bg-green-500' : 'bg-gray-300' }}">3</span>
                                <span>Push SEP (saat Simpan pendaftaran)</span>
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
                            {{-- Tab SPRI --}}
                            <button type="button" wire:click="$set('activeTab', 'spri')"
                                class="flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                    {{ $activeTab === 'spri'
                                        ? 'border-purple-500 text-purple-600 dark:text-purple-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                SPRI
                                @if (!empty($SPRIForm['noSPRIBPJS']))
                                    <span class="w-2 h-2 rounded-full bg-purple-500 shrink-0"></span>
                                @endif
                            </button>
                            {{-- Tab SEP --}}
                            <button type="button" wire:click="$set('activeTab', 'sep')"
                                class="flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                    {{ $activeTab === 'sep'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                SEP
                                @if (!empty($sepData['noSep']))
                                    <span class="w-2 h-2 rounded-full bg-green-500 shrink-0"></span>
                                @elseif (!empty($SEPForm['diagAwal']))
                                    <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                                @endif
                            </button>
                        </div>

                        {{-- ====================================================
                             TAB SPRI
                             ==================================================== --}}
                        @if ($activeTab === 'spri')
                            <div wire:key="{{ $this->renderKey('form-spri', []) }}"
                                class="p-5 bg-white rounded-b-xl shadow dark:bg-gray-800">

                                <div class="flex items-center justify-between mb-4">
                                    <h3
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        Surat Perintah Rawat Inap (SPRI) BPJS
                                    </h3>
                                    <x-badge :variant="!empty($SPRIForm['noSPRIBPJS']) ? 'warning' : 'success'">
                                        {{ !empty($SPRIForm['noSPRIBPJS']) ? 'Update Mode' : 'Insert Mode' }}
                                    </x-badge>
                                </div>

                                {{-- Info alur SPRI → SEP --}}
                                <div
                                    class="mb-4 px-3 py-2 text-xs text-purple-700 bg-purple-50 border border-purple-200 rounded-lg dark:bg-purple-900/20 dark:border-purple-800 dark:text-purple-300">
                                    Setelah SPRI berhasil disimpan, nomor SPRI otomatis mengisi
                                    <strong>skdp.noSurat</strong> dan <strong>rujukan.noRujukan</strong> pada form SEP.
                                    kodeDPJP dokter kontrol juga otomatis tersync.
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

                                    {{-- No SPRI BPJS + tombol fetch --}}
                                    <div>
                                        <x-input-label value="No. SPRI BPJS" />
                                        <div class="flex gap-2 mt-1">
                                            <x-text-input wire:model="SPRIForm.noSPRIBPJS" class="flex-1"
                                                placeholder="Kosong = Insert baru" />
                                            <x-secondary-button type="button" wire:click="fetchDataSPRI"
                                                title="Fetch data SPRI existing dari BPJS" class="shrink-0 px-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </x-secondary-button>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-400">Isi lalu klik tombol untuk ambil data
                                            dari BPJS.</p>
                                    </div>

                                    {{-- No Kontrol RS --}}
                                    <div>
                                        <x-input-label value="No. Kontrol RS" />
                                        <x-text-input wire:model="SPRIForm.noKontrolRS" class="w-full mt-1"
                                            placeholder="Auto-generate jika kosong" />
                                    </div>

                                    {{-- Tgl Kontrol --}}
                                    <div>
                                        <x-input-label value="Tgl Rencana Kontrol" />
                                        <x-text-input wire:model="SPRIForm.tglKontrol" class="w-full mt-1"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('SPRIForm.tglKontrol')" />
                                        <x-input-error :messages="$errors->get('SPRIForm.tglKontrol')" class="mt-1" />
                                    </div>

                                    {{-- No Kartu --}}
                                    <div>
                                        <x-input-label value="No. Kartu BPJS Pasien" />
                                        <x-text-input wire:model="SPRIForm.noKartu" class="w-full mt-1"
                                            :disabled="true" />
                                        <x-input-error :messages="$errors->get('SPRIForm.noKartu')" class="mt-1" />
                                    </div>

                                    {{-- LOV Dokter Kontrol --}}
                                    <div class="lg:col-span-2">
                                        <livewire:lov.dokter.lov-dokter label="Cari Dokter Kontrol SPRI"
                                            target="riFormDokterSPRI" :initialDrId="$SPRIForm['drKontrol'] ?? null" />
                                    </div>

                                    {{-- Info dokter & poli hasil LOV --}}
                                    <div>
                                        <x-input-label value="Dokter Kontrol" />
                                        <x-text-input wire:model="SPRIForm.drKontrolDesc" class="w-full mt-1"
                                            :disabled="true" placeholder="Otomatis dari LOV" />
                                        <x-input-error :messages="$errors->get('SPRIForm.drKontrolDesc')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Kode Dr BPJS" />
                                        <x-text-input wire:model="SPRIForm.drKontrolBPJS" class="w-full mt-1"
                                            :disabled="true" />
                                        <x-input-error :messages="$errors->get('SPRIForm.drKontrolBPJS')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Poli Kontrol" />
                                        <x-text-input wire:model="SPRIForm.poliKontrolDesc" class="w-full mt-1"
                                            :disabled="true" placeholder="Otomatis dari LOV Dokter" />
                                        <x-input-error :messages="$errors->get('SPRIForm.poliKontrolDesc')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Kode Poli BPJS" />
                                        <x-text-input wire:model="SPRIForm.poliKontrolBPJS" class="w-full mt-1"
                                            :disabled="true" />
                                    </div>

                                    {{-- Catatan --}}
                                    <div class="lg:col-span-3">
                                        <x-input-label value="Catatan SPRI (opsional)" />
                                        <x-text-input wire:model="SPRIForm.catatan" class="w-full mt-1"
                                            placeholder="Catatan rencana kontrol" />
                                    </div>

                                    {{-- Preview sync ke SEP --}}
                                    @if (!empty($SPRIForm['drKontrol']))
                                        <div class="lg:col-span-3">
                                            <div
                                                class="px-3 py-2 text-xs border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 text-blue-700 dark:text-blue-300">
                                                <span class="font-semibold">Preview sync ke SEP setelah
                                                    disimpan:</span>
                                                <span class="ml-2">
                                                    skdp.noSurat =
                                                    <strong>{{ $SPRIForm['noSPRIBPJS'] ?: '(no SPRI baru)' }}</strong>
                                                    &nbsp;·&nbsp;
                                                    skdp.kodeDPJP =
                                                    <strong>{{ $SPRIForm['drKontrolBPJS'] ?: '-' }}</strong>
                                                    &nbsp;·&nbsp;
                                                    dpjpLayan =
                                                    <strong>{{ $SPRIForm['drKontrolBPJS'] ?: '-' }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                    @endif

                                </div>
                            </div>
                        @endif

                        {{-- ====================================================
                             TAB SEP
                             ==================================================== --}}
                        @if ($activeTab === 'sep')
                            <div wire:key="{{ $this->renderKey('form-sep', [$formMode]) }}"
                                class="p-5 bg-white rounded-b-xl shadow dark:bg-gray-800">

                                <h3
                                    class="flex items-center gap-2 mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Surat Eligibilitas Peserta (SEP) — Rawat Inap
                                </h3>

                                {{-- Warning jika SPRI belum ada --}}
                                @if (empty($SPRIForm['noSPRIBPJS']))
                                    <div
                                        class="mb-4 px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                                        <svg class="inline w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        SPRI belum dibuat. Disarankan membuat SPRI terlebih dahulu agar
                                        skdp.noSurat & rujukan.noRujukan terisi otomatis.
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

                                    {{-- Identitas --}}
                                    <div>
                                        <x-input-label value="No. Kartu BPJS" />
                                        <x-text-input wire:model="SEPForm.noKartu" class="w-full" :disabled="true"
                                            :error="$errors->has('SEPForm.noKartu')" />
                                        <x-input-error :messages="$errors->get('SEPForm.noKartu')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="No. MR" />
                                        <x-text-input wire:model="SEPForm.noMR" class="w-full" :disabled="true" />
                                    </div>

                                    <div>
                                        <x-input-label value="Tanggal SEP" />
                                        <x-text-input wire:model="SEPForm.tglSep" class="w-full" :disabled="$isFormLocked"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('SEPForm.tglSep')" />
                                        <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                    </div>

                                    <div class="flex flex-col justify-end">
                                        <x-secondary-button type="button" wire:click="fetchKlasRawat"
                                            :disabled="$isFormLocked" class="w-full text-xs gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Muat Kelas Rawat
                                        </x-secondary-button>
                                    </div>

                                    {{-- Kelas Rawat --}}
                                    <div>
                                        <x-input-label value="Kelas Rawat Hak" />
                                        <x-select-input wire:model="SEPForm.klsRawat.klsRawatHak" class="w-full"
                                            :disabled="true">
                                            <option value="">-- Auto dari BPJS --</option>
                                            <option value="1">Kelas 1</option>
                                            <option value="2">Kelas 2</option>
                                            <option value="3">Kelas 3</option>
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label value="Kelas Rawat Naik" />
                                        <x-select-input wire:model="SEPForm.klsRawat.klsRawatNaik" class="w-full"
                                            :disabled="$isFormLocked">
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
                                        <x-select-input wire:model="SEPForm.klsRawat.pembiayaan" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="">Pilih</option>
                                            <option value="1">Pribadi</option>
                                            <option value="2">Pemberi Kerja</option>
                                            <option value="3">Asuransi Tambahan</option>
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label value="Penanggung Jawab" />
                                        <x-text-input wire:model="SEPForm.klsRawat.penanggungJawab" class="w-full"
                                            :disabled="$isFormLocked" />
                                    </div>

                                    {{-- Rujukan --}}
                                    <div>
                                        <x-input-label value="Asal Rujukan" />
                                        <x-select-input wire:model.live="SEPForm.rujukan.asalRujukan" class="w-full"
                                            :disabled="$isFormLocked">
                                            @foreach ($asalRujukanOptions as $opt)
                                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label value="Tgl Rujukan" />
                                        <x-text-input wire:model="SEPForm.rujukan.tglRujukan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="yyyy-mm-dd (auto dari SPRI)" />
                                    </div>

                                    <div>
                                        <x-input-label value="No. Rujukan / No. SPRI" />
                                        <x-text-input wire:model="SEPForm.rujukan.noRujukan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Auto dari SPRI" />
                                    </div>

                                    <div>
                                        <x-input-label value="PPK Rujukan" />
                                        <x-text-input wire:model="SEPForm.rujukan.ppkRujukan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Kode PPK asal rujukan" />
                                    </div>

                                    {{-- LOV Dokter DPJP --}}
                                    <div class="lg:col-span-2">
                                        <livewire:lov.dokter.lov-dokter label="Cari Dokter DPJP RI"
                                            target="riFormDokterVclaim" :initialDrId="$drId ?? null" :disabled="$isFormLocked" />
                                    </div>

                                    <div>
                                        <x-input-label value="DPJP (kode BPJS)" />
                                        <x-text-input wire:model="SEPForm.dpjpLayan" class="w-full" :disabled="true"
                                            placeholder="Otomatis dari LOV / SPRI" :error="$errors->has('SEPForm.dpjpLayan')" />
                                        <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Kode Poli BPJS" />
                                        <x-text-input wire:model="SEPForm.poli.tujuan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Otomatis dari LOV / SPRI"
                                            :error="$errors->has('SEPForm.poli.tujuan')" />
                                        <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                    </div>

                                    {{-- LOV Diagnosa --}}
                                    <div class="lg:col-span-2" x-ref="lovDiagnosaVclaim">
                                        <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa Awal (ICD-10)"
                                            target="riFormDiagnosaVclaim" :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked" />
                                    </div>

                                    <div>
                                        <x-input-label value="Diagnosa Awal (ICD-10)" />
                                        <x-text-input wire:model="SEPForm.diagAwal" class="w-full" :disabled="true"
                                            placeholder="Otomatis dari LOV" :error="$errors->has('SEPForm.diagAwal')" />
                                        <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Poli Eksekutif" />
                                        <x-select-input wire:model="SEPForm.poli.eksekutif" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="0">Tidak</option>
                                            <option value="1">Ya</option>
                                        </x-select-input>
                                    </div>

                                    {{-- SKDP (auto dari SPRI) --}}
                                    <div>
                                        <x-input-label value="SKDP — No. Surat" />
                                        <x-text-input wire:model="SEPForm.skdp.noSurat" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Auto dari SPRI" />
                                    </div>

                                    <div>
                                        <x-input-label value="SKDP — Kode DPJP" />
                                        <x-text-input wire:model="SEPForm.skdp.kodeDPJP" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Auto dari SPRI / LOV" />
                                    </div>

                                    {{-- Tujuan Kunjungan --}}
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

                                    <div>
                                        <x-input-label value="COB" />
                                        <x-select-input wire:model="SEPForm.cob.cob" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="0">Tidak</option>
                                            <option value="1">Ya</option>
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label value="Katarak" />
                                        <x-select-input wire:model="SEPForm.katarak.katarak" class="w-full"
                                            :disabled="$isFormLocked">
                                            <option value="0">Tidak</option>
                                            <option value="1">Ya</option>
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label value="No. Telepon" />
                                        <x-text-input wire:model="SEPForm.noTelp" class="w-full" :disabled="$isFormLocked"
                                            placeholder="08xxxx" />
                                    </div>

                                    <div class="lg:col-span-4">
                                        <x-input-label value="Catatan" />
                                        <x-textarea wire:model="SEPForm.catatan" class="w-full" rows="2"
                                            :disabled="$isFormLocked" placeholder="Catatan (opsional)" />
                                    </div>

                                    {{-- Jaminan KLL --}}
                                    <div class="lg:col-span-4 p-3 border rounded-lg bg-gray-50 dark:bg-gray-700/30">
                                        <h4 class="flex items-center gap-2 mb-3 text-sm font-medium">
                                            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
                                                    <option value="1">KLL dan bukan Kecelakaan Kerja</option>
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
                                                    <x-input-label value="Tgl Kejadian" />
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

                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- ============================================================
                 FOOTER — kontekstual per tab
                 ============================================================ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200
                        dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    {{-- Keterangan kontekstual --}}
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($activeTab === 'spri')
                            SPRI: Push langsung ke BPJS sekarang. Nomor SPRI akan otomatis mengisi form SEP.
                        @else
                            SEP: Data disimpan ke form pendaftaran. Push ke BPJS saat klik <strong>Simpan</strong> di
                            form pendaftaran.
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
                            <x-primary-button type="button" wire:click="generateSEP" wire:loading.attr="disabled"
                                :disabled="$isFormLocked" x-ref="btnSimpanSEP">
                                <span wire:loading.remove>
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan Data SEP ke Form
                                </span>
                                <span wire:loading><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
