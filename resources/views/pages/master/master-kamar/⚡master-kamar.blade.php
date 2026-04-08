<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* ─── Filter Bangsal ──────────────────────────────────────── */
    public string $searchBangsal = '';
    public int $itemsPerPage = 10;

    /* ─── Pilihan bangsal → tampil kamar ──────────────────────── */
    public ?string $selectedBangsalId = null;
    public string $selectedBangsalName = '';

    /* ─── Filter Kamar ────────────────────────────────────────── */
    public string $searchKamar = '';
    public int $itemsPerPageKamar = 10;

    /* ─── Expand bed per kamar ────────────────────────────────── */
    public array $expandedRooms = [];
    public array $bedsCache = [];

    public function updatedSearchBangsal(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }
    public function updatedSearchKamar(): void
    {
        $this->resetPage('pageKamar');
    }
    public function updatedItemsPerPageKamar(): void
    {
        $this->resetPage('pageKamar');
    }

    /* ─── Dispatch ke actions ─────────────────────────────────── */
    public function openCreateBangsal(): void
    {
        $this->dispatch('master.kamar.openCreateBangsal');
    }
    public function openEditBangsal(string $id): void
    {
        $this->dispatch('master.kamar.openEditBangsal', bangsalId: $id);
    }
    public function requestDeleteBangsal(string $id): void
    {
        $this->dispatch('master.kamar.deleteBangsal', bangsalId: $id);
    }

    public function openCreateKamar(): void
    {
        if (!$this->selectedBangsalId) {
            return;
        }
        $this->dispatch('master.kamar.openCreateKamar', bangsalId: $this->selectedBangsalId);
    }
    public function openEditKamar(string $id): void
    {
        $this->dispatch('master.kamar.openEditKamar', roomId: $id);
    }
    public function requestDeleteKamar(string $id): void
    {
        $this->dispatch('master.kamar.deleteKamar', roomId: $id);
    }

    public function openCreateBed(string $roomId): void
    {
        $this->dispatch('master.kamar.openCreateBed', roomId: $roomId);
    }
    public function openEditBed(string $bedNo, string $roomId): void
    {
        $this->dispatch('master.kamar.openEditBed', bedNo: $bedNo, roomId: $roomId);
    }
    public function requestDeleteBed(string $bedNo, string $roomId): void
    {
        $this->dispatch('master.kamar.deleteBed', bedNo: $bedNo, roomId: $roomId);
    }

    /* ─── Refresh setelah save/delete ────────────────────────── */
    #[On('master.kamar.saved')]
    public function afterSaved(string $entity, string $roomId = ''): void
    {
        if ($entity === 'bangsal') {
            $this->resetPage();
        }
        if ($entity === 'kamar') {
            unset($this->computedPropertyCache); // reset computed
            $this->resetPage('pageKamar');
        }
        if ($entity === 'bed' && $roomId) {
            // Hapus cache bed room ini supaya re-load saat expand
            unset($this->bedsCache[$roomId]);
            $this->expandedRooms = array_values(array_filter($this->expandedRooms, fn($id) => $id !== $roomId));
        }
    }

    /* ─── Query Bangsal ───────────────────────────────────────── */
    #[Computed]
    public function bangsals()
    {
        $q = DB::table(DB::raw('rsmst_bangsals b'))
            ->selectRaw(
                "
                b.bangsal_id,
                b.bangsal_name,
                b.sl_codefrom,
                b.bangsal_seq,
                b.bed_bangsal,
                COUNT(DISTINCT r.room_id) AS jumlah_kamar,
                COUNT(bd.bed_no)          AS jumlah_bed
            ",
            )
            ->leftJoin(DB::raw('rsmst_rooms r'), 'b.bangsal_id', '=', 'r.bangsal_id')
            ->leftJoin(DB::raw('rsmst_beds bd'), 'r.room_id', '=', 'bd.room_id')
            ->groupBy('b.bangsal_id', 'b.bangsal_name', 'b.sl_codefrom', 'b.bangsal_seq', 'b.bed_bangsal')
            ->orderBy('b.bangsal_seq')
            ->orderBy('b.bangsal_name');

        if (trim($this->searchBangsal) !== '') {
            $kw = mb_strtoupper(trim($this->searchBangsal));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(b.bangsal_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(b.bangsal_id)   LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    /* ─── Pilih Bangsal ───────────────────────────────────────── */
    public function selectBangsal(string $id, string $name): void
    {
        $this->selectedBangsalId = $id;
        $this->selectedBangsalName = $name;
        $this->searchKamar = '';
        $this->expandedRooms = [];
        $this->bedsCache = [];
        $this->resetPage('pageKamar');
    }

    /* ─── Query Kamar ─────────────────────────────────────────── */
    #[Computed]
    public function rooms()
    {
        if (!$this->selectedBangsalId) {
            return null;
        }

        $q = DB::table(DB::raw('rsmst_rooms r'))
            ->selectRaw(
                "
                r.room_id,
                r.room_name,
                r.class_id,
                c.class_desc,
                r.room_price,
                r.perawatan_price,
                r.common_service,
                r.active_status,
                COUNT(bd.bed_no) AS jumlah_bed
            ",
            )
            ->leftJoin(DB::raw('rsmst_class c'), 'r.class_id', '=', 'c.class_id')
            ->leftJoin(DB::raw('rsmst_beds bd'), 'r.room_id', '=', 'bd.room_id')
            ->where('r.bangsal_id', $this->selectedBangsalId)
            ->groupBy('r.room_id', 'r.room_name', 'r.class_id', 'c.class_desc', 'r.room_price', 'r.perawatan_price', 'r.common_service', 'r.active_status')
            ->orderBy('r.room_name');

        if (trim($this->searchKamar) !== '') {
            $kw = mb_strtoupper(trim($this->searchKamar));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(r.room_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(r.room_id)   LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPageKamar, ['*'], 'pageKamar');
    }

    /* ─── Toggle expand bed ───────────────────────────────────── */
    public function toggleRoom(string $roomId): void
    {
        if (in_array($roomId, $this->expandedRooms)) {
            $this->expandedRooms = array_values(array_filter($this->expandedRooms, fn($id) => $id !== $roomId));
            return;
        }

        $this->expandedRooms[] = $roomId;

        if (!isset($this->bedsCache[$roomId])) {
            $beds = DB::table('rsmst_beds')->select('bed_no', 'bed_desc')->where('room_id', $roomId)->orderBy('bed_no')->get();

            $this->bedsCache[$roomId] = $beds->map(fn($b) => (array) $b)->toArray();
        }
    }
};
?>

<div>

    {{-- ══ HEADER ══════════════════════════════════════════════════ --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Kamar
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Bangsal, kamar & bed rawat inap
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6 space-y-6 ">


            <div class="grid grid-cols-2 gap-2"> {{-- Tabel Bangsal --}}
                <div>
                    {{-- ══ BANGSAL ══════════════════════════════════════════ --}}
                    {{-- Toolbar Bangsal --}}
                    <div
                        class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div class="w-full lg:max-w-xs">
                                <x-input-label for="searchBangsal" value="Cari Bangsal" class="sr-only" />
                                <x-text-input id="searchBangsal" type="text"
                                    wire:model.live.debounce.300ms="searchBangsal" placeholder="Cari bangsal..."
                                    class="block w-full" />
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-28">
                                    <x-select-input wire:model.live="itemsPerPage">
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="15">15</option>
                                        <option value="20">20</option>
                                    </x-select-input>
                                </div>
                                <x-primary-button type="button" wire:click="openCreateBangsal">
                                    + Tambah Bangsal
                                </x-primary-button>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-5 py-3 font-semibold">BANGSAL</th>
                                        <th class="px-5 py-3 font-semibold">KAPASITAS</th>
                                        <th class="px-5 py-3 font-semibold">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                    @forelse ($this->bangsals as $bangsal)
                                        @php $isActive = $selectedBangsalId === $bangsal->bangsal_id; @endphp
                                        <tr wire:key="bangsal-{{ $bangsal->bangsal_id }}"
                                            wire:click="selectBangsal('{{ $bangsal->bangsal_id }}', '{{ addslashes($bangsal->bangsal_name) }}')"
                                            class="cursor-pointer transition
                                           {{ $isActive
                                               ? 'bg-brand-green/5 dark:bg-brand-green/10 ring-1 ring-inset ring-brand-green/30 dark:ring-brand-green/40'
                                               : 'bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">

                                            {{-- BANGSAL: nama + id + kode sl + seq --}}
                                            <td class="px-5 py-4 align-top space-y-1">
                                                <div class="flex items-center gap-2">
                                                    @if ($isActive)
                                                        <svg class="w-3.5 h-3.5 text-brand shrink-0"
                                                            fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                    <span
                                                        class="font-semibold text-base {{ $isActive ? 'text-brand dark:text-brand-lime' : 'text-gray-800 dark:text-gray-100' }}">
                                                        {{ $bangsal->bangsal_name }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="font-mono">{{ $bangsal->bangsal_id }}</span>
                                                    @if ($bangsal->sl_codefrom)
                                                        <span>SL: <span
                                                                class="font-mono">{{ $bangsal->sl_codefrom }}</span></span>
                                                    @endif
                                                    @if ($bangsal->bangsal_seq)
                                                        <span>Seq: {{ $bangsal->bangsal_seq }}</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- KAPASITAS: jumlah kamar + bed + bed bangsal --}}
                                            <td class="px-5 py-4 align-top space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <x-badge variant="info">{{ $bangsal->jumlah_kamar }} Kamar</x-badge>
                                                    <x-badge variant="success">{{ $bangsal->jumlah_bed }} Bed</x-badge>
                                                </div>
                                                @if ($bangsal->bed_bangsal)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                                        Bed bangsal: <span
                                                            class="font-mono">{{ $bangsal->bed_bangsal }}</span>
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- AKSI --}}
                                            <td class="px-5 py-4 align-top" wire:click.stop>
                                                <div class="flex flex-wrap gap-2">
                                                    <x-outline-button type="button"
                                                        wire:click="openEditBangsal('{{ $bangsal->bangsal_id }}')">
                                                        Edit
                                                    </x-outline-button>
                                                    <x-confirm-button variant="danger" :action="'requestDeleteBangsal(\'' . $bangsal->bangsal_id . '\')'"
                                                        title="Hapus Bangsal"
                                                        message="Yakin hapus bangsal {{ $bangsal->bangsal_name }}?"
                                                        confirmText="Ya, hapus" cancelText="Batal">
                                                        Hapus
                                                    </x-confirm-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3"
                                                class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                                Data bangsal tidak ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $this->bangsals->links() }}
                        </div>
                    </div>
                </div>
                {{-- ══ KAMAR (muncul setelah bangsal dipilih) ═══════════ --}}
                @if ($selectedBangsalId)
                    <div wire:loading.class="opacity-60" wire:target="selectBangsal">

                        {{-- Toolbar Kamar --}}
                        <div
                            class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div class="flex items-center gap-3 w-full lg:max-w-xs">
                                    <x-text-input type="text" wire:model.live.debounce.300ms="searchKamar"
                                        placeholder="Cari kamar..." class="block w-full" />
                                </div>
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-28">
                                        <x-select-input wire:model.live="itemsPerPageKamar">
                                            <option value="5">5</option>
                                            <option value="10">10</option>
                                            <option value="15">15</option>
                                            <option value="20">20</option>
                                        </x-select-input>
                                    </div>
                                    <x-primary-button type="button" wire:click="openCreateKamar">
                                        + Tambah Kamar
                                    </x-primary-button>
                                </div>
                            </div>
                        </div>

                        {{-- Tabel Kamar --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                        <tr class="text-left">
                                            <th class="px-4 py-3 w-8"></th>
                                            <th class="px-5 py-3 font-semibold">
                                                KAMAR
                                                <span class="font-normal text-brand dark:text-brand-lime ml-1">— {{ $selectedBangsalName }}</span>
                                            </th>
                                            <th class="px-5 py-3 font-semibold">TARIF</th>
                                            <th class="px-5 py-3 font-semibold">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                        @forelse ($this->rooms as $room)
                                            @php
                                                $isExpanded = in_array($room->room_id, $expandedRooms);
                                                $beds = $bedsCache[$room->room_id] ?? [];
                                                $isActive =
                                                    strtoupper($room->active_status ?? '') === 'AC' ||
                                                    (string) $room->active_status === '1';
                                            @endphp

                                            {{-- Row Kamar --}}
                                            <tr wire:key="room-{{ $room->room_id }}"
                                                wire:click="toggleRoom('{{ $room->room_id }}')"
                                                class="cursor-pointer transition
                                                   {{ $isExpanded ? 'bg-indigo-50 dark:bg-indigo-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">

                                                {{-- Chevron --}}
                                                <td class="px-4 py-4 text-center text-gray-400 align-top">
                                                    <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>

                                                {{-- KAMAR: nama + id + kelas + status + bed --}}
                                                <td class="px-5 py-4 align-top space-y-1">
                                                    <div
                                                        class="font-semibold text-base text-gray-800 dark:text-gray-100">
                                                        {{ $room->room_name }}
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                        <span class="font-mono">{{ $room->room_id }}</span>
                                                        <span>{{ $room->class_desc ?? 'Kelas ' . $room->class_id }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2 pt-0.5">
                                                        <x-badge :variant="$isActive ? 'success' : 'gray'">
                                                            {{ $isActive ? 'Aktif' : $room->active_status ?? '-' }}
                                                        </x-badge>
                                                        <x-badge variant="info">{{ $room->jumlah_bed }} Bed</x-badge>
                                                    </div>
                                                </td>

                                                {{-- TARIF: kamar | perawatan --}}
                                                <td class="px-5 py-4 align-top">
                                                    <div class="flex items-start gap-6">
                                                        <div>
                                                            <div
                                                                class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                                                Kamar</div>
                                                            <div
                                                                class="font-mono font-semibold text-gray-700 dark:text-gray-200">
                                                                {{ number_format($room->room_price ?? 0, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div
                                                                class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                                                Perawatan</div>
                                                            <div class="font-mono text-gray-600 dark:text-gray-300">
                                                                {{ number_format($room->perawatan_price ?? 0, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div
                                                                class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                                                Pel. Umum</div>
                                                            <div class="font-mono text-gray-600 dark:text-gray-300">
                                                                {{ number_format($room->common_service ?? 0, 0, ',', '.') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {{-- AKSI --}}
                                                <td class="px-5 py-4 align-top" wire:click.stop>
                                                    <div class="flex flex-wrap gap-2">
                                                        <x-outline-button type="button"
                                                            wire:click="openEditKamar('{{ $room->room_id }}')">
                                                            Edit
                                                        </x-outline-button>
                                                        <x-confirm-button variant="danger" :action="'requestDeleteKamar(\'' . $room->room_id . '\')'"
                                                            title="Hapus Kamar"
                                                            message="Yakin hapus kamar {{ $room->room_name }}?"
                                                            confirmText="Ya, hapus" cancelText="Batal">
                                                            Hapus
                                                        </x-confirm-button>
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- Row Bed (expandable) --}}
                                            @if ($isExpanded)
                                                <tr wire:key="beds-{{ $room->room_id }}"
                                                    class="bg-indigo-50/60 dark:bg-indigo-900/5">
                                                    <td colspan="4" class="px-8 py-3">
                                                        <div class="flex flex-wrap gap-2">
                                                            {{-- Tambah bed --}}
                                                            <button wire:click="openCreateBed('{{ $room->room_id }}')"
                                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-dashed
                                                                       border-indigo-300 dark:border-indigo-600 text-indigo-500 dark:text-indigo-400
                                                                       hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-xs font-medium transition">
                                                                <svg class="w-3.5 h-3.5" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round" stroke-width="2"
                                                                        d="M12 4v16m8-8H4" />
                                                                </svg>
                                                                Tambah Bed
                                                            </button>

                                                            @if (empty($beds))
                                                                <p class="text-xs text-gray-400 italic self-center">
                                                                    Belum
                                                                    ada bed.</p>
                                                            @else
                                                                @foreach ($beds as $bed)
                                                                    <div
                                                                        class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                                                                            border border-indigo-200 dark:border-indigo-700
                                                                            bg-white dark:bg-gray-800 shadow-sm text-xs">
                                                                        <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M5 12h14M5 12c0-2.761 2.686-5 6-5s6 2.239 6 5M5 12c0 2.761 2.686 5 6 5s6-2.239 6-5" />
                                                                        </svg>
                                                                        <span
                                                                            class="font-bold text-gray-700 dark:text-gray-200 font-mono">{{ $bed['bed_no'] }}</span>
                                                                        @if (!empty($bed['bed_desc']))
                                                                            <span
                                                                                class="text-gray-400 dark:text-gray-500">{{ $bed['bed_desc'] }}</span>
                                                                        @endif
                                                                        <span
                                                                            class="hidden group-hover:inline-flex items-center gap-1 ml-1">
                                                                            <button
                                                                                wire:click="openEditBed('{{ $bed['bed_no'] }}', '{{ $room->room_id }}')"
                                                                                class="text-indigo-500 hover:text-indigo-700 transition">
                                                                                <svg class="w-3 h-3" fill="none"
                                                                                    stroke="currentColor"
                                                                                    viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828A2 2 0 019 16H7v-2a2 2 0 01.586-1.414z" />
                                                                                </svg>
                                                                            </button>
                                                                            <button
                                                                                wire:click="requestDeleteBed('{{ $bed['bed_no'] }}', '{{ $room->room_id }}')"
                                                                                wire:confirm="Hapus bed {{ $bed['bed_no'] }}?"
                                                                                class="text-red-400 hover:text-red-600 transition">
                                                                                <svg class="w-3 h-3" fill="none"
                                                                                    stroke="currentColor"
                                                                                    viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M6 18L18 6M6 6l12 12" />
                                                                                </svg>
                                                                            </button>
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif

                                        @empty
                                            <tr>
                                                <td colspan="4"
                                                    class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                    Tidak ada kamar untuk bangsal ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                                {{ $this->rooms->links() }}
                            </div>
                        </div>

                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <p class="text-sm">Pilih bangsal di atas untuk melihat daftar kamar & bed.</p>
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Child actions (modal CRUD bangsal / kamar / bed) --}}
    <livewire:pages::master.master-kamar.master-kamar-actions wire:key="master-kamar-actions" />

</div>
