<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana (mis. "from", "to") */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Lokasi';
    public string $placeholder = 'Ketik kode / nama lokasi...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /** Mode edit: parent bisa kirim sl_code yang sudah tersimpan. */
    public ?string $initialSlCode = null;

    /** Mode readonly: jika true, tombol "Ubah" akan hilang saat selected. */
    public bool $readonly = false;

    /**
     * Filter jenis lokasi
     * 'all', 'medis', 'nonmedis'
     */
    public string $jenisLokasi = 'all';

    /**
     * Jika true, hanya tampilkan lokasi dengan stock_status='1'
     * (lokasi yang mengelola saldo stok — sumber/tujuan transfer).
     */
    public bool $onlyStockAktif = false;

    /**
     * Daftar sl_code yang harus dikecualikan dari hasil pencarian.
     * Berguna untuk modul transfer agar tujuan ≠ sumber.
     */
    public array $excludeSlCode = [];

    public function mount(): void
    {
        if (!$this->initialSlCode) {
            return;
        }

        $row = DB::table('immst_stocklocations')
            ->select(['sl_code', 'sl_name', 'stock_status', 'active_status', 'medis', 'nonmedis'])
            ->where('sl_code', $this->initialSlCode)
            ->first();

        if ($row) {
            $this->selected = $this->mapPayload($row);
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

        $upperKeyword = mb_strtoupper($keyword);

        // ===== 1) exact match by sl_code =====
        $exactRow = DB::table('immst_stocklocations')
            ->select(['sl_code', 'sl_name', 'stock_status', 'active_status', 'medis', 'nonmedis'])
            ->where('active_status', '1')
            ->whereRaw('UPPER(sl_code) = ?', [$upperKeyword])
            ->when(!empty($this->excludeSlCode), fn($q) => $q->whereNotIn('sl_code', $this->excludeSlCode))
            ->first();

        if ($exactRow && $this->passesFilter($exactRow)) {
            $this->dispatchSelected($this->mapPayload($exactRow));
            return;
        }

        // ===== 2) search by sl_code / sl_name partial =====
        $query = DB::table('immst_stocklocations')
            ->select(['sl_code', 'sl_name', 'stock_status', 'active_status', 'medis', 'nonmedis'])
            ->where('active_status', '1')
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw("UPPER(sl_code) LIKE '%' || ? || '%'", [$upperKeyword])
                    ->orWhereRaw("UPPER(sl_name) LIKE '%' || ? || '%'", [$upperKeyword]);
            });

        if ($this->jenisLokasi === 'medis') {
            $query->where('medis', '1');
        } elseif ($this->jenisLokasi === 'nonmedis') {
            $query->where('nonmedis', '1');
        }

        if ($this->onlyStockAktif) {
            $query->where('stock_status', '1');
        }

        if (!empty($this->excludeSlCode)) {
            $query->whereNotIn('sl_code', $this->excludeSlCode);
        }

        $rows = $query->orderBy('sl_code')->limit(50)->get();

        $this->options = array_map(function ($row) {
            $payload = $this->mapPayload($row);

            $jenis = '';
            if ($payload['medis'] === '1' && $payload['nonmedis'] === '1') {
                $jenis = 'Medis & Non-Medis';
            } elseif ($payload['medis'] === '1') {
                $jenis = 'Medis';
            } elseif ($payload['nonmedis'] === '1') {
                $jenis = 'Non-Medis';
            }

            return [
                ...$payload,
                'label' => $payload['sl_name'] ?: '-',
                'hint' => "Kode: {$payload['sl_code']}",
                'jenis' => $jenis,
                'stockAktif' => $payload['stock_status'] === '1',
            ];
        }, $rows->toArray());

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function updatedJenisLokasi(): void
    {
        if ($this->search !== '') {
            $this->updatedSearch();
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

        $opt = $this->options[$index];
        $this->dispatchSelected([
            'sl_code' => (string) ($opt['sl_code'] ?? ''),
            'sl_name' => (string) ($opt['sl_name'] ?? ''),
            'stock_status' => (string) ($opt['stock_status'] ?? '0'),
            'active_status' => (string) ($opt['active_status'] ?? '0'),
            'medis' => (string) ($opt['medis'] ?? '0'),
            'nonmedis' => (string) ($opt['nonmedis'] ?? '0'),
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

    protected function mapPayload($row): array
    {
        return [
            'sl_code' => (string) $row->sl_code,
            'sl_name' => (string) ($row->sl_name ?? ''),
            'stock_status' => (string) ($row->stock_status ?? '0'),
            'active_status' => (string) ($row->active_status ?? '0'),
            'medis' => (string) ($row->medis ?? '0'),
            'nonmedis' => (string) ($row->nonmedis ?? '0'),
        ];
    }

    protected function passesFilter($row): bool
    {
        if ($this->jenisLokasi === 'medis' && (string) ($row->medis ?? '0') !== '1') {
            return false;
        }
        if ($this->jenisLokasi === 'nonmedis' && (string) ($row->nonmedis ?? '0') !== '1') {
            return false;
        }
        if ($this->onlyStockAktif && (string) ($row->stock_status ?? '0') !== '1') {
            return false;
        }
        return true;
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

    {{-- Filter jenis lokasi --}}
    @if ($selected === null && !$readonly)
        <div class="grid grid-cols-3 gap-2 mt-1 mb-2">
            <x-radio-button label="Semua" value="all" name="jenisLokasi" wire:model.live="jenisLokasi" />
            <x-radio-button label="Medis" value="medis" name="jenisLokasi" wire:model.live="jenisLokasi" />
            <x-radio-button label="Non-Medis" value="nonmedis" name="jenisLokasi" wire:model.live="jenisLokasi" />
        </div>
    @endif

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$readonly)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                    wire:model.live.debounce.250ms="search" wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
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
                        :value="($selected['sl_code'] ?? '') . ' — ' . ($selected['sl_name'] ?? '')" disabled />
                </div>

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
                        <li wire:key="lov-stocklocation-{{ $option['sl_code'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $option['label'] ?? '-' }}
                                        </div>
                                        <div class="flex flex-wrap gap-1 shrink-0">
                                            @if (!empty($option['jenis']))
                                                <span
                                                    class="px-2 py-0.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-300">
                                                    {{ $option['jenis'] }}
                                                </span>
                                            @endif
                                            @if (!empty($option['stockAktif']))
                                                <span
                                                    class="px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-300">
                                                    Stok Aktif
                                                </span>
                                            @endif
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

                @if (mb_strlen(trim($search)) >= 1 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Lokasi tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
