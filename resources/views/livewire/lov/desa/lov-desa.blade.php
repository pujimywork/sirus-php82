<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Desa';
    public string $placeholder = 'Ketik nama desa, kecamatan, atau kota...';

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
     * Optional filter propinsi dan kota (tidak wajib lagi)
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

        $row = $this->baseQuery()
            ->where('rsmst_desas.des_id', $this->initialDesaId)
            ->first();

        if ($row) {
            $this->selected = $this->mapRow($row);
        }
    }

    public function updatedSearch(): void
    {
        // kalau sudah selected, jangan cari lagi
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // minimal 3 char untuk search se-Indonesia
        if (mb_strlen($keyword) < 3) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by des_id =====
        if (ctype_digit($keyword)) {
            $query = $this->baseQuery()->where('rsmst_desas.des_id', $keyword);

            // Jika ada filter kota/propinsi, gunakan
            if ($this->kotaId) {
                $query->where('rsmst_kabupatens.kab_id', $this->kotaId);
            }
            if ($this->propinsiId) {
                $query->where('rsmst_propinsis.prop_id', $this->propinsiId);
            }

            $exactRow = $query->first();

            if ($exactRow) {
                $this->dispatchSelected($this->mapPayload($exactRow));
                return;
            }
        }

        // ===== 2) search gabungan: "ngunut tulungagung" → match setiap kata di desa+kec+kab+prop =====
        $words = preg_split('/\s+/', strtoupper($keyword));

        $query = $this->baseQuery();

        // Setiap kata harus cocok di gabungan desa+kec+kab+prop
        foreach ($words as $word) {
            $term = '%' . $word . '%';
            $query->whereRaw(
                "UPPER(rsmst_desas.des_name || ' ' || rsmst_kecamatans.kec_name || ' ' || rsmst_kabupatens.kab_name || ' ' || rsmst_propinsis.prop_name) LIKE ?",
                [$term]
            );
        }

        // Jika ada filter kota/propinsi, prioritaskan
        if ($this->kotaId) {
            $query->where('rsmst_kabupatens.kab_id', $this->kotaId);
        }
        if ($this->propinsiId) {
            $query->where('rsmst_propinsis.prop_id', $this->propinsiId);
        }

        $rows = $query
            ->orderBy('rsmst_propinsis.prop_name')
            ->orderBy('rsmst_kabupatens.kab_name')
            ->orderBy('rsmst_kecamatans.kec_name')
            ->orderBy('rsmst_desas.des_name')
            ->limit(30)
            ->get();

        $this->options = $rows->map(fn($row) => $this->mapRow($row))->toArray();

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

        $option = $this->options[$index];

        $this->dispatchSelected([
            'des_id' => $option['des_id'] ?? '',
            'des_name' => $option['des_name'] ?? '',
            'kec_id' => $option['kec_id'] ?? '',
            'kec_name' => $option['kec_name'] ?? '',
            'kab_id' => $option['kab_id'] ?? '',
            'kab_name' => $option['kab_name'] ?? '',
            'prop_id' => $option['prop_id'] ?? '',
            'prop_name' => $option['prop_name'] ?? '',
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

    protected function baseQuery()
    {
        return DB::table('rsmst_desas')
            ->select(
                'rsmst_desas.des_id', 'rsmst_desas.des_name',
                'rsmst_kecamatans.kec_id', 'rsmst_kecamatans.kec_name',
                'rsmst_kabupatens.kab_id', 'rsmst_kabupatens.kab_name',
                'rsmst_propinsis.prop_id', 'rsmst_propinsis.prop_name'
            )
            ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', 'rsmst_desas.kec_id')
            ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', 'rsmst_kecamatans.kab_id')
            ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', 'rsmst_kabupatens.prop_id');
    }

    protected function mapRow($row): array
    {
        return [
            'des_id' => (string) $row->des_id,
            'des_name' => (string) ($row->des_name ?? ''),
            'kec_id' => (string) ($row->kec_id ?? ''),
            'kec_name' => (string) ($row->kec_name ?? ''),
            'kab_id' => (string) ($row->kab_id ?? ''),
            'kab_name' => (string) ($row->kab_name ?? ''),
            'prop_id' => (string) ($row->prop_id ?? ''),
            'prop_name' => (string) ($row->prop_name ?? ''),
            'label' => $row->des_name ?: '-',
            'hint' => "Kode: {$row->des_id} | Kec. {$row->kec_name}",
            'full_address' => "{$row->des_name}, Kec. {$row->kec_name}, {$row->kab_name}, {$row->prop_name}",
        ];
    }

    protected function mapPayload($row): array
    {
        return [
            'des_id' => (string) $row->des_id,
            'des_name' => (string) ($row->des_name ?? ''),
            'kec_id' => (string) ($row->kec_id ?? ''),
            'kec_name' => (string) ($row->kec_name ?? ''),
            'kab_id' => (string) ($row->kab_id ?? ''),
            'kab_name' => (string) ($row->kab_name ?? ''),
            'prop_id' => (string) ($row->prop_id ?? ''),
            'prop_name' => (string) ($row->prop_name ?? ''),
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
        $this->selected = array_merge($payload, [
            'label' => $payload['des_name'] ?? '-',
            'hint' => "Kode: {$payload['des_id']} | Kec. {$payload['kec_name']}",
            'full_address' => "{$payload['des_name']}, Kec. {$payload['kec_name']}, {$payload['kab_name']}, {$payload['prop_name']}",
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
            {{-- Mode cari --}}
            @if (!$readonly)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                    wire:model.live.debounce.300ms="search" wire:keydown.escape.prevent="resetLov"
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

        {{-- dropdown --}}
        @if ($isOpen && $selected === null && !$readonly)
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
                                        @if (!empty($option['kec_name']))
                                            <span class="ml-2 text-sm font-normal text-gray-600 dark:text-gray-400">
                                                (Kec. {{ $option['kec_name'] }})
                                            </span>
                                        @endif
                                    </div>

                                    @if (!empty($option['full_address']))
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $option['full_address'] }}
                                        </div>
                                    @endif
                                </div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 3 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Desa tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
