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
    public ?string $poliId = null; // selalu 'UGD'
    public ?string $poliDesc = null; // selalu 'Instalasi Gawat Darurat'
    public ?string $kdpolibpjs = null;
    public ?string $kunjunganId = '1'; // UGD: default '1' (darurat, tidak pakai kontrol/internal)
    public ?string $noReferensi = null;
    public ?string $diagnosaId = null;

    // State
    public string $formMode = 'create';
    public bool $isFormLocked = false;
    public bool $showRujukanLov = false;
    public array $dataRujukan = [];
    public array $selectedRujukan = [];
    public array $dataPasien = [];

    // SEP Form
    public array $SEPForm = [
        'noKartu' => '',
        'tglSep' => '',
        'ppkPelayanan' => '0184R006',
        'jnsPelayanan' => '2', // UGD: rawat jalan darurat = 2
        'klsRawat' => [
            'klsRawatHak' => '',
            'klsRawatNaik' => '',
            'pembiayaan' => '',
            'penanggungJawab' => '',
        ],
        'noMR' => '',
        'rujukan' => [
            'asalRujukan' => '2', // UGD: default asal RS (darurat)
            'asalRujukanNama' => 'Faskes Tingkat 2 (RS)',
            'tglRujukan' => '',
            'noRujukan' => '',
            'ppkRujukan' => '0184R006',
            'ppkRujukanNama' => 'RSI Madinah',
        ],
        'catatan' => '',
        'diagAwal' => '',
        'poli' => [
            'tujuan' => 'IGD',
            'eksekutif' => '0',
        ],
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

    public array $sepData = [
        'noSep' => '',
        'reqSep' => [],
        'resSep' => [],
    ];

    // Options
    public array $tujuanKunjOptions = [['id' => '0', 'name' => 'Normal'], ['id' => '1', 'name' => 'Prosedur'], ['id' => '2', 'name' => 'Konsul Dokter']];

    public array $flagProcedureOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '0', 'name' => 'Prosedur Tidak Berkelanjutan'], ['id' => '1', 'name' => 'Prosedur dan Terapi Berkelanjutan']];

    public array $kdPenunjangOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Radioterapi'], ['id' => '2', 'name' => 'Kemoterapi'], ['id' => '3', 'name' => 'Rehabilitasi Medik'], ['id' => '4', 'name' => 'Rehabilitasi Psikososial'], ['id' => '5', 'name' => 'Transfusi Darah'], ['id' => '6', 'name' => 'Pelayanan Gigi'], ['id' => '7', 'name' => 'Laboratorium'], ['id' => '8', 'name' => 'USG'], ['id' => '9', 'name' => 'Farmasi'], ['id' => '10', 'name' => 'Lain-Lain'], ['id' => '11', 'name' => 'MRI'], ['id' => '12', 'name' => 'HEMODIALISA']];

    public array $assesmentPelOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Poli spesialis tidak tersedia pada hari sebelumnya'], ['id' => '2', 'name' => 'Jam Poli telah berakhir pada hari sebelumnya'], ['id' => '3', 'name' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya'], ['id' => '4', 'name' => 'Atas Instruksi RS'], ['id' => '5', 'name' => 'Tujuan Kontrol']];

    /* ===============================
     | OPEN dari parent UGD
     =============================== */
    #[On('open-vclaim-modal-ugd')]
    public function handleOpenVclaimModal(?string $rjNo = null, ?string $regNo = null, ?string $drId = null, ?string $drDesc = null, ?string $poliId = null, ?string $poliDesc = null, ?string $kdpolibpjs = null, ?string $kunjunganId = '1', ?string $noReferensi = null, array $sepData = []): void
    {
        $this->rjNo = $rjNo;
        $this->regNo = $regNo;
        $this->drId = $drId;
        $this->drDesc = $drDesc;
        $this->poliId = $poliId ?? 'UGD';
        $this->poliDesc = $poliDesc ?? 'Instalasi Gawat Darurat';
        $this->kdpolibpjs = $kdpolibpjs;
        $this->kunjunganId = $kunjunganId ?? '1';
        $this->noReferensi = $noReferensi;
        $this->formMode = $rjNo ? 'edit' : 'create';

        $this->loadDataPasien($regNo);

        // Restore dari sepData yang sudah tersimpan
        if (!empty($sepData)) {
            $this->sepData = $sepData;

            if (!empty($sepData['noSep'])) {
                $this->isFormLocked = true;
            }

            if (!empty($sepData['reqSep']['request']['t_sep'])) {
                $this->SEPForm = array_replace_recursive($this->SEPForm, $sepData['reqSep']['request']['t_sep']);
            }

            if (!empty($sepData['reqSep']['request']['t_sep']['tglSep'])) {
                $this->SEPForm['tglSep'] = Carbon::parse($sepData['reqSep']['request']['t_sep']['tglSep'])->format('d/m/Y');
            }

            if (!empty($sepData['reqSep']['request']['t_sep']['diagAwal'])) {
                $this->diagnosaId = $sepData['reqSep']['request']['t_sep']['diagAwal'];
            }

            if (!empty($sepData['reqSep']['request']['t_sep']['rujukan']['noRujukan'])) {
                $this->selectedRujukan = [
                    'noKunjungan' => $sepData['reqSep']['request']['t_sep']['rujukan']['noRujukan'],
                ];
            }
        }

        $this->resetVersion();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'vclaim-ugd-actions');
    }

    /* ===============================
     | Load data pasien
     =============================== */
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
                'identitas' => [
                    'idbpjs' => $data->nokartu_bpjs ?? '',
                    'nik' => $data->nik_bpjs ?? '',
                ],
                'kontak' => [
                    'nomerTelponSelulerPasien' => $data->phone ?? '',
                ],
            ],
        ];

        // Isi default SEPForm dari data pasien
        $this->SEPForm['noKartu'] = $data->nokartu_bpjs ?? '';
        $this->SEPForm['noMR'] = $data->reg_no;
        $this->SEPForm['noTelp'] = $data->phone ?? '';
        $this->SEPForm['dpjpLayan'] = $this->getKdDrBpjs($this->drId);
        $this->SEPForm['poli']['tujuan'] = $this->kdpolibpjs ?? 'IGD';

        // UGD: asal rujukan selalu dari RS sendiri (darurat)
        $this->SEPForm['rujukan']['asalRujukan'] = '2';
        $this->SEPForm['rujukan']['asalRujukanNama'] = 'Faskes Tingkat 2 (RS)';
        $this->SEPForm['rujukan']['ppkRujukan'] = '0184R006';
        $this->SEPForm['rujukan']['ppkRujukanNama'] = 'RSI Madinah';
    }

    private function getKdDrBpjs(?string $drId): string
    {
        if (!$drId) {
            return '';
        }
        return DB::table('rsmst_doctors')->where('dr_id', $drId)->value('kd_dr_bpjs') ?? '';
    }

    /* ===============================
     | Cari Rujukan — UGD bisa cari rujukan FKTL
     |  (pasien datang dari RS lain / dirujuk balik)
     =============================== */
    public function cariRujukan(): void
    {
        $idBpjs = $this->dataPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (empty($idBpjs)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor BPJS tidak ditemukan.');
            return;
        }

        $this->showRujukanLov = true;
        $this->dataRujukan = [];

        // UGD umumnya rujukan dari RS lain (FKTL) atau tanpa rujukan (darurat)
        // Coba FKTL dulu, fallback ke FKTP
        $response = VclaimTrait::rujukan_rs_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200 && !empty($response['response']['rujukan'])) {
            $this->dataRujukan = $response['response']['rujukan'];
        } else {
            // Fallback ke rujukan FKTP
            $response = VclaimTrait::rujukan_peserta($idBpjs)->getOriginalContent();

            if ($response['metadata']['code'] == 200) {
                $this->dataRujukan = $response['response']['rujukan'] ?? [];
            } else {
                $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal cari rujukan.');
            }
        }

        if (empty($this->dataRujukan)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data rujukan. Pasien datang sendiri / darurat.');
        }

        $this->incrementVersion('lov-rujukan');
        $this->incrementVersion('modal');
    }

    public function pilihRujukan(int $index): void
    {
        $rujukan = $this->dataRujukan[$index];
        $this->selectedRujukan = $rujukan;
        $this->setSEPFormFromRujukan($rujukan);
        $this->showRujukanLov = false;
        $this->incrementVersion('form-sep');
        $this->incrementVersion('modal');
        $this->dispatch('toast', type: 'success', message: 'Rujukan dipilih.');
    }

    private function setSEPFormFromRujukan(array $rujukan): void
    {
        $peserta = $rujukan['peserta'] ?? [];

        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => '2', // UGD: asal RS
                'asalRujukanNama' => 'Faskes Tingkat 2 (RS)',
                'tglRujukan' => Carbon::parse($rujukan['tglKunjungan'] ?? now())->format('Y-m-d'),
                'noRujukan' => $rujukan['noKunjungan'] ?? '',
                'ppkRujukan' => $rujukan['provPerujuk']['kode'] ?? '0184R006',
                'ppkRujukanNama' => $rujukan['provPerujuk']['nama'] ?? 'RSI Madinah',
            ],
            'diagAwal' => $rujukan['diagnosa']['kode'] ?? '',
            'poli' => [
                'tujuan' => $this->kdpolibpjs ?? 'IGD',
                'eksekutif' => '0',
            ],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
        ]);
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
        $this->incrementVersion('modal');
    }

    /* ===============================
     | Generate SEP → kirim ke parent UGD
     =============================== */
    public function generateSEP(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'warning', message: 'SEP sudah terbentuk, tidak dapat diubah.');
            return;
        }

        $this->validateSEPForm();

        $request = $this->buildSEPRequest();

        // Dispatch ke parent UGD (beda event dari RJ)
        $this->dispatch('sep-generated-ugd', reqSep: $request);

        $this->dispatch('toast', type: 'success', message: 'Data SEP berhasil disimpan.');
        $this->showRujukanLov = false;
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
                'SEPForm.tglSep.date_format' => 'Format Tanggal SEP harus DD/MM/YYYY.',
                'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi.',
                'SEPForm.poli.tujuan.required' => 'Poli tujuan harus diisi.',
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
                    'jnsPelayanan' => '2', // UGD: selalu rawat jalan darurat
                    'klsRawat' => [
                        'klsRawatHak' => $this->SEPForm['klsRawat']['klsRawatHak'] ?? '',
                        'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $this->SEPForm['noMR'] ?? '',
                    'rujukan' => [
                        'asalRujukan' => '2', // UGD: selalu asal RS
                        'asalRujukanNama' => $this->SEPForm['rujukan']['asalRujukanNama'] ?? 'Faskes Tingkat 2 (RS)',
                        'tglRujukan' => $this->SEPForm['rujukan']['tglRujukan'] ?? '',
                        'noRujukan' => $this->SEPForm['rujukan']['noRujukan'] ?? '',
                        'ppkRujukan' => $this->SEPForm['rujukan']['ppkRujukan'] ?? '0184R006',
                        'ppkRujukanNama' => $this->SEPForm['rujukan']['ppkRujukanNama'] ?? 'RSI Madinah',
                    ],
                    'catatan' => $this->SEPForm['catatan'] ?: '-',
                    'diagAwal' => $this->SEPForm['diagAwal'] ?? '',
                    'poli' => [
                        'tujuan' => $this->SEPForm['poli']['tujuan'] ?? 'IGD',
                        'eksekutif' => $this->SEPForm['poli']['eksekutif'] ?? '0',
                    ],
                    'cob' => ['cob' => $this->SEPForm['cob']['cob'] ?? '0'],
                    'katarak' => ['katarak' => $this->SEPForm['katarak']['katarak'] ?? '0'],
                    'jaminan' => $this->buildJaminan(),
                    'tujuanKunj' => (string) ($this->SEPForm['tujuanKunj'] ?? '0'),
                    'flagProcedure' => $this->SEPForm['flagProcedure'] ?? '',
                    'kdPenunjang' => $this->SEPForm['kdPenunjang'] ?? '',
                    'assesmentPel' => $this->SEPForm['assesmentPel'] ?? '',
                    'skdp' => ['noSurat' => '', 'kodeDPJP' => ''], // UGD tidak pakai SKDP
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
     | LOV Listeners — prefix ugd
     =============================== */
    #[On('lov.selected.ugdFormDokterVclaim')]
    public function ugdFormDokterVclaim(string $target, array $payload): void
    {
        $this->drId = $payload['dr_id'] ?? null;
        $this->drDesc = $payload['dr_name'] ?? '';

        $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';
        $this->SEPForm['poli']['tujuan'] = $payload['kd_poli_bpjs'] ?? ($this->kdpolibpjs ?? 'IGD');

        // UGD tidak pakai SKDP — kosongkan saja
        $this->SEPForm['skdp']['kodeDPJP'] = '';

        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
    }

    #[On('lov.selected.ugdFormDiagnosaVclaim')]
    public function ugdFormDiagnosaVclaim(string $target, array $payload): void
    {
        $this->diagnosaId = $payload['icdx'] ?? null;
        $this->SEPForm['diagAwal'] = $payload['icdx'] ?? '';

        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
    }

    /* ---- Reset & Close ---- */
    private function resetForm(): void
    {
        $this->reset(['SEPForm', 'selectedRujukan', 'showRujukanLov', 'dataRujukan', 'diagnosaId']);
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->SEPForm['jnsPelayanan'] = '2';
        $this->SEPForm['poli']['tujuan'] = 'IGD';
        $this->SEPForm['rujukan']['asalRujukan'] = '2';
        $this->SEPForm['rujukan']['ppkRujukan'] = '0184R006';
        $this->SEPForm['rujukan']['ppkRujukanNama'] = 'RSI Madinah';
        $this->isFormLocked = false;
    }

    #[On('close-vclaim-modal-ugd')]
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'vclaim-ugd-actions');
        $this->resetForm();
        $this->resetVersion();
    }

    public function mount(): void
    {
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->registerAreas(['modal', 'lov-rujukan', 'form-sep', 'info-pasien']);
    }
};
?>

<div>
    <x-modal name="vclaim-ugd-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah SEP UGD' : 'Buat SEP UGD' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Surat Eligibilitas Peserta BPJS — Unit Gawat Darurat
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit SEP' : 'Mode: Buat SEP' }}
                            </x-badge>
                            <x-badge variant="danger">UGD / IGD</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Cari Rujukan BPJS
                    </x-secondary-button>

                    <div
                        class="px-3 py-1 text-xs text-red-700 bg-red-100 rounded-full dark:bg-red-900/30 dark:text-red-300">
                        Asal Rujukan UGD: RS (darurat — tidak memerlukan rujukan FKTP)
                    </div>

                    @if (!empty($selectedRujukan))
                        <x-badge variant="success" class="gap-1">
                            Rujukan: {{ $selectedRujukan['noKunjungan'] ?? ($SEPForm['rujukan']['noRujukan'] ?? '-') }}
                        </x-badge>
                    @endif
                </div>

                {{-- LOV Rujukan --}}
                @if ($showRujukanLov)
                    <div wire:key="{{ $this->renderKey('lov-rujukan') }}"
                        class="mb-4 overflow-hidden bg-white border rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Rujukan</h3>
                        </div>
                        <div class="p-4">
                            <div class="space-y-2 overflow-y-auto max-h-60">
                                @forelse ($dataRujukan as $index => $rujukan)
                                    <div wire:key="rujukan-ugd-{{ $index }}"
                                        class="p-3 transition-colors border rounded cursor-pointer hover:bg-red-50 dark:hover:bg-gray-700/50"
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
                                                    class="ml-1 text-sm">{{ Carbon::parse($rujukan['tglKunjungan'] ?? now())->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <div>
                                                <span class="text-xs text-gray-500">Asal Rujukan:</span>
                                                <span
                                                    class="block text-sm">{{ $rujukan['provPerujuk']['nama'] ?? '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">Poli Tujuan:</span>
                                                <span
                                                    class="block text-sm">{{ $rujukan['poliRujukan']['nama'] ?? '-' }}</span>
                                            </div>
                                            <div class="col-span-2">
                                                <span class="text-xs text-gray-500">Diagnosa:</span>
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

                {{-- MAIN CONTENT --}}
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">

                    {{-- Info Pasien --}}
                    <div wire:key="{{ $this->renderKey('info-pasien', $regNo ?? '') }}" class="lg:col-span-1">
                        <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                            <h3
                                class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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

                                {{-- Info UGD --}}
                                <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700 space-y-2">
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Layanan:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs text-red-800 bg-red-100 rounded-full dark:bg-red-900 dark:text-red-200">
                                                UGD / IGD (Darurat)
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Jns Pelayanan BPJS:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs text-orange-800 bg-orange-100 rounded-full dark:bg-orange-900 dark:text-orange-200">
                                                2 — Rawat Jalan
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Asal Rujukan:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs text-purple-800 bg-purple-100 rounded-full dark:bg-purple-900 dark:text-purple-200">
                                                2 — Faskes Tingkat 2 (RS)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Form SEP --}}
                    <div wire:key="{{ $this->renderKey('form-sep', [$formMode]) }}" class="space-y-4 lg:col-span-3">

                        <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                            <h3
                                class="flex items-center gap-2 mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Form SEP UGD
                            </h3>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

                                {{-- No Kartu --}}
                                <div>
                                    <x-input-label value="No. Kartu BPJS" />
                                    <x-text-input wire:model="SEPForm.noKartu" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.noKartu')" placeholder="0000000000000" />
                                    <x-input-error :messages="$errors->get('SEPForm.noKartu')" class="mt-1" />
                                </div>

                                {{-- No MR --}}
                                <div>
                                    <x-input-label value="No. MR" />
                                    <x-text-input wire:model="SEPForm.noMR" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.noMR')" />
                                    <x-input-error :messages="$errors->get('SEPForm.noMR')" class="mt-1" />
                                </div>

                                {{-- Tgl SEP --}}
                                <div>
                                    <x-input-label value="Tanggal SEP" />
                                    <x-text-input wire:model="SEPForm.tglSep" class="w-full" :disabled="$isFormLocked"
                                        placeholder="dd/mm/yyyy" :error="$errors->has('SEPForm.tglSep')" />
                                    <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                </div>

                                {{-- PPK Rujukan --}}
                                <div>
                                    <x-input-label value="PPK Rujukan" />
                                    <x-text-input wire:model="SEPForm.rujukan.ppkRujukan" class="w-full"
                                        :disabled="true" placeholder="Kode faskes" />
                                    @if (!empty($SEPForm['rujukan']['ppkRujukanNama']))
                                        <p class="mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                                            {{ $SEPForm['rujukan']['ppkRujukanNama'] }}
                                        </p>
                                    @endif
                                </div>

                                {{-- No Rujukan --}}
                                <div>
                                    <x-input-label value="No. Rujukan (opsional)" />
                                    <x-text-input wire:model="SEPForm.rujukan.noRujukan" class="w-full"
                                        :disabled="$isFormLocked" placeholder="Kosongkan jika darurat murni" />
                                </div>

                                {{-- Asal Rujukan (fixed UGD = 2) --}}
                                <div>
                                    <x-input-label value="Asal Rujukan" />
                                    <x-select-input wire:model="SEPForm.rujukan.asalRujukan" class="w-full"
                                        :disabled="true">
                                        <option value="1">Faskes Tingkat 1</option>
                                        <option value="2">Faskes Tingkat 2 (RS)</option>
                                    </x-select-input>
                                    @if (!empty($SEPForm['rujukan']['asalRujukanNama']))
                                        <p class="mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                                            {{ $SEPForm['rujukan']['asalRujukanNama'] }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Tgl Rujukan --}}
                                <div>
                                    <x-input-label value="Tgl Rujukan" />
                                    <x-text-input wire:model="SEPForm.rujukan.tglRujukan" class="w-full"
                                        :disabled="true" placeholder="yyyy-mm-dd" />
                                </div>

                                {{-- Kelas Rawat Hak --}}
                                <div>
                                    <x-input-label value="Kelas Rawat Hak" />
                                    <x-select-input wire:model="SEPForm.klsRawat.klsRawatHak" class="w-full"
                                        :disabled="true">
                                        <option value="">Pilih Kelas</option>
                                        <option value="1">Kelas 1</option>
                                        <option value="2">Kelas 2</option>
                                        <option value="3">Kelas 3</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('SEPForm.klsRawat.klsRawatHak')" class="mt-1" />
                                </div>

                                {{-- Kelas Rawat Naik --}}
                                <div>
                                    <x-input-label value="Kelas Rawat Naik" />
                                    <x-select-input wire:model="SEPForm.klsRawat.klsRawatNaik" class="w-full"
                                        :disabled="$isFormLocked">
                                        <option value="">Tidak Naik Kelas</option>
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
                                <div>
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
                                <div>
                                    <x-input-label value="Penanggung Jawab" />
                                    <x-text-input wire:model="SEPForm.klsRawat.penanggungJawab" class="w-full"
                                        :disabled="$isFormLocked" />
                                </div>

                                {{-- Poli Tujuan (fixed IGD) --}}
                                <div>
                                    <x-input-label value="Poli Tujuan" />
                                    <x-text-input wire:model="SEPForm.poli.tujuan" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.poli.tujuan')" />
                                    <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                </div>

                                {{-- Poli Eksekutif --}}
                                <div>
                                    <x-input-label value="Poli Eksekutif" />
                                    <x-select-input wire:model="SEPForm.poli.eksekutif" class="w-full"
                                        :disabled="$isFormLocked">
                                        <option value="0">Tidak</option>
                                        <option value="1">Ya</option>
                                    </x-select-input>
                                </div>

                                {{-- DPJP --}}
                                <div>
                                    <x-input-label value="DPJP" />
                                    <x-text-input wire:model="SEPForm.dpjpLayan" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.dpjpLayan')" placeholder="Kode DPJP" />
                                    <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                </div>

                                {{-- Diagnosa Awal --}}
                                <div>
                                    <x-input-label value="Diagnosa Awal (ICD-10)" />
                                    <x-text-input wire:model="SEPForm.diagAwal" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.diagAwal')" placeholder="Kode ICD-10" />
                                    <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                </div>

                                {{-- LOV Dokter DPJP --}}
                                <div class="lg:col-span-4">
                                    <livewire:lov.dokter.lov-dokter label="Cari Dokter DPJP UGD"
                                        target="ugdFormDokterVclaim" :initialDrId="$drId ?? null" :disabled="$isFormLocked" />
                                </div>

                                {{-- LOV Diagnosa --}}
                                <div class="lg:col-span-4">
                                    <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa"
                                        target="ugdFormDiagnosaVclaim" :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked" />
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

                                {{-- Catatan --}}
                                <div class="lg:col-span-4">
                                    <x-input-label value="Catatan" />
                                    <x-textarea wire:model="SEPForm.catatan" class="w-full" rows="2"
                                        :disabled="$isFormLocked" placeholder="Catatan (opsional)" />
                                </div>

                                {{-- COB & Katarak --}}
                                <div>
                                    <x-input-label value="COB" />
                                    <x-select-input wire:model="SEPForm.cob.cob" class="w-full" :disabled="$isFormLocked">
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

                                {{-- No Telepon --}}
                                <div>
                                    <x-input-label value="No. Telepon" />
                                    <x-text-input wire:model="SEPForm.noTelp" class="w-full" :disabled="$isFormLocked"
                                        placeholder="08xxxx" />
                                </div>

                                {{-- JAMINAN KLL --}}
                                <div class="p-3 border rounded lg:col-span-4 bg-gray-50 dark:bg-gray-700/30">
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
                                                <option value="1">KLL dan bukan kecelakaan Kerja</option>
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

                        {{-- Info Rujukan Terpilih --}}
                        @if (!empty($selectedRujukan))
                            <div
                                class="p-4 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                <h4
                                    class="flex items-center gap-2 mb-2 text-sm font-medium text-blue-800 dark:text-blue-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Data Rujukan Terpilih
                                </h4>
                                <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                    <div>
                                        <span class="text-xs text-blue-600">No. Rujukan:</span>
                                        <p class="font-medium">
                                            {{ $selectedRujukan['noKunjungan'] ?? ($SEPForm['rujukan']['noRujukan'] ?? '-') }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-blue-600">Tgl Rujukan:</span>
                                        <p class="font-medium">
                                            @if (!empty($selectedRujukan['tglKunjungan']))
                                                {{ Carbon::parse($selectedRujukan['tglKunjungan'])->format('d/m/Y') }}
                                            @elseif (!empty($SEPForm['rujukan']['tglRujukan']))
                                                {{ Carbon::parse($SEPForm['rujukan']['tglRujukan'])->format('d/m/Y') }}
                                            @else
                                                -
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-blue-600">Asal Rujukan:</span>
                                        <p class="font-medium">
                                            {{ $selectedRujukan['provPerujuk']['nama'] ?? ($SEPForm['rujukan']['ppkRujukanNama'] ?? '-') }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-blue-600">Poli Tujuan:</span>
                                        <p class="font-medium">{{ $selectedRujukan['poliRujukan']['nama'] ?? 'IGD' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- SEP sudah ada --}}
                        @if (!empty($sepData['noSep']))
                            <div
                                class="p-4 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-green-100 rounded-full dark:bg-green-800">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-300" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-green-700 dark:text-green-300">No. SEP
                                            UGD</span>
                                        <p class="text-lg font-semibold text-green-800 dark:text-green-200">
                                            {{ $sepData['noSep'] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>

                    <x-primary-button type="button" wire:click="generateSEP" wire:loading.attr="disabled"
                        :disabled="$isFormLocked">
                        <span wire:loading.remove>
                            <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                            </svg>
                            Simpan SEP
                        </span>
                        <span wire:loading>
                            <x-loading /> Menyimpan...
                        </span>
                    </x-primary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
