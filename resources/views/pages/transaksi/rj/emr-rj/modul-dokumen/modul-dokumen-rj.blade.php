<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/modul-dokumen-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* ===============================
     | OPEN MODUL DOKUMEN RJ
     =============================== */
    #[On('emr-rj.modul-dokumen.open')]
    public function openModulDokumen(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dispatch('open-modal', name: 'modul-dokumen-rj');
        $this->dispatch('open-rm-suket-rj', $rjNo);
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'modul-dokumen-rj');
    }

    public function save(): void
    {
        // Suket / General Consent / Inform Consent punya tombol simpan sendiri
    }

    #[On('refresh-modul-dokumen-rj-data')]
    public function refreshDataDaftarRJ(int $rjNo): void
    {
        if ($this->rjNo !== $rjNo) {
            return;
        }

        $data = $this->findDataRJ($rjNo);
        if ($data) {
            $this->dataDaftarPoliRJ = $data;
        }
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }
};

?>

<div>
    <x-modal name="modul-dokumen-rj" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    {{-- Data Pasien RJ di header (contek EMR RJ / Modul Dokumen UGD-RI) menggantikan judul statis --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                            wire:key="modul-dokumen-display-pasien-rj-header-{{ $rjNo }}" />
                        @if ($isFormLocked)
                            <div class="flex flex-wrap gap-2 mt-2">
                                <x-badge variant="danger">Read Only</x-badge>
                            </div>
                        @endif
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- TAB NAVIGATOR --}}
                        <div x-data="{ activeTab: 'suket' }">

                            <div class="border-b border-hairline dark:border-gray-700 mb-4">
                                <div class="flex flex-wrap gap-1 -mb-px">

                                    {{-- Surat Keterangan --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'suket'"
                                        x-on:click="activeTab = 'suket'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Surat Keterangan
                                    </x-tab>

                                    {{-- General Consent --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'general-consent'"
                                        x-on:click="activeTab = 'general-consent'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                        </svg>
                                        General Consent
                                        @if (!empty($dataDaftarPoliRJ['generalConsentPasienRJ']['signature']))
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Inform Consent --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'inform-consent'"
                                        x-on:click="activeTab = 'inform-consent'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        Inform Consent
                                        @if (!empty($dataDaftarPoliRJ['informConsentPasienRJ']) && count($dataDaftarPoliRJ['informConsentPasienRJ']) > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ count($dataDaftarPoliRJ['informConsentPasienRJ']) }}</x-badge>
                                        @endif
                                    </x-tab>

                                </div>
                            </div>

                            {{-- Panel: Surat Keterangan --}}
                            <div x-show="activeTab === 'suket'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.suket.rm-suket-rj-actions
                                    :rjNo="$rjNo" wire:key="suket-rj-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: General Consent --}}
                            <div x-show="activeTab === 'general-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.general-consent.rm-general-consent-rj-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="general-consent-rj-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Inform Consent --}}
                            <div x-show="activeTab === 'inform-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.inform-consent.rm-inform-consent-rj-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="inform-consent-rj-{{ $rjNo ?? 'init' }}" />
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
