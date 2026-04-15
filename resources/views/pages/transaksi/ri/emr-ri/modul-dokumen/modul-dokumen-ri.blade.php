<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/rm-modul-dokumen-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-modul-dokumen-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-modul-dokumen-ri']);
    }

    #[On('emr-ri.modul-dokumen.open')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->isFormLocked = $this->checkRIStatus($riHdrNo);

        $this->dispatch('open-rm-inform-consent-ri', $riHdrNo);
        $this->dispatch('open-rm-general-consent-ri', $riHdrNo);
        $this->dispatch('open-rm-case-manager-ri', $riHdrNo);

        $this->incrementVersion('modal-modul-dokumen-ri');

        $this->dispatch('open-modal', name: 'modul-dokumen-ri'); // ← WAJIB ada ini
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'modul-dokumen-ri');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
    }
};
?>

<div>
    <x-modal name="modul-dokumen-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-modul-dokumen-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
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
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Modul Dokumen</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Pengelolaan consent dan dokumen pasien rawat inap
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 mt-3">
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data="{ activeTab: 'informConsent' }">

                {{-- Display Pasien --}}
                <div class="mb-4">
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="modul-dokumen-display-pasien-ri-{{ $riHdrNo }}" />
                </div>

                {{-- TAB NAV --}}
                <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                    <ul class="flex flex-wrap -mb-px text-xs font-medium text-gray-500 dark:text-gray-400">

                        <li class="mr-2">
                            <button type="button" @click="activeTab = 'informConsent'"
                                :class="activeTab === 'informConsent'
                                    ?
                                    'text-brand border-brand bg-brand/5 font-semibold' :
                                    'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Inform Consent
                            </button>
                        </li>

                        <li class="mr-2">
                            <button type="button" @click="activeTab = 'generalConsent'"
                                :class="activeTab === 'generalConsent'
                                    ?
                                    'text-brand border-brand bg-brand/5 font-semibold' :
                                    'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                General Consent
                            </button>
                        </li>

                        @hasanyrole('Perawat|Admin|MPP')
                            <li class="mr-2">
                                <button type="button" @click="activeTab = 'caseManager'"
                                    :class="activeTab === 'caseManager'
                                        ?
                                        'text-brand border-brand bg-brand/5 font-semibold' :
                                        'border-transparent hover:text-gray-600 hover:border-gray-300'"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Case Manager (MPP)
                                </button>
                            </li>
                        @endhasanyrole

                    </ul>
                </div>

                {{-- TAB: INFORM CONSENT --}}
                <div x-show="activeTab === 'informConsent'" x-transition.opacity.duration.200ms>
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.inform-consent-ri.rm-inform-consent-ri-actions
                        :riHdrNo="$riHdrNo" wire:key="inform-consent-ri-{{ $riHdrNo }}" />
                </div>

                {{-- TAB: GENERAL CONSENT --}}
                <div x-show="activeTab === 'generalConsent'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.general-consent-ri.rm-general-consent-ri-actions
                        :riHdrNo="$riHdrNo" wire:key="general-consent-ri-{{ $riHdrNo }}" />
                </div>

                {{-- TAB: CASE MANAGER --}}
                @hasanyrole('Perawat|Admin|MPP')
                    <div x-show="activeTab === 'caseManager'" x-transition.opacity.duration.200ms style="display:none">
                        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.case-manager-ri.rm-case-manager-ri-actions
                            :riHdrNo="$riHdrNo" wire:key="case-manager-ri-{{ $riHdrNo }}" />
                    </div>
                @endhasanyrole

            </div>{{-- end body --}}

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
