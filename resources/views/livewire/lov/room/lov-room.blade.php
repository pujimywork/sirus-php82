<?php
// resources/views/livewire/lov/room/lov-room.blade.php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Ruangan';
    public string $placeholder = 'Ketik nama ruangan / nomor bed...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state */
    public ?array $selected = null;

    #[Reactive]
    public ?string $initialRoomId = null;

    public bool $disabled = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        if (!$this->initialRoomId) {
            return;
        }
        $this->loadSelectedRoom($this->initialRoomId);
    }

    public function updatedInitialRoomId($value): void
    {
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }
        $this->loadSelectedRoom($value);
    }

    protected function loadSelectedRoom(string $roomId): void
    {
        // Hanya filter by room_id. Tidak cek rsview_roominapes (pasien yang di-edit
        // kamarnya pasti terisi) maupun bed_no dari parent (bed bisa dihapus/diganti di master);
        // bed diambil apa adanya dari DB via ROWNUM = 1.
        $row = DB::selectOne(
            "SELECT r.room_name, r.room_id, b.bed_no,
                    r.class_id, c.class_desc,
                    r.room_price, r.perawatan_price
             FROM   rsmst_rooms r
             JOIN   rsmst_beds  b ON b.room_id = r.room_id
             JOIN   rsmst_class c ON c.class_id = r.class_id
             WHERE  r.room_id = :room_id
             AND    ROWNUM = 1",
            ['room_id' => $roomId],
        );

        if ($row) {
            $this->selected = [
                'room_id' => (string) $row->room_id,
                'room_name' => (string) ($row->room_name ?? ''),
                'bed_no' => (string) ($row->bed_no ?? ''),
                'class_id' => (string) ($row->class_id ?? ''),
                'class_desc' => (string) ($row->class_desc ?? ''),
                'room_price' => (int) ($row->room_price ?? 0),
                'perawatan_price' => (int) ($row->perawatan_price ?? 0),
            ];
        }
    }

    /* ===============================
     | SEARCH
     =============================== */
    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        $upper = mb_strtoupper($keyword);

        $rows = DB::select(
            "SELECT *
             FROM (
                SELECT r.room_name, r.room_id, b.bed_no,
                       r.class_id, c.class_desc,
                       r.room_price, r.perawatan_price
                FROM   rsmst_rooms r
                JOIN   rsmst_beds  b ON b.room_id = r.room_id
                JOIN   rsmst_class c ON c.class_id = r.class_id
                WHERE  r.active_status = '1'
                AND    r.room_id || b.bed_no NOT IN (
                           SELECT room_code FROM rsview_roominapes
                       )
                AND   (UPPER(r.room_name) LIKE :kw1
                    OR UPPER(b.bed_no)    LIKE :kw2
                    OR UPPER(r.room_id)   LIKE :kw3
                    OR UPPER(c.class_desc) LIKE :kw4)
                ORDER  BY r.class_id, r.room_name, b.bed_no
             )
             WHERE ROWNUM <= 50",
            ['kw1' => "%{$upper}%", 'kw2' => "%{$upper}%", 'kw3' => "%{$upper}%", 'kw4' => "%{$upper}%"],
        );

        $this->options = collect($rows)
            ->map(function ($row) {
                $roomId = (string) $row->room_id;
                $roomName = (string) ($row->room_name ?? '');
                $bedNo = (string) ($row->bed_no ?? '');
                $classId = (string) ($row->class_id ?? '');
                $classDesc = (string) ($row->class_desc ?? '');
                $roomPrice = (int) ($row->room_price ?? 0);
                $perawatanPrice = (int) ($row->perawatan_price ?? 0);

                $hints = array_filter(["ID {$roomId}", $bedNo ? "Bed {$bedNo}" : null, $classDesc ? $classDesc : null, $roomPrice ? 'Rp ' . number_format($roomPrice, 0, ',', '.') : null]);

                return [
                    'room_id' => $roomId,
                    'room_name' => $roomName,
                    'bed_no' => $bedNo,
                    'class_id' => $classId,
                    'class_desc' => $classDesc,
                    'room_price' => $roomPrice,
                    'perawatan_price' => $perawatanPrice,
                    'label' => $roomName ?: '-',
                    'hint' => implode(' • ', $hints),
                ];
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    /* ===============================
     | ACTIONS
     =============================== */
    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $this->dispatchSelected([
            'room_id' => $this->options[$index]['room_id'] ?? '',
            'room_name' => $this->options[$index]['room_name'] ?? '',
            'bed_no' => $this->options[$index]['bed_no'] ?? '',
            'class_id' => $this->options[$index]['class_id'] ?? '',
            'class_desc' => $this->options[$index]['class_desc'] ?? '',
            'room_price' => $this->options[$index]['room_price'] ?? 0,
            'perawatan_price' => $this->options[$index]['perawatan_price'] ?? 0,
        ]);
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
        $this->resetLov();
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: null);
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
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || !count($this->options)) {
            return;
        }
        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }
        $this->emitScroll();
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function dispatchSelected(array $payload): void
    {
        $this->selected = $payload;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
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
                    <x-text-input type="text" class="block w-full" :value="$selected['room_name'] . ($selected['bed_no'] ? ' — Bed ' . $selected['bed_no'] : '')" disabled />
                </div>
                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
            {{-- Info tambahan saat selected --}}
            <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                @if (!empty($selected['class_desc']))
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        {{ $selected['class_desc'] }}
                    </span>
                @endif
                @if (!empty($selected['room_price']))
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Rp {{ number_format($selected['room_price'], 0, ',', '.') }}/hari
                    </span>
                @endif
            </div>
        @endif

        {{-- Dropdown --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-room-{{ $option['room_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['room_name'] ?? '-' }}
                                        @if (!empty($option['bed_no']))
                                            <span class="ml-1 text-xs font-normal text-gray-500">Bed
                                                {{ $option['bed_no'] }}</span>
                                        @endif
                                    </div>
                                    @if (!empty($option['room_price']))
                                        <span
                                            class="text-xs font-medium text-brand-green dark:text-brand-lime shrink-0">
                                            Rp {{ number_format($option['room_price'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                                @if (!empty($option['class_desc']) || !empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ implode(' • ', array_filter([$option['class_desc'] ?? null, $option['hint'] ?? null])) }}
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Ruangan tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
