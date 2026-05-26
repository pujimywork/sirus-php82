<?php
// resources/views/pages/transaksi/ri/emr-ri/observasi-ri/rm-observasi-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public string $subTab = 'obat-cairan';

    public array $subDirty = [
        'obat-cairan' => false,
        'pengeluaran' => false,
        'oksigen' => false,
        'ttv' => false,
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-observasi-ri']);
    }

    #[On('open-rm-observasi-ri')]
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

        // Inisialisasi struktur array jika belum ada
        $this->dataDaftarRi['observasi'] ??= [];
        $this->dataDaftarRi['observasi']['obatDanCairan'] ??= [
            'pemberianObatDanCairanTab' => 'Pemberian Obat Dan Cairan',
            'pemberianObatDanCairan' => [],
        ];
        $this->dataDaftarRi['observasi']['pengeluaranCairan'] ??= [
            'pengeluaranCairanTab' => 'Pengeluaran Cairan',
            'pengeluaranCairan' => [],
        ];
        $this->dataDaftarRi['observasi']['pemakaianOksigen'] ??= [
            'pemakaianOksigenTab' => 'Pemakaian Oksigen',
            'pemakaianOksigenData' => [],
        ];
        $this->dataDaftarRi['observasi']['observasiLanjutan'] ??= [
            'tandaVitalTab' => 'Observasi Lanjutan',
            'tandaVital' => [],
        ];

        // Gunakan trait untuk cek status
        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        // Dispatch ke semua sub-komponen observasi
        $this->dispatch('open-rm-obat-dan-cairan-ri', $riHdrNo);
        $this->dispatch('open-observasi-lanjutan-ri', $riHdrNo);
        $this->dispatch('open-pemakaian-oksigen-ri', $riHdrNo);
        $this->dispatch('open-pengeluaran-cairan-ri', $riHdrNo);

        $this->incrementVersion('modal-observasi-ri');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->subDirty = ['obat-cairan' => false, 'pengeluaran' => false, 'oksigen' => false, 'ttv' => false];
    }

    /**
     * Bridge: tombol Simpan di modal footer EMR RI (top tab Observasi)
     * dispatch event ini → forward ke save event untuk SEMUA sub-tab yg
     * dirty (sync dari Alpine via @entangle('subDirty').live). Fallback
     * ke sub-tab aktif kalau belum ada yg dirty.
     */
    #[On('save-active-rm-observasi-ri')]
    public function dispatchActiveSubTabSave(): void
    {
        $eventMap = [
            'obat-cairan' => 'save-rm-obat-dan-cairan-ri',
            'pengeluaran' => 'save-rm-pengeluaran-cairan-ri',
            'oksigen' => 'save-rm-pemakaian-oksigen-ri',
            'ttv' => 'save-rm-observasi-lanjutan-ri',
        ];

        $targets = array_keys(array_filter($this->subDirty));
        if (empty($targets)) {
            $targets = [$this->subTab];
        }

        foreach ($targets as $key) {
            if (isset($eventMap[$key])) {
                $this->dispatch($eventMap[$key]);
            }
        }
    }

    // Helper untuk mengambil count tiap tab
    public function getCountObatProperty(): int
    {
        return count($this->dataDaftarRi['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? []);
    }

    public function getCountPengeluaranProperty(): int
    {
        return count($this->dataDaftarRi['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? []);
    }

    public function getCountOksigenProperty(): int
    {
        return count($this->dataDaftarRi['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? []);
    }

    public function getCountTTVProperty(): int
    {
        return count($this->dataDaftarRi['observasi']['observasiLanjutan']['tandaVital'] ?? []);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-observasi-ri', [$riHdrNo ?? 'new']) }}">

    {{-- Locked banner --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-3 rounded-lg text-sm
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — semua form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ══ 4 TAB OBSERVASI ══ --}}
    <div x-data="{
        sectionDirty: false,
        openedAt: 0,
        topTab: 'observasi',
        tab: @entangle('subTab').live,
        subDirty: @entangle('subDirty').live,
        saveLabels: {
            'obat-cairan': 'Pemberian Obat & Cairan',
            'pengeluaran': 'Pengeluaran Cairan',
            'oksigen': 'Pemakaian Oksigen',
            'ttv': 'Observasi Lanjutan',
        },
        markDirty() {
            if (Date.now() - this.openedAt <= 300) return;
            if (!this.subDirty[this.tab]) {
                this.subDirty[this.tab] = true;
            }
            if (!this.sectionDirty) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: this.topTab });
            }
        },
    }"
        x-init="
            openedAt = Date.now();
            window.addEventListener('refresh-after-ri.saved', (e) => {
                const savedSub = e.detail?.subTab;
                if (savedSub && subDirty.hasOwnProperty(savedSub)) {
                    subDirty[savedSub] = false;
                } else {
                    subDirty[tab] = false;
                }
                if (!Object.values(subDirty).some(v => v)) {
                    sectionDirty = false;
                    openedAt = Date.now();
                    $dispatch('section-clean', { tab: topTab });
                }
            });
        "
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-effect="if (typeof saveMap !== 'undefined' && saveMap.observasi) saveMap.observasi.label = saveLabels[tab]">

        {{-- Tab header --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-gray-500 dark:text-gray-400">
                @php
                    $obsTabs = [
                        [
                            'key' => 'obat-cairan',
                            'label' => 'Pemberian Obat & Cairan',
                            'count' => $this->countObat,
                            'icon' =>
                                'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
                        ],
                        [
                            'key' => 'pengeluaran',
                            'label' => 'Pengeluaran Cairan',
                            'count' => $this->countPengeluaran,
                            'icon' =>
                                'M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z',
                        ],
                        [
                            'key' => 'oksigen',
                            'label' => 'Pemakaian Oksigen',
                            'count' => $this->countOksigen,
                            'icon' =>
                                'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
                        ],
                        [
                            'key' => 'ttv',
                            'label' => 'Observasi Lanjutan',
                            'count' => $this->countTTV,
                            'icon' =>
                                'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                        ],
                    ];
                @endphp

                @foreach ($obsTabs as $t)
                    <li class="mr-0.5">
                        <button type="button" @click="tab = '{{ $t['key'] }}'"
                            class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg text-xs transition-colors"
                            :class="tab === '{{ $t['key'] }}'
                                ?
                                'text-brand border-brand bg-brand/5 dark:bg-brand/10 font-semibold' :
                                'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="{{ $t['icon'] }}" />
                            </svg>
                            {{ $t['label'] }}
                            @if ($t['count'] > 0)
                                <span
                                    class="px-1.5 py-0.5 rounded-full text-[10px] font-bold
                                    bg-brand-green text-white dark:bg-brand-lime dark:text-gray-900">
                                    {{ $t['count'] }}
                                </span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Tab 1: Pemberian Obat & Cairan --}}
        <div x-show="tab === 'obat-cairan'" x-transition.opacity.duration.150ms class="pt-3">
            <livewire:pages::transaksi.ri.emr-ri.observasi-ri.obat-dan-cairan-ri.rm-obat-dan-cairan-ri-actions
                :riHdrNo="$riHdrNo" wire:key="obat-cairan-{{ $riHdrNo }}" />
        </div>

        {{-- Tab 2: Pengeluaran Cairan --}}
        <div x-show="tab === 'pengeluaran'" x-transition.opacity.duration.150ms class="pt-4" style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.observasi-ri.pengeluaran-cairan-ri.rm-pengeluaran-cairan-ri-actions
                :riHdrNo="$riHdrNo" wire:key="pengeluaran-{{ $riHdrNo }}" />
        </div>

        {{-- Tab 3: Pemakaian Oksigen --}}
        <div x-show="tab === 'oksigen'" x-transition.opacity.duration.150ms class="pt-4" style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.observasi-ri.pemakaian-oksigen-ri.rm-pemakaian-oksigen-ri-actions
                :riHdrNo="$riHdrNo" wire:key="oksigen-{{ $riHdrNo }}" />
        </div>

        {{-- Tab 4: Observasi Lanjutan (Tanda Vital) --}}
        <div x-show="tab === 'ttv'" x-transition.opacity.duration.150ms class="pt-4" style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.observasi-ri.observasi-lanjutan-ri.rm-observasi-lanjutan-ri-actions
                :riHdrNo="$riHdrNo" wire:key="ttv-{{ $riHdrNo }}" />
        </div>

    </div>{{-- end x-data tab --}}
</div>
