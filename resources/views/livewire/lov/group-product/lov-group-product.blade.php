<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Kelompok Produk';
    public string $placeholder = 'Ketik nama/kode kelompok produk...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state */
    public ?array $selected = null;

    /** Mode edit: parent bisa kirim grp_id yang sudah tersimpan */
    public ?string $initialGrpId = null;

    /** Mode readonly */
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialGrpId) {
            return;
        }

        $row = DB::table('immst_groupproducts')
            ->select(['grp_id', 'grp_name'])
            ->where('grp_id', $this->initialGrpId)
            ->first();

        if ($row) {
            $this->selected = [
                'grp_id' => (string) $row->grp_id,
                'grp_name' => (string) ($row->grp_name ?? ''),
            ];
        }
    }

    public function updatedSearch(): void
    {
        // kalau sudah selected, jangan cari lagi
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // minimal 2 char
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by grp_id =====
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('immst_groupproducts')
                ->select(['grp_id', 'grp_name'])
                ->where('grp_id', $keyword)
                ->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'grp_id' => (string) $exactRow->grp_id,
                    'grp_name' => (string) ($exactRow->grp_name ?? ''),
                ]);
                return;
            }
        }

        // ===== 2) search by grp_name partial =====
        $upperKeyword = mb_strtoupper($keyword);

        $rows = DB::table('immst_groupproducts')
            ->select(['grp_id', 'grp_name'])
            ->where(function ($query) use ($keyword, $upperKeyword) {
                if (ctype_digit($keyword)) {
                    $query->orWhere('grp_id', 'like', "%{$keyword}%");
                }
                $query->orWhereRaw('UPPER(grp_name) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('grp_name')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(function ($row) use ($keyword) {
                $grpId = (string) $row->grp_id;
                $grpName = (string) ($row->grp_name ?? '');

                // Highlight search term in grp_name (opsional)
                $highlightedName = $grpName;
                if (!empty($keyword) && stripos($grpName, $keyword) !== false) {
                    $highlightedName = preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<mark class="bg-yellow-200 dark:bg-yellow-800">$1</mark>', $grpName);
                }

                return [
                    // payload
                    'grp_id' => $grpId,
                    'grp_name' => $grpName,

                    // UI
                    'label' => $grpName ?: '-',
                    'hint' => "ID: {$grpId}",
                    'highlighted_name' => $highlightedName,
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
        // Jika readonly, tidak bisa clear selected
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

        $payload = [
            'grp_id' => $this->options[$index]['grp_id'] ?? '',
            'grp_name' => $this->options[$index]['grp_name'] ?? '',
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
        // set selected -> UI berubah jadi nama + tombol ubah
        $this->selected = $payload;

        // bersihkan mode search
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        // emit ke parent
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
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <x-text-input type="text" class="flex-1 block w-full" :value="$selected['grp_name'] ?? ''" disabled />

                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak readonly --}}
        @if ($isOpen && $selected === null && !$readonly)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-group-{{ $option['grp_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="flex items-center justify-between">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            @if (isset($option['highlighted_name']) && $option['highlighted_name'] !== $option['grp_name'])
                                                <span>{!! $option['highlighted_name'] !!}</span>
                                            @else
                                                {{ $option['label'] ?? '-' }}
                                            @endif
                                        </div>
                                        <div
                                            class="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded dark:bg-gray-800 dark:text-gray-400">
                                            {{ $option['grp_id'] }}
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
                        Kelompok produk tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
