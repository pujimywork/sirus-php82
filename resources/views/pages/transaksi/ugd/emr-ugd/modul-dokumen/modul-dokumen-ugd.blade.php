<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/modul-dokumen-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('emr-ugd.modul-dokumen.open')]
    public function openModulDokumen(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dispatch('open-modal', name: 'modul-dokumen-ugd');
        $this->dispatch('open-rm-suket-ugd', $rjNo);
        $this->dispatch('open-rm-form-trf-ugd-ri', $rjNo);
        $this->dispatch('open-rm-general-consent', $rjNo);
        $this->dispatch('open-rm-inform-consent', $rjNo);
        $this->dispatch('open-rm-form-penjaminan', $rjNo);
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'modul-dokumen-ugd');
    }

    public function save(): void
    {
        // General Consent, Inform Consent, dan Form Penjaminan punya tombol simpan sendiri
    }

    #[On('refresh-modul-dokumen-ugd-data')]
    public function refreshDataDaftarUGD(int $rjNo): void
    {
        if ($this->rjNo !== $rjNo) {
            return;
        }

        $data = $this->findDataUGD($rjNo);
        if ($data) {
            $this->dataDaftarUGD = $data;
        }
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="modul-dokumen-ugd" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Modul Dokumen UGD
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Pengelolaan surat keterangan dan dokumen pasien Unit Gawat Darurat
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
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
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Display Pasien --}}
                        <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="modul-dokumen-display-pasien-ugd-{{ $rjNo }}" />

                        {{-- TAB NAVIGATOR --}}
                        <div x-data="{ activeTab: 'suket' }">

                            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                                <ul
                                    class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400">

                                    {{-- Surat Keterangan --}}
                                    <li class="mr-1">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                            :class="activeTab === 'suket'
                                                ?
                                                'text-primary border-primary bg-gray-100 dark:bg-gray-800' :
                                                'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                            @click="activeTab = 'suket'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Surat Keterangan
                                        </button>
                                    </li>

                                    {{-- Form Transfer UGD → RI --}}
                                    <li class="mr-1">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                            :class="activeTab === 'trf-ri'
                                                ?
                                                'text-primary border-primary bg-gray-100 dark:bg-gray-800' :
                                                'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                            @click="activeTab = 'trf-ri'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                            Form Transfer UGD &rarr; RI
                                        </button>
                                    </li>

                                    {{-- General Consent --}}
                                    <li class="mr-1">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                            :class="activeTab === 'general-consent'
                                                ?
                                                'text-primary border-primary bg-gray-100 dark:bg-gray-800' :
                                                'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                            @click="activeTab = 'general-consent'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                            </svg>
                                            General Consent
                                            @if (!empty($dataDaftarUGD['generalConsentPasienUGD']['signature']))
                                                <x-badge variant="success"
                                                    class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                                            @endif
                                        </button>
                                    </li>

                                    {{-- Inform Consent --}}
                                    <li class="mr-1">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                            :class="activeTab === 'inform-consent'
                                                ?
                                                'text-primary border-primary bg-gray-100 dark:bg-gray-800' :
                                                'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                            @click="activeTab = 'inform-consent'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                            </svg>
                                            Inform Consent
                                            @if (!empty($dataDaftarUGD['informConsentPasienUGD']) && count($dataDaftarUGD['informConsentPasienUGD']) > 0)
                                                <x-badge variant="success"
                                                    class="text-[10px] px-1.5 py-0">{{ count($dataDaftarUGD['informConsentPasienUGD']) }}</x-badge>
                                            @endif
                                        </button>
                                    </li>

                                    {{-- Form Penjaminan & Orientasi Kamar --}}
                                    <li class="mr-1">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 border-b-2 rounded-t-lg transition-colors"
                                            :class="activeTab === 'form-penjaminan'
                                                ?
                                                'text-primary border-primary bg-gray-100 dark:bg-gray-800' :
                                                'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                            @click="activeTab = 'form-penjaminan'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Form Penjaminan & Orientasi Kamar
                                            @if (!empty($dataDaftarUGD['formPenjaminanOrientasiKamar']) && count($dataDaftarUGD['formPenjaminanOrientasiKamar']) > 0)
                                                <x-badge variant="success"
                                                    class="text-[10px] px-1.5 py-0">{{ count($dataDaftarUGD['formPenjaminanOrientasiKamar']) }}</x-badge>
                                            @endif
                                        </button>
                                    </li>

                                </ul>
                            </div>

                            {{-- Panel: Surat Keterangan --}}
                            <div x-show="activeTab === 'suket'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.suket.rm-suket-ugd-actions
                                    :rjNo="$rjNo" wire:key="suket-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: Form Transfer UGD → RI --}}
                            <div x-show="activeTab === 'trf-ri'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.form-trf-ugd-ri.rm-form-trf-ugd-ri-actions
                                    :rjNo="$rjNo" wire:key="form-trf-ugd-ri-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: General Consent --}}
                            <div x-show="activeTab === 'general-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.general-consent.rm-general-consent-actions
                                    :rjNo="$rjNo" wire:key="general-consent-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: Inform Consent --}}
                            <div x-show="activeTab === 'inform-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.inform-consent.rm-inform-consent-actions
                                    :rjNo="$rjNo" wire:key="inform-consent-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: Form Penjaminan & Orientasi Kamar --}}
                            <div x-show="activeTab === 'form-penjaminan'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.form-penjaminan.rm-form-penjaminan-actions
                                    :rjNo="$rjNo" wire:key="form-penjaminan-ugd-{{ $rjNo }}" />
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                    {{-- @if (!$isFormLocked)
                        <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan
                            </span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    @endif --}}
                </div>
            </div>

        </div>
    </x-modal>
</div>
