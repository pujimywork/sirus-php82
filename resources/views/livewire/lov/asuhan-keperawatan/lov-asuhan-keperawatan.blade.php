<?php
// resources/views/livewire/lov/asuhan-keperawatan/lov-asuhan-keperawatan.blade.php

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
        return view('livewire.lov.asuhan-keperawatan.lov-asuhan-keperawatan');
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
    <div class="relative mt-1.5">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full text-sm" :placeholder="$placeholder"
                    wire:model.live.debounce.250ms="search" wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                    wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text"
                    class="block w-full text-sm bg-gray-100 cursor-not-allowed dark:bg-gray-800" :placeholder="$placeholder"
                    disabled />
            @endif

            {{-- Loading indicator --}}
            <div wire:loading wire:target="search" class="absolute right-3 top-2.5">
                <svg class="w-4 h-4 animate-spin text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </div>
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <div
                        class="flex items-center justify-between px-4 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selected['diagkep_desc'] ?? '-' }}</span>
                        <span class="px-2 py-0.5 font-mono text-xs bg-brand/10 text-brand rounded-full dark:bg-brand/20">
                            {{ $selected['diagkep_id'] ?? '' }}
                        </span>
                    </div>
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected"
                        class="px-4 py-3 text-sm whitespace-nowrap">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        Ganti
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-96 dark:divide-gray-800">
                    @forelse ($options as $index => $option)
                        <li wire:key="lov-diagkep-{{ $option['diagkep_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex"
                                class="px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <div class="space-y-1.5">
                                    {{-- Header: Nama dan Kode --}}
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $option['label'] ?? '-' }}
                                        </div>
                                        <div class="px-2 py-0.5 text-xs font-mono bg-brand/10 text-brand rounded-full dark:bg-brand/20">
                                            {{ $option['diagkep_id'] ?? '-' }}
                                        </div>
                                    </div>

                                    {{-- Kategori & Subkategori --}}
                                    @if (!empty($option['diagkep_json']['sdki']['kategori']))
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                </svg>
                                                {{ $option['diagkep_json']['sdki']['kategori'] }}{{ !empty($option['diagkep_json']['sdki']['subkategori']) ? ' / ' . $option['diagkep_json']['sdki']['subkategori'] : '' }}
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Preview definisi SDKI --}}
                                    @if (!empty($option['diagkep_json']['sdki']['definisi']))
                                        <div class="flex items-start gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                            <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="line-clamp-2">{{ $option['diagkep_json']['sdki']['definisi'] }}</span>
                                        </div>
                                    @endif

                                    {{-- SLKI & SIKI count --}}
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        @if (!empty($option['diagkep_json']['slki']))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                SLKI: {{ count($option['diagkep_json']['slki']) }}
                                            </span>
                                        @endif
                                        @if (!empty($option['diagkep_json']['siki']))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                SIKI: {{ count($option['diagkep_json']['siki']) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </x-lov.item>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 mb-3 text-gray-300 dark:text-gray-600" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm">Diagnosis tidak ditemukan</p>
                                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                                    Coba dengan kata kunci lain
                                </p>
                            </div>
                        </li>
                    @endforelse
                </ul>

                {{-- Footer info --}}
                <div
                    class="px-4 py-2 text-sm text-gray-500 border-t border-gray-100 bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                    <div class="flex items-center justify-between">
                        <span>Cari berdasarkan: Kode atau Nama Diagnosis</span>
                        <span class="text-gray-400 dark:text-gray-500">
                            {{ count($options) }} data ditemukan
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-lov.dropdown>
