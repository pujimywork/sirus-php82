<?php
// resources/views/livewire/lov/diag-kep/lov-diag-kep.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {

    /** Target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label       = 'Cari Diagnosis Keperawatan';
    public string $placeholder = 'Ketik kode atau nama diagnosis keperawatan...';

    /** State pencarian */
    public string $search        = '';
    public array  $options       = [];
    public bool   $isOpen        = false;
    public int    $selectedIndex = 0;

    /** Selected state */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim diagkep_id yang sudah tersimpan.
     * Komponen akan load data lengkap + diagkep_json dari DB.
     */
    #[Reactive]
    public ?string $initialDiagKepId = null;

    /** Jika true, input non-aktif dan tombol Ubah disembunyikan */
    public bool $disabled = false;

    /* ============================================================
     | MOUNT & REACTIVE
     ============================================================ */
    public function mount(): void
    {
        if (!$this->initialDiagKepId) {
            return;
        }
        $this->loadSelected($this->initialDiagKepId);
    }

    public function updatedInitialDiagKepId(?string $value): void
    {
        $this->selected = null;
        $this->resetLov();

        if (empty($value)) {
            return;
        }
        $this->loadSelected($value);
    }

    protected function loadSelected(string $diagKepId): void
    {
        $row = DB::table('rsmst_diagkeperawatans')
            ->select('diagkep_id', 'diagkep_desc', 'diagkep_json')
            ->where('diagkep_id', $diagKepId)
            ->first();

        if ($row) {
            $this->selected = $this->buildPayload($row);
        }
    }

    /* ============================================================
     | SEARCH
     ============================================================ */
    public function updatedSearch(): void
    {
        // Kalau sudah selected jangan cari
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 1) {
            $this->closeAndResetList();
            return;
        }

        // 1) Exact match by diagkep_id
        $exact = DB::table('rsmst_diagkeperawatans')
            ->select('diagkep_id', 'diagkep_desc', 'diagkep_json')
            ->where('diagkep_id', $keyword)
            ->first();

        if ($exact) {
            $this->dispatchSelected($this->buildPayload($exact));
            return;
        }

        // 2) Partial search
        $upperKw = mb_strtoupper($keyword);

        $rows = DB::table('rsmst_diagkeperawatans')
            ->select('diagkep_id', 'diagkep_desc', 'diagkep_json')
            ->where(function ($q) use ($keyword, $upperKw) {
                $q->orWhereRaw('UPPER(diagkep_desc) LIKE ?', ["%{$upperKw}%"])
                  ->orWhereRaw('UPPER(diagkep_id)   LIKE ?', ["%{$upperKw}%"]);
            })
            ->orderBy('diagkep_desc')
            ->limit(10)
            ->get();

        $this->options = $rows->map(fn($r) => array_merge(
            $this->buildPayload($r),
            [
                'label' => $r->diagkep_desc ?? '-',
                'hint'  => 'Kode: ' . $r->diagkep_id,
            ]
        ))->toArray();

        $this->isOpen        = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ============================================================
     | ACTIONS
     ============================================================ */
    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }
        $this->dispatchSelected($this->options[$index]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;
        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    public function clearSelected(): void
    {
        if ($this->disabled) return;
        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    /* ============================================================
     | HELPERS
     ============================================================ */
    protected function buildPayload(object $row): array
    {
        $json = $row->diagkep_json ?? null;

        return [
            'diagkep_id'   => (string) ($row->diagkep_id   ?? ''),
            'diagkep_desc' => (string) ($row->diagkep_desc ?? ''),
            'diagkep_json' => is_array($json)
                ? $json
                : (json_decode((string) $json, true) ?? []),
            /* Alias untuk kompatibilitas dengan LOVDiagKepTrait lama */
            'diag_id'      => (string) ($row->diagkep_id   ?? ''),
            'diag_desc'    => (string) ($row->diagkep_desc ?? ''),
        ];
    }

    protected function closeAndResetList(): void
    {
        $this->options       = [];
        $this->isOpen        = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected      = $payload;
        $this->search        = '';
        $this->options       = [];
        $this->isOpen        = false;
        $this->selectedIndex = 0;

        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    protected function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.lov.diag-kep.lov-diag-kep');
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">

    <x-input-label :value="$label" />

    <div class="relative mt-1">

        @if ($selected === null)
            {{-- ── MODE CARI ── --}}
            @if (!$disabled)
                <x-text-input
                    type="text"
                    class="block w-full"
                    :placeholder="$placeholder"
                    wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious"
                    wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif

        @else
            {{-- ── MODE SELECTED ── --}}
            <div class="flex items-start gap-2">

                {{-- Info terpilih --}}
                <div class="flex-1 min-w-0">
                    <x-text-input type="text" class="block w-full"
                        :value="$selected['diagkep_desc'] ?? '-'" disabled />

                    {{-- Preview ringkas diagkep_json --}}
                    @if (!empty($selected['diagkep_json']) && is_array($selected['diagkep_json']))
                        <div class="mt-1.5 rounded-lg border border-brand/20 bg-brand/5 px-3 py-2 space-y-1">
                            @foreach ($selected['diagkep_json'] as $kunci => $nilai)
                                @if (!empty($nilai))
                                    <div class="text-xs">
                                        <span class="font-bold text-brand uppercase tracking-wide">{{ $kunci }}:</span>
                                        @if (is_array($nilai))
                                            <ul class="mt-0.5 ml-3 list-disc text-gray-700 dark:text-gray-300 space-y-0.5">
                                                @foreach ($nilai as $item)
                                                    <li>
                                                        @if (is_array($item))
                                                            @foreach ($item as $sk => $sv)
                                                                <span class="font-medium">{{ $sk }}:</span>
                                                                {{ is_array($sv) ? implode(', ', $sv) : $sv }}
                                                            @endforeach
                                                        @else
                                                            {{ $item }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $nilai }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap shrink-0">
                        Ubah
                    </x-secondary-button>
                @endif

            </div>
        @endif

        {{-- ── DROPDOWN LIST ── --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200
                        shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li
                            wire:key="lov-diagkep-{{ $option['diagkep_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">

                                {{-- Nama diagnosis --}}
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? '-' }}
                                </div>

                                {{-- Kode --}}
                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif

                                {{-- Preview JSON singkat (key pertama saja) --}}
                                @if (!empty($option['diagkep_json']) && is_array($option['diagkep_json']))
                                    @php $firstKey = array_key_first($option['diagkep_json']); @endphp
                                    @if ($firstKey && !empty($option['diagkep_json'][$firstKey]))
                                        <div class="mt-0.5 text-xs text-brand/70 dark:text-emerald-400/70 truncate">
                                            {{ $firstKey }}:
                                            @php $val = $option['diagkep_json'][$firstKey]; @endphp
                                            @if (is_array($val))
                                                {{ implode(', ', array_map(fn($v) => is_array($v) ? json_encode($v) : $v, $val)) }}
                                            @else
                                                {{ $val }}
                                            @endif
                                        </div>
                                    @endif
                                @endif

                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif

    </div>

</x-lov.dropdown>
