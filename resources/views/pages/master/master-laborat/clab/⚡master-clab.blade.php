<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  MASTER LABORAT — Pengelolaan Kategori Lab & Item Pemeriksaan      ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// FILE INI ADALAH HALAMAN UTAMA (entry point)
// Route: /master/laborat → pages::master.master-laborat.clab.master-clab
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  STRUKTUR FOLDER                                                   │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  master-laborat/                                                    │
// │  ├── clab/                                                          │
// │  │   ├── ⚡master-clab.blade.php          ← FILE INI (MAIN)        │
// │  │   └── ⚡master-clab-actions.blade.php  ← CRUD kategori lab      │
// │  └── clabitem/                                                      │
// │      ├── ⚡master-clabitem.blade.php      ← Grid item pemeriksaan  │
// │      └── ⚡master-clabitem-actions.blade.php ← CRUD item           │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  CARA KERJA & ALUR TAMPILAN                                        │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  Halaman terbagi 2 kolom (grid 1:2):                                │
// │                                                                     │
// │  ┌──────────────────────┐  ┌──────────────────────────────────┐     │
// │  │   CLAB (kiri)        │  │   CLABITEM (kanan)               │     │
// │  │   master-clab        │  │   master-clabitem                │     │
// │  │                      │  │                                  │     │
// │  │  ┌────────────────┐  │  │  (kosong sampai CLAB dipilih)    │     │
// │  │  │ Hematologi ◄───┼──┼──┤                                  │     │
// │  │  │ Kimia Darah    │  │  │  ┌────────────────────────────┐  │     │
// │  │  │ Urinalisa      │  │  │  │ Hemoglobin        Rp 25.000│  │     │
// │  │  └────────────────┘  │  │  │ Leukosit          Rp 20.000│  │     │
// │  │                      │  │  │ Trombosit         Rp 20.000│  │     │
// │  │                      │  │  └────────────────────────────┘  │     │
// │  └──────────────────────┘  └──────────────────────────────────┘     │
// │                                                                     │
// │  Hirarki data: CLAB → CLABITEM                                      │
// │  - 1 Kategori Lab (CLAB) punya banyak Item Pemeriksaan (CLABITEM)   │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  KOMUNIKASI ANTAR KOMPONEN (Livewire Events)                       │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  1. User klik CLAB di tabel kiri                                    │
// │     master-clab ──dispatch('clab.selected')──► master-clabitem      │
// │                                                                     │
// │  2. User klik tombol CRUD (Tambah/Edit/Hapus)                       │
// │     master-clab     ──dispatch('master.laborat.openCreateClab')──►  │
// │     master-clabitem ──dispatch('master.laborat.openEditClabitem')──►│
// │     → Masing-masing actions mendengarkan & buka modal               │
// │                                                                     │
// │  3. Setelah CRUD berhasil (save/delete)                             │
// │     *-actions ──dispatch('master.laborat.saved')──► semua komponen  │
// │                                                                     │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  TABEL DATABASE                                                     │
// ├─────────────────────────────────────────────────────────────────────┤
// │                                                                     │
// │  lbmst_clabs     → Master kategori lab (clab_id, clab_desc)        │
// │  lbmst_clabitems → Master item pemeriksaan, FK: clab_id            │
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
    public string $searchClab = '';
    public int $itemsPerPage = 10;

    /* --- Pilihan CLAB --- */
    public ?string $selectedClabId = null;
    public string $selectedClabDesc = '';

    public function updatedSearchClab(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* --- Dispatch ke actions --- */
    public function openCreateClab(): void
    {
        $this->dispatch('master.laborat.openCreateClab');
    }

    public function openEditClab(string $id): void
    {
        $this->dispatch('master.laborat.openEditClab', clabId: $id);
    }

    public function requestDeleteClab(string $id): void
    {
        $this->dispatch('master.laborat.deleteClab', clabId: $id);
    }

    /* --- Pilih CLAB --- */
    public function selectClab(string $id, string $desc): void
    {
        $this->selectedClabId = $id;
        $this->selectedClabDesc = $desc;

        $this->dispatch('clab.selected', clabId: $id, clabDesc: $desc);
    }

    /* --- Refresh setelah save/delete --- */
    #[On('master.laborat.saved')]
    public function afterSaved(string $entity): void
    {
        if ($entity === 'clab') {
            $this->resetPage();
        }
    }

    /* --- Query CLAB --- */
    #[Computed]
    public function clabs()
    {
        $q = DB::table(DB::raw('lbmst_clabs c'))
            ->selectRaw(
                "
                c.clab_id,
                c.clab_desc,
                c.app_seq,
                COUNT(ci.clabitem_id) AS jumlah_item
            ",
            )
            ->leftJoin(DB::raw('lbmst_clabitems ci'), 'c.clab_id', '=', 'ci.clab_id')
            ->groupBy('c.clab_id', 'c.clab_desc', 'c.app_seq')
            ->orderBy('c.app_seq')
            ->orderBy('c.clab_desc');

        if (trim($this->searchClab) !== '') {
            $kw = mb_strtoupper(trim($this->searchClab));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(c.clab_desc) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(c.clab_id) LIKE ?', ["%{$kw}%"]);
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
                    Master Laboratorium
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Kategori lab & item pemeriksaan
                </p>
            </div>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6 space-y-6">

            {{-- ══ GRID: CLAB (kiri) + CLABITEM (kanan) ══════════════ --}}
            <div class="grid grid-cols-3 gap-2">

                {{-- ── CLAB ─────────────────────────────────────── --}}
                <div>
                    {{-- Toolbar CLAB --}}
                    <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div class="w-full lg:max-w-xs">
                                <x-input-label for="searchClab" value="Cari Kategori" class="sr-only" />
                                <x-text-input id="searchClab" type="text"
                                    wire:model.live.debounce.300ms="searchClab" placeholder="Cari kategori lab..."
                                    class="block w-full" />
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-28">
                                    <x-select-input wire:model.live="itemsPerPage">
                                        <option value="10">10</option>
                                        <option value="15">15</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                    </x-select-input>
                                </div>
                                <x-primary-button type="button" wire:click="openCreateClab">
                                    + Tambah Kategori
                                </x-primary-button>
                            </div>
                        </div>
                    </div>

                    {{-- Tabel CLAB --}}
                    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-5 py-3 font-semibold">KATEGORI LAB</th>
                                        <th class="px-5 py-3 font-semibold text-center">ITEM</th>
                                        <th class="px-5 py-3 font-semibold">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                    @forelse ($this->clabs as $clab)
                                        @php $isActive = $selectedClabId === $clab->clab_id; @endphp
                                        <tr wire:key="clab-{{ $clab->clab_id }}"
                                            wire:click="selectClab('{{ $clab->clab_id }}', '{{ addslashes($clab->clab_desc) }}')"
                                            class="cursor-pointer transition
                                           {{ $isActive
                                               ? 'bg-brand-green/5 dark:bg-brand-green/10 ring-1 ring-inset ring-brand-green/30 dark:ring-brand-green/40'
                                               : 'bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">

                                            {{-- KATEGORI --}}
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
                                                        {{ $clab->clab_desc }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="font-mono">{{ $clab->clab_id }}</span>
                                                    @if ($clab->app_seq)
                                                        <span>Seq: {{ $clab->app_seq }}</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- JUMLAH ITEM --}}
                                            <td class="px-5 py-4 align-top text-center">
                                                <x-badge variant="info">{{ $clab->jumlah_item }} Item</x-badge>
                                            </td>

                                            {{-- AKSI --}}
                                            <td class="px-5 py-4 align-top" wire:click.stop>
                                                <div class="flex flex-wrap gap-2">
                                                    <x-secondary-button type="button"
                                                        wire:click="openEditClab('{{ $clab->clab_id }}')" class="px-2 py-1 text-xs">
                                                        Edit
                                                    </x-secondary-button>
                                                    <x-confirm-button variant="danger" :action="'requestDeleteClab(\'' . $clab->clab_id . '\')'"
                                                        title="Hapus Kategori Lab"
                                                        message="Yakin hapus kategori {{ $clab->clab_desc }}?"
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
                                                Data kategori lab tidak ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $this->clabs->links() }}
                        </div>
                    </div>
                </div>

                {{-- ── CLABITEM (child component) ───────────────── --}}
                <div class="col-span-2">
                    <livewire:pages::master.master-laborat.clabitem.master-clabitem wire:key="master-clabitem" />
                </div>

            </div>

        </div>
    </div>

    {{-- Child: CRUD actions --}}
    <livewire:pages::master.master-laborat.clab.master-clab-actions wire:key="master-clab-actions" />
    <livewire:pages::master.master-laborat.clabitem.master-clabitem-actions wire:key="master-clabitem-actions" />

</div>
