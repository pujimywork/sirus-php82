<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-anamnesa-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT - ANAMNESA
     =============================== */
    #[On('open-rm-anamnesa-rj')]
    public function openAnamnesa($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->$rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();
        // Ambil data kunjungan RJ
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize anamnesa data if not exists
        if (!isset($this->dataDaftarPoliRJ['anamnesa'])) {
            $this->dataDaftarPoliRJ['anamnesa'] = $this->getDefaultAnamnesa();
        }

        // ✅ Ambil data pasien dari master pasien (untuk alergi & riwayat penyakit)
        $pasienData = $this->findDataMasterPasien($dataDaftarPoliRJ['regNo']);

        // ✅ Isi alergi jika ada di data pasien
        if (isset($pasienData['pasien']['alergi'])) {
            // Masukkan ke struktur anamnesa
            $this->dataDaftarPoliRJ['anamnesa']['alergi']['alergi'] = $pasienData['pasien']['alergi'];
        }

        // ✅ Isi riwayat penyakit dahulu jika ada
        if (isset($pasienData['pasien']['riwayatPenyakitDahulu'])) {
            $this->dataDaftarPoliRJ['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] = $pasienData['pasien']['riwayatPenyakitDahulu'];
        }

        // 🔥 INCREMENT: Refresh seluruh modal anamnesa
        $this->incrementVersion('modal-anamnesa-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT ANAMNESA STRUCTURE
     =============================== */
    private function getDefaultAnamnesa(): array
    {
        return [
            'pengkajianPerawatanTab' => 'Pengkajian Perawatan',
            'pengkajianPerawatan' => [
                'perawatPenerima' => '',
                'jamDatang' => '',
            ],

            'keluhanUtamaTab' => 'Keluhan Utama',
            'keluhanUtama' => [
                'keluhanUtama' => '',
            ],

            'anamnesaDiperolehTab' => 'Anamnesa Diperoleh',
            'anamnesaDiperoleh' => [
                'autoanamnesa' => [],
                'allonanamnesa' => [],
                'anamnesaDiperolehDari' => '',
            ],

            'riwayatPenyakitSekarangUmumTab' => 'Riwayat Penyakit Sekarang (Umum)',
            'riwayatPenyakitSekarangUmum' => [
                'riwayatPenyakitSekarangUmum' => '',
            ],

            'riwayatPenyakitDahuluTab' => 'Riwayat Penyakit (Dahulu)',
            'riwayatPenyakitDahulu' => [
                'riwayatPenyakitDahulu' => '',
            ],

            'alergiTab' => 'Alergi',
            'alergi' => [
                'alergi' => '',
            ],

            // 'rekonsiliasiObatTab' => 'Rekonsiliasi Obat',
            // 'rekonsiliasiObat' => [],

            // 'lainLainTab' => 'lain-Lain',
            // 'lainLain' => [
            //     'merokok' => [],
            //     'terpaparRokok' => [],
            // ],

            // 'faktorResikoTab' => 'Faktor Resiko',
            // 'faktorResiko' => [
            //     'hipertensi' => [],
            //     'diabetesMelitus' => [],
            //     'penyakitJantung' => [],
            //     'asma' => [],
            //     'stroke' => [],
            //     'liver' => [],
            //     'tuberculosisParu' => [],
            //     'rokok' => [],
            //     'minumAlkohol' => [],
            //     'ginjal' => [],
            //     'lainLain' => '',
            // ],

            // 'penyakitKeluargaTab' => 'Riwayat Penyakit Keluarga',
            // 'penyakitKeluarga' => [
            //     'hipertensi' => [],
            //     'diabetesMelitus' => [],
            //     'penyakitJantung' => [],
            //     'asma' => [],
            //     'lainLain' => '',
            // ],

            // 'statusFungsionalTab' => 'Status Fungsional',
            // 'statusFungsional' => [
            //     'tongkat' => [],
            //     'kursiRoda' => [],
            //     'brankard' => [],
            //     'walker' => [],
            //     'lainLain' => '',
            // ],

            // 'cacatTubuhTab' => 'Cacat Tubuh',
            // 'cacatTubuh' => [
            //     'cacatTubuh' => [],
            //     'sebutCacatTubuh' => '',
            // ],

            'statusPsikologisTab' => 'Status Psikologis',
            'statusPsikologis' => [
                'tidakAdaKelainan' => [],
                'marah' => [],
                'ccemas' => [],
                'takut' => [],
                'sedih' => [],
                'cenderungBunuhDiri' => [],
                'sebutstatusPsikologis' => '',
            ],

            'statusMentalTab' => 'Status Mental',
            'statusMental' => [
                'statusMental' => '',
                'statusMentalOption' => [['statusMental' => 'Sadar dan Orientasi Baik'], ['statusMental' => 'Ada Masalah Perilaku'], ['statusMental' => 'Perilaku Kekerasan yang dialami sebelumnya']],
                'keteranganStatusMental' => '',
            ],

            // 'hubunganDgnKeluargaTab' => 'Sosial',
            // 'hubunganDgnKeluarga' => [
            //     'hubunganDgnKeluarga' => '',
            //     'hubunganDgnKeluargaOption' => [['hubunganDgnKeluarga' => 'Baik'], ['hubunganDgnKeluarga' => 'Tidak Baik']],
            // ],

            // 'tempatTinggalTab' => 'Tempat Tinggal',
            // 'tempatTinggal' => [
            //     'tempatTinggal' => '',
            //     'tempatTinggalOption' => [['tempatTinggal' => 'Rumah'], ['tempatTinggal' => 'Panti'], ['tempatTinggal' => 'Lain-lain']],
            //     'keteranganTempatTinggal' => '',
            // ],

            // 'spiritualTab' => 'Spiritual',
            // 'spiritual' => [
            //     'spiritual' => 'Islam',
            //     'ibadahTeratur' => '',
            //     'ibadahTeraturOptions' => [['ibadahTeratur' => 'Ya'], ['ibadahTeratur' => 'Tidak']],
            //     'nilaiKepercayaan' => '',
            //     'nilaiKepercayaanOptions' => [['nilaiKepercayaan' => 'Ya'], ['nilaiKepercayaan' => 'Tidak']],
            //     'keteranganSpiritual' => '',
            // ],

            // 'ekonomiTab' => 'Ekonomi',
            // 'ekonomi' => [
            //     'pengambilKeputusan' => 'Ayah',
            //     'pekerjaan' => 'Swasta',
            //     'penghasilanBln' => '',
            //     'penghasilanBlnOptions' => [['penghasilanBln' => '< 5Jt'], ['penghasilanBln' => '5Jt - 10Jt'], ['penghasilanBln' => '>10Jt']],
            //     'keteranganEkonomi' => '',
            // ],

            // 'edukasiTab' => 'Edukasi',
            // 'edukasi' => [
            //     'pasienKeluargaMenerimaInformasi' => '',
            //     'pasienKeluargaMenerimaInformasiOptions' => [['pasienKeluargaMenerimaInformasi' => 'Ya'], ['pasienKeluargaMenerimaInformasi' => 'Tidak']],
            //     'hambatanEdukasi' => '',
            //     'keteranganHambatanEdukasi' => '',
            //     'hambatanEdukasiOptions' => [['hambatanEdukasi' => 'Ya'], ['hambatanEdukasi' => 'Tidak']],
            //     'penerjemah' => '',
            //     'keteranganPenerjemah' => '',
            //     'penerjemahOptions' => [['penerjemah' => 'Ya'], ['penerjemah' => 'Tidak']],
            //     'diagPenyakit' => [],
            //     'obat' => [],
            //     'dietNutrisi' => [],
            //     'rehabMedik' => [],
            //     'managemenNyeri' => [],
            //     'penggunaanAlatMedis' => [],
            //     'hakKewajibanPasien' => [],
            //     'edukasiFollowUp' => '',
            //     'segeraKembaliRjjika' => '',
            //     'informedConsent' => '',
            //     'keteranganEdukasi' => '',
            // ],

            // 'screeningGiziTab' => 'Screening Gizi',
            // 'screeningGizi' => [
            //     'perubahanBB3Bln' => '',
            //     'perubahanBB3BlnScore' => '0',
            //     'perubahanBB3BlnOptions' => [['perubahanBB3Bln' => 'Ya (1)'], ['perubahanBB3Bln' => 'Tidak (0)']],
            //     'jmlPerubahabBB' => '',
            //     'jmlPerubahabBBScore' => '0',
            //     'jmlPerubahabBBOptions' => [['jmlPerubahabBB' => '0,5Kg-1Kg (1)'], ['jmlPerubahabBB' => '>5Kg-10Kg (2)'], ['jmlPerubahabBB' => '>10Kg-15Kg (3)'], ['jmlPerubahabBB' => '>15Kg-20Kg (4)']],
            //     'intakeMakanan' => '',
            //     'intakeMakananScore' => '0',
            //     'intakeMakananOptions' => [['intakeMakanan' => 'Ya (1)'], ['intakeMakanan' => 'Tidak (0)']],
            //     'keteranganScreeningGizi' => '',
            //     'scoreTotalScreeningGizi' => '0',
            //     'tglScreeningGizi' => '',
            // ],

            'batukTab' => 'Screening Batuk',
            'batuk' => [
                'riwayatDemam' => [],
                'keteranganRiwayatDemam' => '',
                'berkeringatMlmHari' => [],
                'keteranganBerkeringatMlmHari' => '',
                'bepergianDaerahWabah' => [],
                'keteranganBepergianDaerahWabah' => '',
                'riwayatPakaiObatJangkaPanjangan' => [],
                'keteranganRiwayatPakaiObatJangkaPanjangan' => '',
                'BBTurunTanpaSebab' => [],
                'keteranganBBTurunTanpaSebab' => '',
                'pembesaranGetahBening' => [],
                'keteranganPembesaranGetahBening' => '',
            ],
        ];
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-anamnesa-actions');
    }

    protected function rules(): array
    {
        $rules['dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang'] = 'date_format:d/m/Y H:i:s';
        return $rules;
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang' => 'Waktu Datang',
        ];
    }

    /* ===============================
     | SAVE ANAMNESA
     =============================== */
    #[On('save-rm-anamnesa-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // Whitelist field anamnesa yang boleh diupdate
                $allowedAnamnesaFields = ['anamnesa'];

                // Untuk update, ambil data existing dari database
                $existingData = $this->findDataRJ($this->rjNo);

                // Ambil hanya field anamnesa yang diizinkan dari form
                $formAnamnesa = array_intersect_key($this->dataDaftarPoliRJ ?? [], array_flip($allowedAnamnesaFields));

                // Merge anamnesa data: existing diupdate dengan form data
                $mergedData = array_replace_recursive($existingData ?? [], $formAnamnesa);

                // Update RJ with merged data
                $this->updateJsonRJ($this->rjNo, $mergedData);

                // Update pasien riwayat medis pasien data if needed (fixed typo in comment)
                $this->updateRiwayatMedisPasien();
            });

            $this->afterSave('Anamnesa berhasil disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    private function updateRiwayatMedisPasien(): void
    {
        $regNo = $this->dataDaftarPoliRJ['regNo'];

        // Ambil data pasien
        $pasienData = $this->findDataMasterPasien($regNo);

        $updated = false;

        // ✅ Update Alergi (text)
        if (!empty(($alergi = $this->dataDaftarPoliRJ['anamnesa']['alergi']['alergi'] ?? ''))) {
            $pasienData['pasien']['alergi'] = $alergi;
            $updated = true;
        }

        // ✅ Update Riwayat Penyakit Dahulu (text)
        if (!empty(($riwayat = $this->dataDaftarPoliRJ['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? ''))) {
            $pasienData['pasien']['riwayatPenyakitDahulu'] = $riwayat;
            $updated = true;
        }

        // ✅ Update jika ada perubahan
        if ($updated) {
            $pasienData['pasien']['regNo'] = $regNo;
            $this->updateJsonMasterPasien($regNo, $pasienData);
        }
    }
    /* ===============================
     | SET PERAWAT PENERIMA
     =============================== */
    public function setPerawatPenerima(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (auth()->user()->hasRole('Perawat')) {
            $this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] = auth()->user()->myuser_name;
            $this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] = auth()->user()->myuser_code;
            // 🔥 INCREMENT: Refresh untuk menampilkan perawat yang sudah di-set
            $this->incrementVersion('modal-anamnesa-rj');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Hanya user dengan role Perawat yang dapat melakukan TTD-E.');
        }
    }

    /* ===============================
     | SET JAM DATANG
     =============================== */
    public function setJamDatang($time): void
    {
        if (!$this->isFormLocked) {
            $this->dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] = $time;

            // 🔥 INCREMENT: Refresh untuk menampilkan perawat yang sudah di-set
            $this->incrementVersion('modal-anamnesa-rj');
        }
    }

    private function afterSave(string $message): void
    {
        // 🔥 INCREMENT: Refresh seluruh modal anamnesa
        $this->incrementVersion('modal-anamnesa-rj');

        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount()
    {
        $this->registerAreas(['modal-anamnesa-rj']);
    }
};

?>
<div>
    {{-- CONTAINER UTAMA - SATU-SATUNYA WIRE:KEY --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-anamnesa-rj', [$rjNo ?? 'new']) }}">
        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- jika anamnesa ada --}}
                @if (isset($dataDaftarPoliRJ['anamnesa']))
                    <div class="w-full">
                        <div id="TransaksiRawatJalan" x-data="{ activeTab: '{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian Perawatan' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-wrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    {{-- PENGKAJIAN PERAWATAN TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian Perawatan' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian Perawatan' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian Perawatan' }}
                                        </label>
                                    </li>

                                    {{-- STATUS PSIKOLOGIS TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}
                                        </label>
                                    </li>

                                    {{-- BATUK TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}'">
                                            {{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}
                                        </label>
                                    </li>
                                </ul>
                            </div>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">
                                {{-- PENGKAJIAN PERAWATAN TAB --}}
                                @if (isset($dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatanTab'] ?? 'Pengkajian Perawatan' }}'">
                                        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.pengkajian-perawatan-tab')
                                    </div>
                                @endif

                                {{-- STATUS PSIKOLOGIS TAB --}}
                                @if (isset($dataDaftarPoliRJ['anamnesa']['statusPsikologisTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['statusPsikologisTab'] ?? 'Status Psikologis' }}'">
                                        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.status-psikologis-tab')
                                    </div>
                                @endif

                                {{-- BATUK TAB --}}
                                @if (isset($dataDaftarPoliRJ['anamnesa']['batukTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['anamnesa']['batukTab'] ?? 'Screening Batuk' }}'">
                                        @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.batuk-tab')
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
