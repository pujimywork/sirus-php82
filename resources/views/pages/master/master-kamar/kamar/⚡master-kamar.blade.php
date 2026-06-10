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

    /* --- Kamar terpilih (panel kanan: tarif + bed) --- */
    public ?string $selectedRoomId = null;
    public string $selectedRoomName = '';
    public array $beds = [];

    /** Tarif kamar utk inline edit, key = room_id: room_price/perawatan_price/common_service */
    public array $hargaDasar = [];

    public function updatedSearchKamar(): void
    {
        $this->resetPage('pageKamar');
    }

    public function updatedItemsPerPageKamar(): void
    {
        $this->resetPage('pageKamar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKamar']);
        $this->itemsPerPageKamar = 10;
        $this->resetPage('pageKamar');
    }

    /* --- Terima bangsal terpilih --- */
    #[On('bangsal.selected')]
    public function onBangsalSelected(string $bangsalId, string $bangsalName): void
    {
        $this->selectedBangsalId = $bangsalId;
        $this->selectedBangsalName = $bangsalName;
        $this->searchKamar = '';
        $this->resetSelectedRoom();
        $this->resetPage('pageKamar');
    }

    private function resetSelectedRoom(): void
    {
        $this->selectedRoomId = null;
        $this->selectedRoomName = '';
        $this->beds = [];
    }

    /* --- Pilih kamar → muat detail bed di panel kanan --- */
    public function selectRoom(string $roomId): void
    {
        $room = DB::table('rsmst_rooms')->select('room_id', 'room_name')->where('room_id', $roomId)->first();
        if (!$room) {
            return;
        }
        $this->selectedRoomId = $room->room_id;
        $this->selectedRoomName = $room->room_name;
        $this->loadBeds();
    }

    private function loadBeds(): void
    {
        if (!$this->selectedRoomId) {
            $this->beds = [];
            return;
        }
        $this->beds = DB::table('rsmst_beds')
            ->select('bed_no', 'bed_desc')
            ->where('room_id', $this->selectedRoomId)
            ->orderBy('bed_no')
            ->get()
            ->map(fn($b) => (array) $b)
            ->toArray();
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
            // Jika kamar terpilih dihapus/berubah, segarkan nama
            if ($this->selectedRoomId) {
                $this->selectRoom($this->selectedRoomId);
            }
        }
        if ($entity === 'bed' && $roomId) {
            if ($roomId === $this->selectedRoomId) {
                $this->loadBeds();
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

        $rows = $q->paginate($this->itemsPerPageKamar, ['*'], 'pageKamar');

        // Snapshot tarif halaman ini utk inline edit (binding x-text-input-number).
        foreach ($rows->items() as $r) {
            $this->hargaDasar[$r->room_id] = [
                'room_price' => (int) ($r->room_price ?? 0),
                'perawatan_price' => (int) ($r->perawatan_price ?? 0),
                'common_service' => (int) ($r->common_service ?? 0),
            ];
        }

        return $rows;
    }

    /** Data kamar terpilih (utk panel kanan: tarif & meta). */
    #[Computed]
    public function selectedRoom()
    {
        if (!$this->selectedRoomId) {
            return null;
        }
        return collect($this->rooms?->items() ?? [])->firstWhere('room_id', $this->selectedRoomId);
    }

    /**
     * Inline edit tarif kamar — auto-save saat blur/Enter
     * (x-text-input-number sync via $wire.set, bukan .live).
     */
    public function updatedHargaDasar($value, string $key): void
    {
        $segments = explode('.', $key);
        $field = array_pop($segments);
        $roomId = implode('.', $segments);

        if (!in_array($field, ['room_price', 'perawatan_price', 'common_service'], true) || $roomId === '') {
            return;
        }

        if (!is_numeric($value) || (int) $value < 0) {
            $this->dispatch('toast', type: 'error', message: 'Tarif harus berupa angka.');
            return;
        }

        DB::table('rsmst_rooms')->where('room_id', $roomId)->update([$field => (int) $value]);
        $this->dispatch('toast', type: 'success', message: 'Tarif kamar tersimpan.');
    }

    /* --- Toggle Status Aktif kamar (delegate ke actions) --- */
    public function toggleActiveRoom(string $roomId): void
    {
        $this->dispatch('master.kamar.toggleActiveRoom', roomId: $roomId);
    }
};
?>

<div class="flex flex-col h-full min-h-0">
    @if ($selectedBangsalId)
        <div wire:loading.class="opacity-60" wire:target="onBangsalSelected" class="flex flex-col flex-1 min-h-0">

            {{-- Toolbar Kamar --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
                        <x-toolbar-refresh-reset :label="null" />
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
            <div class="flex flex-wrap items-center gap-4 px-5 py-2 text-xs border-b border-hairline bg-surface-soft dark:border-gray-800 dark:bg-gray-800/40">
                <div class="flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-surface-card dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-muted dark:text-gray-300">Kamar</span>
                    <div class="flex items-center gap-1.5" title="Total kamar di bangsal ini">
                        <span class="text-muted dark:text-gray-400">Total</span>
                        <span class="font-bold text-ink dark:text-gray-200">{{ $totalKamar }}</span>
                    </div>
                    <span class="text-hairline dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5" title="Kamar berstatus Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-success"></span>
                        <span class="text-muted dark:text-gray-400">Aktif</span>
                        <span class="font-bold text-success dark:text-emerald-400">{{ $aktifKamar }}</span>
                    </div>
                    <span class="text-hairline dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5" title="Kamar berstatus Non-Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-error"></span>
                        <span class="text-muted dark:text-gray-400">Non-Aktif</span>
                        <span class="font-bold text-error dark:text-red-400">{{ $nonAktif }}</span>
                    </div>
                </div>

                <span class="hidden sm:inline-block h-4 w-px bg-hairline dark:bg-gray-600"></span>

                <div class="flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-surface-card dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-muted dark:text-gray-300">Tempat Tidur</span>
                    <div class="flex items-center gap-1.5" title="Jumlah bed di kamar yang Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-success"></span>
                        <span class="text-muted dark:text-gray-400">Aktif</span>
                        <span class="font-bold text-success dark:text-emerald-400">{{ $bedAktif }}</span>
                    </div>
                    <span class="text-hairline dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5" title="Jumlah bed di kamar yang Non-Aktif">
                        <span class="inline-block w-2 h-2 rounded-full bg-error"></span>
                        <span class="text-muted dark:text-gray-400">Non-Aktif</span>
                        <span class="font-bold text-error dark:text-red-400">{{ $bedNonAktif }}</span>
                    </div>
                </div>
            </div>

            {{-- 1:1 — Kiri daftar Kamar · Kanan detail (tarif + Tempat Tidur) --}}
            <div class="grid flex-1 min-h-0 grid-cols-1 gap-4 px-1 pt-4 lg:grid-cols-2">

                {{-- ============ KIRI: DAFTAR KAMAR ============ --}}
                <div class="flex flex-col min-h-0 bg-canvas border shadow-sm border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex-1 min-h-0 overflow-y-auto rounded-t-2xl">
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th>Kamar — <span class="font-normal normal-case text-brand-green dark:text-brand-lime">{{ $selectedBangsalName }}</span></th>
                                    <th class="ds-c">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rooms as $room)
                                    @php
                                        $isActive = (string) $room->active_status === '1';
                                        $isSelected = $room->room_id === $selectedRoomId;
                                    @endphp
                                    <tr wire:key="room-{{ $room->room_id }}"
                                        wire:click="selectRoom('{{ $room->room_id }}')"
                                        class="cursor-pointer transition
                                           {{ $isSelected ? 'bg-brand-green/10' : (!$isActive ? 'bg-red-50/60 dark:bg-red-900/15 hover:bg-red-100/60 dark:hover:bg-red-900/25' : 'hover:bg-surface-soft dark:hover:bg-gray-800/60') }}">
                                        {{-- KAMAR --}}
                                        <td>
                                            <div class="flex items-center gap-2">
                                                @if ($isSelected)
                                                    <span class="inline-block w-1.5 h-5 rounded-full bg-brand-green dark:bg-brand-lime shrink-0"></span>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="text-base font-semibold text-ink dark:text-white">{{ $room->room_name }}</div>
                                                    <div class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1 text-xs text-muted dark:text-gray-400">
                                                        <span class="font-mono">{{ $room->room_id }}</span>
                                                        <span>{{ $room->class_desc ?? 'Kelas ' . $room->class_id }}</span>
                                                        @if ($room->aplic_kodekelas)
                                                            <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300" title="Kode kelas Aplicares BPJS">BPJS {{ $room->aplic_kodekelas }}</span>
                                                        @endif
                                                        @if ($room->sirs_id_tt || $room->sirs_id_t_tt)
                                                            @php $ttLabel = $room->sirs_id_tt ? $this->sirsTtLabelOf($room->sirs_id_tt) : ''; @endphp
                                                            <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300" title="SIRS Kemenkes">SIRS @if ($room->sirs_id_tt){{ $room->sirs_id_tt }}{{ $ttLabel ? ' — ' . $ttLabel : '' }}@endif</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- STATUS --}}
                                        <td class="ds-c" wire:click.stop>
                                            <div class="flex flex-col items-center gap-1.5">
                                                <x-toggle :current="(string) $room->active_status" trueValue="1" falseValue="0"
                                                    wireClick="toggleActiveRoom('{{ $room->room_id }}')">
                                                    {{ $isActive ? 'Aktif' : 'Non Aktif' }}
                                                </x-toggle>
                                                <x-badge variant="info">{{ $room->jumlah_bed }} Bed</x-badge>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-6 py-10 text-center" style="color:var(--muted)">
                                            Tidak ada kamar untuk bangsal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="sticky bottom-0 z-10 px-4 py-3 border-t bg-canvas border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                        {{ $this->rooms->links() }}
                    </div>
                </div>

                {{-- ============ KANAN: DETAIL KAMAR (tarif + bed) ============ --}}
                <div class="flex flex-col min-h-0 overflow-y-auto bg-canvas border shadow-sm border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    @if ($this->selectedRoom)
                        @php $sr = $this->selectedRoom; @endphp
                        {{-- Header detail --}}
                        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-hairline dark:border-gray-700">
                            <div class="min-w-0">
                                <div class="font-serif text-2xl leading-tight text-ink dark:text-white">{{ $sr->room_name }}</div>
                                <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                    <span class="font-mono">{{ $sr->room_id }}</span> ·
                                    {{ $sr->class_desc ?? 'Kelas ' . $sr->class_id }}
                                </div>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <x-action-edit wire:click="openEditKamar('{{ $sr->room_id }}')" />
                                <x-action-delete :action="'requestDeleteKamar(\'' . $sr->room_id . '\')'"
                                    title="Hapus Kamar" message="Yakin hapus kamar {{ $sr->room_name }}?" />
                            </div>
                        </div>

                        {{-- Tarif (inline edit, auto-save blur/Enter) --}}
                        <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                            <div class="ds-caption-up mb-2.5">Tarif Kamar</div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <div class="text-xs text-muted dark:text-gray-500 mb-0.5">Kamar</div>
                                    <x-text-input-number wire:model="hargaDasar.{{ $sr->room_id }}.room_price"
                                        wire:key="hd-kamar-{{ $sr->room_id }}" x-on:keydown.enter.prevent="$el.blur()" class="w-full" />
                                </div>
                                <div>
                                    <div class="text-xs text-muted dark:text-gray-500 mb-0.5">Perawatan</div>
                                    <x-text-input-number wire:model="hargaDasar.{{ $sr->room_id }}.perawatan_price"
                                        wire:key="hd-perawatan-{{ $sr->room_id }}" x-on:keydown.enter.prevent="$el.blur()" class="w-full" />
                                </div>
                                <div>
                                    <div class="text-xs text-muted dark:text-gray-500 mb-0.5">Pelayanan Umum</div>
                                    <x-text-input-number wire:model="hargaDasar.{{ $sr->room_id }}.common_service"
                                        wire:key="hd-pelumum-{{ $sr->room_id }}" x-on:keydown.enter.prevent="$el.blur()" class="w-full" />
                                </div>
                            </div>
                        </div>

                        {{-- Tempat Tidur --}}
                        <div class="flex-1 px-5 py-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="ds-caption-up">Tempat Tidur ({{ count($beds) }})</div>
                                <x-ghost-button wire:click="openCreateBed('{{ $sr->room_id }}')"
                                    class="!text-brand-green dark:!text-brand-lime !px-3 !py-1.5 !text-xs border border-dashed border-brand-green/40 dark:border-brand-lime/40">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Tambah Bed
                                </x-ghost-button>
                            </div>

                            @if (empty($beds))
                                <p class="text-sm italic text-muted">Belum ada tempat tidur di kamar ini.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($beds as $bed)
                                        <div class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-hairline bg-surface-soft dark:border-gray-700 dark:bg-gray-800 shadow-sm text-sm">
                                            <svg class="w-4 h-4 text-brand-green dark:text-brand-lime shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12c0-2.761 2.686-5 6-5s6 2.239 6 5M5 12c0 2.761 2.686 5 6 5s6-2.239 6-5" />
                                            </svg>
                                            <span class="font-mono font-bold text-ink dark:text-gray-200">{{ $bed['bed_no'] }}</span>
                                            @if (!empty($bed['bed_desc']))
                                                <span class="text-muted dark:text-gray-500">{{ $bed['bed_desc'] }}</span>
                                            @endif
                                            <span class="items-center hidden gap-1 ml-1 group-hover:inline-flex">
                                                <x-ghost-button wire:click="openEditBed('{{ $bed['bed_no'] }}', '{{ $sr->room_id }}')"
                                                    class="!text-brand-green dark:!text-brand-lime !p-1 !rounded">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828A2 2 0 019 16H7v-2a2 2 0 01.586-1.414z" />
                                                    </svg>
                                                </x-ghost-button>
                                                <x-confirm-button variant="danger"
                                                    :action="'requestDeleteBed(\'' . $bed['bed_no'] . '\', \'' . $sr->room_id . '\')'"
                                                    title="Hapus Bed" :message="'Hapus bed ' . $bed['bed_no'] . '?'"
                                                    confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1 text-sm">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </x-confirm-button>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Belum pilih kamar --}}
                        <div class="flex flex-col items-center justify-center flex-1 px-6 py-12 text-center text-muted dark:text-gray-500">
                            <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12c0-2.761 2.686-5 6-5s6 2.239 6 5M5 12c0 2.761 2.686 5 6 5s6-2.239 6-5" />
                            </svg>
                            <p class="text-sm">Pilih kamar di kiri untuk melihat tarif & tempat tidur.</p>
                        </div>
                    @endif
                </div>

            </div>

        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-muted dark:text-gray-500">
            <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <p class="text-sm">Pilih bangsal di sebelah kiri untuk melihat daftar kamar & bed.</p>
        </div>
    @endif
</div>
