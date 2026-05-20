<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Tindakan / Prosedur';
    public string $placeholder = 'Ketik kode/nama tindakan...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim proc_id yang sudah tersimpan.
     * Cukup kirim initialProcedureId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialProcedureId = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    /**
     * Tampilkan info tambahan di dropdown
     */
    public bool $showAdditionalInfo = true;

    public function mount(): void
    {
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialProcedureId)) {
            return;
        }

        // Cek berdasarkan proc_id
        $row = DB::table('rsmst_mstprocedures')->where('proc_id', $this->initialProcedureId)->first();

        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'proc_id' => (string) $row->proc_id,
            'proc_desc' => (string) ($row->proc_desc ?? ''),
            'valid_code' => (int) ($row->valid_code ?? 0),
            'im' => (int) ($row->im ?? 0),
        ];
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

        // Selalu tampilkan dropdown — user pilih manual (no auto-select on exact)
        $upperKeyword = mb_strtoupper($keyword);

        // Tampilkan SEMUA code (termasuk valid_code=0).
        // Invalid akan ditandai visual + diblok di choose() dengan toast error.
        $query = DB::table('rsmst_mstprocedures')
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw('UPPER(proc_id) LIKE ?', ["%{$upperKeyword}%"])->orWhereRaw('UPPER(proc_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('proc_id')
            ->orderBy('proc_desc');

        $rows = $query->limit(50)->get();

        $this->options = $rows
            ->map(function ($row) {
                return $this->mapRowToOption($row);
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
        }
    }

    protected function mapRowToPayload($row): array
    {
        return [
            'proc_id' => (string) $row->proc_id,
            'proc_desc' => (string) ($row->proc_desc ?? ''),
            'valid_code' => (int) ($row->valid_code ?? 0),
            'im' => (int) ($row->im ?? 0),
        ];
    }

    protected function mapRowToOption($row): array
    {
        $procId = (string) $row->proc_id;
        $procDesc = (string) ($row->proc_desc ?? '');

        return [
            // payload
            'proc_id' => $procId,
            'proc_desc' => $procDesc,
            'valid_code' => (int) ($row->valid_code ?? 0),
            'im' => (int) ($row->im ?? 0),

            // UI
            'label' => $procId ? "{$procId} - {$procDesc}" : $procDesc,
            'code' => $procId,
            'description' => $procDesc,
            'hint' => "Kode: {$procId}",
        ];
    }

    public function clearSelected(): void
    {
        // Jika disabled, tidak bisa clear selected
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();

        // Dispatch event ke parent bahwa selection di-clear
        $this->dispatch('lov.cleared.' . $this->target, target: $this->target);
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
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
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

        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $opt = $this->options[$index];

        // Guard: blok code invalid (parent/category placeholder).
        if ((int) ($opt['valid_code'] ?? 0) !== 1) {
            $procId = $opt['proc_id'] ?? '?';
            $this->dispatch('toast', type: 'error', message: "Kode {$procId} tidak valid (parent/category). Pilih kode leaf/spesifik.");
            return;
        }

        $payload = [
            'proc_id' => $opt['proc_id'] ?? '',
            'proc_desc' => $opt['proc_desc'] ?? '',
            'valid_code' => (int) ($opt['valid_code'] ?? 0),
            'im' => (int) ($opt['im'] ?? 0),
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
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function updatedInitialProcedureId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $row = DB::table('rsmst_mstprocedures')->where('proc_id', $value)->first();

        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    /**
     * Get display text for selected item
     */
    public function getSelectedDisplayProperty(): string
    {
        if (!$this->selected) {
            return '';
        }

        $code = $this->selected['proc_id'] ?? '';
        $desc = $this->selected['proc_desc'] ?? '';

        return $code ? "{$code} - {$desc}" : $desc;
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
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted"
                    autocomplete="off" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full bg-gray-50 dark:bg-gray-800" :value="$this->selectedDisplay"
                        disabled />
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
                        @php
                            $isInvalid = (int) ($option['valid_code'] ?? 0) !== 1;
                            $rowClass = $isInvalid
                                ? 'bg-red-50 dark:bg-red-900/10 cursor-not-allowed'
                                : '';
                            $textClass = $isInvalid
                                ? 'text-red-700 dark:text-red-300'
                                : 'text-gray-900 dark:text-gray-100';
                        @endphp
                        <li wire:key="lov-proc-{{ $option['proc_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}" class="{{ $rowClass }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-semibold {{ $textClass }} flex-1">
                                        {{ $option['label'] ?? '-' }}
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1 shrink-0">
                                        @if (!empty($option['im']))
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-emerald-100 text-emerald-800 rounded dark:bg-emerald-900/30 dark:text-emerald-300"
                                                title="Kode spesifik iDRG/INACBG Indonesian Modification">iM</span>
                                        @endif
                                    </div>
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
                        Data prosedur/tindakan tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
