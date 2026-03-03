<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Akun Kas';
    public string $placeholder = 'Ketik kode/nama kas...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim acc_id yang sudah tersimpan.
     */
    #[Reactive]
    public ?string $initialAccId = null;

    /**
     * Mode disabled: tombol "Ubah" akan hilang saat selected.
     */
    public bool $disabled = false;

    public function mount(): void
    {
        if (!$this->initialAccId) {
            return;
        }

        $this->loadSelectedKas($this->initialAccId);
    }

    public function updatedInitialAccId($value): void
    {
        $this->selected = null;
        $this->search   = '';
        $this->options  = [];
        $this->isOpen   = false;

        if (empty($value)) {
            return;
        }

        $this->loadSelectedKas($value);
    }

    protected function loadSelectedKas(string $accId): void
    {
        $userCode = auth()->user()->user_code ?? null;

        $row = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name')
            ->where('b.rj', '1')
            ->where('a.acc_id', $accId)
            ->whereIn('a.acc_id', function ($q) use ($userCode) {
                $q->select('acc_id')
                    ->from('smmst_kases')
                    ->where('user_code', $userCode);
            })
            ->first();

        if ($row) {
            $this->selected = [
                'acc_id'   => (string) $row->acc_id,
                'acc_name' => (string) ($row->acc_name ?? ''),
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

        $userCode     = auth()->user()->user_code ?? null;
        $upperKeyword = mb_strtoupper($keyword);

        // ===== 1) exact match by acc_id =====
        $exactRow = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name')
            ->where('b.rj', '1')
            ->where('a.acc_id', $keyword)
            ->whereIn('a.acc_id', function ($q) use ($userCode) {
                $q->select('acc_id')->from('smmst_kases')->where('user_code', $userCode);
            })
            ->first();

        if ($exactRow) {
            $this->dispatchSelected([
                'acc_id'   => (string) $exactRow->acc_id,
                'acc_name' => (string) ($exactRow->acc_name ?? ''),
            ]);
            return;
        }

        // ===== 2) partial search =====
        $rows = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name')
            ->where('b.rj', '1')
            ->whereIn('a.acc_id', function ($q) use ($userCode) {
                $q->select('acc_id')->from('smmst_kases')->where('user_code', $userCode);
            })
            ->where(function ($q) use ($keyword, $upperKeyword) {
                $q->where('a.acc_id', 'like', "%{$keyword}%")
                  ->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('a.acc_id')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(fn($row) => [
                'acc_id'   => (string) $row->acc_id,
                'acc_name' => (string) ($row->acc_name ?? ''),
                'label'    => (string) ($row->acc_name ?: $row->acc_id),
                'hint'     => 'ID ' . $row->acc_id,
            ])
            ->toArray();

        $this->isOpen        = count($this->options) > 0;
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

        $this->dispatch('lov.selected', target: $this->target, payload: null);
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
            'acc_id'   => $this->options[$index]['acc_id']   ?? '',
            'acc_name' => $this->options[$index]['acc_name'] ?? '',
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* ── helpers ── */

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
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
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
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full"
                        :value="$selected['acc_id'] . ' — ' . $selected['acc_name']"
                        disabled />
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-kas-{{ $option['acc_id'] }}-{{ $index }}"
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