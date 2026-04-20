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
    public ?string $diagnosaId = null;

    // FIX #1: deklarasi noSep sebagai public property (sebelumnya diset di handleOpenVclaimModal tapi tidak dideklarasi)
    public ?string $noSep = null;

    // State
    public string $formMode = 'create';
    public bool $isFormLocked = false;
    public bool $showRujukanLov = false;
    public bool $showSkdpLov = false;
    public bool $showRiwayatRILov = false;
    public array $skdpOptions = [];
    public array $dataRujukan = [];
    public array $dataRiwayatRI = [];
    public array $selectedRujukan = [];
    public array $dataPasien = [];

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
            'asalRujukanNama' => '',
            'tglRujukan' => '',
            'noRujukan' => '',
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
     | OPEN dari parent RJ
     =============================== */
    #[On('open-vclaim-modal')]
    public function handleOpenVclaimModal($rjNo = null, $regNo = null, $drId = null, $drDesc = null, $poliId = null, $poliDesc = null, $kdpolibpjs = null, $kunjunganId = null, $kontrol12 = null, $internal12 = null, $postInap = false, $noReferensi = null, $sepData = [])
    {
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
        $this->formMode = $rjNo ? 'edit' : 'create';

        $this->loadDataPasien($regNo);

        if (!empty($sepData)) {
            $this->sepData = $sepData;
            $this->noSep = $sepData['noSep'] ?? null;

            if (!empty($this->noSep)) {
                $this->isFormLocked = true;
            }

            $tSep = $sepData['reqSep']['request']['t_sep'] ?? [];

            if (!empty($tSep)) {
                $this->SEPForm = array_replace_recursive($this->SEPForm, $tSep);

                // tglSep: convert Y-m-d → d/m/Y untuk display
                if (!empty($tSep['tglSep'])) {
                    $this->SEPForm['tglSep'] = Carbon::parse($tSep['tglSep'])->format('d/m/Y');
                }

                // tglRujukan: convert Y-m-d → d/m/Y untuk display
                if (!empty($tSep['rujukan']['tglRujukan'])) {
                    $this->SEPForm['rujukan']['tglRujukan'] = Carbon::parse($tSep['rujukan']['tglRujukan'])->format('d/m/Y');
                }

                // diagnosaId dari diagAwal
                if (!empty($tSep['diagAwal'])) {
                    $this->diagnosaId = $tSep['diagAwal'];
                }

                // FIX: lookup drId dari kd_dr_bpjs (dpjpLayan) di reqSep
                // karena drId dari parent mungkin stale saat edit
                if (!empty($tSep['dpjpLayan'])) {
                    $dokter = DB::table('rsmst_doctors')->where('kd_dr_bpjs', $tSep['dpjpLayan'])->select('dr_id', 'dr_name')->first();
                    if ($dokter) {
                        $this->drId = $dokter->dr_id;
                        $this->drDesc = $dokter->dr_name;
                    }
                }

                // FIX: populate selectedRujukan agar form SEP muncul saat edit
                if (!empty($tSep['rujukan']['noRujukan'])) {
                    $this->selectedRujukan = [
                        'noKunjungan' => $tSep['rujukan']['noRujukan'],
                        'tglKunjungan' => $tSep['rujukan']['tglRujukan'] ?? null,
                        'provPerujuk' => [
                            'kode' => $tSep['rujukan']['ppkRujukan'] ?? '',
                            'nama' => $tSep['rujukan']['ppkRujukanNama'] ?? '',
                        ],
                        'poliRujukan' => ['nama' => '-'],
                    ];
                } elseif ($this->kunjunganId == '3' && !empty($this->postInap)) {
                    // postInap: noRujukan = noSep RI, set selectedRujukan agar form muncul
                    $this->selectedRujukan = [
                        'noKunjungan' => $tSep['rujukan']['noRujukan'] ?? '',
                        'tglKunjungan' => $tSep['rujukan']['tglRujukan'] ?? null,
                        'provPerujuk' => [
                            'kode' => $tSep['rujukan']['ppkRujukan'] ?? '0184R006',
                            'nama' => $tSep['rujukan']['ppkRujukanNama'] ?? 'RSI Madinah',
                        ],
                        'poliRujukan' => ['nama' => '-'],
                    ];
                } else {
                    // Ada reqSep tapi noRujukan kosong (IGD-like) — tetap tampilkan form
                    $this->selectedRujukan = ['noKunjungan' => ''];
                }
            }
        }

        $this->resetVersion();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'vclaim-rj-actions');
    }

    /* ---- Load data pasien ---- */
    private function loadDataPasien($regNo): void
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
                'identitas' => ['idbpjs' => $data->nokartu_bpjs ?? '', 'nik' => $data->nik_bpjs ?? ''],
                'kontak' => ['nomerTelponSelulerPasien' => $data->phone ?? ''],
                'regNo' => $data->reg_no,
                'regName' => $data->reg_name,
            ],
        ];

        $this->SEPForm['noKartu'] = $data->nokartu_bpjs ?? '';
        $this->SEPForm['noMR'] = $data->reg_no;
        $this->SEPForm['noTelp'] = $data->phone ?? '';
        $this->SEPForm['dpjpLayan'] = $this->getKdDrBpjs($this->drId);
        $this->SEPForm['poli']['tujuan'] = $this->kdpolibpjs ?? '';
        $this->SEPForm['rujukan']['asalRujukan'] = $this->getAsalRujukan();
    }

    private function getKdDrBpjs($drId): string
    {
        if (!$drId) {
            return '';
        }
        return DB::table('rsmst_doctors')->where('dr_id', $drId)->value('kd_dr_bpjs') ?? '';
    }

    private function getAsalRujukan(): string
    {
        return match ($this->kunjunganId) {
            '2' => $this->internal12 ?? '1',
            '3' => $this->postInap ? '2' : $this->kontrol12 ?? '1',
            '4' => '2',
            default => '1',
        };
    }

    /* ---- LOV SKDP ---- */
    public function loadSkdpOptions(): void
    {
        if (empty($this->regNo)) {
            $this->dispatch('toast', type: 'warning', message: 'No. registrasi pasien tidak ditemukan.');
            return;
        }

        $rows = DB::table('rstxn_rjhdrs')
            ->where('reg_no', $this->regNo)
            ->whereNotNull('datadaftarpolirj_json')
            ->orderByDesc('rj_date')
            ->limit(30)
            ->pluck('datadaftarpolirj_json');

        $options = [];
        foreach ($rows as $json) {
            $data = json_decode($json, true) ?? [];
            $noSkdp = $data['kontrol']['noSKDPBPJS'] ?? '';
            if (empty($noSkdp)) {
                continue;
            }
            $options[] = [
                'noSKDP'       => $noSkdp,
                'tglKontrol'   => $data['kontrol']['tglKontrol'] ?? '-',
                'drKontrol'    => $data['kontrol']['drKontrolDesc'] ?? '',
                'drKontrolBpjs'=> $data['kontrol']['drKontrolBPJS'] ?? '',
                'poliKontrol'  => $data['kontrol']['poliKontrolDesc'] ?? '',
            ];
        }

        // deduplikasi berdasarkan noSKDP
        $seen = [];
        $this->skdpOptions = array_values(array_filter($options, function ($o) use (&$seen) {
            if (isset($seen[$o['noSKDP']])) return false;
            return $seen[$o['noSKDP']] = true;
        }));

        if (empty($this->skdpOptions)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada SKDP tersimpan untuk pasien ini.');
            return;
        }

        $this->showSkdpLov = true;
    }

    public function selectSkdp(string $noSkdp, string $kodeDpjp): void
    {
        $this->SEPForm['skdp']['noSurat']  = $noSkdp;
        $this->SEPForm['skdp']['kodeDPJP'] = $kodeDpjp;
        $this->showSkdpLov = false;
    }

    /* ---- Cari Rujukan ---- */
    public function cariRujukan(): void
    {
        $idBpjs = $this->dataPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (empty($idBpjs)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor BPJS tidak ditemukan.');
            return;
        }

        $this->showRujukanLov = true;
        $this->dataRujukan = [];

        match ($this->kunjunganId) {
            '1' => $this->cariRujukanFKTP($idBpjs),
            '2' => $this->internal12 == '1' ? $this->cariRujukanFKTP($idBpjs) : $this->cariRujukanFKTL($idBpjs),
            '3' => $this->postInap ? $this->loadRiwayatRI() : ($this->kontrol12 == '1' ? $this->cariRujukanFKTP($idBpjs) : $this->cariRujukanFKTL($idBpjs)),
            '4' => $this->cariRujukanFKTL($idBpjs),
            default => $this->cariRujukanFKTP($idBpjs),
        };
    }

    // FIX #2: gunakan $this-> bukan VclaimTrait:: static call
    private function cariRujukanFKTP(string $idBpjs): void
    {
        $response = $this->rujukan_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $this->dataRujukan = $response['response']['rujukan'] ?? [];
            $this->incrementVersion('lov-rujukan');
            $this->incrementVersion('modal');
            if (empty($this->dataRujukan)) {
                $this->dispatch('toast', type: 'warning', message: 'Tidak ada data rujukan FKTP.');
            }
        } else {
            $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal memuat rujukan FKTP.');
        }
    }

    private function cariRujukanFKTL(string $idBpjs): void
    {
        $response = $this->rujukan_rs_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $this->dataRujukan = $response['response']['rujukan'] ?? [];
            $this->incrementVersion('lov-rujukan');
            $this->incrementVersion('modal');
            if (empty($this->dataRujukan)) {
                $this->dispatch('toast', type: 'warning', message: 'Tidak ada data rujukan FKTL.');
            }
        } else {
            $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal memuat rujukan FKTL.');
        }
    }

    private function loadRiwayatRI(): void
    {
        if (empty($this->regNo)) {
            $this->dispatch('toast', type: 'warning', message: 'No. registrasi pasien tidak ditemukan.');
            return;
        }

        $rows = DB::table('rstxn_rihdrs')
            ->where('reg_no', $this->regNo)
            ->whereNotNull('datadaftarri_json')
            ->orderByDesc('entry_date')
            ->limit(20)
            ->get([
                'rihdr_no',
                'datadaftarri_json',
                'vno_sep',
                DB::raw("to_char(entry_date, 'dd/mm/yyyy') as entry_date"),
                DB::raw("to_char(exit_date, 'dd/mm/yyyy') as exit_date"),
            ]);

        $this->dataRiwayatRI = [];
        foreach ($rows as $row) {
            $data = json_decode($row->datadaftarri_json, true) ?? [];
            $noSep = $row->vno_sep ?: ($data['sep']['noSep'] ?? '');
            $kontrol = $data['kontrol'] ?? [];
            $noSKDP = $kontrol['noSKDPBPJS'] ?? '';

            if (empty($noSep)) {
                continue;
            }

            $this->dataRiwayatRI[] = [
                'riHdrNo'       => $row->rihdr_no,
                'noSep'         => $noSep,
                'noSKDPBPJS'    => $noSKDP,
                'tglKontrol'    => $kontrol['tglKontrol'] ?? '-',
                'drKontrolDesc' => $kontrol['drKontrolDesc'] ?? '-',
                'drKontrolBPJS' => $kontrol['drKontrolBPJS'] ?? '',
                'poliKontrolDesc' => $kontrol['poliKontrolDesc'] ?? '-',
                'entryDate'     => $row->entry_date ?: '-',
                'exitDate'      => $row->exit_date ?: '-',
                'drDesc'        => $data['drDesc'] ?? '-',
                'bangsalDesc'   => $data['bangsalDesc'] ?? '-',
            ];
        }

        if (empty($this->dataRiwayatRI)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada riwayat rawat inap dengan SEP untuk pasien ini.');
            return;
        }

        $this->showRiwayatRILov = true;
        $this->showRujukanLov = false;
        $this->incrementVersion('modal');
    }

    public function pilihRiwayatRI(int $index): void
    {
        $ri = $this->dataRiwayatRI[$index] ?? null;
        if (!$ri) {
            return;
        }

        $idBpjs = $this->dataPasien['pasien']['identitas']['idbpjs'] ?? '';
        $tglSep = !empty($this->SEPForm['tglSep']) ? Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        $response = $this->peserta_nomorkartu($idBpjs, $tglSep)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $peserta = $response['response']['peserta'] ?? [];
            if (!empty($peserta)) {
                $this->setSEPFormPostInap($peserta, $ri);
                $this->showRiwayatRILov = false;
                $this->incrementVersion('form-sep');
                $this->incrementVersion('modal');
                $this->dispatch('toast', type: 'success', message: 'Data rawat inap dipilih. No. Rujukan & SKDP terisi otomatis.');
            }
        } else {
            $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal memuat data peserta.');
        }
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
        $asalRujukan = $this->getAsalRujukan();

        /* BUG #1 FIX — poli.tujuan:
         * - Rujukan FKTP (1) & Kontrol (3): poli HARUS sesuai rujukan — terkunci
         * - Rujukan Internal (2) & Antar RS (4): poli BEBAS dipilih — pakai poli dokter terpilih
         *   (dari $this->SEPForm['poli']['tujuan'] yang sudah diisi saat LOV dokter dipilih,
         *    atau fallback ke kode poli rujukan)
         */
        $poliTujuan = match ($this->kunjunganId) {
            '1', '3' => $rujukan['poliRujukan']['kode'] ?? $this->SEPForm['poli']['tujuan'],
            '2', '4' => $this->SEPForm['poli']['tujuan'] ?: $rujukan['poliRujukan']['kode'] ?? '',
            default => $rujukan['poliRujukan']['kode'] ?? $this->SEPForm['poli']['tujuan'],
        };

        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => $asalRujukan,
                'asalRujukanNama' => $asalRujukan == '1' ? 'Faskes Tingkat 1' : 'Faskes Tingkat 2 (RS)',
                // Simpan d/m/Y untuk display — convert ke Y-m-d di buildSEPRequest()
                'tglRujukan' => Carbon::parse($rujukan['tglKunjungan'])->format('d/m/Y'),
                'noRujukan' => $rujukan['noKunjungan'] ?? '',
                'ppkRujukan' => $rujukan['provPerujuk']['kode'] ?? '',
                'ppkRujukanNama' => $rujukan['provPerujuk']['nama'] ?? '',
            ],
            'diagAwal' => $rujukan['diagnosa']['kode'] ?? '',
            'poli' => ['tujuan' => $poliTujuan, 'eksekutif' => '0'],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
                'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
        ]);

        /* BUG #2 FIX — skdp.noSurat untuk Kontrol:
         * Nomor surat kontrol BERBEDA dari nomor rujukan FKTP (noKunjungan).
         * Surat kontrol diterbitkan oleh FKRTL via RencanaKontrol/insert — user isi manual.
         * noSurat dikosongkan agar user tidak bingung dengan nomor rujukan yang salah.
         * kodeDPJP diisi dari DPJP yang sudah dipilih (auto dari LOV dokter).
         */
        if ($this->kunjunganId == '3' && !$this->postInap) {
            $this->SEPForm['skdp'] = [
                'noSurat' => '', // user isi manual dari surat kontrol yang sudah dibuat
                'kodeDPJP' => $this->SEPForm['dpjpLayan'] ?? '',
            ];
        }
    }

    private function setSEPFormPostInap(array $peserta, array $riData = []): void
    {
        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => '2',
                'asalRujukanNama' => 'Faskes Tingkat 2 (RS)',
                // Simpan d/m/Y untuk display — convert ke Y-m-d di buildSEPRequest()
                'tglRujukan' => !empty($this->SEPForm['tglSep'])
                    ? $this->SEPForm['tglSep'] // sudah d/m/Y
                    : Carbon::now()->format('d/m/Y'),
                // No. Rujukan = No. SEP rawat inap
                'noRujukan' => $riData['noSep'] ?? '',
                'ppkRujukan' => '0184R006',
                'ppkRujukanNama' => 'RSI Madinah',
            ],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
                'klsRawatNaik' => '',
                'pembiayaan' => '',
                'penanggungJawab' => '',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
            'skdp' => [
                // No. Surat Kontrol = SKDP dari pulang rawat inap
                'noSurat' => $riData['noSKDPBPJS'] ?? ($this->noReferensi ?? ''),
                'kodeDPJP' => $riData['drKontrolBPJS'] ?? $this->getKdDrBpjs($this->drId),
            ],
        ]);

        // Set selectedRujukan agar form SEP muncul
        // tglKunjungan pakai Y-m-d (native API) supaya view Carbon::parse tidak gagal
        $this->selectedRujukan = [
            'noKunjungan' => $riData['noSep'] ?? '',
            'tglKunjungan' => Carbon::createFromFormat('d/m/Y', $this->SEPForm['rujukan']['tglRujukan'])->format('Y-m-d'),
            'provPerujuk' => ['kode' => '0184R006', 'nama' => 'RSI Madinah'],
            'poliRujukan' => ['nama' => $riData['poliKontrolDesc'] ?? '-'],
        ];
    }

    public function updatedSEPFormTujuanKunj(string $value): void
    {
        if ($value == '0') {
            $this->SEPForm['flagProcedure'] = '';
            $this->SEPForm['kdPenunjang'] = '';
            $this->SEPForm['assesmentPel'] = '';
        }
        if ($value != '2') {
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

        $this->dispatch('sep-generated', reqSep: $request);
        $this->dispatch('toast', type: 'success', message: 'Data SEP berhasil disimpan.');
        $this->showRujukanLov = false;
        $this->showRiwayatRILov = false;
        $this->closeModal();
    }

    private function validateSEPForm(): void
    {
        $rules = [
            'SEPForm.noKartu' => 'required',
            'SEPForm.tglSep' => 'required|date_format:d/m/Y',
            'SEPForm.noMR' => 'required',
            'SEPForm.diagAwal' => 'required',
            'SEPForm.poli.tujuan' => 'required',
            'SEPForm.dpjpLayan' => 'required',
            'SEPForm.klsRawat.klsRawatHak' => 'required',
            'SEPForm.noTelp' => 'required',
            // FIX #4: validasi KLL — lokasi wajib jika lakaLantas != 0 (UAT 6.1.3–6.1.5)
            'SEPForm.jaminan.lakaLantas' => 'required|in:0,1,2,3',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi' => 'required_unless:SEPForm.jaminan.lakaLantas,0',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten' => 'required_unless:SEPForm.jaminan.lakaLantas,0',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan' => 'required_unless:SEPForm.jaminan.lakaLantas,0',
        ];

        if ($this->kunjunganId == '3') {
            $rules['SEPForm.skdp.noSurat'] = 'required';
            $rules['SEPForm.skdp.kodeDPJP'] = 'required';
        }

        $messages = [
            'SEPForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi.',
            'SEPForm.tglSep.required' => 'Tanggal SEP wajib diisi.',
            'SEPForm.tglSep.date_format' => 'Format Tanggal SEP harus DD/MM/YYYY.',
            'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi.',
            'SEPForm.poli.tujuan.required' => 'Poli tujuan harus diisi.',
            'SEPForm.dpjpLayan.required' => 'DPJP harus diisi.',
            'SEPForm.klsRawat.klsRawatHak.required' => 'Kelas rawat hak belum terisi. Pilih rujukan terlebih dahulu.',
            'SEPForm.noTelp.required' => 'No. telepon pasien harus diisi.',
            'SEPForm.jaminan.lakaLantas.in' => 'Nilai Laka Lantas tidak valid.',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdPropinsi.required_unless' => 'Kode Propinsi wajib diisi untuk kasus KLL.',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKabupaten.required_unless' => 'Kode Kabupaten wajib diisi untuk kasus KLL.',
            'SEPForm.jaminan.penjamin.suplesi.lokasiLaka.kdKecamatan.required_unless' => 'Kode Kecamatan wajib diisi untuk kasus KLL.',
            'SEPForm.skdp.noSurat.required' => 'No. Surat Kontrol wajib diisi untuk jenis kunjungan Kontrol.',
            'SEPForm.skdp.kodeDPJP.required' => 'Kode DPJP Kontrol wajib diisi.',
        ];

        $this->validate($rules, $messages);
    }

    private function buildSEPRequest(): array
    {
        // tglSep: d/m/Y → Y-m-d untuk API
        $tglSepFormatted = Carbon::createFromFormat('d/m/Y', $this->SEPForm['tglSep'])->format('Y-m-d');

        // tglRujukan: d/m/Y → Y-m-d untuk API, fallback ke tglSep jika kosong
        $tglRujukanRaw = $this->SEPForm['rujukan']['tglRujukan'] ?? '';
        $tglRujukan = !empty($tglRujukanRaw) ? Carbon::createFromFormat('d/m/Y', $tglRujukanRaw)->format('Y-m-d') : $tglSepFormatted;

        $asalRujukan = $this->SEPForm['rujukan']['asalRujukan'] ?? $this->getAsalRujukan();
        if ($this->kunjunganId == '3' && !empty($this->postInap)) {
            $asalRujukan = '2';
        }

        $skdp = [
            'noSurat' => $this->SEPForm['skdp']['noSurat'] ?? '',
            'kodeDPJP' => $this->SEPForm['skdp']['kodeDPJP'] ?? '',
        ];

        $request = [
            'request' => [
                't_sep' => [
                    'noKartu' => $this->SEPForm['noKartu'] ?? '',
                    'tglSep' => $tglSepFormatted,
                    'ppkPelayanan' => $this->SEPForm['ppkPelayanan'] ?? '0184R006',
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
                        'asalRujukanNama' => $this->SEPForm['rujukan']['asalRujukanNama'] ?? '',
                        'tglRujukan' => $tglRujukan,
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
                    'skdp' => $skdp,
                    'dpjpLayan' => $this->SEPForm['dpjpLayan'] ?? '',
                    'noTelp' => $this->SEPForm['noTelp'] ?? '',
                    'user' => 'sirus App',
                ],
            ],
        ];

        if (($this->SEPForm['jnsPelayanan'] ?? '2') == '1') {
            $request['request']['t_sep']['dpjpLayan'] = '';
        }

        return $request;
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

        if ($lakaLantas != '0') {
            $p = $this->SEPForm['jaminan']['penjamin'] ?? [];
            $s = $p['suplesi'] ?? [];
            $l = $s['lokasiLaka'] ?? [];

            $jaminan['penjamin'] = [
                'tglKejadian' => $p['tglKejadian'] ?? '',
                'keterangan' => $p['keterangan'] ?? '',
                'suplesi' => [
                    'suplesi' => $s['suplesi'] ?? '0',
                    // FIX #5: typo 'penjukan' → 'penjamin' (key yang benar)
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

    /* ---- FIX #2: cetakSEP — method yang dipanggil di template tapi tidak ada ---- */
    public function cetakSEP(): void
    {
        if (empty($this->noSep) || empty($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk dicetak.');
            return;
        }
        $this->dispatch('cetak-sep-rj.open', rjNo: $this->rjNo);
    }

    /* ---- Reset & Close ---- */
    private function resetForm(): void
    {
        $this->reset(['SEPForm', 'selectedRujukan', 'showRujukanLov', 'showRiwayatRILov', 'dataRujukan', 'dataRiwayatRI', 'noSep']);
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->isFormLocked = false;
    }

    #[On('close-vclaim-modal')]
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'vclaim-rj-actions');
        $this->resetForm();
        $this->resetVersion();
    }

    /* ---- LOV Listeners ---- */
    #[On('lov.selected.rjFormDokterVclaim')]
    public function rjFormDokterVclaim(string $target, array $payload): void
    {
        $this->drId = $payload['dr_id'] ?? null;
        $this->drDesc = $payload['dr_name'] ?? '';
        $this->SEPForm['dpjpLayan'] = $payload['kd_dr_bpjs'] ?? '';
        $this->poliId = $payload['poli_id'] ?? null;
        $this->poliDesc = $payload['poli_desc'] ?? '';
        $this->SEPForm['poli']['tujuan'] = $payload['kd_poli_bpjs'] ?? ($this->kdpolibpjs ?? '');

        if ($this->kunjunganId == '3' && !$this->postInap) {
            $this->SEPForm['skdp']['kodeDPJP'] = $payload['kd_dr_bpjs'] ?? '';
        } else {
            $this->SEPForm['skdp']['kodeDPJP'] = '';
        }

        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
    }

    #[On('lov.selected.rjFormDiagnosaVclaim')]
    public function rjFormDiagnosaVclaim(string $target, array $payload): void
    {
        $this->diagnosaId = $payload['icdx'] ?? null;
        $this->SEPForm['diagAwal'] = $payload['icdx'] ?? '';
        $this->incrementVersion('modal');
        $this->incrementVersion('form-sep');
    }

    public function mount(): void
    {
        $this->SEPForm['tglSep'] = Carbon::now()->format('d/m/Y');
        $this->registerAreas(['modal', 'lov-rujukan', 'form-sep', 'info-pasien']);
    }
};
?>

<div>
    <x-modal name="vclaim-rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data SEP' : 'Buat SEP Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola data SEP (Surat Eligibilitas Peserta) BPJS.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit SEP' : 'Mode: Buat SEP' }}
                            </x-badge>
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">

                {{-- TOMBOL AKSI --}}
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <x-secondary-button type="button" wire:click="cariRujukan" class="gap-2" :disabled="$isFormLocked">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        {{ ($kunjunganId == '3' && $postInap) ? 'Cari Riwayat Rawat Inap' : 'Cari Rujukan BPJS' }}
                    </x-secondary-button>

                    @if (!empty($selectedRujukan))
                        <x-badge variant="info" class="gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
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

                {{-- LOV Riwayat Rawat Inap (Post Inap) --}}
                @if ($showRiwayatRILov)
                    <div class="mb-4 overflow-hidden bg-white border rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Riwayat Rawat Inap</h3>
                            <p class="mt-1 text-xs text-gray-500">No. SEP RI akan digunakan sebagai No. Rujukan, dan No. SKDP sebagai Surat Kontrol</p>
                        </div>
                        <div class="p-4">
                            <div class="space-y-2 overflow-y-auto max-h-60">
                                @forelse($dataRiwayatRI as $index => $ri)
                                    <div wire:key="ri-item-{{ $index }}"
                                        class="p-3 transition-colors border rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                        wire:click="pilihRiwayatRI({{ $index }})">
                                        <div class="flex justify-between">
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">No. SEP RI:</span>
                                                <span class="ml-1 text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $ri['noSep'] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Masuk:</span>
                                                <span class="ml-1 text-sm">{{ $ri['entryDate'] }}</span>
                                                <span class="mx-1 text-gray-400">-</span>
                                                <span class="text-xs font-medium text-gray-500">Pulang:</span>
                                                <span class="ml-1 text-sm">{{ $ri['exitDate'] }}</span>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Dokter:</span>
                                                <span class="block text-sm">{{ $ri['drDesc'] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Bangsal:</span>
                                                <span class="block text-sm">{{ $ri['bangsalDesc'] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">No. SKDP:</span>
                                                <span class="block text-sm {{ !empty($ri['noSKDPBPJS']) ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-red-500' }}">
                                                    {{ !empty($ri['noSKDPBPJS']) ? $ri['noSKDPBPJS'] : 'Belum dibuat' }}
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-500">Tgl. Kontrol:</span>
                                                <span class="block text-sm">{{ $ri['tglKontrol'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-4 text-sm text-center text-gray-500">Tidak ada riwayat rawat inap</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/50">
                            <x-secondary-button type="button" wire:click="$set('showRiwayatRILov', false)"
                                class="justify-center w-full">
                                Tutup
                            </x-secondary-button>
                        </div>
                    </div>
                @endif

                {{-- MAIN CONTENT --}}
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">

                    {{-- Data Pasien --}}
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
                                @php
                                    $isPostInap = !empty($postInap);
                                    $jenisRujukanLabels = [
                                        '1' => 'Rujukan FKTP',
                                        '2' => 'Rujukan Internal',
                                        '3' => 'Kontrol',
                                        '4' => 'Rujukan Antar RS',
                                    ];
                                    $faskesLabels = ['1' => 'Faskes Tingkat 1', '2' => 'Faskes Tingkat 2 RS'];
                                    $asalRujukan = $this->getAsalRujukan();
                                @endphp
                                <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700 space-y-2">
                                    <div>
                                        <span class="text-xs font-medium text-gray-500">Jenis Rujukan:</span>
                                        <div class="mt-1">
                                            <span
                                                class="px-2 py-1 text-xs rounded-full {{ $asalRujukan == '1' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                {{ $jenisRujukanLabels[$kunjunganId] ?? 'Internal' }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($kunjunganId == '3')
                                        <div>
                                            <span class="text-xs font-medium text-gray-500">Post Inap:</span>
                                            <div class="mt-1">
                                                <span
                                                    class="px-2 py-1 text-xs rounded-full {{ $isPostInap ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                    {{ $isPostInap ? 'Ya' : 'Tidak' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Form SEP --}}
                    @if (!empty($selectedRujukan) || ($kunjunganId == '3' && $postInap))
                        <div wire:key="{{ $this->renderKey('form-sep', [$formMode, $selectedRujukan['noKunjungan'] ?? '']) }}"
                            class="space-y-4 lg:col-span-3">

                            <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                                <h3
                                    class="flex items-center gap-2 mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Form SEP
                                </h3>

                                {{-- ============================================================
                                     URUTAN FIELD mengikuti form VClaim BPJS (screenshot):
                                     1.  Spesialis/SubSpesialis + Eksekutif toggle
                                     2.  DPJP yang Melayani (LOV)
                                     3.  Asal Rujukan
                                     4.  PPK Asal Rujukan
                                     5.  Tgl. Rujukan (yyyy-mm-dd)
                                     6.  No. Rujukan
                                     7.  No. Surat Kontrol/SKDP  ← hanya kontrol non-postInap
                                     8.  DPJP Pemberi Surat SKDP ← hanya kontrol non-postInap
                                     9.  Tgl. SEP (dd/mm/yyyy)
                                     10. No. MR + Peserta COB
                                     11. Diagnosa (LOV)
                                     12. No. Telepon
                                     13. Catatan
                                     14. Status Kecelakaan (KLL)
                                     15. Jenis SEP (Tujuan Kunjungan)
                                     --- accordion: Kelas Rawat, Katarak ---
                                ============================================================ --}}
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

                                    {{-- 1. Spesialis/SubSpesialis + Eksekutif --}}
                                    @php $poliEditable = in_array($kunjunganId, ['2', '4']) && !$isFormLocked; @endphp
                                    <div class="lg:col-span-3">
                                        <x-input-label :value="'Spesialis / Sub Spesialis *' .
                                            ($poliEditable ? ' (bebas)' : ' (sesuai rujukan)')" />
                                        <x-text-input wire:model="SEPForm.poli.tujuan" class="w-full"
                                            :disabled="!$poliEditable" placeholder="Kode Poli" :error="$errors->has('SEPForm.poli.tujuan')" />
                                        @if ($poliEditable)
                                            <p class="mt-1 text-xs text-amber-500">Otomatis terisi saat LOV Dokter
                                                dipilih</p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                    </div>
                                    <div class="flex items-end pb-1">
                                        <x-toggle wire:model="SEPForm.poli.eksekutif" trueValue="1" falseValue="0"
                                            label="Eksekutif" :disabled="$isFormLocked" />
                                    </div>

                                    {{-- 2. DPJP yang Melayani (LOV) --}}
                                    <div class="lg:col-span-4">
                                        <livewire:lov.dokter.lov-dokter label="DPJP yang Melayani *"
                                            target="rjFormDokterVclaim" :initialDrId="$drId ?? null" :disabled="$isFormLocked" />
                                        @if (!empty($SEPForm['dpjpLayan']))
                                            <p class="mt-1 text-xs text-gray-400">
                                                Kode DPJP: <span
                                                    class="font-mono font-semibold">{{ $SEPForm['dpjpLayan'] }}</span>
                                            </p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                    </div>

                                    {{-- 3. Asal Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Asal Rujukan" />
                                        <x-text-input class="w-full" :disabled="true"
                                            value="{{ $SEPForm['rujukan']['asalRujukanNama'] ?: ($SEPForm['rujukan']['asalRujukan'] == '1' ? 'Faskes Tingkat 1' : ($SEPForm['rujukan']['asalRujukan'] == '2' ? 'Faskes Tingkat 2 (RS)' : '-')) }}" />
                                    </div>

                                    {{-- 4. PPK Asal Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="PPK Asal Rujukan *" />
                                        <x-text-input wire:model="SEPForm.rujukan.ppkRujukanNama" class="w-full"
                                            :disabled="true" placeholder="Nama faskes perujuk" />
                                        @if (!empty($SEPForm['rujukan']['ppkRujukan']))
                                            <p class="mt-1 text-xs text-gray-400">Kode:
                                                {{ $SEPForm['rujukan']['ppkRujukan'] }}</p>
                                        @endif
                                    </div>

                                    {{-- 5. Tgl. Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Tgl. Rujukan" />
                                        <x-text-input wire:model="SEPForm.rujukan.tglRujukan" class="w-full"
                                            :disabled="true" placeholder="dd/mm/yyyy" />
                                    </div>

                                    {{-- 6. No. Rujukan --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. Rujukan *" />
                                        <x-text-input wire:model="SEPForm.rujukan.noRujukan" class="w-full"
                                            :disabled="$isFormLocked" placeholder="Nomor rujukan" />
                                    </div>

                                    {{-- 7 & 8. Surat Kontrol/SKDP — untuk semua Kontrol (termasuk post inap) --}}
                                    @if ($kunjunganId == '3')
                                        <div class="lg:col-span-4">
                                            <x-input-label value="No. Surat Kontrol/SKDP *" />

                                            @if (!$isFormLocked)
                                                <div class="flex gap-2 mt-1">
                                                    <x-text-input wire:model="SEPForm.skdp.noSurat" class="flex-1"
                                                        placeholder="Pilih dari riwayat atau ketik manual" />
                                                    <button type="button" wire:click="loadSkdpOptions"
                                                        wire:loading.attr="disabled" wire:target="loadSkdpOptions"
                                                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white rounded-xl bg-brand-green hover:bg-brand-green/90 dark:bg-brand-lime dark:text-gray-900 transition whitespace-nowrap">
                                                        <span wire:loading.remove wire:target="loadSkdpOptions">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h8" />
                                                            </svg>
                                                        </span>
                                                        <span wire:loading wire:target="loadSkdpOptions"><x-loading class="w-4 h-4" /></span>
                                                        Pilih Riwayat
                                                    </button>
                                                </div>

                                                {{-- LOV dropdown riwayat SKDP --}}
                                                @if ($showSkdpLov && count($skdpOptions) > 0)
                                                    <div class="relative mt-1">
                                                        <div class="absolute z-50 w-full overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                                            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                                                                <span class="text-xs font-semibold text-gray-500 uppercase">Riwayat SKDP Pasien</span>
                                                                <button type="button" wire:click="$set('showSkdpLov', false)"
                                                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            <ul class="overflow-y-auto divide-y divide-gray-100 max-h-56 dark:divide-gray-800">
                                                                @foreach ($skdpOptions as $opt)
                                                                    <li>
                                                                        <button type="button"
                                                                            wire:click="selectSkdp('{{ $opt['noSKDP'] }}', '{{ $opt['drKontrolBpjs'] }}')"
                                                                            class="w-full px-4 py-2.5 text-left hover:bg-brand-green/5 dark:hover:bg-brand-lime/5 transition">
                                                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $opt['noSKDP'] }}</div>
                                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                                Tgl Kontrol: {{ $opt['tglKontrol'] }}
                                                                                @if ($opt['drKontrol']) · Dr. {{ $opt['drKontrol'] }} @endif
                                                                                @if ($opt['poliKontrol']) · {{ $opt['poliKontrol'] }} @endif
                                                                            </div>
                                                                        </button>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </div>
                                                @endif
                                            @else
                                                <x-text-input wire:model="SEPForm.skdp.noSurat" class="w-full mt-1" disabled />
                                            @endif

                                            <x-input-error :messages="$errors->get('SEPForm.skdp.noSurat')" class="mt-1" />

                                            <div class="mt-2">
                                                <x-input-label value="Kode DPJP Pemberi SKDP *" />
                                                <x-text-input wire:model="SEPForm.skdp.kodeDPJP" class="w-full mt-1"
                                                    :disabled="$isFormLocked" placeholder="Otomatis dari pilih riwayat atau isi manual" />
                                                <x-input-error :messages="$errors->get('SEPForm.skdp.kodeDPJP')" class="mt-1" />
                                            </div>
                                        </div>
                                    @endif

                                    {{-- 9. Tgl. SEP --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="(dd/mm/yyyy) Tgl. SEP *" />
                                        <x-text-input wire:model="SEPForm.tglSep" class="w-full" :disabled="$isFormLocked"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('SEPForm.tglSep')" />
                                        <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                    </div>

                                    {{-- 10. No. MR + Peserta COB --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. MR *" />
                                        <div class="flex items-center gap-3">
                                            <x-text-input wire:model="SEPForm.noMR" class="flex-1"
                                                :disabled="true" />
                                            <label class="flex items-center gap-2 cursor-pointer whitespace-nowrap">
                                                <input type="checkbox" wire:model="SEPForm.cob.cob" value="1"
                                                    @checked($SEPForm['cob']['cob'] == '1') {{ $isFormLocked ? 'disabled' : '' }}
                                                    class="w-4 h-4 text-blue-600 rounded border-gray-300" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Peserta
                                                    COB</span>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- 11. Diagnosa (LOV) --}}
                                    <div class="lg:col-span-4">
                                        <livewire:lov.diagnosa.lov-diagnosa label="Diagnosa *"
                                            target="rjFormDiagnosaVclaim" :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked" />
                                        @if (!empty($SEPForm['diagAwal']))
                                            <p class="mt-1 text-xs text-gray-400">
                                                Kode ICD-10: <span
                                                    class="font-mono font-semibold">{{ $SEPForm['diagAwal'] }}</span>
                                            </p>
                                        @endif
                                        <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                    </div>

                                    {{-- 12. No. Telepon --}}
                                    <div class="lg:col-span-2">
                                        <x-input-label value="No. Telepon *" />
                                        <x-text-input wire:model="SEPForm.noTelp" class="w-full" :disabled="$isFormLocked"
                                            placeholder="08xxxx"
                                            :error="$errors->has('SEPForm.noTelp')" />
                                        <x-input-error :messages="$errors->get('SEPForm.noTelp')" class="mt-1" />
                                    </div>

                                    {{-- 13. Catatan --}}
                                    <div class="lg:col-span-4">
                                        <x-input-label value="Catatan" />
                                        <x-textarea wire:model="SEPForm.catatan" class="w-full" rows="2"
                                            :disabled="$isFormLocked" placeholder="Catatan (opsional)" />
                                    </div>

                                    {{-- 14. Status Kecelakaan (KLL) --}}
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
                                                    <p class="mt-1 text-xs text-red-500">* Wajib diisi untuk kasus KLL
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- 15. Jenis SEP / Tujuan Kunjungan --}}
                                    <div class="lg:col-span-1">
                                        <x-input-label value="Jenis SEP *" />
                                        <x-select-input wire:model.live="SEPForm.tujuanKunj" class="w-full"
                                            :disabled="$isFormLocked">
                                            @foreach ($tujuanKunjOptions as $option)
                                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    @if ($SEPForm['tujuanKunj'] != '0')
                                        <div>
                                            <x-input-label value="Flag Procedure" />
                                            <x-select-input wire:model="SEPForm.flagProcedure" class="w-full"
                                                :disabled="$isFormLocked">
                                                @foreach ($flagProcedureOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                        <div>
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

                                </div>

                                {{-- Accordion: Data Tambahan (Kelas Rawat, Katarak) --}}
                                <div x-data="{ open: false }" class="mt-4 border rounded dark:border-gray-700">
                                    <button type="button" @click="open = !open"
                                        class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-left text-gray-600 bg-gray-100 rounded dark:bg-gray-700/50 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <span>Data Tambahan (Kelas Rawat, Katarak)</span>
                                        <svg x-bind:class="open ? 'rotate-180' : ''"
                                            class="w-4 h-4 transition-transform" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" x-collapse class="p-4">
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                            <div>
                                                <x-input-label value="Kelas Rawat Hak *" />
                                                <x-select-input wire:model="SEPForm.klsRawat.klsRawatHak"
                                                    class="w-full" :disabled="true"
                                                    :error="$errors->has('SEPForm.klsRawat.klsRawatHak')">
                                                    <option value="">-- Pilih Rujukan dulu --</option>
                                                    <option value="1">Kelas 1</option>
                                                    <option value="2">Kelas 2</option>
                                                    <option value="3">Kelas 3</option>
                                                </x-select-input>
                                                <x-input-error :messages="$errors->get('SEPForm.klsRawat.klsRawatHak')" class="mt-1" />
                                            </div>
                                            <div>
                                                <x-input-label value="Kelas Rawat Naik" />
                                                <x-select-input wire:model="SEPForm.klsRawat.klsRawatNaik"
                                                    class="w-full" :disabled="$isFormLocked">
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
                                                <x-select-input wire:model="SEPForm.klsRawat.pembiayaan"
                                                    class="w-full" :disabled="$isFormLocked">
                                                    <option value="">Pilih</option>
                                                    <option value="1">Pribadi</option>
                                                    <option value="2">Pemberi Kerja</option>
                                                    <option value="3">Asuransi Kesehatan Tambahan</option>
                                                </x-select-input>
                                            </div>
                                            <div>
                                                <x-input-label value="Penanggung Jawab" />
                                                <x-text-input wire:model="SEPForm.klsRawat.penanggungJawab"
                                                    class="w-full" :disabled="$isFormLocked" />
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
                            </div>

                            {{-- Info Rujukan Terpilih --}}
                            @if (!empty($selectedRujukan))
                                <div
                                    class="p-4 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                    <h4
                                        class="flex items-center gap-2 mb-2 text-sm font-medium text-blue-800 dark:text-blue-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Data Rujukan Terpilih
                                    </h4>
                                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">No. Rujukan:</span>
                                            <p class="font-medium">
                                                {{ $selectedRujukan['noKunjungan'] ?? ($SEPForm['rujukan']['noRujukan'] ?? '-') }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">Tgl Rujukan:</span>
                                            <p class="font-medium">
                                                {{ isset($selectedRujukan['tglKunjungan'])
                                                    ? Carbon::parse($selectedRujukan['tglKunjungan'])->format('d/m/Y')
                                                    : ($SEPForm['rujukan']['tglRujukan'] ?? '-') }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-blue-600 dark:text-blue-400">Asal Rujukan:</span>
                                            <p class="font-medium">
                                                {{ $selectedRujukan['provPerujuk']['nama'] ?? ($SEPForm['rujukan']['ppkRujukanNama'] ?? '-') }}
                                            </p>
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

                {{-- SEP sudah ada --}}
                @if (!empty($noSep))
                    <div
                        class="p-4 mt-4 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-green-100 rounded-full dark:bg-green-800">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-300" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-green-700 dark:text-green-300">No. SEP</span>
                                    <p class="text-lg font-semibold text-green-800 dark:text-green-200">
                                        {{ $noSep }}</p>
                                </div>
                            </div>
                            <x-info-button type="button" wire:click="cetakSEP" class="gap-2 text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak SEP
                            </x-info-button>
                        </div>
                    </div>
                @endif
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
                        <span wire:loading><x-loading /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
