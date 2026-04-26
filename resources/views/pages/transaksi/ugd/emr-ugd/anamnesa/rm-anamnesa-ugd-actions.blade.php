<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public string $tingkatKegawatan = '';
    public string $caraMasukIgd = '';
    public string $saranaTransportasiId = '4';

    /* ---- Rekonsiliasi Obat ---- */
    public string $rekonNamaObat = '';
    public string $rekonDosis = '';
    public string $rekonRute = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-anamnesa-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-anamnesa-ugd']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultAnamnesa();
        $current = $this->dataDaftarUGD['anamnesa'] ?? [];
        $this->dataDaftarUGD['anamnesa'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-anamnesa-ugd')]
    public function openAnamnesa(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;

        // Inisialisasi key anamnesa jika belum ada
        if (!isset($this->dataDaftarUGD['anamnesa']) || !is_array($this->dataDaftarUGD['anamnesa'])) {
            $this->dataDaftarUGD['anamnesa'] = $this->getDefaultAnamnesa();
        }

        // Sync property lokal
        $this->tingkatKegawatan = $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? '';
        $this->caraMasukIgd = $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['caraMasukIgd'] ?? '';
        $this->saranaTransportasiId = $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiId'] ?? '4';

        // Sync keluhan utama dari screening → anamnesa jika kosong
        if (empty($this->dataDaftarUGD['anamnesa']['keluhanUtama']['keluhanUtama']) && !empty($this->dataDaftarUGD['screening']['keluhanUtama'])) {
            $this->dataDaftarUGD['anamnesa']['keluhanUtama']['keluhanUtama'] = $this->dataDaftarUGD['screening']['keluhanUtama'];
        }

        // Sync alergi + riwayat dari master pasien
        $pasienData = $this->findDataMasterPasien($data['regNo']);
        if (!empty($pasienData['pasien']['alergi'])) {
            $this->dataDaftarUGD['anamnesa']['alergi']['alergi'] = $pasienData['pasien']['alergi'];
        }
        if (!empty($pasienData['pasien']['riwayatPenyakitDahulu'])) {
            $this->dataDaftarUGD['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] = $pasienData['pasien']['riwayatPenyakitDahulu'];
        }

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-anamnesa-ugd');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.jamDatang' => 'nullable|date_format:d/m/Y H:i:s',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd' => 'required',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan' => 'required',
            'dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus dalam format dd/mm/yyyy HH:ii:ss.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.jamDatang' => 'Jam Datang',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd' => 'Cara Masuk IGD',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan' => 'Tingkat Kegawatan',
            'dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama' => 'Keluhan Utama',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-anamnesa-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu — cegah race condition update JSON bersamaan
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Patch key anamnesa
                $data['anamnesa'] = $this->dataDaftarUGD['anamnesa'] ?? [];

                // 4. Update waktu_pasien_datang + waktu_pasien_dilayani
                $now = Carbon::now()->format('d/m/Y H:i:s');
                $waktuDatang = $data['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? $now;
                $waktuDilayani = $data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? $now;

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_pasien_datang' => DB::raw("to_date('{$waktuDatang}','dd/mm/yyyy hh24:mi:ss')"),
                        'waktu_pasien_dilayani' => DB::raw("to_date('{$waktuDilayani}','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                // 5. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;

                // 6. Update riwayat medis master pasien (masih dalam transaksi yang sama)
                $this->updateRiwayatMedisPasien();
            });

            // 7. Notify + increment version — di luar transaksi
            $this->incrementVersion('modal-anamnesa-ugd');
            $this->dispatch('toast', type: 'success', message: 'Anamnesa berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE RIWAYAT MEDIS PASIEN
     | Dipanggil dari dalam transaksi + lock sudah ada di caller.
     =============================== */
    private function updateRiwayatMedisPasien(): void
    {
        $regNo = $this->dataDaftarUGD['regNo'] ?? null;
        if (!$regNo) {
            return;
        }

        $pasienData = $this->findDataMasterPasien($regNo);
        $updated = false;

        $alergi = $this->dataDaftarUGD['anamnesa']['alergi']['alergi'] ?? '';
        $riwayat = $this->dataDaftarUGD['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '';

        if (!empty($alergi)) {
            $pasienData['pasien']['alergi'] = $alergi;
            $updated = true;
        }
        if (!empty($riwayat)) {
            $pasienData['pasien']['riwayatPenyakitDahulu'] = $riwayat;
            $updated = true;
        }

        if ($updated) {
            $pasienData['pasien']['regNo'] = $regNo;
            $this->updateJsonMasterPasien($regNo, $pasienData);
        }
    }

    /* ===============================
     | ACTIONS
     =============================== */
    public function setPerawatPenerima(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (
            !auth()
                ->user()
                ->hasAnyRole(['Perawat', 'Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya role Perawat / Dokter / Admin yang dapat melakukan TTD-E.');
            return;
        }

        $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['perawatPenerima'] = auth()->user()->myuser_name;
        $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] = auth()->user()->myuser_code;

        if (empty($this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'])) {
            $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
        }

        $this->incrementVersion('modal-anamnesa-ugd');
    }

    public function setAutoJamDatang(): void
    {
        $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | REKONSILIASI OBAT
     =============================== */
    public function addRekonsiliasiObat(): void
    {
        if (empty($this->rekonNamaObat)) {
            $this->dispatch('toast', type: 'error', message: 'Nama obat tidak boleh kosong.');
            return;
        }

        $sudahAda = collect($this->dataDaftarUGD['anamnesa']['rekonsiliasiObat'] ?? [])
            ->where('namaObat', $this->rekonNamaObat)
            ->count();

        if ($sudahAda > 0) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada dalam daftar.');
            return;
        }

        $this->dataDaftarUGD['anamnesa']['rekonsiliasiObat'][] = [
            'namaObat' => $this->rekonNamaObat,
            'dosis' => $this->rekonDosis,
            'rute' => $this->rekonRute,
        ];

        $this->reset(['rekonNamaObat', 'rekonDosis', 'rekonRute']);
        $this->save();
    }

    public function removeRekonsiliasiObat(int $index): void
    {
        if (isset($this->dataDaftarUGD['anamnesa']['rekonsiliasiObat'][$index])) {
            unset($this->dataDaftarUGD['anamnesa']['rekonsiliasiObat'][$index]);
            $this->dataDaftarUGD['anamnesa']['rekonsiliasiObat'] = array_values($this->dataDaftarUGD['anamnesa']['rekonsiliasiObat']);
            $this->save();
        }
    }

    /* ===============================
     | SCREENING GIZI
     =============================== */
    public function calculateScreeningGizi(): void
    {
        $sg = $this->dataDaftarUGD['anamnesa']['screeningGizi'] ?? [];
        $total = (int) ($sg['perubahanBB3BlnScore'] ?? 0) + (int) ($sg['jmlPerubahanBBScore'] ?? 0) + (int) ($sg['intakeMakananScore'] ?? 0);

        $this->dataDaftarUGD['anamnesa']['screeningGizi']['scoreTotalScreeningGizi'] = (string) $total;
        $this->dataDaftarUGD['anamnesa']['screeningGizi']['tglScreeningGizi'] = now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        match ($name) {
            'tingkatKegawatan' => ($this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] = $value),
            'caraMasukIgd' => ($this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['caraMasukIgd'] = $value),
            'saranaTransportasiId' => ($this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiId'] = $value),
            default => null,
        };
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultAnamnesa(): array
    {
        return [
            'pengkajianPerawatanTab' => 'Pengkajian',
            'pengkajianPerawatan' => [
                'perawatPenerima' => '',
                'perawatPenerimaCode' => '',
                'jamDatang' => '',
                'caraMasukIgd' => '',
                'caraMasukIgdDesc' => '',
                'caraMasukIgdOption' => [['caraMasukIgd' => 'Sendiri'], ['caraMasukIgd' => 'Rujuk'], ['caraMasukIgd' => 'Kasus Polisi']],
                'tingkatKegawatan' => '',
                'tingkatKegawatanOption' => [['tingkatKegawatan' => 'P1'], ['tingkatKegawatan' => 'P2'], ['tingkatKegawatan' => 'P3'], ['tingkatKegawatan' => 'P0']],
                'saranaTransportasiId' => '4',
                'saranaTransportasiDesc' => 'Lain-lain',
                'saranaTransportasiKet' => '',
                'saranaTransportasiOptions' => [['saranaTransportasiId' => '1', 'saranaTransportasiDesc' => 'Ambulans'], ['saranaTransportasiId' => '2', 'saranaTransportasiDesc' => 'Mobil'], ['saranaTransportasiId' => '3', 'saranaTransportasiDesc' => 'Motor'], ['saranaTransportasiId' => '4', 'saranaTransportasiDesc' => 'Lain-lain']],
            ],
            'keluhanUtamaTab' => 'Keluhan Utama',
            'keluhanUtama' => ['keluhanUtama' => ''],

            'anamnesaDiperolehTab' => 'Anamnesa Diperoleh',
            'anamnesaDiperoleh' => ['autoanamnesa' => [], 'allonanamnesa' => [], 'anamnesaDiperolehDari' => ''],

            'riwayatPenyakitSekarangUmumTab' => 'Riwayat Penyakit Sekarang',
            'riwayatPenyakitSekarangUmum' => ['riwayatPenyakitSekarangUmum' => ''],

            'riwayatPenyakitDahuluTab' => 'Riwayat Penyakit Dahulu',
            'riwayatPenyakitDahulu' => ['riwayatPenyakitDahulu' => ''],

            'alergiTab' => 'Alergi',
            'alergi' => ['alergi' => ''],

            'rekonsiliasiObatTab' => 'Rekonsiliasi Obat',
            'rekonsiliasiObat' => [],

            'statusPsikologisTab' => 'Status Psikologis',
            'statusPsikologis' => [
                'tidakAdaKelainan' => [],
                'marah' => [],
                'cemas' => [],
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
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->rekonNamaObat = '';
        $this->rekonDosis = '';
        $this->rekonRute = '';
        $this->tingkatKegawatan = '';
        $this->caraMasukIgd = '';
        $this->saranaTransportasiId = '4';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-anamnesa-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarUGD['anamnesa']))
                    <div x-data="{ activeTab: 'pengkajian' }" class="w-full">

                        {{-- TAB NAVIGATION --}}
                        <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                            <ul
                                class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'pengkajian' ? 'text-primary border-primary bg-gray-100' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'pengkajian'">
                                        Pengkajian
                                    </button>
                                </li>

                                {{-- <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'keluhan' ? 'text-primary border-primary bg-gray-100' : 'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'keluhan'">
                                        Keluhan & Riwayat
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'rekonsiliasi' ? 'text-primary border-primary bg-gray-100' : 'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'rekonsiliasi'">
                                        Rekonsiliasi Obat
                                    </button>
                                </li> --}}

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'psikologis' ? 'text-primary border-primary bg-gray-100' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'psikologis'">
                                        Psikologis & Mental
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'batuk' ? 'text-primary border-primary bg-gray-100' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'batuk'">
                                        Screening Batuk
                                    </button>
                                </li>

                            </ul>
                        </div>

                        {{-- TAB CONTENTS --}}
                        <div class="w-full p-4">

                            <div x-show="activeTab === 'pengkajian'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab')
                            </div>

                            {{-- <div x-show="activeTab === 'keluhan'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.keluhan-riwayat-tab')
                            </div>

                            <div x-show="activeTab === 'rekonsiliasi'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.rekonsiliasi-obat-tab')
                            </div> --}}

                            <div x-show="activeTab === 'psikologis'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.status-psikologis-tab')
                            </div>

                            <div x-show="activeTab === 'batuk'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.batuk-tab')
                            </div>

                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-24 text-gray-300 dark:text-gray-600">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="text-sm font-medium">Data UGD belum dimuat</p>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
