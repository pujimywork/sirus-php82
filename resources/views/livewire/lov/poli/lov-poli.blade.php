<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Poli';
    public string $placeholder = 'Ketik nama/kode poli...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim poli_id yang sudah tersimpan.
     * Cukup kirim initialPoliId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialPoliId = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    public function mount(): void
    {
        if (!$this->initialPoliId) {
            return;
        }

        $this->loadSelectedPoli($this->initialPoliId);
    }

    public function updatedInitialPoliId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $this->loadSelectedPoli($value);
    }

    protected function loadSelectedPoli(string $poliId): void
    {
        $row = DB::table('rsmst_polis')
            ->select(['poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status'])
            ->where('poli_id', $poliId)
            ->first();

        if ($row) {
            $this->selected = [
                'poli_id' => (string) $row->poli_id,
                'poli_desc' => (string) ($row->poli_desc ?? ''),
                'kd_poli_bpjs' => (string) ($row->kd_poli_bpjs ?? ''),
                'poli_uuid' => (string) ($row->poli_uuid ?? ''),
                'spesialis_status' => (string) ($row->spesialis_status ?? ''),
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

        // ===== 1) exact match by poli_id / poli_uuid / kd_poli_bpjs =====
        $exactQuery = DB::table('rsmst_polis')
            ->select(['poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status'])
            ->where(function ($q) use ($keyword) {
                if (ctype_digit($keyword)) {
                    $q->orWhere('poli_id', $keyword);
                }
                $q->orWhere('poli_uuid', $keyword);
                $q->orWhere('kd_poli_bpjs', $keyword);
            });

        $exactRow = $exactQuery->first();

        if ($exactRow) {
            $this->dispatchSelected([
                'poli_id' => (string) $exactRow->poli_id,
                'poli_desc' => (string) ($exactRow->poli_desc ?? ''),
                'kd_poli_bpjs' => (string) ($exactRow->kd_poli_bpjs ?? ''),
                'poli_uuid' => (string) ($exactRow->poli_uuid ?? ''),
                'spesialis_status' => (string) ($exactRow->spesialis_status ?? ''),
            ]);
            return;
        }

        // ===== 2) search by desc / kode / id partial =====
        $upperKeyword = mb_strtoupper($keyword);

        $rows = DB::table('rsmst_polis')
            ->select(['poli_id', 'poli_desc', 'kd_poli_bpjs', 'poli_uuid', 'spesialis_status'])
            ->where(function ($q) use ($keyword, $upperKeyword) {
                if (ctype_digit($keyword)) {
                    $q->orWhere('poli_id', 'like', "%{$keyword}%");
                }

                $q->orWhere('kd_poli_bpjs', 'like', "%{$keyword}%");
                $q->orWhereRaw('UPPER(poli_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('poli_desc')
            ->limit(50)
            ->get();

        $this->options = $rows
            ->map(function ($row) {
                $poliId = (string) $row->poli_id;
                $desc = (string) ($row->poli_desc ?? '');
                $bpjs = (string) ($row->kd_poli_bpjs ?? '');
                $uuid = (string) ($row->poli_uuid ?? '');
                $spes = (string) ($row->spesialis_status ?? '');

                $uuidShort = $uuid ? substr($uuid, 0, 8) . '…' : '';
                $parts = array_filter([$poliId ? "ID {$poliId}" : null, $bpjs ? "BPJS {$bpjs}" : null, $uuidShort ? "UUID {$uuidShort}" : null]);

                return [
                    // payload
                    'poli_id' => $poliId,
                    'poli_desc' => $desc,
                    'kd_poli_bpjs' => $bpjs,
                    'poli_uuid' => $uuid,
                    'spesialis_status' => $spes,

                    // UI
                    'label' => $desc ?: '-',
                    'hint' => implode(' • ', $parts),
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
        // Jika disabled, tidak bisa clear selected
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();

        // Emit ke parent bahwa selected di-clear
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

        $payload = [
            'poli_id' => $this->options[$index]['poli_id'] ?? '',
            'poli_desc' => $this->options[$index]['poli_desc'] ?? '',
            'kd_poli_bpjs' => $this->options[$index]['kd_poli_bpjs'] ?? '',
            'poli_uuid' => $this->options[$index]['poli_uuid'] ?? '',
            'spesialis_status' => $this->options[$index]['spesialis_status'] ?? '',
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
            @if (!$disabled)
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
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full" :value="$selected['poli_desc'] .
                        ($selected['kd_poli_bpjs'] ? ' (BPJS: ' . $selected['kd_poli_bpjs'] . ')' : '')" disabled />
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-poli-{{ $option['poli_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? '-' }}
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

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
