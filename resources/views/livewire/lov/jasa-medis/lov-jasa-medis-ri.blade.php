<?php
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

/**
 * LOV Jasa Medis khusus Rawat Inap — harga otomatis sesuai kelas kamar pasien.
 *
 * Beda dgn lov-jasa-medis (RJ/UGD): menerima riHdrNo, lalu resolve sendiri
 * kelas kamar (rstxn_rihdrs → rsmst_rooms) + status klaim (rsmst_klaimtypes).
 * Harga di payload sudah harga efektif: tarif per kelas (rsmst_actpclasses)
 * kalau ada & > 0, fallback tarif header (rsmst_actparamedics).
 */
new class extends Component {
    public string $target = 'default';
    public string $label = 'Cari Jasa Medis';
    public string $placeholder = 'Ketik kode/nama jasa medis...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public ?array $selected = null;

    #[Reactive]
    public ?string $initialPactId = null;

    public bool $disabled = false;

    /* -------------------- KONTEKS RI -------------------- */
    public ?int $riHdrNo = null;

    /** Kelas kamar pasien saat ini (null = tidak ketemu → selalu tarif header). */
    public ?int $classId = null;
    public string $classDesc = '';
    public string $klaimStatus = 'UMUM';

    public function mount(): void
    {
        $this->resolveKonteksRI();

        if (!$this->initialPactId) {
            return;
        }
        $this->loadSelected($this->initialPactId);
    }

    /** Resolve kelas kamar + status klaim dari riHdrNo (sekali per mount/remount). */
    private function resolveKonteksRI(): void
    {
        if (!$this->riHdrNo) {
            return;
        }

        $row = DB::table('rstxn_rihdrs as h')
            ->join('rsmst_rooms as r', 'h.room_id', '=', 'r.room_id')
            ->leftJoin('rsmst_class as c', 'r.class_id', '=', 'c.class_id')
            ->leftJoin('rsmst_klaimtypes as k', 'h.klaim_id', '=', 'k.klaim_id')
            ->where('h.rihdr_no', $this->riHdrNo)
            ->select('r.class_id', 'c.class_desc', 'k.klaim_status')
            ->first();

        if ($row) {
            $this->classId = $row->class_id !== null ? (int) $row->class_id : null;
            $this->classDesc = (string) ($row->class_desc ?? '');
            $this->klaimStatus = (string) ($row->klaim_status ?? 'UMUM');
        }
    }

    /** Query jasa medis + tarif kelas kamar (left join → tetap muncul tanpa baris kelas). */
    private function baseQuery()
    {
        $q = DB::table('rsmst_actparamedics as p')->select('p.pact_id', 'p.pact_desc', 'p.pact_price', 'p.pact_price_bpjs');

        if ($this->classId !== null) {
            $q->leftJoin('rsmst_actpclasses as pc', function ($join) {
                $join->on('pc.pact_id', '=', 'p.pact_id')->where('pc.class_id', '=', $this->classId);
            })->addSelect('pc.actp_price', 'pc.actp_price_bpjs');
        } else {
            $q->addSelect(DB::raw('NULL as actp_price'), DB::raw('NULL as actp_price_bpjs'));
        }

        return $q;
    }

    /**
     * Harga efektif: tarif kelas dulu (kalau > 0), fallback tarif header.
     * BPJS turun ke header BPJS lalu header umum (konsisten handler lama).
     */
    private function effectivePrice(object $row): array
    {
        if ($this->klaimStatus === 'BPJS') {
            $chain = [
                ['kelas', $row->actp_price_bpjs ?? 0],
                ['header', $row->pact_price_bpjs ?? 0],
                ['header', $row->pact_price ?? 0],
            ];
        } else {
            $chain = [
                ['kelas', $row->actp_price ?? 0],
                ['header', $row->pact_price ?? 0],
            ];
        }

        foreach ($chain as [$source, $price]) {
            if ((int) $price > 0) {
                return ['price' => (int) $price, 'source' => $source];
            }
        }

        return ['price' => 0, 'source' => 'header'];
    }

    private function buildPayload(object $row): array
    {
        $eff = $this->effectivePrice($row);

        return [
            'pact_id' => (string) $row->pact_id,
            'pact_desc' => (string) ($row->pact_desc ?? ''),
            'pact_price' => $eff['price'],
            'price_source' => $eff['source'], // 'kelas' | 'header'
            'class_id' => $this->classId,
        ];
    }

    private function priceHint(array $payload): string
    {
        $sumber = $payload['price_source'] === 'kelas' ? ($this->classDesc ?: 'Kelas ' . $this->classId) : 'tarif dasar';

        return 'Kode: ' . $payload['pact_id'] . ' • Rp ' . number_format($payload['pact_price']) . ' — ' . $this->klaimStatus . ', ' . $sumber;
    }

    public function updatedInitialPactId($value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }
        $this->loadSelected($value);
    }

    protected function loadSelected(string $pactId): void
    {
        $row = $this->baseQuery()->where('p.pact_id', $pactId)->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ── Exact match by pact_id ──
        if (ctype_alnum($keyword)) {
            $exact = $this->baseQuery()->where('p.pact_id', $keyword)->first();

            if ($exact) {
                $this->dispatchSelected($this->buildPayload($exact));
                return;
            }
        }

        // ── Partial search ──
        $upper = mb_strtoupper($keyword);

        $rows = $this->baseQuery()
            ->where(function ($q) use ($upper) {
                $q->where(DB::raw('upper(p.pact_desc)'), 'like', "%{$upper}%")->orWhere(DB::raw('upper(p.pact_id)'), 'like', "%{$upper}%");
            })
            ->orderBy('p.pact_desc')
            ->orderBy('p.pact_id')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(function ($row) {
                $payload = $this->buildPayload($row);
                return [
                    ...$payload,
                    'label' => $payload['pact_desc'] ?: '-',
                    'hint' => $this->priceHint($payload),
                ];
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $opt = $this->options[$index];

        $this->dispatchSelected([
            'pact_id' => $opt['pact_id'] ?? '',
            'pact_desc' => $opt['pact_desc'] ?? '',
            'pact_price' => $opt['pact_price'] ?? 0,
            'price_source' => $opt['price_source'] ?? 'header',
            'class_id' => $opt['class_id'] ?? null,
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected = $payload;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <div class="flex items-center justify-between gap-2">
        <x-input-label :value="$label" />
        @if ($classId !== null)
            <span class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded
                         bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                title="Tarif otomatis mengikuti kelas kamar & status klaim pasien">
                {{ $classDesc ?: 'Kelas ' . $classId }} • {{ $klaimStatus }}
            </span>
        @endif
    </div>

    <div class="relative mt-1">
        @if ($selected === null)
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['pact_id'] . ' — ' . $selected['pact_desc']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- Dropdown list --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-jmri-{{ $option['pact_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] }}
                                </div>
                                @if (!empty($option['hint']))
                                    <div class="text-xs {{ ($option['price_source'] ?? '') === 'kelas' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
