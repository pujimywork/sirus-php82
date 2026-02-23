<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Pasien';
    public string $placeholder = 'Ketik No RM / Nama / NIK / No BPJS / Alamat...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim reg_no yang sudah tersimpan.
     */

    #[Reactive]
    public ?string $initialRegNo = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    public function mount(): void
    {
        if (!$this->initialRegNo) {
            return;
        }

        $row = DB::table('rsmst_pasiens')
            ->select(['reg_no', 'reg_name', 'sex', DB::raw("TO_CHAR(birth_date, 'dd/mm/yyyy') as birth_date_formatted"), 'birth_place', 'address', 'rt', 'rw', 'phone', 'kk', 'no_kk', 'no_jkn', 'nokartu_bpjs', 'nik_bpjs', 'thn', 'bln', 'hari'])
            ->where('reg_no', $this->initialRegNo)
            ->first();

        if ($row) {
            $this->selected = $this->formatPayload($row);
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

        try {
            // ===== 1) exact match by reg_no =====
            if (ctype_digit($keyword)) {
                $exactRow = DB::table('rsmst_pasiens')
                    ->select(['reg_no', 'reg_name', 'sex', DB::raw("TO_CHAR(birth_date, 'dd/mm/yyyy') as birth_date_formatted"), 'birth_place', 'address', 'rt', 'rw', 'phone', 'kk', 'no_kk', 'no_jkn', 'nokartu_bpjs', 'nik_bpjs', 'thn', 'bln', 'hari'])
                    ->where('reg_no', $keyword)
                    ->first();

                if ($exactRow) {
                    $this->dispatchSelected($this->formatPayload($exactRow));
                    return;
                }
            }

            // ===== 2) search by name / NIK / No BPJS / No RM / Alamat =====
            $upperKeyword = mb_strtoupper($keyword);

            $rows = DB::table('rsmst_pasiens')
                ->select(['reg_no', 'reg_name', 'sex', DB::raw("TO_CHAR(birth_date, 'dd/mm/yyyy') as birth_date_formatted"), 'birth_place', 'address', 'rt', 'rw', 'phone', 'kk', 'no_kk', 'no_jkn', 'nokartu_bpjs', 'nik_bpjs', 'thn', 'bln', 'hari'])
                ->where(function ($q) use ($upperKeyword, $keyword) {
                    $q->where(DB::raw('UPPER(reg_name)'), 'LIKE', "%{$upperKeyword}%")
                        ->orWhere(DB::raw('UPPER(nik_bpjs)'), 'LIKE', "%{$upperKeyword}%")
                        ->orWhere(DB::raw('UPPER(nokartu_bpjs)'), 'LIKE', "%{$upperKeyword}%")
                        ->orWhere('reg_no', 'LIKE', "%{$keyword}%")
                        ->orWhere(DB::raw('UPPER(address)'), 'LIKE', "%{$upperKeyword}%");
                })
                ->orderBy('reg_name')
                ->limit(50)
                ->get();

            $this->options = [];
            foreach ($rows as $row) {
                $this->options[] = $this->formatPayload($row);
            }

            $this->isOpen = count($this->options) > 0;
            $this->selectedIndex = 0;

            if ($this->isOpen) {
                $this->emitScroll();
            }
        } catch (\Exception $e) {
            logger('Error in LOV Pasien: ' . $e->getMessage());
            $this->closeAndResetList();
        }
    }

    public function clearSelected(): void
    {
        if ($this->disabled) {
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

        $this->dispatchSelected($this->options[$index]);
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

    protected function formatPayload($row): array
    {
        // Format umur dengan aman
        $umur = '';
        if (!empty($row->thn) || !empty($row->bln) || !empty($row->hari)) {
            $umur = ($row->thn ?? 0) . ' Thn ' . ($row->bln ?? 0) . ' Bln ' . ($row->hari ?? 0) . ' Hr';
        }

        // Format jenis kelamin
        $sex = '-';
        if ($row->sex === 'L') {
            $sex = 'Laki-laki';
        } elseif ($row->sex === 'P') {
            $sex = 'Perempuan';
        }

        // Format NIK/No BPJS
        $identitas = [];
        if (!empty($row->nik_bpjs)) {
            $identitas[] = 'NIK: ' . $row->nik_bpjs;
        }
        if (!empty($row->nokartu_bpjs)) {
            $identitas[] = 'BPJS: ' . $row->nokartu_bpjs;
        }

        // Format alamat lengkap
        $alamat = (string) ($row->address ?? '');
        if (!empty($row->rt) || !empty($row->rw)) {
            $alamat .= ' RT ' . ($row->rt ?? '') . '/RW ' . ($row->rw ?? '');
        }

        return [
            // payload
            'reg_no' => (string) ($row->reg_no ?? ''),
            'reg_name' => (string) ($row->reg_name ?? ''),
            'sex' => (string) ($row->sex ?? ''),
            'sex_label' => $sex,
            'birth_date_formatted' => (string) ($row->birth_date_formatted ?? ''),
            'birth_place' => (string) ($row->birth_place ?? ''),
            'address' => (string) ($row->address ?? ''),
            'address_full' => $alamat,
            'rt' => (string) ($row->rt ?? ''),
            'rw' => (string) ($row->rw ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'nokartu_bpjs' => (string) ($row->nokartu_bpjs ?? ''),
            'nik_bpjs' => (string) ($row->nik_bpjs ?? ''),
            'thn' => (int) ($row->thn ?? 0),
            'bln' => (int) ($row->bln ?? 0),
            'hari' => (int) ($row->hari ?? 0),

            // UI
            'label' => ($row->reg_name ?? '-') . ' (' . ($row->reg_no ?? '-') . ')',
            'hint' => $row->birth_date_formatted ? "Tgl Lahir: {$row->birth_date_formatted} • {$sex} • No RM: {$row->reg_no}" : "No RM: {$row->reg_no} • {$sex}",
            'identitas' => implode(' • ', $identitas),
            'alamat' => $alamat,
            'umur_info' => $umur ?: '-',
        ];
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

    public function updatedInitialRegNo($value): void
    {
        // Reset state dulu
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        // Jika nilai kosong, stop di sini
        if (empty($value)) {
            return;
        }

        // ✅ PAKAI $value (parameter), BUKAN $this->initialRegNo
        $row = DB::table('rsmst_pasiens')
            ->select(['reg_no', 'reg_name', 'sex', DB::raw("TO_CHAR(birth_date, 'dd/mm/yyyy') as birth_date_formatted"), 'birth_place', 'address', 'rt', 'rw', 'phone', 'kk', 'no_kk', 'no_jkn', 'nokartu_bpjs', 'nik_bpjs', 'thn', 'bln', 'hari'])
            ->where('reg_no', $value) // ✅ Pakai $value
            ->first();

        if ($row) {
            $this->selected = $this->formatPayload($row);
        }
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
    <div class="relative mt-1.5">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full text-sm" :placeholder="$placeholder"
                    wire:model.live.debounce.300ms="search" wire:keydown.escape.prevent="resetLov"
                    wire:keydown.arrow-down.prevent="selectNext" wire:keydown.arrow-up.prevent="selectPrevious"
                    wire:keydown.enter.prevent="chooseHighlighted" />
            @else
                <x-text-input type="text"
                    class="block w-full text-sm bg-gray-100 cursor-not-allowed dark:bg-gray-800" :placeholder="$placeholder"
                    disabled />
            @endif

            {{-- Loading indicator --}}
            <div wire:loading wire:target="search" class="absolute right-3 top-2.5">
                <svg class="w-4 h-4 animate-spin text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </div>
        @else
            {{-- Mode selected --}}
            <div class="flex items-start gap-2">
                <div class="flex-1">
                    <div
                        class="px-4 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <span
                                class="font-semibold text-gray-900 dark:text-white">{{ $selected['reg_name'] ?? '' }}</span>
                            <span class="font-mono text-sm text-brand dark:text-emerald-400">
                                {{ $selected['reg_no'] ?? '' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-2 text-sm">
                            @if (!empty($selected['sex_label']))
                                <div>
                                    <span class="text-gray-500">Jenis Kelamin:</span>
                                    <span
                                        class="ml-1 text-gray-700 dark:text-gray-300">{{ $selected['sex_label'] }}</span>
                                </div>
                            @endif

                            @if (!empty($selected['umur_info']) && $selected['umur_info'] != '-')
                                <div>
                                    <span class="text-gray-500">Umur:</span>
                                    <span
                                        class="ml-1 text-gray-700 dark:text-gray-300">{{ $selected['umur_info'] }}</span>
                                </div>
                            @endif

                            @if (!empty($selected['birth_date_formatted']))
                                <div class="col-span-2">
                                    <span class="text-gray-500">Tgl Lahir:</span>
                                    <span class="ml-1 text-gray-700 dark:text-gray-300">
                                        {{ $selected['birth_place'] ? $selected['birth_place'] . ', ' : '' }}{{ $selected['birth_date_formatted'] }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        @if (!empty($selected['address_full']))
                            <div class="flex items-start gap-1 mt-2 text-sm">
                                <span class="text-gray-500">📍</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $selected['address_full'] }}</span>
                            </div>
                        @endif

                        @if (!empty($selected['phone']))
                            <div class="flex items-start gap-1 mt-1 text-sm">
                                <span class="text-gray-500">📞</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $selected['phone'] }}</span>
                            </div>
                        @endif

                        @if (!empty($selected['identitas']))
                            <div class="flex items-start gap-1 mt-1 text-sm">
                                <span class="text-gray-500">🆔</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $selected['identitas'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected"
                        class="px-4 py-3 text-sm whitespace-nowrap">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        Ganti
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-96 dark:divide-gray-800">
                    @forelse ($options as $index => $option)
                        <li wire:key="lov-pasien-{{ $option['reg_no'] }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex"
                                class="px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <div class="space-y-2">
                                    {{-- Header: Nama dan No RM --}}
                                    <div class="flex items-center justify-between">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ $option['reg_name'] ?? '-' }}
                                        </div>
                                        <div
                                            class="px-2 py-0.5 text-sm font-mono bg-brand/10 text-brand rounded-full dark:bg-brand/20">
                                            {{ $option['reg_no'] ?? '-' }}
                                        </div>
                                    </div>

                                    {{-- Info Demografi --}}
                                    <div
                                        class="flex flex-wrap items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            {{ $option['sex_label'] ?? '-' }}
                                        </span>

                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ $option['umur_info'] ?? '-' }}
                                        </span>

                                        @if (!empty($option['birth_date_formatted']))
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                {{ $option['birth_date_formatted'] }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Alamat --}}
                                    @if (!empty($option['alamat']))
                                        <div class="flex items-start gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span>{{ $option['alamat'] }}</span>
                                        </div>
                                    @endif

                                    {{-- Kontak & Identitas --}}
                                    <div class="flex flex-wrap gap-3 text-sm">
                                        @if (!empty($option['phone']))
                                            <span
                                                class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                                {{ $option['phone'] }}
                                            </span>
                                        @endif

                                        @if (!empty($option['nik_bpjs']))
                                            <span
                                                class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                                </svg>
                                                NIK: {{ $option['nik_bpjs'] }}
                                            </span>
                                        @endif

                                        @if (!empty($option['nokartu_bpjs']) && empty($option['nik_bpjs']))
                                            <span
                                                class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                BPJS: {{ $option['nokartu_bpjs'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </x-lov.item>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 mb-3 text-gray-300 dark:text-gray-600" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <p class="text-sm">Pasien tidak ditemukan</p>
                                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                                    Coba dengan kata kunci lain
                                </p>
                            </div>
                        </li>
                    @endforelse
                </ul>

                {{-- Footer info --}}
                <div
                    class="px-4 py-2 text-sm text-gray-500 border-t border-gray-100 bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                    <div class="flex items-center justify-between">
                        <span>🔍 Cari berdasarkan: Nama, No RM, NIK, No BPJS, atau Alamat</span>
                        <span class="text-gray-400 dark:text-gray-500">
                            {{ count($options) }} data ditemukan
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-lov.dropdown>
