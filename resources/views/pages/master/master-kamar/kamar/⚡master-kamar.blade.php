<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /**
     * Mapping id_tt → jenis tempat tidur SIRS Kemenkes (referensi resmi).
     * Sumber: LOV SIRS /Referensi/tempat_tidur.
     */
    private const SIRS_TT_LABEL = [
        '1'  => 'VVIP/ Super VIP',
        '2'  => 'VIP',
        '3'  => 'Kelas I',
        '4'  => 'Kelas II',
        '5'  => 'Kelas III',
        '6'  => 'ICU Tanpa Ventilator',
        '7'  => 'HCU',
        '8'  => 'ICCU/ICVCU Tanpa Ventilator',
        '9'  => 'RICU Tanpa Ventilator',
        '10' => 'NICU Tanpa Ventilator',
        '11' => 'PICU Tanpa Ventilator',
        '12' => 'Isolasi',
        '14' => 'Perinatologi',
        '24' => 'ICU Tekanan Negatif dengan Ventilator',
        '25' => 'ICU Tekanan Negatif tanpa Ventilator',
        '26' => 'ICU Tanpa Tekanan Negatif Dengan Ventilator',
        '27' => 'ICU Tanpa Tekanan Negatif Tanpa Ventilator',
        '28' => 'Isolasi Tekanan Negatif',
        '29' => 'Isolasi Tanpa Tekanan Negatif',
        '30' => 'NICU Khusus Covid',
        '31' => 'PICU Khusus Covid',
        '32' => 'IGD Khusus Covid',
        '33' => 'VK (TT Observasi di R Bersalin) Khusus Covid',
        '34' => 'Isolasi Perinatologi Khusus Covid',
        '36' => 'VK (TT Observasi di R Bersalin) Non Covid',
        '37' => 'Intermediate Ward (IGD)',
        '38' => 'ICU Dengan Ventilator',
        '39' => 'NICU Dengan Ventilator',
        '40' => 'PICU Dengan Ventilator',
        '50' => 'RICU Dengan Ventilator',
        '51' => 'ICCU/ICVCU Dengan Ventilator',
        '52' => 'KRIS JKN',
    ];

    public function sirsTtLabelOf(?string $id): string
    {
        return self::SIRS_TT_LABEL[(string) ($id ?? '')] ?? '';
    }

    /* --- Bangsal terpilih (dari event) --- */
    public ?string $selectedBangsalId = null;
    public string $selectedBangsalName = '';

    /* --- Filter Kamar --- */
    public string $searchKamar = '';
    public int $itemsPerPageKamar = 10;

    /* --- Expand bed per kamar --- */
    public array $expandedRooms = [];
    public array $bedsCache = [];

    public function updatedSearchKamar(): void
    {
        $this->resetPage('pageKamar');
    }

    public function updatedItemsPerPageKamar(): void
    {
        $this->resetPage('pageKamar');
    }

    /* --- Terima bangsal terpilih --- */
    #[On('bangsal.selected')]
    public function onBangsalSelected(string $bangsalId, string $bangsalName): void
    {
        $this->selectedBangsalId = $bangsalId;
        $this->selectedBangsalName = $bangsalName;
        $this->searchKamar = '';
        $this->expandedRooms = [];
        $this->bedsCache = [];
        $this->resetPage('pageKamar');
    }

    /* --- Dispatch ke actions --- */
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

    /* --- Refresh setelah save/delete --- */
    #[On('master.kamar.saved')]
    public function afterSaved(string $entity, string $roomId = ''): void
    {
        if ($entity === 'kamar') {
            unset($this->computedPropertyCache);
            $this->resetPage('pageKamar');
        }
        if ($entity === 'bed' && $roomId) {
            $beds = DB::table('rsmst_beds')->select('bed_no', 'bed_desc')->where('room_id', $roomId)->orderBy('bed_no')->get();
            $this->bedsCache[$roomId] = $beds->map(fn($b) => (array) $b)->toArray();
            if (!in_array($roomId, $this->expandedRooms)) {
                $this->expandedRooms[] = $roomId;
            }
            unset($this->computedPropertyCache);
        }
    }

    /* --- Query Kamar --- */
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
                r.aplic_kodekelas,
                r.sirs_id_tt,
                r.sirs_id_t_tt,
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
            ->groupBy('r.room_id', 'r.room_name', 'r.class_id', 'c.class_desc', 'r.aplic_kodekelas', 'r.sirs_id_tt', 'r.sirs_id_t_tt', 'r.room_price', 'r.perawatan_price', 'r.common_service', 'r.active_status')
            ->orderByDesc('r.active_status')
            ->orderBy('r.room_name');

        if (trim($this->searchKamar) !== '') {
            $kw = mb_strtoupper(trim($this->searchKamar));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(r.room_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(r.room_id)   LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPageKamar, ['*'], 'pageKamar');
    }

    /* --- Toggle expand bed --- */
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
    @if ($selectedBangsalId)
        <div wire:loading.class="opacity-60" wire:target="onBangsalSelected">

            {{-- Toolbar Kamar --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
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
                            + Tambah Data Kamar Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- Rekap Kamar --}}
            @php
                $rekapRooms    = $this->rooms;
                $totalKamar    = $rekapRooms->total();
                $itemsAktif    = collect($rekapRooms->items())->where('active_status', '1');
                $itemsNonAktif = collect($rekapRooms->items())->where('active_status', '0');
                $aktifKamar    = $itemsAktif->count();
                $nonAktif      = $itemsNonAktif->count();
                $bedAktif      = (int) $itemsAktif->sum('jumlah_bed');
                $bedNonAktif   = (int) $itemsNonAktif->sum('jumlah_bed');
            @endphp
            <div class="flex items-center gap-4 px-5 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 text-xs flex-wrap">
                {{-- Kelompok: Kamar --}}
                <div class="flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Kamar</span>
                    <div class="flex items-center gap-1.5" title="Total kamar di bangsal ini">
                        <span class="text-gray-500 dark:text-gray-400">Total</span>
                        <span class="font-bold text-gray-700 dark:text-gray-200">{{ $totalKamar }}</span>
                    </div>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5" title="Kamar berstatus Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Aktif</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $aktifKamar }}</span>
                    </div>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5" title="Kamar berstatus Non-Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                        <span class="text-gray-500 dark:text-gray-400">Non-Aktif</span>
                        <span class="font-bold text-red-500 dark:text-red-400">{{ $nonAktif }}</span>
                    </div>
                </div>

                {{-- Pemisah vertikal --}}
                <span class="hidden sm:inline-block h-4 w-px bg-gray-300 dark:bg-gray-600"></span>

                {{-- Kelompok: Tempat Tidur (Bed) --}}
                <div class="flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Tempat Tidur</span>
                    <div class="flex items-center gap-1.5" title="Jumlah bed di kamar yang Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Aktif</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $bedAktif }}</span>
                    </div>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5" title="Jumlah bed di kamar yang Non-Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                        <span class="text-gray-500 dark:text-gray-400">Non-Aktif</span>
                        <span class="font-bold text-red-500 dark:text-red-400">{{ $bedNonAktif }}</span>
                    </div>
                </div>
            </div>

            {{-- Tabel Kamar --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 w-8"></th>
                                <th class="px-5 py-3 font-semibold">
                                    KAMAR
                                    <span class="font-normal text-brand dark:text-brand-lime ml-1">&mdash;
                                        {{ $selectedBangsalName }}</span>
                                </th>
                                <th class="px-5 py-3 font-semibold">TARIF &amp; AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rooms as $room)
                                @php
                                    $isExpanded = in_array($room->room_id, $expandedRooms);
                                    $beds = $bedsCache[$room->room_id] ?? [];
                                    $isActive = (string) $room->active_status === '1';
                                @endphp

                                {{-- Row Kamar --}}
                                <tr wire:key="room-{{ $room->room_id }}"
                                    wire:click="toggleRoom('{{ $room->room_id }}')"
                                    class="cursor-pointer transition
                                       {{ !$isActive ? 'bg-red-50/70 dark:bg-red-900/15 hover:bg-red-100/70 dark:hover:bg-red-900/25' : ($isExpanded ? 'bg-indigo-50 dark:bg-indigo-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60') }}">

                                    {{-- Chevron --}}
                                    <td class="px-4 py-4 text-center text-gray-400 align-top">
                                        <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </td>

                                    {{-- KAMAR --}}
                                    <td class="px-5 py-4 align-top space-y-1">
                                        <div class="font-semibold text-base text-gray-800 dark:text-gray-100">
                                            {{ $room->room_name }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ $room->room_id }}</span>
                                            <span>{{ $room->class_desc ?? 'Kelas ' . $room->class_id }}</span>

                                            @if ($room->aplic_kodekelas)
                                                <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold
                                                             bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300"
                                                      title="Kode kelas Aplicares BPJS">
                                                    BPJS {{ $room->aplic_kodekelas }}
                                                </span>
                                            @endif

                                            @if ($room->sirs_id_tt || $room->sirs_id_t_tt)
                                                @php $ttLabel = $room->sirs_id_tt ? $this->sirsTtLabelOf($room->sirs_id_tt) : ''; @endphp
                                                <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold
                                                             bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                                      title="SIRS Kemenkes — id_tt{{ $room->sirs_id_t_tt ? ' · id_t_tt' : '' }}">
                                                    SIRS
                                                    @if ($room->sirs_id_tt) {{ $room->sirs_id_tt }}{{ $ttLabel ? ' — ' . $ttLabel : '' }} @endif
                                                    @if ($room->sirs_id_t_tt)<span class="opacity-60 font-normal">· #{{ $room->sirs_id_t_tt }}</span>@endif
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 pt-0.5">
                                            <x-badge :variant="$isActive ? 'success' : 'danger'">
                                                {{ $isActive ? 'Aktif' : 'Non Aktif' }}
                                            </x-badge>
                                            <x-badge variant="info">{{ $room->jumlah_bed }} Bed</x-badge>
                                        </div>
                                    </td>

                                    {{-- TARIF & AKSI --}}
                                    <td class="px-5 py-4 align-top">
                                        <div class="flex items-start gap-6">
                                            <div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Kamar</div>
                                                <div class="font-mono font-semibold text-gray-700 dark:text-gray-200">
                                                    {{ number_format($room->room_price ?? 0, 0, ',', '.') }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Perawatan</div>
                                                <div class="font-mono text-gray-600 dark:text-gray-300">
                                                    {{ number_format($room->perawatan_price ?? 0, 0, ',', '.') }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Pel. Umum</div>
                                                <div class="font-mono text-gray-600 dark:text-gray-300">
                                                    {{ number_format($room->common_service ?? 0, 0, ',', '.') }}
                                                </div>
                                                <div class="flex flex-wrap gap-2 mt-2" wire:click.stop>
                                                    <x-secondary-button type="button"
                                                        wire:click="openEditKamar('{{ $room->room_id }}')" class="px-2 py-1 text-xs">
                                                        Edit
                                                    </x-secondary-button>
                                                    <x-confirm-button variant="danger" :action="'requestDeleteKamar(\'' . $room->room_id . '\')'"
                                                        title="Hapus Kamar"
                                                        message="Yakin hapus kamar {{ $room->room_name }}?"
                                                        confirmText="Ya, hapus" cancelText="Batal"
                                                        class="px-2 py-1 text-xs">
                                                        Hapus
                                                    </x-confirm-button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Row Bed (expandable) --}}
                                @if ($isExpanded)
                                    <tr wire:key="beds-{{ $room->room_id }}"
                                        class="bg-indigo-50/60 dark:bg-indigo-900/5">
                                        <td colspan="3" class="px-8 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                {{-- Tambah bed --}}
                                                <x-ghost-button
                                                    wire:click="openCreateBed('{{ $room->room_id }}')"
                                                    class="!text-indigo-500 hover:!bg-indigo-50 dark:!text-indigo-400 dark:hover:!bg-indigo-900/20
                                                           !px-3 !py-1.5 !text-xs border border-dashed border-indigo-300 dark:border-indigo-600
                                                           focus:!ring-indigo-200 dark:focus:!ring-indigo-900/40">
                                                    <svg class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round"
                                                            stroke-linejoin="round" stroke-width="2"
                                                            d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Tambah Bed
                                                </x-ghost-button>

                                                @if (empty($beds))
                                                    <p class="text-xs text-gray-400 italic self-center">
                                                        Belum ada bed.</p>
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
                                                                <x-ghost-button
                                                                    wire:click="openEditBed('{{ $bed['bed_no'] }}', '{{ $room->room_id }}')"
                                                                    class="!text-indigo-500 hover:!bg-indigo-50 dark:!text-indigo-400 dark:hover:!bg-indigo-900/20
                                                                           !p-1 !rounded focus:!ring-indigo-200">
                                                                    <svg class="w-3 h-3" fill="none"
                                                                        stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828A2 2 0 019 16H7v-2a2 2 0 01.586-1.414z" />
                                                                    </svg>
                                                                </x-ghost-button>
                                                                <x-confirm-button variant="danger"
                                                                    :action="'requestDeleteBed(\'' . $bed['bed_no'] . '\', \'' . $room->room_id . '\')'"
                                                                    title="Hapus Bed"
                                                                    :message="'Hapus bed ' . $bed['bed_no'] . '?'"
                                                                    confirmText="Ya, hapus" cancelText="Batal"
                                                                    class="px-2 py-1 text-xs">
                                                                    <svg class="w-3 h-3" fill="none"
                                                                        stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M6 18L18 6M6 6l12 12" />
                                                                    </svg>
                                                                </x-confirm-button>
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
                                    <td colspan="3"
                                        class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada kamar untuk bangsal ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
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
            <p class="text-sm">Pilih bangsal di sebelah kiri untuk melihat daftar kamar & bed.</p>
        </div>
    @endif
</div>
