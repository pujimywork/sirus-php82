<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/rm-penilaian-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public string $subTab = 'nyeri';

    public array $subDirty = [
        'nyeri' => false,
        'resikoJatuh' => false,
        'dekubitus' => false,
        'gizi' => false,
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-ri']);
    }

    #[On('open-rm-penilaian-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['penilaian'] ??= ['nyeri' => [], 'resikoJatuh' => [], 'dekubitus' => [], 'gizi' => []];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← pakai trait

        $this->dispatch('open-rm-penilaian-nyeri-ri', $riHdrNo);
        $this->dispatch('open-rm-penilaian-resiko-jatuh-ri', $riHdrNo);
        $this->dispatch('open-rm-penilaian-dekubitus-ri', $riHdrNo);
        $this->dispatch('open-rm-penilaian-gizi-ri', $riHdrNo);

        $this->incrementVersion('modal-penilaian-ri');
    }

    #[On('penilaian-ri-saved')]
    public function onChildSaved(string $riHdrNo): void
    {
        $data = $this->findDataRI($riHdrNo);
        if ($data) {
            $this->dataDaftarRi = $data;
        }
    }

    /**
     * Bridge: tombol Simpan di modal footer EMR RI (top tab Penilaian)
     * dispatch event ini → forward ke save event untuk SEMUA sub-tab yg
     * dirty (sync dari Alpine via @entangle('subDirty').live). Fallback
     * ke sub-tab aktif kalau belum ada yg dirty (mis. user langsung klik
     * Simpan tanpa edit).
     */
    #[On('save-active-rm-penilaian-ri')]
    public function dispatchActiveSubTabSave(): void
    {
        $eventMap = [
            'nyeri' => 'save-rm-penilaian-nyeri-ri',
            'resikoJatuh' => 'save-rm-penilaian-resiko-jatuh-ri',
            'dekubitus' => 'save-rm-penilaian-dekubitus-ri',
            'gizi' => 'save-rm-penilaian-gizi-ri',
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

    public function getCountNyeriProperty(): int
    {
        return count($this->dataDaftarRi['penilaian']['nyeri'] ?? []);
    }

    public function getCountResikoJatuhProperty(): int
    {
        return count($this->dataDaftarRi['penilaian']['resikoJatuh'] ?? []);
    }

    public function getCountDekubitusProperty(): int
    {
        return count($this->dataDaftarRi['penilaian']['dekubitus'] ?? []);
    }

    public function getCountGiziProperty(): int
    {
        return count($this->dataDaftarRi['penilaian']['gizi'] ?? []);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->subDirty = ['nyeri' => false, 'resikoJatuh' => false, 'dekubitus' => false, 'gizi' => false];
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-3 rounded-lg text-sm
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    <div x-data="{
        sectionDirty: false,
        openedAt: 0,
        tab: 'penilaian',
        subTab: @entangle('subTab').live,
        subDirty: @entangle('subDirty').live,
        saveLabels: {
            nyeri: 'Penilaian Nyeri',
            resikoJatuh: 'Penilaian Risiko Jatuh',
            dekubitus: 'Penilaian Dekubitus',
            gizi: 'Penilaian Gizi',
        },
        markDirty() {
            if (Date.now() - this.openedAt <= 300) return;
            if (!this.subDirty[this.subTab]) {
                this.subDirty[this.subTab] = true;
            }
            if (!this.sectionDirty) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: this.tab });
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
                    subDirty[subTab] = false;
                }
                if (!Object.values(subDirty).some(v => v)) {
                    sectionDirty = false;
                    openedAt = Date.now();
                    $dispatch('section-clean', { tab: tab });
                }
            });
        "
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-effect="if (typeof saveMap !== 'undefined' && saveMap.penilaian) saveMap.penilaian.label = saveLabels[subTab]">

        {{-- TAB NAV --}}
        <div class="border-b border-hairline dark:border-gray-700 mb-4">
            <div class="flex flex-wrap gap-2 -mb-px">
                @php
                    $penilaianTabs = [
                        ['key' => 'nyeri', 'label' => 'Penilaian Nyeri', 'count' => $this->countNyeri],
                        ['key' => 'resikoJatuh', 'label' => 'Risiko Jatuh', 'count' => $this->countResikoJatuh],
                        ['key' => 'dekubitus', 'label' => 'Dekubitus', 'count' => $this->countDekubitus],
                        ['key' => 'gizi', 'label' => 'Gizi', 'count' => $this->countGizi],
                    ];
                @endphp
                @foreach ($penilaianTabs as $tab)
                    <x-tab variant="underline" active-expr="subTab === '{{ $tab['key'] }}'"
                        x-on:click="subTab = '{{ $tab['key'] }}'" class="inline-flex items-center gap-1.5">
                        {{ $tab['label'] }}
                        @if ($tab['count'] > 0)
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand text-white">
                                {{ $tab['count'] }}
                            </span>
                        @endif
                    </x-tab>
                @endforeach
            </div>
        </div>

        {{-- TAB: NYERI --}}
        <div x-show="subTab === 'nyeri'" x-transition.opacity.duration.200ms>
            <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.nyeri-ri.rm-penilaian-nyeri-ri-actions :riHdrNo="$riHdrNo"
                wire:key="penilaian-nyeri-{{ $riHdrNo }}" />
        </div>

        {{-- TAB: RISIKO JATUH --}}
        <div x-show="subTab === 'resikoJatuh'" x-transition.opacity.duration.200ms style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.resiko-jatuh-ri.rm-penilaian-resiko-jatuh-ri-actions
                :riHdrNo="$riHdrNo" wire:key="penilaian-ri-{{ $riHdrNo }}" />
        </div>

        {{-- TAB: DEKUBITUS --}}
        <div x-show="subTab === 'dekubitus'" x-transition.opacity.duration.200ms style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.dekubitus-ri.rm-penilaian-dekubitus-ri-actions
                :riHdrNo="$riHdrNo" wire:key="penilaian-dekubitus-{{ $riHdrNo }}" />
        </div>

        {{-- TAB: GIZI --}}
        <div x-show="subTab === 'gizi'" x-transition.opacity.duration.200ms style="display:none">
            <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.gizi-ri.rm-penilaian-gizi-ri-actions :riHdrNo="$riHdrNo"
                wire:key="penilaian-gizi-{{ $riHdrNo }}" />
        </div>

    </div>
</div>
