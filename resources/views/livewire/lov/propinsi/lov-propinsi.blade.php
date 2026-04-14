<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Cari Provinsi';
    public string $placeholder = 'Ketik kode/nama provinsi...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;
    public ?string $initialPropId = null;
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialPropId) {
            return;
        }

        $row = DB::table('rsmst_propinsis')
            ->select('prop_id', 'prop_name')
            ->where('prop_id', $this->initialPropId)
            ->first();

        if ($row) {
            $this->selected = [
                'prop_id' => (string) $row->prop_id,
                'prop_name' => (string) ($row->prop_name ?? ''),
                'label' => $row->prop_name ?: '-',
                'hint' => "Kode: {$row->prop_id}",
            ];
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

        // Exact match by prop_id
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('rsmst_propinsis')
                ->select('prop_id', 'prop_name')
                ->where('prop_id', $keyword)
                ->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'prop_id' => (string) $exactRow->prop_id,
                    'prop_name' => (string) ($exactRow->prop_name ?? ''),
                ]);
                return;
            }
        }

        // Search by name
        $searchTerm = '%' . strtoupper($keyword) . '%';

        $rows = DB::table('rsmst_propinsis')
            ->select('prop_id', 'prop_name')
            ->whereRaw("UPPER(prop_name) LIKE ?", [$searchTerm])
            ->orderBy('prop_name')
            ->limit(20)
            ->get();

        $this->options = $rows->map(fn($row) => [
            'prop_id' => (string) $row->prop_id,
            'prop_name' => (string) ($row->prop_name ?? ''),
            'label' => $row->prop_name ?: '-',
            'hint' => "Kode: {$row->prop_id}",
        ])->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->selected = null;
        $this->resetLov();
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

        $this->dispatchSelected([
            'prop_id' => $this->options[$index]['prop_id'] ?? '',
            'prop_name' => $this->options[$index]['prop_name'] ?? '',
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
        $this->selected = array_merge($payload, [
            'label' => $payload['prop_name'] ?? '-',
            'hint' => "Kode: {$payload['prop_id']}",
        ]);

        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        $eventName = 'lov.selected.' . $this->target;
        $this->dispatch($eventName, target: $this->target, payload: $payload);
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
            @if (!$readonly)
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
                    <x-text-input type="text" class="block w-full" :value="$selected['prop_name'] ?? ''" disabled />
                    @if (!empty($selected['hint']))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $selected['hint'] }}
                        </p>
                    @endif
                </div>

                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && !$readonly)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-prop-{{ $option['prop_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] ?? '-' }}
                                    </div>

                                    @if (!empty($option['hint']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $option['hint'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Provinsi tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
