<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\SATUSEHAT\LoincTrait;

/**
 * LOV LOINC — hybrid local + FHIR server.
 *
 * Alur pencarian:
 *   1. User ketik keyword (min 2 karakter)
 *   2. Cari di tabel lokal rsmst_loinc_codes (display + display_id)
 *   3. Kalau hasil lokal < 5, panggil FHIR server (tx.fhir.org) via LoincTrait
 *   4. Hasil dari FHIR server disimpan ke tabel lokal (cache)
 *   5. Gabungkan hasil lokal + server, tampilkan di dropdown
 *
 * Pemakaian:
 *   <livewire:lov.loinc.lov-loinc
 *       target="loincLab"
 *       label="Kode LOINC (Satu Sehat)"
 *       :initialLoincCode="$data['loinc_code'] ?? null"
 *       :disabled="false"
 *   />
 *
 * Event: lov.selected.{target} → payload: [loinc_code, display, display_id]
 */
new class extends Component {
    use LoincTrait;

    public string $target = 'default';
    public string $label = 'Kode LOINC';
    public string $placeholder = 'Ketik nama pemeriksaan / kode LOINC...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;
    public bool $isSearchingOnline = false;

    #[Reactive]
    public ?string $initialLoincCode = null;

    public bool $disabled = false;

    public function mount(): void
    {
        $this->initializeLoincFhir();
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialLoincCode)) return;

        $row = DB::table('rsmst_loinc_codes')->where('loinc_code', $this->initialLoincCode)->first();
        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    public function updatedInitialLoincCode($value): void
    {
        $this->selected = null;
        $this->resetLov();

        if (empty($value)) return;

        $row = DB::table('rsmst_loinc_codes')->where('loinc_code', $value)->first();
        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'loinc_code' => (string) $row->loinc_code,
            'display'    => (string) ($row->display ?? ''),
            'display_id' => (string) ($row->display_id ?? ''),
        ];
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;

        $keyword = trim($this->search);
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ═══ Step 1: Cari di tabel lokal ═══
        $upper = mb_strtoupper($keyword);
        $localRows = DB::table('rsmst_loinc_codes')
            ->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(display) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(display_id) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(component) LIKE ?', ["%{$upper}%"])
                  ->orWhere('loinc_code', 'LIKE', "%{$upper}%");
            })
            ->orderBy('display_id')
            ->orderBy('display')
            ->limit(20)
            ->get();

        $this->options = $localRows->map(fn($row) => $this->mapRowToOption($row))->toArray();

        // ═══ Step 2: Kalau lokal kurang, cari online ═══
        if ($localRows->count() < 5) {
            $this->searchOnline($keyword);
        }

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->dispatch('lov-scroll', id: $this->getId(), index: 0);
        }
    }

    protected function searchOnline(string $keyword): void
    {
        try {
            $this->initializeLoincFhir();
            $this->isSearchingOnline = true;

            $results = $this->searchLoincConcepts($keyword, 10);

            $existingCodes = collect($this->options)->pluck('loinc_code')->toArray();

            foreach ($results as $item) {
                $code = $item['code'] ?? '';
                $display = $item['display'] ?? '';
                $system = $item['system'] ?? '';

                // Hanya ambil yang dari loinc.org
                if (empty($code) || !str_contains($system, 'loinc.org') || in_array($code, $existingCodes)) continue;

                // Cache ke DB lokal
                DB::table('rsmst_loinc_codes')->updateOrInsert(
                    ['loinc_code' => $code],
                    [
                        'display'    => $display,
                        'created_at' => now(),
                    ]
                );

                $this->options[] = [
                    'loinc_code' => $code,
                    'display'    => $display,
                    'display_id' => '',
                    'label'      => "{$display} ({$code})",
                    'hint'       => 'FHIR server',
                    'source'     => 'online',
                ];

                $existingCodes[] = $code;
            }

            $this->isSearchingOnline = false;
        } catch (\Throwable $e) {
            $this->isSearchingOnline = false;
        }
    }

    protected function mapRowToOption($row): array
    {
        $code = (string) $row->loinc_code;
        $display = (string) ($row->display ?? '');
        $displayId = (string) ($row->display_id ?? '');

        $label = !empty($displayId) ? "{$displayId} — {$display} ({$code})" : "{$display} ({$code})";
        $hint = !empty($displayId) ? $display : '';

        return [
            'loinc_code' => $code,
            'display'    => $display,
            'display_id' => $displayId,
            'label'      => $label,
            'hint'       => $hint,
            'source'     => 'local',
        ];
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;

        $this->dispatchSelected([
            'loinc_code' => $this->options[$index]['loinc_code'] ?? '',
            'display'    => $this->options[$index]['display'] ?? '',
            'display_id' => $this->options[$index]['display_id'] ?? '',
        ]);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    public function clearSelected(): void
    {
        if ($this->disabled) return;
        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.cleared.' . $this->target, target: $this->target);
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
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;
        $this->selectedIndex = ($this->selectedIndex - 1 + count($this->options)) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
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
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function getSelectedDisplayProperty(): string
    {
        if (!$this->selected) return '';
        $id = $this->selected['display_id'] ?? '';
        $display = $this->selected['display'] ?? '';
        $code = $this->selected['loinc_code'] ?? '';
        if (!empty($id)) return "{$id} — {$display} ({$code})";
        return "{$display} ({$code})";
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder"
                    wire:model.live.debounce.400ms="search" wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                    wire:keydown.enter.prevent="chooseHighlighted" autocomplete="off" />

                <div wire:loading wire:target="updatedSearch"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <x-loading />
                </div>
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full bg-gray-50 dark:bg-gray-800"
                        :value="$this->selectedDisplay" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-loinc-{{ $option['loinc_code'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? '-' }}
                                </div>
                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                        @if (($option['source'] ?? '') === 'online')
                                            <span
                                                class="ml-1 px-1 py-0.5 text-[10px] bg-teal-100 text-teal-600 rounded dark:bg-teal-900/30 dark:text-teal-300">online</span>
                                        @endif
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data LOINC tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
