<?php

/**
 * LOV Akun Cash-In (CI) — untuk penerimaan kas lainnya.
 * Query: acmst_accounts where active_status='1' and ci_status='1'
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $target = 'default';
    public string $label = 'Akun Penerimaan';
    public string $placeholder = 'Ketik kode/nama akun...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;
    public ?string $initialAccId = null;
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialAccId) {
            return;
        }

        $row = DB::table('acmst_accounts')
            ->select('acc_id', 'acc_name')
            ->where('acc_id', $this->initialAccId)
            ->where('active_status', '1')
            ->where('ci_status', '1')
            ->first();

        if ($row) {
            $this->selected = [
                'acc_id' => (string) $row->acc_id,
                'acc_name' => (string) ($row->acc_name ?? ''),
                'label' => $row->acc_name ?: '-',
                'hint' => "Kode: {$row->acc_id}",
            ];
        }
    }

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

        $upperKeyword = strtoupper($keyword);

        $rows = DB::table('acmst_accounts')
            ->select('acc_id', 'acc_name')
            ->where('active_status', '1')
            ->where('ci_status', '1')
            ->where(function ($q) use ($upperKeyword, $keyword) {
                $q->whereRaw('UPPER(acc_name) LIKE ?', ["%{$upperKeyword}%"])
                  ->orWhere('acc_id', 'like', "%{$keyword}%");
            })
            ->orderBy('acc_id')
            ->limit(20)
            ->get();

        $this->options = $rows->map(fn($row) => [
            'acc_id' => (string) $row->acc_id,
            'acc_name' => (string) ($row->acc_name ?? ''),
            'label' => $row->acc_name ?: '-',
            'hint' => "Kode: {$row->acc_id}",
        ])->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function clearSelected(): void
    {
        if ($this->readonly) return;
        $this->selected = null;
        $this->resetLov();
    }

    public function close(): void { $this->isOpen = false; }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
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
        if ($this->selectedIndex < 0) $this->selectedIndex = count($this->options) - 1;
        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;
        $this->dispatchSelected([
            'acc_id' => $this->options[$index]['acc_id'] ?? '',
            'acc_name' => $this->options[$index]['acc_name'] ?? '',
        ]);
    }

    public function chooseHighlighted(): void { $this->choose($this->selectedIndex); }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected = array_merge($payload, [
            'label' => $payload['acc_name'] ?? '-',
            'hint' => "Kode: {$payload['acc_id']}",
        ]);

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
                    <x-text-input type="text" class="block w-full" :value="$selected['acc_name'] ?? ''" disabled />
                    @if (!empty($selected['hint']))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $selected['hint'] }}</p>
                    @endif
                </div>
                @if (!$readonly)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">Ubah</x-secondary-button>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && !$readonly)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-ci-{{ $option['acc_id'] ?? $index }}" x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $option['label'] ?? '-' }}</div>
                                    @if (!empty($option['hint']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $option['hint'] }}</div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Akun tidak ditemukan.</div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
