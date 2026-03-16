<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-anamnesa-ugd'];

    /* ================================================
     | DEFAULT STRUCTURES
     ================================================ */
    private function getDefaultScreening(): array
    {
        return [
            'keluhanUtama' => '',
            'pernafasan' => '',
            'pernafasanOptions' => [['pernafasan' => 'Nafas Normal'], ['pernafasan' => 'Tampak Sesak']],
            'kesadaran' => '',
            'kesadaranOptions' => [['kesadaran' => 'Sadar Penuh'], ['kesadaran' => 'Tampak Mengantuk'], ['kesadaran' => 'Gelisah'], ['kesadaran' => 'Bicara Tidak Jelas']],
            'nyeriDada' => '',
            'nyeriDadaOptions' => [['nyeriDada' => 'Tidak Ada'], ['nyeriDada' => 'Ada']],
            'nyeriDadaTingkat' => '',
            'nyeriDadaTingkatOptions' => [['nyeriDadaTingkat' => 'Ringan'], ['nyeriDadaTingkat' => 'Sedang'], ['nyeriDadaTingkat' => 'Berat']],
            'prioritasPelayanan' => '',
            'prioritasPelayananOptions' => [['prioritasPelayanan' => 'Preventif'], ['prioritasPelayanan' => 'Paliatif'], ['prioritasPelayanan' => 'Kuratif'], ['prioritasPelayanan' => 'Rehabilitatif']],
            'tanggalPelayanan' => '',
            'petugasPelayanan' => '',
        ];
    }

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
            'anamnesaDiperoleh' => [
                'autoanamnesa' => [],
                'allonanamnesa' => [],
                'anamnesaDiperolehDari' => '',
            ],

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

    /* ================================================
     | OPEN
     ================================================ */
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

        // Init screening jika belum ada
        if (!isset($this->dataDaftarUGD['screening']) || !is_array($this->dataDaftarUGD['screening'])) {
            $this->dataDaftarUGD['screening'] = $this->getDefaultScreening();
        }

        // Init anamnesa jika belum ada
        if (!isset($this->dataDaftarUGD['anamnesa']) || !is_array($this->dataDaftarUGD['anamnesa'])) {
            $this->dataDaftarUGD['anamnesa'] = $this->getDefaultAnamnesa();
        }

        // Sync keluhan utama dari screening → anamnesa jika kosong
        if (empty($this->dataDaftarUGD['anamnesa']['keluhanUtama']['keluhanUtama']) && !empty($this->dataDaftarUGD['screening']['keluhanUtama'])) {
            $this->dataDaftarUGD['anamnesa']['keluhanUtama']['keluhanUtama'] = $this->dataDaftarUGD['screening']['keluhanUtama'];
        }

        // Sync data alergi + riwayat penyakit dari master pasien
        $pasienData = $this->findDataMasterPasien($data['regNo']);
        if (!empty($pasienData['pasien']['alergi'])) {
            $this->dataDaftarUGD['anamnesa']['alergi']['alergi'] = $pasienData['pasien']['alergi'];
        }
        if (!empty($pasienData['pasien']['riwayatPenyakitDahulu'])) {
            $this->dataDaftarUGD['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] = $pasienData['pasien']['riwayatPenyakitDahulu'];
        }

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);

        $this->incrementVersion('modal-anamnesa-ugd');
        $this->dispatch('open-modal', name: 'rm-anamnesa-ugd-actions');
    }

    /* ================================================
     | CLOSE
     ================================================ */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-anamnesa-ugd-actions');
    }

    /* ================================================
     | VALIDATION RULES
     ================================================ */
    protected function rules(): array
    {
        return [
            'dataDaftarUGD.screening.keluhanUtama' => 'required',
            'dataDaftarUGD.screening.pernafasan' => 'required',
            'dataDaftarUGD.screening.kesadaran' => 'required',
            'dataDaftarUGD.screening.nyeriDada' => 'required',
            'dataDaftarUGD.screening.prioritasPelayanan' => 'required',
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
            'dataDaftarUGD.screening.keluhanUtama' => 'Keluhan Utama (Screening)',
            'dataDaftarUGD.screening.pernafasan' => 'Pernafasan',
            'dataDaftarUGD.screening.kesadaran' => 'Kesadaran',
            'dataDaftarUGD.screening.nyeriDada' => 'Nyeri Dada',
            'dataDaftarUGD.screening.prioritasPelayanan' => 'Prioritas Pelayanan',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.jamDatang' => 'Jam Datang',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd' => 'Cara Masuk IGD',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan' => 'Tingkat Kegawatan',
            'dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama' => 'Keluhan Utama',
        ];
    }

    /* ================================================
     | SAVE — screening + anamnesa dalam satu transaksi
     ================================================ */
    #[On('save-rm-anamnesa-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validate();

        $rjNo = $this->rjNo;
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD kosong.');
            return;
        }

        $lockKey = "ugd:anamnesa:{$rjNo}";

        try {
            Cache::lock($lockKey, 10)->block(5, function () use ($rjNo) {
                DB::transaction(function () use ($rjNo) {
                    $fresh = $this->findDataUGD($rjNo);
                    if (empty($fresh)) {
                        $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan, simpan dibatalkan.');
                        return;
                    }

                    // Merge screening
                    $fresh['screening'] = array_merge($fresh['screening'] ?? $this->getDefaultScreening(), $this->dataDaftarUGD['screening'] ?? []);

                    // Merge anamnesa
                    $fresh['anamnesa'] = array_merge($fresh['anamnesa'] ?? $this->getDefaultAnamnesa(), $this->dataDaftarUGD['anamnesa'] ?? []);

                    // Update header fields di tabel rstxn_ugdhdrs
                    [$pStatus, $waktuDatang, $waktuDilayani] = $this->deriveHeaderFields($fresh);

                    DB::table('rstxn_ugdhdrs')
                        ->where('rj_no', $rjNo)
                        ->update([
                            'p_status' => $pStatus,
                            'waktu_pasien_datang' => DB::raw("to_date('{$waktuDatang}','dd/mm/yyyy hh24:mi:ss')"),
                            'waktu_pasien_dilayani' => DB::raw("to_date('{$waktuDilayani}','dd/mm/yyyy hh24:mi:ss')"),
                        ]);

                    $this->updateJsonUGD($rjNo, $fresh);
                    $this->dataDaftarUGD = $fresh;

                    // Sync alergi + riwayat ke master pasien
                    $this->updateRiwayatMedisPasien();
                });
            });

            $this->afterSave('Screening & Anamnesa berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, silakan coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ---- Derive header fields dari state ---- */
    private function deriveHeaderFields(array $state): array
    {
        $now = Carbon::now()->format('d/m/Y H:i:s');

        $pStatus = $state['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? 'P0';
        $pStatus = $pStatus ?: 'P0';

        $waktuDatang = $state['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '';
        $waktuDatang = $waktuDatang ?: $now;

        $waktuDilayani = $state['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '';
        $waktuDilayani = $waktuDilayani ?: $now;

        return [$pStatus, $waktuDatang, $waktuDilayani];
    }

    /* ---- Sync alergi & riwayat ke master pasien ---- */
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

    /* ================================================
     | SCREENING ACTIONS
     ================================================ */
    public function setPetugasPelayanan(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (
            !auth()
                ->user()
                ->hasAnyRole(['Perawat', 'Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menandatangani screening.');
            return;
        }

        $this->dataDaftarUGD['screening']['petugasPelayanan'] = auth()->user()->myuser_name;
        $this->dataDaftarUGD['screening']['tanggalPelayanan'] = now()->format('d/m/Y H:i:s');
    }

    public function autoSetTanggalPelayanan(): void
    {
        $this->dataDaftarUGD['screening']['tanggalPelayanan'] = now()->format('d/m/Y H:i:s');
    }

    /* ================================================
     | ANAMNESA ACTIONS
     ================================================ */
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

        // Auto-isi jam datang jika belum ada
        if (empty($this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'])) {
            $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
        }

        $this->incrementVersion('modal-anamnesa-ugd');
    }

    public function setAutoJamDatang(): void
    {
        $this->dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
    }

    /* ---- Rekonsiliasi Obat ---- */
    public string $rekonNamaObat = '';
    public string $rekonDosis = '';
    public string $rekonRute = '';

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

    /* ---- Screening Gizi ---- */
    public function calculateScreeningGizi(): void
    {
        $sg = $this->dataDaftarUGD['anamnesa']['screeningGizi'] ?? [];
        $total = (int) ($sg['perubahanBB3BlnScore'] ?? 0) + (int) ($sg['jmlPerubahanBBScore'] ?? 0) + (int) ($sg['intakeMakananScore'] ?? 0);

        $this->dataDaftarUGD['anamnesa']['screeningGizi']['scoreTotalScreeningGizi'] = (string) $total;
        $this->dataDaftarUGD['anamnesa']['screeningGizi']['tglScreeningGizi'] = now()->format('d/m/Y H:i:s');
    }

    /* ================================================
     | AFTER SAVE
     ================================================ */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-anamnesa-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /* ================================================
     | RESET
     ================================================ */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->rekonNamaObat = '';
        $this->rekonDosis = '';
        $this->rekonRute = '';
    }

    public function mount(): void
    {
        $this->registerAreas(['modal-anamnesa-ugd']);
    }
};
?>

<div>
    <x-modal name="rm-anamnesa-ugd-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-anamnesa-ugd', [$rjNo ?? 'new']) }}">

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
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Screening & Anamnesa UGD
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Rekam medis pengkajian awal pasien Unit Gawat Darurat
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-2 mt-3">
                            <x-badge variant="danger">UGD / IGD</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only — EMR Terkunci</x-badge>
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

                @if (!empty($dataDaftarUGD))
                    {{-- Display Pasien --}}
                    <div class="mb-4">
                        <livewire:pages::transaksi.ugd.emr-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="display-pasien-ugd-{{ $rjNo }}" />
                    </div>

                    <div x-data="{ activeTab: 'screening' }" class="w-full">

                        {{-- TAB NAVIGATION --}}
                        <div
                            class="sticky z-20 px-2 mb-0 bg-white border-b border-gray-200 top-0 dark:bg-gray-900 dark:border-gray-700">
                            <ul
                                class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'screening'
                                            ?
                                            'text-red-600 border-red-500 bg-red-50 dark:bg-red-900/20' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'screening'">
                                        🚨 Screening
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'pengkajian'
                                            ?
                                            'text-brand border-brand bg-brand/5' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'pengkajian'">
                                        Pengkajian
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'keluhan'
                                            ?
                                            'text-brand border-brand bg-brand/5' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'keluhan'">
                                        Keluhan & Riwayat
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'rekonsiliasi'
                                            ?
                                            'text-brand border-brand bg-brand/5' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'rekonsiliasi'">
                                        Rekonsiliasi Obat
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'psikologis'
                                            ?
                                            'text-brand border-brand bg-brand/5' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'psikologis'">
                                        Psikologis & Mental
                                    </button>
                                </li>

                                <li class="mr-1">
                                    <button type="button"
                                        class="inline-block px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                        :class="activeTab === 'batuk'
                                            ?
                                            'text-brand border-brand bg-brand/5' :
                                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                        @click="activeTab = 'batuk'">
                                        Screening Batuk
                                    </button>
                                </li>

                            </ul>
                        </div>

                        {{-- TAB CONTENTS --}}
                        <div
                            class="p-4 bg-white border border-t-0 border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">

                            {{-- ============================
                             | TAB: SCREENING
                             ============================ --}}
                            <div x-show="activeTab === 'screening'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.screening-tab')
                            </div>

                            {{-- ============================
                             | TAB: PENGKAJIAN PERAWATAN
                             ============================ --}}
                            <div x-show="activeTab === 'pengkajian'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-ugd-tab')
                            </div>

                            {{-- ============================
                             | TAB: KELUHAN & RIWAYAT
                             ============================ --}}
                            <div x-show="activeTab === 'keluhan'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.keluhan-riwayat-ugd-tab')
                            </div>

                            {{-- ============================
                             | TAB: REKONSILIASI OBAT
                             ============================ --}}
                            <div x-show="activeTab === 'rekonsiliasi'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.rekonsiliasi-obat-ugd-tab')
                            </div>

                            {{-- ============================
                             | TAB: STATUS PSIKOLOGIS & MENTAL
                             ============================ --}}
                            <div x-show="activeTab === 'psikologis'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.status-psikologis-ugd-tab')
                            </div>

                            {{-- ============================
                             | TAB: SCREENING BATUK
                             ============================ --}}
                            <div x-show="activeTab === 'batuk'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.batuk-ugd-tab')
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

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">

                    {{-- Tombol TTD-E Perawat --}}
                    @hasanyrole('Perawat|Dokter|Admin')
                        @if (!$isFormLocked)
                            <x-secondary-button type="button" wire:click="setPerawatPenerima" class="gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                TTD-E Perawat
                            </x-secondary-button>
                        @endif
                    @endhasanyrole

                    <div class="flex gap-3 ml-auto">
                        <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save()" class="min-w-[140px]"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan Screening & Anamnesa
                                </span>
                                <span wire:loading>
                                    <x-loading /> Menyimpan...
                                </span>
                            </x-primary-button>
                        @endif
                    </div>

                </div>
            </div>

        </div>
    </x-modal>
</div>
