<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Diagnosa (ICD 10)';
    public string $placeholder = 'Ketik kode/nama diagnosa...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim diag_id yang sudah tersimpan.
     * Cukup kirim initialDiagnosaId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialDiagnosaId = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    /**
     * Tampilkan info tambahan di dropdown
     */
    public bool $showAdditionalInfo = true;

    /**
     * Kalau true, filter hanya code yang accpdx='Y' (boleh jadi diagnosa primer).
     * Pakai untuk LOV diagnosa primer (DXP) di iDRG/INACBG flow.
     */
    public bool $primaryOnly = false;

    public function mount(): void
    {
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialDiagnosaId)) {
            return;
        }

        // Cek berdasarkan diag_id terlebih dahulu
        $row = DB::table('rsmst_mstdiags')->where('diag_id', $this->initialDiagnosaId)->first();

        // Jika tidak ditemukan, cek berdasarkan icdx
        if (!$row) {
            $row = DB::table('rsmst_mstdiags')->where('icdx', $this->initialDiagnosaId)->first();
        }
        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'diag_id' => (string) $row->diag_id,
            'diag_desc' => (string) ($row->diag_desc ?? ''),
            'icdx' => (string) ($row->icdx ?? ''),
            'valid_code' => (int) ($row->valid_code ?? 0),
            'accpdx' => (string) ($row->accpdx ?? 'N'),
            'asterisk' => (int) ($row->asterisk ?? 0),
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

        // Tampilkan SEMUA code (termasuk valid_code=0 / accpdx='N').
        // Code invalid akan ditandai visual + diblok di choose() dengan toast error.
        $query = DB::table('rsmst_mstdiags')
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw('UPPER(diag_id) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(icdx) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(diag_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('icdx')
            ->orderBy('diag_desc');

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
            'diag_id' => (string) $row->diag_id,
            'diag_desc' => (string) ($row->diag_desc ?? ''),
            'icdx' => (string) ($row->icdx ?? ''),
            'valid_code' => (int) ($row->valid_code ?? 0),
            'accpdx' => (string) ($row->accpdx ?? 'N'),
            'asterisk' => (int) ($row->asterisk ?? 0),
            'im' => (int) ($row->im ?? 0),
        ];
    }

    protected function mapRowToOption($row): array
    {
        $diagId = (string) $row->diag_id;
        $icdx = (string) ($row->icdx ?? '');
        $diagDesc = (string) ($row->diag_desc ?? '');

        $displayCode = $icdx ?: $diagId;
        $displayText = $diagDesc ?: '-';

        return [
            // payload
            'diag_id' => $diagId,
            'diag_desc' => $diagDesc,
            'icdx' => $icdx,
            'valid_code' => (int) ($row->valid_code ?? 0),
            'accpdx' => (string) ($row->accpdx ?? 'N'),
            'asterisk' => (int) ($row->asterisk ?? 0),
            'im' => (int) ($row->im ?? 0),

            // UI
            'label' => $displayCode ? "{$displayCode} - {$displayText}" : $displayText,
            'code' => $displayCode,
            'description' => $diagDesc,
            'hint' => "Kode: {$displayCode}",
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
        $code = $opt['icdx'] ?: $opt['diag_id'];

        // Guard 1: blok code invalid (parent/category placeholder).
        if ((int) ($opt['valid_code'] ?? 0) !== 1) {
            $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak valid (parent/category). Pilih kode leaf/spesifik.");
            return;
        }

        // Guard 2: kalau LOV ini untuk diagnosa primer, blok kode dgn accpdx='N'.
        if ($this->primaryOnly && ($opt['accpdx'] ?? 'N') !== 'Y') {
            $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak boleh dipakai sebagai diagnosa primer (accpdx='N').");
            return;
        }

        $payload = [
            'diag_id' => $opt['diag_id'] ?? '',
            'diag_desc' => $opt['diag_desc'] ?? '',
            'icdx' => $opt['icdx'] ?? '',
            'valid_code' => (int) ($opt['valid_code'] ?? 0),
            'accpdx' => (string) ($opt['accpdx'] ?? 'N'),
            'asterisk' => (int) ($opt['asterisk'] ?? 0),
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

    public function updatedInitialDiagnosaId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $row = DB::table('rsmst_mstdiags')->where('diag_id', $value)->first()
            ?? DB::table('rsmst_mstdiags')->where('icdx', $value)->first();

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

        $code = $this->selected['icdx'] ?: $this->selected['diag_id'];
        $desc = $this->selected['diag_desc'] ?? '';

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
                            $isBlockedPrimary = $primaryOnly && ($option['accpdx'] ?? 'N') !== 'Y';
                            $isBlocked = $isInvalid || $isBlockedPrimary;
                            $rowClass = $isBlocked
                                ? 'bg-red-50 dark:bg-red-900/10 opacity-60 cursor-not-allowed'
                                : '';
                            $textClass = $isBlocked
                                ? 'text-red-700 dark:text-red-300 line-through decoration-red-400/60'
                                : 'text-gray-900 dark:text-gray-100';
                        @endphp
                        <li wire:key="lov-diag-{{ $option['diag_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}" class="{{ $rowClass }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-semibold {{ $textClass }} flex-1">
                                        {{ $option['label'] ?? '-' }}
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1 shrink-0">
                                        @if ($isInvalid)
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-red-200 text-red-900 rounded dark:bg-red-900/40 dark:text-red-200"
                                                title="Kode tidak valid (parent/category), tidak bisa dipilih">INVALID</span>
                                        @endif
                                        @if (($option['accpdx'] ?? 'N') === 'N' && !$isInvalid)
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-amber-100 text-amber-800 rounded dark:bg-amber-900/30 dark:text-amber-300"
                                                title="Tidak boleh sebagai diagnosa primer">!PDX</span>
                                        @endif
                                        @if (!empty($option['asterisk']))
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-purple-100 text-purple-800 rounded dark:bg-purple-900/30 dark:text-purple-300"
                                                title="Kode asterisk — wajib pair dengan etiologi (dagger)">★</span>
                                        @endif
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
                        Data diagnosa tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
