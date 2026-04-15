<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\SATUSEHAT\SnomedTrait;

/**
 * LOV SNOMED CT — hybrid local + FHIR server.
 *
 * Alur pencarian:
 *   1. User ketik keyword (min 3 karakter)
 *   2. Cari di tabel lokal rsmst_snomed_codes (display_id + display_en)
 *   3. Kalau hasil lokal < 5, panggil FHIR server (tx.fhir.org) via SnomedTrait
 *   4. Hasil dari FHIR server disimpan ke tabel lokal (cache)
 *   5. Gabungkan hasil lokal + server, tampilkan di dropdown
 *
 * Pemakaian:
 *   <livewire:lov.snomed.lov-snomed
 *       target="keluhanUtama"
 *       label="Keluhan Utama (SNOMED)"
 *       valueSet="condition-code"
 *       :initialSnomedCode="$data['snomedCode'] ?? null"
 *       :disabled="$isFormLocked"
 *   />
 *
 * Event: lov.selected.{target} → payload: [snomed_code, display_en, display_id]
 */
new class extends Component {
    use SnomedTrait;

    public string $target = 'default';
    public string $label = 'Cari SNOMED CT';
    public string $placeholder = 'Ketik keluhan dalam Bahasa Indonesia / Inggris...';
    public string $valueSet = 'condition-code';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;
    public bool $isSearchingOnline = false;

    #[Reactive]
    public ?string $initialSnomedCode = null;

    public bool $disabled = false;

    public function mount(): void
    {
        $this->initializeTxFhir();
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialSnomedCode)) return;

        $row = DB::table('rsmst_snomed_codes')->where('snomed_code', $this->initialSnomedCode)->first();
        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    public function updatedInitialSnomedCode($value): void
    {
        $this->selected = null;
        $this->resetLov();

        if (empty($value)) return;

        $row = DB::table('rsmst_snomed_codes')->where('snomed_code', $value)->first();
        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'snomed_code' => (string) $row->snomed_code,
            'display_en'  => (string) ($row->display_en ?? ''),
            'display_id'  => (string) ($row->display_id ?? ''),
        ];
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) return;

        $keyword = trim($this->search);
        if (mb_strlen($keyword) < 3) {
            $this->closeAndResetList();
            return;
        }

        // ═══ Step 1: Cari di tabel lokal ═══
        $upperKeyword = mb_strtoupper($keyword);
        $localRows = DB::table('rsmst_snomed_codes')
            ->where('value_set', $this->valueSet)
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw('UPPER(display_id) LIKE ?', ["%{$upperKeyword}%"])
                  ->orWhereRaw('UPPER(display_en) LIKE ?', ["%{$upperKeyword}%"])
                  ->orWhere('snomed_code', 'LIKE', "%{$upperKeyword}%");
            })
            ->orderBy('display_en')
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

    /**
     * Cari dari FHIR server dan cache hasilnya ke DB lokal.
     */
    protected function searchOnline(string $keyword): void
    {
        try {
            $this->initializeTxFhir();
            $this->isSearchingOnline = true;

            $results = $this->searchSnomedConcepts($keyword, 10, $this->valueSet);

            $existingCodes = collect($this->options)->pluck('snomed_code')->toArray();
            $newOptions = [];

            foreach ($results as $item) {
                $code = $item['code'] ?? '';
                $display = $item['display'] ?? '';
                if (empty($code) || in_array($code, $existingCodes)) continue;

                // ═══ Step 3: Simpan ke tabel lokal (cache) ═══
                DB::table('rsmst_snomed_codes')->updateOrInsert(
                    ['snomed_code' => $code],
                    [
                        'display_en' => $display,
                        'value_set'  => $this->valueSet,
                        'created_at' => now(),
                    ]
                );

                $newOptions[] = [
                    'snomed_code' => $code,
                    'display_en'  => $display,
                    'display_id'  => '',
                    'label'       => "{$display} ({$code})",
                    'hint'        => 'FHIR server',
                    'source'      => 'online',
                ];

                $existingCodes[] = $code;
            }

            $this->options = array_merge($this->options, $newOptions);
            $this->isSearchingOnline = false;
        } catch (\Throwable $e) {
            $this->isSearchingOnline = false;
            // Gagal online, tetap pakai hasil lokal
        }
    }

    protected function mapRowToOption($row): array
    {
        $code = (string) $row->snomed_code;
        $en = (string) ($row->display_en ?? '');
        $id = (string) ($row->display_id ?? '');

        $label = !empty($id) ? "{$id} — {$en} ({$code})" : "{$en} ({$code})";
        $hint = !empty($id) ? $en : '';

        return [
            'snomed_code' => $code,
            'display_en'  => $en,
            'display_id'  => $id,
            'label'       => $label,
            'hint'        => $hint,
            'source'      => 'local',
        ];
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;

        $this->dispatchSelected([
            'snomed_code' => $this->options[$index]['snomed_code'] ?? '',
            'display_en'  => $this->options[$index]['display_en'] ?? '',
            'display_id'  => $this->options[$index]['display_id'] ?? '',
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
        $en = $this->selected['display_en'] ?? '';
        $code = $this->selected['snomed_code'] ?? '';
        if (!empty($id)) return "{$id} — {$en} ({$code})";
        return "{$en} ({$code})";
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

                {{-- Loading indicator --}}
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
                        <li wire:key="lov-snomed-{{ $option['snomed_code'] }}-{{ $index }}"
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
                                                class="ml-1 px-1 py-0.5 text-[10px] bg-blue-100 text-blue-600 rounded dark:bg-blue-900/30 dark:text-blue-300">online</span>
                                        @endif
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 3 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data SNOMED tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
