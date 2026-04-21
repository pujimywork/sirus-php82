<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  MASTER KAMAR — Pengelolaan Bangsal, Kamar & Bed Rawat Inap       ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// FILE INI ADALAH HALAMAN UTAMA (entry point)
// Route: /master/kamar → pages::master.master-kamar.bangsal.master-bangsal
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  STRUKTUR FOLDER                                                   │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  master-kamar/                                                      │
// │  ├── bangsal/                                                       │
// │  │   ├── ⚡master-bangsal.blade.php          ← FILE INI (MAIN)     │
// │  │   └── ⚡master-bangsal-actions.blade.php  ← CRUD bangsal        │
// │  ├── kamar/                                                         │
// │  │   ├── ⚡master-kamar.blade.php            ← Grid kamar + bed    │
// │  │   └── ⚡master-kamar-actions.blade.php    ← CRUD kamar          │
// │  ├── bed/                                                           │
// │  │   └── ⚡master-bed-actions.blade.php      ← CRUD bed            │
// │  └── registrasi-aplicares-sirs/                                             │
// │      ├── ⚡registrasi-aplicares-sirs.blade.php ← Bulk registrasi   │
// │      ├── ⚡aplicares-actions.blade.php        ← Data di Aplicares  │
// │      ├── ⚡sirs-actions.blade.php             ← Data di SIRS       │
// │      └── bulk-results.blade.php               ← Tabel hasil bulk   │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  CARA KERJA & ALUR TAMPILAN                                        │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  ┌───────────────────────────────────────────────────────────────┐  │
// │  │ HEADER                                                        │  │
// │  │ Master Kamar              [Daftarkan Semua] [Data Terdaftar]  │  │
// │  │ Bangsal, kamar & bed               ↓                ↓        │  │
// │  └────────────────────────────────────┼────────────────┼─────────┘  │
// │           Alpine $dispatch()          ↓                ↓            │
// │           registrasi.openBulkRegistrasiAplicaresSirs ──► registrasi-aplicares-sirs   │
// │           registrasi.openDataTerdaftarAplicaresSirs ────────► (buka modal)               │
// │                                                                     │
// │  Halaman terbagi 2 kolom (grid 50:50):                              │
// │                                                                     │
// │  ┌──────────────────────┐  ┌──────────────────────┐                 │
// │  │   BANGSAL (kiri)     │  │   KAMAR + BED (kanan)│                 │
// │  │   master-bangsal     │  │   master-kamar       │                 │
// │  │                      │  │                      │                 │
// │  │  ┌────────────────┐  │  │  (kosong sampai      │                 │
// │  │  │ Bangsal A  ◄───┼──┼──┼── bangsal dipilih)   │                 │
// │  │  │ Bangsal B      │  │  │                      │                 │
// │  │  │ Bangsal C      │  │  │  ┌────────────────┐  │                 │
// │  │  └────────────────┘  │  │  │ Kamar 101      │  │                 │
// │  │                      │  │  │  ▶ Bed A, Bed B │  │                 │
// │  │                      │  │  │ Kamar 102      │  │                 │
// │  │                      │  │  └────────────────┘  │                 │
// │  └──────────────────────┘  └──────────────────────┘                 │
// │                                                                     │
// │  Hirarki data: BANGSAL → KAMAR → BED                               │
// │  - 1 Bangsal punya banyak Kamar                                     │
// │  - 1 Kamar punya banyak Bed                                         │
// │  - Bed ditampilkan expand/collapse di bawah baris kamar             │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  KOMUNIKASI ANTAR KOMPONEN (Livewire Events)                       │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  1. User klik bangsal di tabel kiri                                 │
// │     master-bangsal  ──dispatch('bangsal.selected')──►  master-kamar │
// │     → master-kamar load daftar kamar untuk bangsal itu              │
// │                                                                     │
// │  2. User klik tombol CRUD (Tambah/Edit/Hapus)                       │
// │     master-bangsal  ──dispatch('master.kamar.openCreateBangsal')──► │
// │     master-kamar    ──dispatch('master.kamar.openEditKamar')──►     │
// │     master-kamar    ──dispatch('master.kamar.openCreateBed')──►     │
// │     → Masing-masing actions component mendengarkan & buka modal     │
// │                                                                     │
// │  3. Setelah CRUD berhasil (save/delete)                             │
// │     *-actions  ──dispatch('master.kamar.saved')──►  semua komponen  │
// │     → master-bangsal refresh tabel bangsal (jika entity='bangsal')  │
// │     → master-kamar refresh tabel kamar (jika entity='kamar'/'bed')  │
// │                                                                     │
// │  4. Tombol Aplicares/SIRS di header                                 │
// │     Alpine $dispatch('registrasi.openBulkRegistrasiAplicaresSirs')                   │
// │       → registrasi-aplicares-sirs #[On] → buka modal bulk           │
// │     Alpine $dispatch('registrasi.openDataTerdaftarAplicaresSirs')                        │
// │       → registrasi-aplicares-sirs #[On] → buka modal data terdaftar │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  TABEL DATABASE                                                     │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  rsmst_bangsals  → Master bangsal (ward)                            │
// │  rsmst_rooms     → Master kamar (room), FK: bangsal_id, class_id   │
// │  rsmst_beds      → Master bed, FK: room_id                         │
// │  rsmst_class     → Referensi kelas kamar (VIP, I, II, III, dll)    │
// │                                                                     │
// │  Integrasi eksternal (di master-registrasi):                        │
// │  - rsmst_rooms.aplic_kodekelas → kode kelas Aplicares (BPJS)       │
// │  - rsmst_rooms.sirs_id_tt      → tipe tempat tidur SIRS (Kemenkes) │
// │  - rsmst_rooms.sirs_id_t_tt    → ID transaksi TT SIRS              │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* --- Filter --- */
    public string $searchBangsal = '';
    public int $itemsPerPage = 10;

    /* --- Pilihan bangsal --- */
    public ?string $selectedBangsalId = null;
    public string $selectedBangsalName = '';

    public function updatedSearchBangsal(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* --- Dispatch ke actions --- */
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

    /* --- Pilih Bangsal --- */
    public function selectBangsal(string $id, string $name): void
    {
        $this->selectedBangsalId = $id;
        $this->selectedBangsalName = $name;

        $this->dispatch('bangsal.selected', bangsalId: $id, bangsalName: $name);
    }

    /* --- Refresh setelah save/delete --- */
    #[On('master.kamar.saved')]
    public function afterSaved(string $entity, string $roomId = ''): void
    {
        if ($entity === 'bangsal') {
            $this->resetPage();
        }
        unset($this->computedPropertyCache);
    }

    /* --- Rekap jumlah Kamar & Tempat Tidur aktif lintas seluruh bangsal --- */
    #[Computed]
    public function rekapKamarBedLintasBangsal(): array
    {
        $row = DB::table('rsmst_rooms as r')
            ->leftJoin('rsmst_beds as bd', 'r.room_id', '=', 'bd.room_id')
            ->selectRaw("
                COUNT(DISTINCT CASE WHEN r.active_status = '1' THEN r.room_id END) AS kamar_aktif,
                COUNT(CASE WHEN r.active_status = '1' THEN bd.bed_no END) AS bed_aktif
            ")
            ->first();

        return [
            'kamarAktif' => (int) ($row->kamar_aktif ?? 0),
            'bedAktif'   => (int) ($row->bed_aktif ?? 0),
        ];
    }

    /* --- Query Bangsal --- */
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
            ->orderBy('b.bangsal_name');

        if (trim($this->searchBangsal) !== '') {
            $kw = mb_strtoupper(trim($this->searchBangsal));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(b.bangsal_name) LIKE ?', ["%{$kw}%"])->orWhereRaw('UPPER(b.bangsal_id)   LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    {{-- ══ HEADER ══════════════════════════════════════════════════ --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Master Kamar
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Bangsal, kamar & bed rawat inap
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-outline-button x-on:click="$dispatch('registrasi.openKelolaAplicaresSirs')" class="shrink-0 gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Kelola Aplicares &amp; SIRS
                </x-outline-button>
            </div>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6 space-y-6">

            {{-- ══ GRID: BANGSAL (kiri) + KAMAR/BED (kanan) ══════════ --}}
            <div class="grid grid-cols-2 gap-2">

                {{-- ── BANGSAL ─────────────────────────────────────── --}}
                <div>
                    {{-- Toolbar Bangsal --}}
                    <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
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
                                    + Tambah Data Bangsal Baru
                                </x-primary-button>
                            </div>
                        </div>
                    </div>

                    {{-- Rekap Keseluruhan (lintas bangsal) — hanya yang aktif --}}
                    @php $stats = $this->rekapKamarBedLintasBangsal; @endphp
                    <div class="flex items-center gap-4 px-5 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 text-xs flex-wrap">
                        <span class="px-1.5 py-0.5 rounded bg-brand-green/10 dark:bg-brand-lime/10 font-bold text-[10px] uppercase tracking-wider text-brand-green dark:text-brand-lime">Keseluruhan</span>

                        <div class="flex items-center gap-1.5" title="Kamar berstatus Aktif di seluruh bangsal">
                            <span class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Kamar</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['kamarAktif'] }}</span>
                        </div>

                        <span class="hidden sm:inline-block h-4 w-px bg-gray-300 dark:bg-gray-600"></span>

                        <div class="flex items-center gap-1.5" title="Jumlah bed di kamar yang Aktif (se-keseluruhan)">
                            <span class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Tempat Tidur</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['bedAktif'] }}</span>
                        </div>
                    </div>

                    {{-- Tabel Bangsal --}}
                    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-5 py-3 font-semibold">BANGSAL</th>
                                        <th class="px-5 py-3 font-semibold">KAPASITAS</th>
                                        <th class="px-5 py-3 font-semibold">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                    @forelse ($this->bangsals as $bangsal)
                                        @php $isActive = $selectedBangsalId === $bangsal->bangsal_id; @endphp
                                        <tr wire:key="bangsal-{{ $bangsal->bangsal_id }}"
                                            wire:click="selectBangsal('{{ $bangsal->bangsal_id }}', '{{ addslashes($bangsal->bangsal_name) }}')"
                                            class="cursor-pointer transition
                                           {{ $isActive
                                               ? 'bg-brand-green/5 dark:bg-brand-green/10 ring-1 ring-inset ring-brand-green/30 dark:ring-brand-green/40'
                                               : 'bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">

                                            {{-- BANGSAL --}}
                                            <td class="px-5 py-4 align-top space-y-1">
                                                <div class="flex items-center gap-2">
                                                    @if ($isActive)
                                                        <svg class="w-3.5 h-3.5 text-brand shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                    <span class="font-semibold text-base {{ $isActive ? 'text-brand dark:text-brand-lime' : 'text-gray-800 dark:text-gray-100' }}">
                                                        {{ $bangsal->bangsal_name }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="font-mono">{{ $bangsal->bangsal_id }}</span>
                                                    @if ($bangsal->sl_codefrom)
                                                        <span>SL: <span class="font-mono">{{ $bangsal->sl_codefrom }}</span></span>
                                                    @endif
                                                    @if ($bangsal->bangsal_seq)
                                                        <span>Seq: {{ $bangsal->bangsal_seq }}</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- KAPASITAS --}}
                                            <td class="px-5 py-4 align-top space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <x-badge variant="info">{{ $bangsal->jumlah_kamar }} Kamar</x-badge>
                                                    <x-badge variant="success">{{ $bangsal->jumlah_bed }} Bed</x-badge>
                                                </div>
                                                @if ($bangsal->bed_bangsal)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                                        Bed bangsal: <span class="font-mono">{{ $bangsal->bed_bangsal }}</span>
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- AKSI --}}
                                            <td class="px-5 py-4 align-top" wire:click.stop>
                                                <div class="flex flex-wrap gap-2">
                                                    <x-secondary-button type="button"
                                                        wire:click="openEditBangsal('{{ $bangsal->bangsal_id }}')" class="px-2 py-1 text-xs">
                                                        Edit
                                                    </x-secondary-button>
                                                    <x-confirm-button variant="danger" :action="'requestDeleteBangsal(\'' . $bangsal->bangsal_id . '\')'"
                                                        title="Hapus Bangsal"
                                                        message="Yakin hapus bangsal {{ $bangsal->bangsal_name }}?"
                                                        confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                        Hapus
                                                    </x-confirm-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                                Data bangsal tidak ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $this->bangsals->links() }}
                        </div>
                    </div>
                </div>

                {{-- ── KAMAR + BED (child component) ───────────────── --}}
                <livewire:pages::master.master-kamar.kamar.master-kamar wire:key="master-kamar" />

            </div>

        </div>
    </div>

    {{-- Child: CRUD actions --}}
    <livewire:pages::master.master-kamar.bangsal.master-bangsal-actions wire:key="master-bangsal-actions" />
    <livewire:pages::master.master-kamar.kamar.master-kamar-actions wire:key="master-kamar-actions" />
    <livewire:pages::master.master-kamar.bed.master-bed-actions wire:key="master-bed-actions" />

    {{-- Child: Registrasi Aplicares & SIRS --}}
    <livewire:pages::master.master-kamar.registrasi-aplicares-sirs.registrasi-aplicares-sirs wire:key="registrasi-aplicares-sirs" />

</div>
