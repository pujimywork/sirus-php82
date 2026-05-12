<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/inform-consent/rm-inform-consent-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-inform-consent-ugd'];

    public array $newConsent = [
        'tindakan' => '',
        'tujuan' => '',
        'resiko' => '',
        'alternatif' => '',
        'dokter' => '',
        'wali' => '',
        'waliHubungan' => '',
        'saksi' => '',
        'agreement' => '1',
        'dokterCode' => '',
        'dokterDate' => '',
        'petugasPemeriksa' => '',
        'petugasPemeriksaCode' => '',
        'petugasPemeriksaDate' => '',
    ];

    public string $signature = '';
    public string $signatureSaksi = '';

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

    public array $consentList = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-inform-consent-ugd']);

        if ($this->rjNo) {
            $data = $this->findDataUGD($this->rjNo);
            if ($data) {
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'] ?? [];
                $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetNewConsent();
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->resetValidation();

        $data = $this->findDataUGD($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;
        if (!isset($this->dataDaftarUGD['informConsentPasienUGD']) || !is_array($this->dataDaftarUGD['informConsentPasienUGD'])) {
            $this->dataDaftarUGD['informConsentPasienUGD'] = [];
        }
        $this->consentList = $this->dataDaftarUGD['informConsentPasienUGD'];
        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-inform-consent-ugd');

        $this->dispatch('open-modal', name: "rm-inform-consent-ugd-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-inform-consent-ugd-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newConsent.tindakan' => 'required|string|max:500',
            'newConsent.tujuan' => 'nullable|string',
            'newConsent.resiko' => 'nullable|string',
            'newConsent.alternatif' => 'nullable|string',
            'newConsent.dokter' => 'nullable|string',
            'newConsent.wali' => 'required|string|max:200',
            'newConsent.waliHubungan' => 'required|string|max:50',
            'newConsent.saksi' => 'nullable|string|max:200',
            'newConsent.agreement' => 'required|in:0,1',
            'signature' => 'required|string',
            'signatureSaksi' => 'nullable|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newConsent.tindakan' => 'Nama tindakan',
            'newConsent.tujuan' => 'Tujuan tindakan',
            'newConsent.resiko' => 'Risiko tindakan',
            'newConsent.alternatif' => 'Alternatif tindakan',
            'newConsent.dokter' => 'Dokter penjelas',
            'newConsent.wali' => 'Nama pasien/wali',
            'newConsent.waliHubungan' => 'Hubungan wali',
            'newConsent.saksi' => 'Nama saksi',
            'newConsent.agreement' => 'Persetujuan',
            'signature' => 'Tanda tangan pasien/wali',
            'signatureSaksi' => 'Tanda tangan saksi',
        ];
    }

    /* ===============================
     | SET / CLEAR SIGNATURES
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    /* ===============================
     | SET DOKTER PENJELAS
     =============================== */
    public function setDokterPenjelas(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->newConsent['dokter'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan dokter sudah ada.');
            return;
        }

        $this->newConsent['dokter'] = auth()->user()->myuser_name ?? '';
        $this->newConsent['dokterCode'] = auth()->user()->myuser_code ?? '';
        $this->newConsent['dokterDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan dokter berhasil ditambahkan.');
    }

    /* ===============================
     | LOV DOKTER TINDAKAN — listener
     =============================== */
    #[On('lov.selected.icUgdDokterTindakan')]
    public function onDokterTindakanSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->newConsent['petugasPemeriksa'] = $payload['dr_name'] ?? '';
        $this->newConsent['petugasPemeriksaCode'] = $payload['dr_id'] ?? '';
        $this->newConsent['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    #[On('lov.cleared.icUgdDokterTindakan')]
    public function onDokterTindakanCleared(string $target): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->newConsent['petugasPemeriksa'] = '';
        $this->newConsent['petugasPemeriksaCode'] = '';
        $this->newConsent['petugasPemeriksaDate'] = '';
    }

    /* ===============================
     | SAVE
     =============================== */
    public function addConsent(): void
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

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $consentEntry = [
            'tindakan' => $this->newConsent['tindakan'],
            'tujuan' => $this->newConsent['tujuan'],
            'resiko' => $this->newConsent['resiko'],
            'alternatif' => $this->newConsent['alternatif'],
            'dokter' => $this->newConsent['dokter'] ?? '',
            'dokterCode' => $this->newConsent['dokterCode'] ?? '',
            'dokterDate' => $this->newConsent['dokterDate'] ?? '',
            'signature' => $this->signature,
            'signatureDate' => $now,
            'wali' => $this->newConsent['wali'],
            'waliHubungan' => $this->newConsent['waliHubungan'] ?? '',
            'signatureSaksi' => $this->signatureSaksi,
            'signatureSaksiDate' => $this->signatureSaksi ? $now : '',
            'saksi' => $this->newConsent['saksi'],
            'agreement' => $this->newConsent['agreement'],
            'petugasPemeriksa' => $this->newConsent['petugasPemeriksa'] ?? '',
            'petugasPemeriksaCode' => $this->newConsent['petugasPemeriksaCode'] ?? '',
            'petugasPemeriksaDate' => $this->newConsent['petugasPemeriksaDate'] ?? '',
        ];

        try {
            DB::transaction(function () use ($consentEntry) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                if (!isset($data['informConsentPasienUGD']) || !is_array($data['informConsentPasienUGD'])) {
                    $data['informConsentPasienUGD'] = [];
                }

                $data['informConsentPasienUGD'][] = $consentEntry;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'];
            });

            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent berhasil disimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);

            $this->resetNewConsent();
            $this->signature = '';
            $this->signatureSaksi = '';
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $consent = collect($this->consentList)->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-inform-consent-ugd.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                if (!isset($data['informConsentPasienUGD'])) {
                    throw new \RuntimeException('Data consent tidak ditemukan.');
                }

                $data['informConsentPasienUGD'] = collect($data['informConsentPasienUGD'])->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)->values()->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'];
            });

            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    private function resetNewConsent(): void
    {
        $this->newConsent = [
            'tindakan' => '',
            'tujuan' => '',
            'resiko' => '',
            'alternatif' => '',
            'dokter' => '',
            'wali' => '',
            'waliHubungan' => '',
            'saksi' => '',
            'agreement' => '1',
            'dokterCode' => '',
            'dokterDate' => '',
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->consentList = [];
        $this->resetNewConsent();
        $this->signature = '';
        $this->signatureSaksi = '';
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $icCount = count($consentList ?? []); @endphp

    <div
        class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        Inform Consent
                    </h3>
                    @if ($icCount > 0)
                        <x-badge variant="success">{{ $icCount }} tindakan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Persetujuan tindakan medis per-tindakan: tujuan, risiko, alternatif, serta tanda tangan
                    pasien/wali, dokter penjelas, dan saksi.
                </p>

                @if ($icCount > 0)
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($consentList, 0, 3) as $ic)
                            <li>
                                <span
                                    class="font-medium">{{ \Illuminate\Support\Str::limit($ic['tindakan'] ?? '-', 60) }}</span>
                                @if (!empty($ic['signatureDate']))
                                    <span class="text-xs text-gray-400">— {{ $ic['signatureDate'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($icCount > 3)
                            <li class="text-xs italic text-gray-400">
                                +{{ $icCount - 3 }} lainnya…
                            </li>
                        @endif
                    </ul>
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
                        Buka Inform Consent
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-inform-consent-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-inform-consent-ugd', [$rjNo ?? 'new']) }}">

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
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Inform Consent</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Persetujuan tindakan medis UGD — tampilan ini dapat diputar ke arah pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="danger">UGD</x-badge>
                            @if ($icCount > 0)
                                <x-badge variant="info">{{ $icCount }} tersimpan</x-badge>
                            @endif
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
                    <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                        wire:key="ic-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 mb-4 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        {{-- ══ INFORMASI TINDAKAN ══ --}}
                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                Informasi Tindakan
                            </h3>

                            <div>
                                <x-input-label value="Dokter Tindakan *" class="mb-1" />
                                @if (!$isFormLocked)
                                    <livewire:lov.dokter.lov-dokter target="icUgdDokterTindakan" label=""
                                        :initialDrId="$newConsent['petugasPemeriksaCode'] ?? null"
                                        wire:key="lov-dokter-ic-ugd-tindakan-{{ $rjNo ?? 'init' }}-{{ $renderVersions['modal-inform-consent-ugd'] ?? 0 }}" />
                                    @if (!empty($newConsent['petugasPemeriksaDate']))
                                        <p class="mt-1 text-xs text-gray-500">
                                            Dipilih: {{ $newConsent['petugasPemeriksaDate'] }}
                                        </p>
                                    @endif
                                @elseif (!empty($newConsent['petugasPemeriksa']))
                                    <div
                                        class="p-3 border border-gray-200 bg-gray-50 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ $newConsent['petugasPemeriksa'] }}
                                        </div>
                                        @if (!empty($newConsent['petugasPemeriksaCode']))
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                ID: {{ $newConsent['petugasPemeriksaCode'] }}
                                            </div>
                                        @endif
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $newConsent['petugasPemeriksaDate'] ?? '-' }}
                                        </div>
                                    </div>
                                @else
                                    <p class="text-sm italic text-gray-400">Belum dipilih.</p>
                                @endif
                            </div>

                            <div>
                                <x-input-label value="Nama Tindakan / Prosedur *" class="mb-1" />
                                <x-text-input wire:model.live="newConsent.tindakan"
                                    placeholder="Contoh: Hecting, Resusitasi, Pemberian O2..." :disabled="$isFormLocked"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newConsent.tindakan')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Tujuan Tindakan" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.tujuan" rows="3"
                                        placeholder="Uraian singkat mengenai tujuan tindakan..."
                                        :disabled="$isFormLocked" />
                                </div>

                                <div>
                                    <x-input-label value="Risiko Tindakan" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.resiko" rows="3"
                                        placeholder="Kemungkinan risiko / efek samping..." :disabled="$isFormLocked" />
                                </div>

                                <div>
                                    <x-input-label value="Alternatif Tindakan" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.alternatif" rows="3"
                                        placeholder="Alternatif lain yang dapat dilakukan..."
                                        :disabled="$isFormLocked" />
                                </div>
                            </div>

                            <div class="md:max-w-xs">
                                <x-input-label value="Persetujuan *" class="mb-1" />
                                <x-select-input wire:model.live="newConsent.agreement" :disabled="$isFormLocked"
                                    class="w-full">
                                    @foreach ($agreementOptions as $opt)
                                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('newConsent.agreement')" class="mt-1" />
                            </div>

                            @if (($newConsent['agreement'] ?? '1') === '1')
                                <div
                                    class="flex items-start gap-3 px-4 py-3 text-sm border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">Pasien MENYETUJUI tindakan</p>
                                        <p class="mt-0.5">
                                            Setelah ditandatangani, dokumen dicetak sebagai
                                            <strong>Persetujuan Tindakan Medis (Inform Consent)</strong> dan tindakan
                                            dapat dilakukan.
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div
                                    class="flex items-start gap-3 px-4 py-3 text-sm border rounded-xl bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-200">
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">Pasien MENOLAK tindakan</p>
                                        <p class="mt-0.5">
                                            Dokumen akan tercatat sebagai
                                            <strong>Penolakan Tindakan Medis</strong>. Pasien/wali memahami risiko medis
                                            atas penolakan tersebut dan bersedia menandatangani sebagai bukti penolakan.
                                            Tindakan tidak akan dilakukan.
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

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {{-- Pasien / Wali --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-xs font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Pasien / Wali
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$isFormLocked" wireMethod="clearSignature" />
                                    @elseif (!$isFormLocked)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-sm italic text-center text-gray-400">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                                        <x-text-input wire:model.live="newConsent.wali"
                                            placeholder="Nama lengkap pasien atau wali..." :disabled="$isFormLocked"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newConsent.wali')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newConsent.waliHubungan"
                                            :disabled="$isFormLocked" class="w-full">
                                            <option value="">— Pilih hubungan —</option>
                                            @foreach ($waliHubunganOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newConsent.waliHubungan')"
                                            class="mt-1" />
                                    </div>
                                </div>

                                {{-- Saksi --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-xs font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Saksi
                                    </div>
                                    <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />
                                    @if (!empty($signatureSaksi))
                                        <x-signature.signature-result :signature="$signatureSaksi" :date="''"
                                            :disabled="$isFormLocked" wireMethod="clearSignatureSaksi" />
                                    @elseif (!$isFormLocked)
                                        <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                                    @else
                                        <p class="py-8 text-sm italic text-center text-gray-400">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Saksi" class="mb-1" />
                                        <x-text-input wire:model.live="newConsent.saksi" placeholder="Nama saksi..."
                                            :disabled="$isFormLocked" class="w-full" />
                                        <x-input-error :messages="$errors->get('newConsent.saksi')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Dokter Penjelas --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-xs font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Dokter / Petugas Penjelas
                                    </div>
                                    @if (empty($newConsent['dokter']))
                                        @if (!$isFormLocked)
                                            <div
                                                class="flex items-center justify-center flex-1 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setDokterPenjelas"
                                                    wire:loading.attr="disabled" wire:target="setDokterPenjelas"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setDokterPenjelas"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Penjelas
                                                    </span>
                                                    <span wire:loading wire:target="setDokterPenjelas">
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
                                            <div class="font-semibold text-center text-gray-800 dark:text-gray-200">
                                                {{ $newConsent['dokter'] }}
                                            </div>
                                            @if (!empty($newConsent['dokterCode']))
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    Kode: {{ $newConsent['dokterCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $newConsent['dokterDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- DAFTAR CONSENT TERSIMPAN --}}
                        @if (count($consentList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800 mb-3">
                                    Daftar Inform Consent Tersimpan
                                </h3>
                                <table class="min-w-full text-sm border border-gray-200 rounded-lg dark:border-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr class="text-left text-gray-600 dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tindakan</th>
                                            <th class="px-4 py-2 border-b">Tanggal TTD Pasien</th>
                                            <th class="px-4 py-2 border-b">Dokter Penjelas</th>
                                            <th class="px-4 py-2 border-b text-center">Persetujuan</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($consentList as $consent)
                                            <tr
                                                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">
                                                    {{ Str::limit($consent['tindakan'], 50) }}
                                                </td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $consent['signatureDate'] ?? '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $consent['dokter'] ?? '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    @if (($consent['agreement'] ?? '1') === '1')
                                                        <x-badge variant="success">Menyetujui</x-badge>
                                                    @else
                                                        <x-badge variant="danger">Menolak</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-center space-x-2">
                                                    <x-secondary-button wire:click="cetak('{{ $consent['signatureDate'] }}')"
                                                        class="text-xs py-1 px-2">
                                                        <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                                                        </svg>
                                                        Cetak
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-confirm-button variant="danger"
                                                            :action="'hapus(\'' . $consent['signatureDate'] . '\')'"
                                                            title="Hapus Inform Consent"
                                                            message="Yakin hapus Inform Consent ini? Dokumen yang sudah ditandatangani akan dihapus."
                                                            confirmText="Ya, hapus" cancelText="Batal"
                                                            class="text-xs py-1 px-2">
                                                            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                            Hapus
                                                        </x-confirm-button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    @if ($rjNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addConsent" wire:loading.attr="disabled"
                            wire:target="addConsent" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addConsent">Simpan Inform Consent</span>
                            <span wire:loading wire:target="addConsent"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.inform-consent.cetak-inform-consent
        wire:key="cetak-inform-consent-ugd-{{ $rjNo ?? 'init' }}" />
</div>
