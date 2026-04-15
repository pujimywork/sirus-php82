<?php
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Grup Induk';
    public string $placeholder = 'Ketik nama grup...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public ?array $selected = null;

    #[Reactive]
    public ?string $initialClabitemId = null;

    #[Reactive]
    public ?string $clabId = null;

    public bool $disabled = false;

    /* ═══════════════════════════════════════
     | MOUNT
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        if (!$this->initialClabitemId) {
            return;
        }
        $this->loadSelected($this->initialClabitemId);
    }

    public function updatedInitialClabitemId($value): void
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

    protected function loadSelected(string $clabitemId): void
    {
        $row = DB::table('lbmst_clabitems')
            ->select('clabitem_id', 'clabitem_desc', 'price')
            ->where('clabitem_id', $clabitemId)
            ->first();

        if ($row) {
            $this->selected = [
                'clabitem_id'   => (string) $row->clabitem_id,
                'clabitem_desc' => (string) ($row->clabitem_desc ?? ''),
                'price'         => (int) ($row->price ?? 0),
            ];
        }
    }

    /* ═══════════════════════════════════════
     | SEARCH
    ═══════════════════════════════════════ */
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

        $upper = mb_strtoupper($keyword);

        $q = DB::table('lbmst_clabitems')
            ->select('clabitem_id', 'clabitem_desc', 'price')
            ->where('price', '>', 0)
            ->where(function ($sub) use ($upper) {
                $sub->whereRaw('UPPER(clabitem_desc) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(clabitem_id) LIKE ?', ["%{$upper}%"]);
            });

        if ($this->clabId) {
            $q->where('clab_id', $this->clabId);
        }

        $rows = $q->orderBy('item_seq')
            ->orderBy('clabitem_desc')
            ->limit(30)
            ->get();

        $this->options = $rows
            ->map(fn($row) => [
                'clabitem_id'   => (string) $row->clabitem_id,
                'clabitem_desc' => (string) ($row->clabitem_desc ?? ''),
                'price'         => (int) ($row->price ?? 0),
                'label'         => $row->clabitem_desc ?: '-',
                'hint'          => $row->clabitem_id . ' • Rp ' . number_format($row->price ?? 0, 0, ',', '.'),
            ])
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ═══════════════════════════════════════
     | NAVIGATE
    ═══════════════════════════════════════ */
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

        $this->dispatchSelected([
            'clabitem_id'   => $this->options[$index]['clabitem_id'] ?? '',
            'clabitem_desc' => $this->options[$index]['clabitem_desc'] ?? '',
            'price'         => $this->options[$index]['price'] ?? 0,
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* ═══════════════════════════════════════
     | CLEAR / CLOSE
    ═══════════════════════════════════════ */
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

    /* ═══════════════════════════════════════
     | HELPERS
    ═══════════════════════════════════════ */
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
    <x-input-label :value="$label" />

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
                    <x-text-input type="text" class="block w-full" :value="$selected['clabitem_id'] . ' — ' . $selected['clabitem_desc']" disabled />
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
                        <li wire:key="lov-clabgrp-{{ $option['clabitem_id'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] }}
                                </div>
                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
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
