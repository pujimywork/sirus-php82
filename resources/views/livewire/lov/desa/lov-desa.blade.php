<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Desa';
    public string $placeholder = 'Ketik kode/nama desa atau kecamatan...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim desa_id yang sudah tersimpan.
     */
    public ?string $initialDesaId = null;

    /**
     * Data propinsi dan kota dari form parent
     */
    public ?string $propinsiId = null;
    public ?string $kotaId = null;

    /**
     * Mode readonly: jika true, tombol "Ubah" akan hilang saat selected.
     */
    public bool $readonly = false;

    public function mount(): void
    {
        if (!$this->initialDesaId) {
            return;
        }

        $row = DB::table('rsmst_desas')->select('rsmst_desas.des_id', 'rsmst_desas.des_name', 'rsmst_kecamatans.kec_id', 'rsmst_kecamatans.kec_name', 'rsmst_kabupatens.kab_id', 'rsmst_kabupatens.kab_name', 'rsmst_propinsis.prop_id', 'rsmst_propinsis.prop_name')->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', 'rsmst_desas.kec_id')->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', 'rsmst_kecamatans.kab_id')->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', 'rsmst_kabupatens.prop_id')->where('rsmst_desas.des_id', $this->initialDesaId)->first();

        if ($row) {
            $this->selected = [
                'des_id' => (string) $row->des_id,
                'des_name' => (string) ($row->des_name ?? ''),
                'kec_id' => (string) ($row->kec_id ?? ''),
                'kec_name' => (string) ($row->kec_name ?? ''),
                'kab_id' => (string) ($row->kab_id ?? ''),
                'kab_name' => (string) ($row->kab_name ?? ''),
                'prop_id' => (string) ($row->prop_id ?? ''),
                'prop_name' => (string) ($row->prop_name ?? ''),

                // Untuk UI
                'label' => $row->des_name ?: '-',
                'hint' => "Kode: {$row->des_id} | Kec: {$row->kec_name} | Kab: {$row->kab_name}",
                'full_address' => "{$row->des_name}, Kec. {$row->kec_name}, {$row->kab_name}, {$row->prop_name}",
            ];
        }
    }

    public function updatedSearch(): void
    {
        // validasi propinsiId dan kotaId harus ada
        if (!$this->propinsiId || !$this->kotaId) {
            $this->closeAndResetList();
            return;
        }

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

        // ===== 1) exact match by des_id =====
        if (ctype_digit($keyword)) {
            $exactRow = DB::table('rsmst_desas')->select('rsmst_desas.des_id', 'rsmst_desas.des_name', 'rsmst_kecamatans.kec_id', 'rsmst_kecamatans.kec_name', 'rsmst_kabupatens.kab_id', 'rsmst_kabupatens.kab_name', 'rsmst_propinsis.prop_id', 'rsmst_propinsis.prop_name')->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', 'rsmst_desas.kec_id')->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', 'rsmst_kecamatans.kab_id')->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', 'rsmst_kabupatens.prop_id')->where('rsmst_kabupatens.kab_id', $this->kotaId)->where('rsmst_propinsis.prop_id', $this->propinsiId)->where('rsmst_desas.des_id', $keyword)->first();

            if ($exactRow) {
                $this->dispatchSelected([
                    'des_id' => (string) $exactRow->des_id,
                    'des_name' => (string) ($exactRow->des_name ?? ''),
                    'kec_id' => (string) ($exactRow->kec_id ?? ''),
                    'kec_name' => (string) ($exactRow->kec_name ?? ''),
                    'kab_id' => (string) ($exactRow->kab_id ?? ''),
                    'kab_name' => (string) ($exactRow->kab_name ?? ''),
                    'prop_id' => (string) ($exactRow->prop_id ?? ''),
                    'prop_name' => (string) ($exactRow->prop_name ?? ''),
                ]);
                return;
            }
        }

        // ===== 2) search by des_name or kec_name =====
        $searchTerm = str_replace(' ', '', strtoupper($keyword));

        $rows = DB::table('rsmst_desas')
            ->select('rsmst_desas.des_id', 'rsmst_desas.des_name', 'rsmst_kecamatans.kec_id', 'rsmst_kecamatans.kec_name', 'rsmst_kabupatens.kab_id', 'rsmst_kabupatens.kab_name', 'rsmst_propinsis.prop_id', 'rsmst_propinsis.prop_name')
            ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', 'rsmst_desas.kec_id')
            ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', 'rsmst_kecamatans.kab_id')
            ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', 'rsmst_kabupatens.prop_id')
            ->where('rsmst_kabupatens.kab_id', $this->kotaId)
            ->where('rsmst_propinsis.prop_id', $this->propinsiId)
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw("REPLACE(UPPER(CONCAT('ds', des_name)), ' ', '') LIKE ?", ['%' . $searchTerm . '%'])->orWhereRaw("REPLACE(UPPER(CONCAT('kec', kec_name)), ' ', '') LIKE ?", ['%' . $searchTerm . '%']);
            })
            ->orderBy('prop_name')
            ->orderBy('kab_name')
            ->orderBy('kec_name')
            ->orderBy('des_name')
            ->limit(30)
            ->get();

        $this->options = array_map(function ($row) {
            $desId = (string) $row->des_id;
            $desName = (string) ($row->des_name ?? '');
            $kecName = (string) ($row->kec_name ?? '');
            $kabName = (string) ($row->kab_name ?? '');
            $propName = (string) ($row->prop_name ?? '');

            return [
                // payload
                'des_id' => $desId,
                'des_name' => $desName,
                'kec_id' => (string) ($row->kec_id ?? ''),
                'kec_name' => $kecName,
                'kab_id' => (string) ($row->kab_id ?? ''),
                'kab_name' => $kabName,
                'prop_id' => (string) ($row->prop_id ?? ''),
                'prop_name' => $propName,

                // UI
                'label' => $desName ?: '-',
                'hint' => "Kode: {$desId}",
                'subtitle' => "Kec. {$kecName}",
                'full_address' => "{$desName}, Kec. {$kecName}, {$kabName}, {$propName}",
            ];
        }, $rows->toArray());

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
            'des_id' => $this->options[$index]['des_id'] ?? '',
            'des_name' => $this->options[$index]['des_name'] ?? '',
            'kec_id' => $this->options[$index]['kec_id'] ?? '',
            'kec_name' => $this->options[$index]['kec_name'] ?? '',
            'kab_id' => $this->options[$index]['kab_id'] ?? '',
            'kab_name' => $this->options[$index]['kab_name'] ?? '',
            'prop_id' => $this->options[$index]['prop_id'] ?? '',
            'prop_name' => $this->options[$index]['prop_name'] ?? '',
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
        // Tambahkan UI fields ke selected
        $this->selected = array_merge($payload, [
            'label' => $payload['des_name'] ?? '-',
            'hint' => "Kode: {$payload['des_id']} | Kec: {$payload['kec_name']}",
            'full_address' => isset($payload['des_name'], $payload['kec_name'], $payload['kab_name'], $payload['prop_name']) ? "{$payload['des_name']}, Kec. {$payload['kec_name']}, {$payload['kab_name']}, {$payload['prop_name']}" : $payload['des_name'] ?? '-',
        ]);

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
                @if (!$propinsiId || !$kotaId)
                    <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                        placeholder="Pilih propinsi dan kota terlebih dahulu" disabled />
                @else
                    <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                        wire:model.live.debounce.250ms="search" wire:keydown.escape.prevent="resetLov"
                        wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                        wire:keydown.enter.prevent="chooseHighlighted" />
                @endif
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['full_address'] ?? ($selected['des_name'] ?? '')" disabled />
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

        {{-- dropdown hanya saat mode cari dan tidak readonly --}}
        @if ($isOpen && $selected === null && !$readonly && $propinsiId && $kotaId)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-desa-{{ $option['des_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex flex-col">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] ?? '-' }}
                                        @if (!empty($option['subtitle']))
                                            <span class="ml-2 text-sm font-normal text-gray-600 dark:text-gray-400">
                                                ({{ $option['subtitle'] }})
                                            </span>
                                        @endif
                                    </div>

                                    @if (!empty($option['hint']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $option['hint'] }}
                                        </div>
                                    @endif

                                    @if (!empty($option['full_address']) && $option['full_address'] !== $option['label'])
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $option['full_address'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Desa tidak ditemukan di kota/kabupaten ini.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
