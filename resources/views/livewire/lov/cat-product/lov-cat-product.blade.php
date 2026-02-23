<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Kategori Produk';
    public string $placeholder = 'Pilih kategori produk...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim cat_id yang sudah tersimpan.
     */
    public ?string $initialCatId = null;

    /**
     * Mode readonly
     */
    public bool $readonly = false;

    /**
     * Tampilkan semua kategori tanpa search minimal 2 char?
     * true: langsung tampilkan semua saat diklik
     * false: perlu ketik minimal 2 karakter
     */
    public bool $showAllOnClick = true;

    public function mount(): void
    {
        if (!$this->initialCatId) {
            return;
        }

        $row = DB::table('immst_catproducts')
            ->select(['cat_id', 'cat_desc'])
            ->where('cat_id', $this->initialCatId)
            ->first();

        if ($row) {
            $this->selected = [
                'cat_id' => (string) $row->cat_id,
                'cat_desc' => (string) ($row->cat_desc ?? ''),
            ];
        }

        // Load semua data awal jika mode showAllOnClick
        if ($this->showAllOnClick) {
            $this->loadAllCategories();
        }
    }

    public function loadAllCategories(): void
    {
        $rows = DB::table('immst_catproducts')
            ->select(['cat_id', 'cat_desc'])
            ->orderBy('cat_id')
            ->get();

        $this->options = array_map(function ($row) {
            $catId = (string) $row->cat_id;
            $catDesc = (string) ($row->cat_desc ?? '');

            return [
                'cat_id' => $catId,
                'cat_desc' => $catDesc,
                'label' => $catDesc,
                'hint' => "ID: {$catId}",
            ];
        }, $rows->toArray());

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // Jika kosong dan mode showAllOnClick, tampilkan semua
        if (empty($keyword) && $this->showAllOnClick) {
            $this->loadAllCategories();
            return;
        }

        // Jika kurang dari 2 karakter dan bukan mode showAllOnClick, reset
        if (mb_strlen($keyword) < 2 && !$this->showAllOnClick) {
            $this->closeAndResetList();
            return;
        }

        // Exact match by cat_id
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('immst_catproducts')
                ->select(['cat_id', 'cat_desc'])
                ->where('cat_id', $keyword)
                ->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'cat_id' => (string) $exactRow->cat_id,
                    'cat_desc' => (string) ($exactRow->cat_desc ?? ''),
                ]);
                return;
            }
        }

        // Search by cat_desc
        $upperKeyword = mb_strtoupper($keyword);

        $rows = DB::table('immst_catproducts')
            ->select(['cat_id', 'cat_desc'])
            ->whereRaw("UPPER(cat_desc) LIKE '%' || ? || '%'", [$upperKeyword])
            ->orderBy('cat_id')
            ->get();

        $this->options = array_map(function ($row) {
            $catId = (string) $row->cat_id;
            $catDesc = (string) ($row->cat_desc ?? '');

            return [
                'cat_id' => $catId,
                'cat_desc' => $catDesc,
                'label' => $catDesc,
                'hint' => "ID: {$catId}",
            ];
        }, $rows->toArray());

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function openDropdown(): void
    {
        if ($this->readonly || $this->selected !== null) {
            return;
        }

        if ($this->showAllOnClick && empty($this->search)) {
            $this->loadAllCategories();
        } elseif (!empty($this->search)) {
            $this->updatedSearch();
        }
    }

    public function clearSelected(): void
    {
        if ($this->readonly) {
            return;
        }

        $this->selected = null;
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);

        // Jika mode showAllOnClick, load semua data
        if ($this->showAllOnClick) {
            $this->loadAllCategories();
        }
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);

        if ($this->showAllOnClick) {
            $this->loadAllCategories();
        }
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

        $payload = [
            'cat_id' => $this->options[$index]['cat_id'] ?? '',
            'cat_desc' => $this->options[$index]['cat_desc'] ?? '',
        ];

        $this->dispatchSelected($payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

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
            {{-- Mode cari --}}
            @if (!$readonly)
                <div class="relative">
                    <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                        wire:model.live.debounce.250ms="search" wire:keydown.escape.prevent="resetLov"
                        wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                        wire:keydown.enter.prevent="chooseHighlighted" wire:click="openDropdown" />
                    @if ($showAllOnClick && empty($search))
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    @endif
                </div>
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['cat_desc'] ?? ''" disabled />
                    <div class="mt-1 text-xs text-gray-500">
                        ID: {{ $selected['cat_id'] ?? '' }}
                    </div>
                </div>

                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown --}}
        @if ($isOpen && $selected === null && !$readonly)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-cat-{{ $option['cat_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="flex items-center justify-between">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $option['label'] ?? '-' }}
                                        </div>
                                        <div
                                            class="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded dark:bg-gray-800 dark:text-gray-400">
                                            {{ $option['cat_id'] }}
                                        </div>
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
                        Kategori tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
