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
        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

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
            <div class="px-6 py-5 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    {{-- Data Pasien RI di header (contek EMR RI) menggantikan judul statis → ruang kerja lebih besar --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                            wire:key="modul-dokumen-display-pasien-ri-header-{{ $riHdrNo }}" />
                        @if ($isFormLocked)
                            <div class="flex flex-wrap gap-2 mt-2">
                                <x-badge variant="danger">Read Only</x-badge>
                            </div>
                        @endif
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
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
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-data="{ activeTab: 'generalConsent' }">

                {{-- TAB NAV --}}
                <div class="border-b border-hairline dark:border-gray-700 mb-4">
                    <div class="flex flex-wrap gap-2 -mb-px">

                        <x-tab variant="underline" active-expr="activeTab === 'generalConsent'"
                            x-on:click="activeTab = 'generalConsent'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            General Consent
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'penundaanPelayanan'"
                            x-on:click="activeTab = 'penundaanPelayanan'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Penundaan Pelayanan
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'permintaanKerohanian'"
                            x-on:click="activeTab = 'permintaanKerohanian'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            Kerohanian
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'akhirHayat'"
                            x-on:click="activeTab = 'akhirHayat'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            Akhir Hayat
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'informConsent'"
                            x-on:click="activeTab = 'informConsent'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Inform Consent
                        </x-tab>

                        @hasanyrole('Perawat|Admin|MPP')
                            <x-tab variant="underline" active-expr="activeTab === 'caseManager'"
                                x-on:click="activeTab = 'caseManager'" class="inline-flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Case Manager (MPP)
                            </x-tab>
                        @endhasanyrole

                        <x-tab variant="underline" active-expr="activeTab === 'edukasi'"
                            x-on:click="activeTab = 'edukasi'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            Edukasi Pasien
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'edukasiTerintegrasi'"
                            x-on:click="activeTab = 'edukasiTerintegrasi'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Edukasi Terintegrasi
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'pindahRuang'"
                            x-on:click="activeTab = 'pindahRuang'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            Pindah Antar Ruang
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'pelayananBedah'"
                            x-on:click="activeTab = 'pelayananBedah'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Pelayanan Bedah
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'vkKebidanan'"
                            x-on:click="activeTab = 'vkKebidanan'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            VK / Kebidanan
                        </x-tab>

                        {{-- Surat Kematian — tab hanya muncul bila status pulang di Perencanaan
                             adalah Meninggal (statusPulang BPJS 4), supaya tak jadi tab permanen. --}}
                        @if ((string) ($dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '') === '4')
                            <x-tab variant="underline" active-expr="activeTab === 'suratKematian'"
                                x-on:click="activeTab = 'suratKematian'" class="inline-flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Surat Kematian
                                @if (!empty($dataDaftarRi['suratKematianRI']['isFinal']))
                                    <x-badge variant="success" class="text-[10px] px-1.5 py-0">TTD</x-badge>
                                @else
                                    <x-badge variant="danger" class="text-[10px] px-1.5 py-0">!</x-badge>
                                @endif
                            </x-tab>
                        @endif

                    </div>
                </div>

                {{-- TAB: SURAT KEMATIAN --}}
                @if ((string) ($dataDaftarRi['perencanaan']['tindakLanjut']['statusPulang'] ?? '') === '4')
                    <div x-show="activeTab === 'suratKematian'" x-transition.opacity.duration.200ms
                        style="display:none">
                        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surat-kematian-ri.rm-surat-kematian-ri-actions
                            :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                            wire:key="surat-kematian-ri-{{ $riHdrNo ?? 'init' }}" />
                    </div>
                @endif

                {{-- TAB: INFORM CONSENT --}}
                <div x-show="activeTab === 'informConsent'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.inform-consent-ri.rm-inform-consent-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="inform-consent-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: GENERAL CONSENT --}}
                <div x-show="activeTab === 'generalConsent'" x-transition.opacity.duration.200ms>
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.general-consent-ri.rm-general-consent-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="general-consent-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PENUNDAAN PELAYANAN --}}
                <div x-show="activeTab === 'penundaanPelayanan'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.penundaan-pelayanan-ri.rm-penundaan-pelayanan-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="penundaan-pelayanan-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PENGKAJIAN PASIEN MENJELANG AKHIR HAYAT (RM.RI.62) --}}
                <div x-show="activeTab === 'akhirHayat'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.akhir-hayat-ri.rm-akhir-hayat-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="akhir-hayat-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PERMINTAAN KEROHANIAN --}}
                <div x-show="activeTab === 'permintaanKerohanian'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.permintaan-kerohanian-ri.rm-permintaan-kerohanian-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="permintaan-kerohanian-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: CASE MANAGER --}}
                @hasanyrole('Perawat|Admin|MPP')
                    <div x-show="activeTab === 'caseManager'" x-transition.opacity.duration.200ms style="display:none">
                        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.case-manager-ri.rm-case-manager-ri-actions
                            :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                            wire:key="case-manager-ri-{{ $riHdrNo ?? 'init' }}" />
                    </div>
                @endhasanyrole

                {{-- TAB: EDUKASI PASIEN --}}
                <div x-show="activeTab === 'edukasi'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.edukasi-pasien-ri.rm-edukasi-pasien-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="edukasi-pasien-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: EDUKASI TERINTEGRASI --}}
                <div x-show="activeTab === 'edukasiTerintegrasi'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.edukasi-terintegrasi-ri.rm-edukasi-terintegrasi-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="edukasi-terintegrasi-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PINDAH ANTAR RUANG --}}
                <div x-show="activeTab === 'pindahRuang'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.form-pindah-antar-ruang-ri.rm-form-pindah-antar-ruang-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="form-pindah-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PELAYANAN BEDAH (PAB) --}}
                <div x-show="activeTab === 'pelayananBedah'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pelayanan-bedah-ri.rm-pelayanan-bedah-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="pelayanan-bedah-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: VK / KEBIDANAN (umbrella sub-tab semua dokumen kebidanan) --}}
                <div x-show="activeTab === 'vkKebidanan'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.vk-kebidanan-ri.rm-vk-kebidanan-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="vk-kebidanan-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

            </div>{{-- end body --}}

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
