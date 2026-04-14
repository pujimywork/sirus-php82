<?php
// resources/views/pages/transaksi/ugd/daftar-ugd/vclaim-ugd-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use VclaimTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'form-sep', 'info-pasien'];

    public ?string $rjNo = null;
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
    public array $dataPasien = [];

    public array $SEPForm = [
        'noKartu' => '',
        'tglSep' => '',
        'ppkPelayanan' => '0184R006',
        'jnsPelayanan' => '2', // UGD: rawat jalan darurat
        'klsRawat' => [
            'klsRawatHak' => '',
            'klsRawatNaik' => '',
            'pembiayaan' => '',
            'penanggungJawab' => '',
        ],
        'noMR' => '',
        'rujukan' => [
            'asalRujukan' => '2', // UGD: fixed asal RS
            'asalRujukanNama' => 'Faskes Tingkat 2 (RS)',
            'tglRujukan' => '',
            'noRujukan' => '', // opsional — isi manual jika ada
            'ppkRujukan' => '0184R006',
            'ppkRujukanNama' => 'RSI Madinah',
        ],
        'catatan' => '',
        'diagAwal' => '',
        'poli' => ['tujuan' => 'IGD', 'eksekutif' => '0'],
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

    public array $sepData = [
        'noSep' => '',
        'reqSep' => [],
        'resSep' => [],
    ];

    public array $tujuanKunjOptions = [['id' => '0', 'name' => 'Normal'], ['id' => '1', 'name' => 'Prosedur'], ['id' => '2', 'name' => 'Konsul Dokter']];

    public array $flagProcedureOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '0', 'name' => 'Prosedur Tidak Berkelanjutan'], ['id' => '1', 'name' => 'Prosedur dan Terapi Berkelanjutan']];

    public array $kdPenunjangOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Radioterapi'], ['id' => '2', 'name' => 'Kemoterapi'], ['id' => '3', 'name' => 'Rehabilitasi Medik'], ['id' => '4', 'name' => 'Rehabilitasi Psikososial'], ['id' => '5', 'name' => 'Transfusi Darah'], ['id' => '6', 'name' => 'Pelayanan Gigi'], ['id' => '7', 'name' => 'Laboratorium'], ['id' => '8', 'name' => 'USG'], ['id' => '9', 'name' => 'Farmasi'], ['id' => '10', 'name' => 'Lain-Lain'], ['id' => '11', 'name' => 'MRI'], ['id' => '12', 'name' => 'HEMODIALISA']];

    public array $assesmentPelOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Poli spesialis tidak tersedia pada hari sebelumnya'], ['id' => '2', 'name' => 'Jam Poli telah berakhir pada hari sebelumnya'], ['id' => '3', 'name' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya'], ['id' => '4', 'name' => 'Atas Instruksi RS'], ['id' => '5', 'name' => 'Tujuan Kontrol']];

    /* ===============================
     | OPEN dari parent UGD
     =============================== */
    #[On('open-vclaim-modal-ugd')]
    public function handleOpenVclaimModal(?string $rjNo = null, ?string $regNo = null, ?string $drId = null, ?string $drDesc = null, ?string $poliId = null, ?string $poliDesc = null, ?string $kdpolibpjs = null, ?string $noReferensi = null, array $sepData = []): void
    {
        $this->rjNo = $rjNo;
        $this->regNo = $regNo;
        $this->poliId = 'UGD';
        $this->poliDesc = 'Instalasi Gawat Darurat';
        $this->formMode = $rjNo ? 'edit' : 'create';

        $tSep = $sepData['reqSep']['request']['t_sep'] ?? [];

        $this->noReferensi = $tSep['rujukan']['noRujukan'] ?? $noReferensi;
        $this->kdpolibpjs = $tSep['poli']['tujuan'] ?? $kdpolibpjs;

        if (!empty($tSep['dpjpLayan'])) {
            $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $tSep['dpjpLayan'])->select('dr_id', 'dr_name')->first();
            $this->drId = $dokter->dr_id ?? null;
            $this->drDesc = $dokter->dr_name ?? '';
        } else {
            $this->drId = null;
            $this->drDesc = '';
        }

        $this->loadDataPasien($regNo);

        // Restore dari sepData yang sudah tersimpan
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
        }

        // FIX #3: Ambil klsRawatHak dari API peserta BPJS jika belum terisi
        if (empty($this->SEPForm['klsRawat']['klsRawatHak']) && !empty($this->SEPForm['noKartu'])) {
            $this->loadKlsRawatFromBPJS();
        }

        $this->resetVersion();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'vclaim-ugd-actions');
        $this->dispatch('focus-vclaim-tgl-sep');
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
                'identitas' => [
                    'idbpjs' => $data->nokartu_bpjs ?? '',
                    'nik' => $data->nik_bpjs ?? '',
                ],
                'kontak' => [
                    'nomerTelponSelulerPasien' => $data->phone ?? '',
                ],
            ],
        ];

        $this->SEPForm['noKartu'] = $data->nokartu_bpjs ?? '';
        $this->SEPForm['noMR'] = $data->reg_no;
        $this->SEPForm['noTelp'] = $data->phone ?? '';

        // UGD: asal rujukan selalu RS (fixed)
        $this->SEPForm['rujukan']['asalRujukan'] = '2';
        $this->SEPForm['rujukan']['asalRujukanNama'] = 'Faskes Tingkat 2 (RS)';
        $this->SEPForm['rujukan']['ppkRujukan'] = '0184R006';
        $this->SEPForm['rujukan']['ppkRujukanNama'] = 'RSI Madinah';
    }

    /* ----
     | FIX #3: Load klsRawatHak dari API peserta BPJS
     | Memanggil service: /Peserta/nokartu/{noka}/tglSEP/{tgl}
     | Method di VclaimTrait: peserta_nomorkartu($nomorKartu, $tanggal)
     ---- */
    private function loadKlsRawatFromBPJS(): void
    {
        $noKartu = $this->SEPForm['noKartu'] ?? '';
        $tglSep = $this->SEPForm['tglSep'] ? Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        if (empty($noKartu)) {
            return;
        }

        try {
            // nama method sesuai VclaimTrait: peserta_nomorkartu()
            $response = $this->peserta_nomorkartu($noKartu, $tglSep);
            $content = $response->getOriginalContent();
            $code = $content['metadata']['code'] ?? 500;

            if ($code == 200) {
                $peserta = $content['response']['peserta'] ?? [];
                // Ambil hak kelas rawat: 1=Kelas1, 2=Kelas2, 3=Kelas3
                $klsRawatHak = $peserta['hakKelas']['kode'] ?? '';
                if (!empty($klsRawatHak)) {
                    $this->SEPForm['klsRawat']['klsRawatHak'] = (string) $klsRawatHak;
                }
            }
        } catch (\Throwable $e) {
            // Gagal ambil data peserta — lanjutkan, user bisa isi manual jika perlu
            // Tidak dispatch toast agar tidak mengganggu UX modal open
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
        $this->incrementVersion('modal');
    }

    /* ---- Generate SEP ---- */
    public function generateSEP(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'warning', message: 'SEP sudah terbentuk, tidak dapat diubah.');
            return;
        }

        $this->validateSEPForm();
        $request = $this->buildSEPRequest();

        $this->dispatch('sep-generated-ugd', reqSep: $request);
        $this->dispatch('toast', type: 'success', message: 'Data SEP berhasil disimpan.');
        $this->closeModal();
    }

    private function validateSEPForm(): void
    {
        /* FIX #4: Tambah validasi KLL — propinsi/kabupaten/kecamatan wajib isi
         * jika lakaLantas !== '0' (sesuai UAT checklist 6.1.3–6.1.5)
         */
        $rules = [
            'SEPForm.noKartu' => 'required',
            'SEPForm.tglSep' => 'required|date_format:d/m/Y',
            'SEPForm.noMR' => 'required',
            'SEPForm.diagAwal' => 'required',
            'SEPForm.poli.tujuan' => 'required',
            'SEPForm.dpjpLayan' => 'required',
            // KLL — wajib jika bukan "Bukan KLL"
            'SEPForm.jaminan.lakaLantas' => 'required|in:0,1,2,3',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi' => 'required_unless:SEPForm.jaminan.lakaLantas,0',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten' => 'required_unless:SEPForm.jaminan.lakaLantas,0',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan' => 'required_unless:SEPForm.jaminan.lakaLantas,0',
        ];

        $messages = [
            'SEPForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi.',
            'SEPForm.tglSep.required' => 'Tanggal SEP wajib diisi.',
            'SEPForm.tglSep.date_format' => 'Format Tanggal SEP harus DD/MM/YYYY.',
            'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi.',
            'SEPForm.poli.tujuan.required' => 'Poli tujuan harus diisi.',
            'SEPForm.dpjpLayan.required' => 'DPJP harus diisi.',
            'SEPForm.jaminan.lakaLantas.in' => 'Nilai Laka Lantas tidak valid.',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi.required_unless' => 'Kode Propinsi wajib diisi untuk kasus KLL.',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten.required_unless' => 'Kode Kabupaten wajib diisi untuk kasus KLL.',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan.required_unless' => 'Kode Kecamatan wajib diisi untuk kasus KLL.',
        ];

        $this->validate($rules, $messages);
    }

    private function buildSEPRequest(): array
    {
        /* FIX #2: rujukan — tglRujukan WAJIB di validasi VclaimTrait::sep_insert()
         * (rule "tglRujukan" => "required" tidak dikomentari di trait).
         * Untuk IGD murni tanpa rujukan: tglRujukan diisi sama dengan tglSep,
         * noRujukan hanya dikirim jika terisi (opsional di BPJS untuk IGD).
         */
        $tglSepFormatted = Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d');
        $noRujukan = $this->SEPForm['rujukan']['noRujukan'] ?? '';
        $tglRujukan = !empty($this->SEPForm['rujukan']['tglRujukan']) ? $this->SEPForm['rujukan']['tglRujukan'] : $tglSepFormatted; // fallback: sama dengan tglSep (IGD murni)

        $rujukan = [
            'asalRujukan' => '2',
            'asalRujukanNama' => 'Faskes Tingkat 2 (RS)',
            'tglRujukan' => $tglRujukan,
            'noRujukan' => $noRujukan, // boleh kosong untuk IGD
            'ppkRujukan' => '0184R006',
            'ppkRujukanNama' => 'RSI Madinah',
        ];

        return [
            'request' => [
                't_sep' => [
                    'noKartu' => $this->SEPForm['noKartu'] ?? '',
                    'tglSep' => $tglSepFormatted,
                    'ppkPelayanan' => $this->SEPForm['ppkPelayanan'] ?? '0184R006',
                    'jnsPelayanan' => '2', // UGD: selalu rawat jalan darurat
                    'klsRawat' => [
                        'klsRawatHak' => $this->SEPForm['klsRawat']['klsRawatHak'] ?? '',
                        'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $this->SEPForm['noMR'] ?? '',
                    'rujukan' => $rujukan,
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
        $lakaLantas = $this->SEPForm['jaminan']['lakaLantas'] ?? '0';

        $jaminan = [
            'lakaLantas' => $lakaLantas,
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

        if ($lakaLantas !== '0') {
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

    /* ---- LOV Listeners ---- */
    #[On('lov.selected.ugdFormDokterVclaim')]
    public function ugdFormDokterVclaim(string $target, array $payload): void
    {
        $this->drId = $payload['dr_id'] ?? null;
        $this->drDesc = $payload['dr_name'] ?? '';
        $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';
        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
        $this->dispatch('focus-vclaim-diagnosa');
    }

    #[On('lov.selected.ugdFormDiagnosaVclaim')]
    public function ugdFormDiagnosaVclaim(string $target, array $payload): void
    {
        $this->diagnosaId = $payload['icdx'] ?? null;
        $this->SEPForm['diagAwal'] = $payload['icdx'] ?? '';
        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
        $this->dispatch('focus-vclaim-simpan');
    }

    /* ---- Reset & Close ---- */
    private function resetForm(): void
    {
        $this->reset(['SEPForm', 'diagnosaId', 'dataPasien', 'sepData']);
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
        $this->registerAreas(['modal', 'form-sep', 'info-pasien']);
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
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px;">
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
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-vclaim-tgl-sep.window="$nextTick(() => setTimeout(() => $refs.inputTglSep?.focus(), 150))"
                x-on:focus-vclaim-no-rujukan.window="$nextTick(() => setTimeout(() => $refs.inputNoRujukan?.focus(), 150))"
                x-on:focus-vclaim-dokter.window="$nextTick(() => setTimeout(() => $refs.lovDokterVclaim?.querySelector('input')?.focus(), 150))"
                x-on:focus-vclaim-diagnosa.window="$nextTick(() => setTimeout(() => $refs.lovDiagnosaVclaim?.querySelector('input')?.focus(), 150))"
                x-on:focus-vclaim-simpan.window="$nextTick(() => setTimeout(() => $refs.btnSimpanVclaim?.focus(), 150))">

                {{-- Info badge UGD --}}
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <div
                        class="px-3 py-1 text-xs text-red-700 bg-red-100 rounded-full dark:bg-red-900/30 dark:text-red-300">
                        UGD / IGD — pasien darurat tidak memerlukan rujukan FKTP
                    </div>
                    <div
                        class="px-3 py-1 text-xs text-purple-700 bg-purple-100 rounded-full dark:bg-purple-900/30 dark:text-purple-300">
                        Asal Rujukan: Faskes Tingkat 2 (RS) — fixed
                    </div>
                </div>

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
                                    <p class="font-medium">{{ $dataPasien['pasien']['identitas']['idbpjs'] ?? '-' }}</p>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50">
                                    <span class="text-xs text-gray-500">No. Telepon</span>
                                    <p class="font-medium">
                                        {{ $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '-' }}</p>
                                </div>
                                <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700 space-y-2">
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Layanan:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs text-red-800 bg-red-100 rounded-full dark:bg-red-900 dark:text-red-200">UGD
                                                / IGD (Darurat)</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Jns Pelayanan:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs text-orange-800 bg-orange-100 rounded-full dark:bg-orange-900 dark:text-orange-200">2
                                                — Rawat Jalan</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Asal Rujukan:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs text-purple-800 bg-purple-100 rounded-full dark:bg-purple-900 dark:text-purple-200">2
                                                — Faskes Tingkat 2 (RS)</span>
                                        </div>
                                    </div>
                                    {{-- FIX #3: Tampilkan klsRawatHak yang diambil dari API --}}
                                    @if (!empty($SEPForm['klsRawat']['klsRawatHak']))
                                        <div>
                                            <span class="text-xs font-medium text-gray-500">Kelas Rawat Hak:</span>
                                            <div class="mt-1">
                                                <span
                                                    class="px-2 py-1 text-xs text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-200">
                                                    Kelas {{ $SEPForm['klsRawat']['klsRawatHak'] }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
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

                            {{-- ============================================================
                                 URUTAN FIELD mengikuti form VClaim BPJS asli:
                                 1. Spesialis/Poli + Eksekutif
                                 2. DPJP yang Melayani (LOV)
                                 3. Tgl. SEP + No. Rujukan
                                 4. No. MR + No. Kartu BPJS
                                 5. Diagnosa (LOV)
                                 6. No. Telepon
                                 7. Catatan
                                 8. Status Kecelakaan (KLL)
                                 9. Jenis SEP (Tujuan Kunjungan)
                                 --- Data Tambahan (accordion) ---
                                 10. Kelas Rawat, COB, Katarak
                            ============================================================ --}}
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

                                {{-- 1. Spesialis/Poli + Eksekutif — mirip screenshot VClaim --}}
                                <div class="lg:col-span-3">
                                    <x-input-label value="Spesialis / Sub Spesialis *" />
                                    <x-text-input wire:model="SEPForm.poli.tujuan" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.poli.tujuan')" />
                                    <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                </div>
                                <div class="flex items-end pb-1">
                                    <x-toggle wire:model="SEPForm.poli.eksekutif" trueValue="1" falseValue="0"
                                        label="Eksekutif" :disabled="$isFormLocked" />
                                </div>

                                {{-- 2. LOV Dokter DPJP --}}
                                <div class="lg:col-span-4" x-ref="lovDokterVclaim">
                                    <livewire:lov.dokter.lov-dokter label="DPJP yang Melayani *"
                                        target="ugdFormDokterVclaim" :initialDrId="$drId ?? null" :disabled="$isFormLocked" />
                                    {{-- kode DPJP read-only --}}
                                    @if (!empty($SEPForm['dpjpLayan']))
                                        <p class="mt-1 text-xs text-gray-400">
                                            Kode DPJP: <span
                                                class="font-mono font-semibold">{{ $SEPForm['dpjpLayan'] }}</span>
                                        </p>
                                    @endif
                                    <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                </div>

                                {{-- 3. Tgl SEP + No. Rujukan --}}
                                <div class="lg:col-span-2">
                                    <x-input-label value="Tgl. SEP (dd/mm/yyyy) *" />
                                    <x-text-input wire:model="SEPForm.tglSep" class="w-full" :disabled="$isFormLocked"
                                        placeholder="dd/mm/yyyy" :error="$errors->has('SEPForm.tglSep')" x-ref="inputTglSep"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.inputNoRujukan?.focus())" />
                                    <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                </div>
                                <div class="lg:col-span-2">
                                    <x-input-label value="No. Rujukan (Opsional)" />
                                    <x-text-input wire:model="SEPForm.rujukan.noRujukan" class="w-full"
                                        :disabled="$isFormLocked" placeholder="Kosongkan jika darurat murni"
                                        x-ref="inputNoRujukan"
                                        x-on:keydown.enter.prevent="$nextTick(() => $refs.lovDiagnosaVclaim?.querySelector('input')?.focus())" />
                                    <p class="mt-1 text-xs text-gray-400">Isi jika ada surat rujukan dari RS lain.</p>
                                </div>

                                {{-- 4. No. MR + No. Kartu BPJS --}}
                                <div class="lg:col-span-2">
                                    <x-input-label value="No. MR *" />
                                    <x-text-input wire:model="SEPForm.noMR" class="w-full" :disabled="true" />
                                </div>
                                <div class="lg:col-span-2">
                                    <x-input-label value="No. Kartu BPJS" />
                                    <x-text-input wire:model="SEPForm.noKartu" class="w-full" :disabled="true"
                                        :error="$errors->has('SEPForm.noKartu')" />
                                    <x-input-error :messages="$errors->get('SEPForm.noKartu')" class="mt-1" />
                                </div>

                                {{-- 5. LOV Diagnosa --}}
                                <div class="lg:col-span-4" x-ref="lovDiagnosaVclaim">
                                    <livewire:lov.diagnosa.lov-diagnosa label="Diagnosa *"
                                        target="ugdFormDiagnosaVclaim" :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked" />
                                    {{-- kode ICD-10 read-only --}}
                                    @if (!empty($SEPForm['diagAwal']))
                                        <p class="mt-1 text-xs text-gray-400">
                                            Kode ICD-10: <span
                                                class="font-mono font-semibold">{{ $SEPForm['diagAwal'] }}</span>
                                        </p>
                                    @endif
                                    <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                </div>

                                {{-- 6. No. Telepon --}}
                                <div class="lg:col-span-2">
                                    <x-input-label value="No. Telepon *" />
                                    <x-text-input wire:model="SEPForm.noTelp" class="w-full" :disabled="$isFormLocked"
                                        placeholder="08xxxx" />
                                </div>

                                {{-- 7. Catatan --}}
                                <div class="lg:col-span-4">
                                    <x-input-label value="Catatan" />
                                    <x-textarea wire:model="SEPForm.catatan" class="w-full" rows="2"
                                        :disabled="$isFormLocked" placeholder="Catatan (opsional)" />
                                </div>

                                {{-- 8. Status Kecelakaan / KLL --}}
                                <div class="p-3 border rounded lg:col-span-4 bg-gray-50 dark:bg-gray-700/30">
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
                                                <option value="1">KLL dan bukan kecelakaan Kerja</option>
                                                <option value="2">KLL dan KK</option>
                                                <option value="3">KK</option>
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('SEPForm.jaminan.lakaLantas')" class="mt-1" />
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
                                            {{-- FIX #4: Lokasi KLL wajib --}}
                                            <div class="md:col-span-2">
                                                <x-input-label value="Lokasi Kejadian *" />
                                                <div class="grid grid-cols-3 gap-2">
                                                    <div>
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi"
                                                            placeholder="Propinsi" :disabled="$isFormLocked"
                                                            :error="$errors->has(
                                                                'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi',
                                                            )" />
                                                        <x-input-error :messages="$errors->get(
                                                            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi',
                                                        )" class="mt-1" />
                                                    </div>
                                                    <div>
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten"
                                                            placeholder="Kabupaten" :disabled="$isFormLocked"
                                                            :error="$errors->has(
                                                                'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten',
                                                            )" />
                                                        <x-input-error :messages="$errors->get(
                                                            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten',
                                                        )" class="mt-1" />
                                                    </div>
                                                    <div>
                                                        <x-text-input
                                                            wire:model="SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan"
                                                            placeholder="Kecamatan" :disabled="$isFormLocked"
                                                            :error="$errors->has(
                                                                'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan',
                                                            )" />
                                                        <x-input-error :messages="$errors->get(
                                                            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan',
                                                        )" class="mt-1" />
                                                    </div>
                                                </div>
                                                <p class="mt-1 text-xs text-red-500">* Wajib diisi untuk kasus KLL</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- 9. Jenis SEP / Tujuan Kunjungan --}}
                                <div class="lg:col-span-1">
                                    <x-input-label value="Jenis SEP *" />
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

                            {{-- =====================================================
                                 Data Tambahan — accordion, default collapsed
                            ====================================================== --}}
                            <div x-data="{ open: false }" class="mt-4 border rounded dark:border-gray-700">
                                <button type="button" @click="open = !open"
                                    class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-left text-gray-600 bg-gray-100 rounded dark:bg-gray-700/50 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                        Data Tambahan (Kelas Rawat, COB, Katarak)
                                    </span>
                                    <svg x-bind:class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="open" x-collapse class="p-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

                                        {{-- Kelas Rawat --}}
                                        <div>
                                            <x-input-label value="Kelas Rawat Hak" />
                                            <x-text-input wire:model="SEPForm.klsRawat.klsRawatHak" class="w-full"
                                                :disabled="true" placeholder="Auto dari data BPJS" />
                                            @if (empty($SEPForm['klsRawat']['klsRawatHak']))
                                                <p class="mt-1 text-xs text-amber-500">Belum terisi — cek data BPJS
                                                    peserta.</p>
                                            @endif
                                        </div>
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
                                        <div>
                                            <x-input-label value="Penanggung Jawab" />
                                            <x-text-input wire:model="SEPForm.klsRawat.penanggungJawab" class="w-full"
                                                :disabled="$isFormLocked" />
                                        </div>

                                        {{-- COB + Katarak --}}
                                        <div>
                                            <x-input-label value="Peserta COB" />
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

                                    </div>
                                </div>
                            </div>

                            {{-- spacer agar tidak ada div kosong sebelum penutup --}}
                            <div class="hidden"></div>

                        </div>
                    </div>

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
                                        {{ $sepData['noSep'] }}</p>
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
                    :disabled="$isFormLocked" x-ref="btnSimpanVclaim">
                    <span wire:loading.remove>
                        <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                        </svg>
                        Simpan SEP
                    </span>
                    <span wire:loading><x-loading /> Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>

    </x-modal>
</div>
