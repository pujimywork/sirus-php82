<?php
// resources/views/livewire/lov/kelas-kamar/lov-kelas-kamar.blade.php
// LOV Kelas Kamar Rawat Inap — sumber data App\Support\KelasKamar (statis, dipakai form & cetak).

use Livewire\Component;
use Livewire\Attributes\Reactive;
use App\Support\KelasKamar;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Pilih Kelas Kamar';
    public string $placeholder = 'Ketik / pilih kelas kamar...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state */
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialKelas = null;

    public bool $disabled = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        if ($this->initialKelas) {
            $this->loadSelected($this->initialKelas);
        }
    }

    public function updatedInitialKelas($value): void
    {
        $this->selected = null;
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);

        if (!empty($value)) {
            $this->loadSelected($value);
        }
    }

    protected function loadSelected(string $key): void
    {
        $info = KelasKamar::find($key);
        if ($info) {
            $this->selected = $this->toOption($key, $info);
        }
    }

    protected function toOption(string $key, array $info): array
    {
        return [
            'kelas' => $key,
            'nama' => $info['nama'] ?? $key,
            'tarif' => $info['tarif'] ?? 0,
            'tarifLabel' => $info['tarifLabel'] ?? '',
            'fasilitas' => $info['fasilitas'] ?? [],
        ];
    }

    protected function buildOptions(string $keyword = ''): void
    {
        $kw = mb_strtolower(trim($keyword));

        $this->options = collect(KelasKamar::all())
            ->map(fn($info, $key) => $this->toOption($key, $info))
            ->filter(fn($o) => $kw === '' || str_contains(mb_strtolower($o['nama']), $kw))
            ->values()
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
    }

    /* ===============================
     | SEARCH / OPEN
     =============================== */
    public function openAll(): void
    {
        if ($this->selected !== null || $this->disabled) {
            return;
        }
        $this->buildOptions('');
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }
        $this->buildOptions($this->search);
    }

    /* ===============================
     | ACTIONS
     =============================== */
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

    public function clearSelected(): void
    {
        if ($this->disabled) {
            return;
        }
        $this->selected = null;
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex - 1 + count($this->options)) % count($this->options);
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function dispatchSelected(array $payload): void
    {
        $this->selected = $payload;
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari / pilih --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                    wire:model.live.debounce.200ms="search" wire:focus="openAll"
                    wire:keydown.escape.prevent="close" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full"
                        :value="$selected['nama'] . ' — ' . $selected['tarifLabel']" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- Dropdown --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-kelas-{{ $option['kelas'] ?? $index }}" x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['nama'] }}
                                    </div>
                                    <span class="text-xs font-medium text-brand-green dark:text-brand-lime shrink-0">
                                        {{ $option['tarifLabel'] }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ implode(' • ', array_slice($option['fasilitas'], 0, 3)) }}{{ count($option['fasilitas']) > 3 ? ' • …' : '' }}
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Kelas kamar tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
