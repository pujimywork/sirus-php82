<?php
// resources/views/livewire/lov/poli/lov-poli.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** Target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Poli';
    public string $placeholder = 'Ketik kode atau nama poli...';

    /** State pencarian */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** Selected state */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim poli_id yang sudah tersimpan.
     */
    #[Reactive]
    public ?string $initialPoliId = null;

    /** Jika true, input non-aktif dan tombol Ubah disembunyikan */
    public bool $disabled = false;

    /* ============================================================
     | MOUNT & REACTIVE
     ============================================================ */
    public function mount(): void
    {
        if (!$this->initialPoliId) {
            return;
        }
        $this->loadSelected($this->initialPoliId);
    }

    public function updatedInitialPoliId(?string $value): void
    {
        $this->selected = null;
        $this->resetLov();

        if (empty($value)) {
            return;
        }
        $this->loadSelected($value);
    }

    protected function loadSelected(string $poliId): void
    {
        // Guard: rsmst_polis.poli_id bertipe NUMBER di Oracle — kalau parent
        // kirim value non-numeric (mis. partial typing / leak state), skip
        // daripada ORA-01722 invalid number.
        if (!is_numeric($poliId)) {
            return;
        }

        $row = DB::table('rsmst_polis')
            ->select('poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status')
            ->where('poli_id', $poliId)
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
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 1) {
            $this->closeAndResetList();
            return;
        }

        // 1) Exact match by poli_id — HANYA kalau keyword numeric, karena
        //    rsmst_polis.poli_id bertipe NUMBER di Oracle (huruf → ORA-01722).
        if (is_numeric($keyword)) {
            $exact = DB::table('rsmst_polis')
                ->select('poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status')
                ->where('poli_id', $keyword)
                ->first();

            if ($exact) {
                $this->dispatchSelected($this->buildPayload($exact));
                return;
            }
        }

        // 2) Partial search
        $upperKw = mb_strtoupper($keyword);

        $rows = DB::table('rsmst_polis')
            ->select('poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status')
            ->where(function ($q) use ($upperKw) {
                // TO_CHAR eksplisit pada poli_id (NUMBER) supaya aman di driver Oracle apapun.
                $q->orWhereRaw('UPPER(poli_desc) LIKE ?', ["%{$upperKw}%"])
                  ->orWhereRaw('UPPER(TO_CHAR(poli_id)) LIKE ?', ["%{$upperKw}%"]);
            })
            ->orderBy('poli_desc')
            ->limit(10)
            ->get();

        $this->options = $rows
            ->map(
                fn($r) => array_merge($this->buildPayload($r), [
                    'label' => $r->poli_desc ?? '-',
                    'hint' => 'Kode: ' . $r->poli_id,
                ]),
            )
            ->toArray();

        $this->isOpen = count($this->options) > 0;
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

    /* ============================================================
     | HELPERS
     ============================================================ */
    protected function buildPayload(object $row): array
    {
        return [
            'poli_id' => (string) ($row->poli_id ?? ''),
            'poli_desc' => (string) ($row->poli_desc ?? ''),
            'kd_poli_bpjs' => (string) ($row->kd_poli_bpjs ?? ''),
            'poli_uuid' => (string) ($row->poli_uuid ?? ''),
            'spesialis_status' => (string) ($row->spesialis_status ?? '0'),
        ];
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
        return view('livewire.lov.poli.lov-poli');
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">

    <x-input-label :value="$label" />

    <div class="relative mt-1">

        @if ($selected === null)
            {{-- ── MODE CARI ── --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- ── MODE SELECTED ── --}}
            <div class="flex items-start gap-2">

                <div class="flex-1 min-w-0">
                    <x-text-input type="text" class="block w-full" :value="$selected['poli_desc'] ?? '-'" disabled />

                    @if (!empty($selected['kd_poli_bpjs']))
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Kode BPJS: {{ $selected['kd_poli_bpjs'] }}
                        </div>
                    @endif
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected"
                        class="px-4 whitespace-nowrap shrink-0">
                        Ubah
                    </x-secondary-button>
                @endif

            </div>
        @endif

        {{-- ── DROPDOWN LIST ── --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200
                        shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-poli-{{ $option['poli_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">

                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? '-' }}
                                </div>

                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif

                                @if (!empty($option['kd_poli_bpjs']))
                                    <div class="mt-0.5 text-xs text-brand/70 dark:text-emerald-400/70">
                                        BPJS: {{ $option['kd_poli_bpjs'] }}
                                    </div>
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
