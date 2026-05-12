<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/general-consent/rm-general-consent-rj-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-general-consent-rj'];

    // ── Form fields — top-level untuk wire:model ──
    public string $wali = '';
    public string $waliHubungan = ''; // Hubungan wali dengan pasien — HPK 4.2
    public string $agreement = '1'; // 1=Setuju, 0=Tidak Setuju
    public string $pesertaDidikSetuju = '1'; // Persetujuan keterlibatan peserta didik — HPK 4 EP-c
    public string $signature = ''; // base64 dari canvas/signpad

    public array $agreementOptions = [['value' => '1', 'label' => 'Setuju'], ['value' => '0', 'label' => 'Tidak Setuju']];

    public array $waliHubunganOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-general-consent-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultGeneralConsent();
        $current = $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [];
        $this->dataDaftarPoliRJ['generalConsentPasienRJ'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ??= $this->getDefaultGeneralConsent();

        $consent = $this->dataDaftarPoliRJ['generalConsentPasienRJ'];
        $this->wali = $consent['wali'] ?? '';
        $this->waliHubungan = $consent['waliHubungan'] ?? '';
        $this->agreement = $consent['agreement'] ?? '1';
        $this->pesertaDidikSetuju = $consent['pesertaDidikSetuju'] ?? '1';
        $this->signature = $consent['signature'] ?? '';

        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-general-consent-rj');

        $this->dispatch('open-modal', name: "rm-general-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-general-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'signature' => 'required|string',
            'wali' => 'required|string|max:200',
            'waliHubungan' => 'required|string|max:50',
            'agreement' => 'required|in:1',
            'pesertaDidikSetuju' => 'required|in:0,1',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'agreement.in' => 'Persetujuan Pelayanan harus "Setuju" agar General Consent dapat diproses.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'signature' => 'Tanda tangan pasien/wali',
            'wali' => 'Nama wali',
            'waliHubungan' => 'Hubungan wali',
            'agreement' => 'Persetujuan',
            'pesertaDidikSetuju' => 'Persetujuan keterlibatan peserta didik',
        ];
    }

    /* ===============================
     | UPDATED HOOKS — sync top-level → nested
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        $map = [
            'wali' => 'wali',
            'waliHubungan' => 'waliHubungan',
            'agreement' => 'agreement',
            'pesertaDidikSetuju' => 'pesertaDidikSetuju',
        ];
        if (isset($map[$name])) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ'][$map[$name]] = $value;
        }

        if ($name === 'agreement') {
            $this->validateOnly('agreement');
        }
    }

    /* ===============================
     | SET SIGNATURE
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->signature = $dataUrl;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signature'] = $dataUrl;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signatureDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | CLEAR SIGNATURE
     =============================== */
    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->signature = '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signature'] = '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signatureDate'] = '';
        $this->incrementVersion('modal-general-consent-rj');
    }

    /* ===============================
     | SET PETUGAS PEMERIKSA
     =============================== */
    public function setPetugasPemeriksa(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan petugas pemeriksa sudah ada.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $data['generalConsentPasienRJ'] ??= $this->getDefaultGeneralConsent();
                $data['generalConsentPasienRJ']['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
                $data['generalConsentPasienRJ']['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
                $data['generalConsentPasienRJ']['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'Tanda tangan petugas pemeriksa berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-general-consent-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan pasien/wali belum diisi.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                $data['generalConsentPasienRJ'] = array_replace($data['generalConsentPasienRJ'] ?? $this->getDefaultGeneralConsent(), $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? []);

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'General Consent berhasil disimpan.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-general-consent-rj.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultGeneralConsent(): array
    {
        return [
            'signature' => '',
            'signatureDate' => '',
            'wali' => '',
            'waliHubungan' => '',
            'agreement' => '1',
            'pesertaDidikSetuju' => '1',
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->signature = '';
        $this->wali = '';
        $this->waliHubungan = '';
        $this->agreement = '1';
        $this->pesertaDidikSetuju = '1';
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php
        $gc = $dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [];
        $gcSigned = !empty($gc['signature']);
    @endphp

    <div
        class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        General Consent
                    </h3>
                    @if ($gcSigned)
                        <x-badge variant="success">Sudah ditandatangani</x-badge>
                    @else
                        <x-badge variant="warning">Belum ditandatangani</x-badge>
                    @endif
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Persetujuan umum pasien terhadap pelayanan rawat jalan, hak & kewajiban, serta perlindungan data.
                </p>

                @if ($gcSigned)
                    <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3 text-gray-600 dark:text-gray-300">
                        <div>
                            <dt class="text-xs uppercase text-gray-400">Wali</dt>
                            <dd class="font-medium">{{ $gc['wali'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-400">Persetujuan</dt>
                            <dd class="font-medium">
                                {{ ($gc['agreement'] ?? '1') === '1' ? 'Setuju' : 'Tidak Setuju' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-400">Tanggal TTD</dt>
                            <dd class="font-medium">{{ $gc['signatureDate'] ?? '-' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka General Consent
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-general-consent-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-general-consent-rj', [$rjNo ?? 'new']) }}">

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
                                <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    General Consent
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Persetujuan umum pasien rawat jalan — tampilan ini dapat diputar ke arah pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="success">Rawat Jalan</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="gc-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        @if (isset($dataDaftarPoliRJ['generalConsentPasienRJ']))

                            @php $consent = $dataDaftarPoliRJ['generalConsentPasienRJ']; @endphp

                            {{-- ══ ISI PERSETUJUAN ══ --}}
                            <section>
                                <x-consent.general-consent-body context="rj" />
                            </section>

                            {{-- ══ DATA PERSETUJUAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                    Data Persetujuan
                                </h3>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                                        <x-text-input wire:model.live="wali"
                                            placeholder="Nama lengkap pasien atau wali..." :error="$errors->has('wali')"
                                            :disabled="$isFormLocked" class="w-full" />
                                        <x-input-error :messages="$errors->get('wali')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="waliHubungan"
                                            :error="$errors->has('waliHubungan')" :disabled="$isFormLocked" class="w-full">
                                            <option value="">— Pilih hubungan —</option>
                                            @foreach ($waliHubunganOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('waliHubungan')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Persetujuan Pelayanan *" class="mb-1" />
                                        <x-select-input wire:model.live="agreement" :error="$errors->has('agreement')"
                                            :disabled="$isFormLocked" class="w-full">
                                            @foreach ($agreementOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('agreement')" class="mt-1" />
                                    </div>

                                    <div>
                                        <x-input-label value="Persetujuan Keterlibatan Peserta Didik *" class="mb-1" />
                                        <x-select-input wire:model.live="pesertaDidikSetuju"
                                            :error="$errors->has('pesertaDidikSetuju')" :disabled="$isFormLocked"
                                            class="w-full">
                                            @foreach ($agreementOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('pesertaDidikSetuju')" class="mt-1" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Mahasiswa kedokteran/koas, perawat magang, residen, fellow di bawah
                                            supervisi.
                                        </p>
                                    </div>
                                </div>

                                @if (($agreement ?? '1') === '1')
                                    <div
                                        class="flex items-start gap-3 px-4 py-3 text-sm border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
                                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold">Pasien MENYETUJUI General Consent</p>
                                            <p class="mt-0.5">
                                                Persetujuan umum atas pelayanan rawat jalan, hak &amp; kewajiban, serta
                                                perlindungan data. Tindakan medis spesifik tetap memerlukan
                                                <strong>Inform Consent</strong> tersendiri.
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </section>

                            {{-- ══ TANDA TANGAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                    Tanda Tangan
                                </h3>

                                <x-input-error :messages="$errors->get('signature')" />

                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Pasien / Wali --}}
                                    <div class="flex flex-col">
                                        <div
                                            class="mb-2 text-xs font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                            Pasien / Wali
                                        </div>
                                        @if (!empty($consent['signature']))
                                            <x-signature.signature-result :signature="$consent['signature']"
                                                :date="$consent['signatureDate'] ?? ''" :disabled="$isFormLocked"
                                                wireMethod="clearSignature" />
                                        @elseif (!$isFormLocked)
                                            <x-signature.signature-pad wireMethod="setSignature" />
                                        @else
                                            <p class="py-8 text-sm italic text-center text-gray-400">Belum
                                                ditandatangani.</p>
                                        @endif
                                    </div>

                                    {{-- Petugas Pemeriksa --}}
                                    <div class="flex flex-col">
                                        <div
                                            class="mb-2 text-xs font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                            Petugas Pemeriksa
                                        </div>
                                        @if (empty($consent['petugasPemeriksa']))
                                            @if (!$isFormLocked)
                                                <div
                                                    class="flex items-center justify-center flex-1 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                    <x-primary-button wire:click.prevent="setPetugasPemeriksa"
                                                        wire:loading.attr="disabled"
                                                        wire:target="setPetugasPemeriksa" class="gap-2">
                                                        <span wire:loading.remove wire:target="setPetugasPemeriksa"
                                                            class="flex items-center gap-1.5">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                            </svg>
                                                            TTD sebagai Petugas
                                                        </span>
                                                        <span wire:loading wire:target="setPetugasPemeriksa">
                                                            <x-loading class="w-4 h-4" /> Menyimpan...
                                                        </span>
                                                    </x-primary-button>
                                                </div>
                                            @else
                                                <p class="py-8 text-sm italic text-center text-gray-400">Belum
                                                    ditandatangani.</p>
                                            @endif
                                        @else
                                            <div
                                                class="flex flex-col items-center justify-center flex-1 p-4 border border-gray-200 bg-gray-50 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                                <div class="font-semibold text-gray-800 dark:text-gray-200">
                                                    {{ $consent['petugasPemeriksa'] }}
                                                </div>
                                                @if (!empty($consent['petugasPemeriksaCode']))
                                                    <div class="text-xs text-gray-500 mt-0.5">
                                                        Kode: {{ $consent['petugasPemeriksaCode'] }}
                                                    </div>
                                                @endif
                                                <div class="mt-1 text-xs text-gray-500">
                                                    {{ $consent['petugasPemeriksaDate'] ?? '-' }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </section>

                        @else
                            <div
                                class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm font-medium">Data RJ belum dimuat</p>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>

                    @if ($rjNo)
                        <x-secondary-button wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                            class="gap-2">
                            <span wire:loading.remove wire:target="cetak">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                                </svg>
                                Cetak
                            </span>
                            <span wire:loading wire:target="cetak"><x-loading class="w-4 h-4" /></span>
                        </x-secondary-button>

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                                wire:target="save" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="save">Simpan General Consent</span>
                                <span wire:loading wire:target="save"><x-loading class="w-4 h-4" />
                                    Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    @endif
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.r-j.general-consent.cetak-general-consent-rj
        wire:key="cetak-general-consent-rj-{{ $rjNo ?? 'init' }}" />
</div>
