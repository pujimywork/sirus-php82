<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  MASTER INTERAKSI OBAT — Kelompok Interaksi & Produk Anggota  ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// FILE INI ADALAH HALAMAN UTAMA (entry point)
// Route: /master/interaksi-obat → pages::master.master-interaksi-obat.interaksi-obat-hdr.master-interaksi-obat-hdr
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  STRUKTUR FOLDER                                                   │
// ├─────────────────────────────────────────────────────────────────────┤
// │  master-interaksi-obat/                                          │
// │  ├── interaksi-obat-hdr/                                         │
// │  │   ├── ⚡master-interaksi-obat-hdr.blade.php         ← FILE INI│
// │  │   └── ⚡master-interaksi-obat-hdr-actions.blade.php ← CRUD hdr│
// │  └── interaksi-obat-dtl/                                         │
// │      ├── ⚡master-interaksi-obat-dtl.blade.php         ← Grid prd│
// │      └── ⚡master-interaksi-obat-dtl-actions.blade.php ← Tambah  │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  CARA KERJA — 2 kolom (grid 50:50), mirip Master Kamar             │
// ├─────────────────────────────────────────────────────────────────────┤
// │  ┌──────────────────────┐  ┌──────────────────────┐                 │
// │  │ HDR (kiri)           │  │ DTL (kanan)          │                 │
// │  │ interaksi-hdr     │  │ interaksi-dtl     │                 │
// │  │                      │  │                      │                 │
// │  │  ┌────────────────┐  │  │ (kosong sampai       │                 │
// │  │  │ Interaksi ◄─┼──┼──┼─ baris dipilih)      │                 │
// │  │  │ A / B / C ...  │  │  │  ┌────────────────┐  │                 │
// │  │  └────────────────┘  │  │  │ Paracetamol    │  │                 │
// │  │                      │  │  │ Warfarin       │  │                 │
// │  └──────────────────────┘  │  └────────────────┘  │                 │
// │                            └──────────────────────┘                 │
// │  Hirarki: HDR (int_desc) → DTL (product_id)                        │
// └─────────────────────────────────────────────────────────────────────┘
//
// ┌─────────────────────────────────────────────────────────────────────┐
// │  TABEL DATABASE (nama tabel tetap pakai skema lama)                │
// ├─────────────────────────────────────────────────────────────────────┤
// │  immst_interaksi_prodhdrs → header (int_desc)                       │
// │  immst_interaksi_proddtls → detail (int_desc, product_id)           │
// │  immst_products           → master obat (product_id, product_name)  │
// └─────────────────────────────────────────────────────────────────────┘

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* --- Filter --- */
    public string $searchInteraksi = '';
    public int $itemsPerPage = 10;

    /* --- Pilihan interaksi --- */
    public ?string $selectedIntDesc = null;

    public function updatedSearchInteraksi(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* --- Dispatch ke actions --- */
    public function openCreateInteraksi(): void
    {
        $this->dispatch('master.interaksi.openCreate');
    }

    public function openEditInteraksi(string $intDesc): void
    {
        $this->dispatch('master.interaksi.openEdit', intDesc: $intDesc);
    }

    public function requestDeleteInteraksi(string $intDesc): void
    {
        $this->dispatch('master.interaksi.delete', intDesc: $intDesc);
    }

    /* --- Pilih interaksi --- */
    public function selectInteraksi(string $intDesc): void
    {
        $this->selectedIntDesc = $intDesc;
        $this->dispatch('interaksi.selected', intDesc: $intDesc);
    }

    /* --- Refresh setelah save/delete --- */
    #[On('master.interaksi.saved')]
    public function afterSaved(string $oldIntDesc = '', string $newIntDesc = ''): void
    {
        // Hanya relevan jika yang berubah adalah baris yang sedang dipilih.
        if ($oldIntDesc !== '' && $this->selectedIntDesc === $oldIntDesc) {
            if ($newIntDesc === '') {
                // Dihapus → kosongkan panel produk.
                $this->selectedIntDesc = null;
                $this->dispatch('interaksi.cleared');
            } elseif ($newIntDesc !== $oldIntDesc) {
                // Di-rename → arahkan panel produk ke nama baru.
                $this->selectedIntDesc = $newIntDesc;
                $this->dispatch('interaksi.selected', intDesc: $newIntDesc);
            }
        }

        unset($this->computedPropertyCache);
        $this->resetPage();
    }

    /* --- Rekap keseluruhan --- */
    #[Computed]
    public function rekap(): array
    {
        $totalHdr = DB::table('immst_interaksi_prodhdrs')->count();
        $totalDtl = DB::table('immst_interaksi_proddtls')->count();

        return [
            'totalHdr' => (int) $totalHdr,
            'totalDtl' => (int) $totalDtl,
        ];
    }

    /* --- Query interaksi (header) + jumlah produk --- */
    #[Computed]
    public function interaksis()
    {
        $q = DB::table(DB::raw('immst_interaksi_prodhdrs h'))
            ->selectRaw('h.int_desc, COUNT(d.product_id) AS jumlah_produk')
            ->leftJoin(DB::raw('immst_interaksi_proddtls d'), 'h.int_desc', '=', 'd.int_desc')
            ->groupBy('h.int_desc')
            ->orderBy('h.int_desc');

        if (trim($this->searchInteraksi) !== '') {
            $kw = mb_strtoupper(trim($this->searchInteraksi));
            $q->whereRaw('UPPER(h.int_desc) LIKE ?', ["%{$kw}%"]);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    {{-- ══ HEADER ══════════════════════════════════════════════════ --}}
    <x-page-title
        title="Master Interaksi Obat"
        subtitle="Kelompok interaksi obat & produk anggotanya" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6 space-y-6">

            {{-- ══ GRID: HDR (kiri) + DTL (kanan) ═══════════════════ --}}
            <div class="grid grid-cols-2 gap-2 flex-1 min-h-0">

                {{-- ── HDR (INTERAKSI) ──────────────────────────── --}}
                <div class="flex flex-col min-h-0">
                    {{-- Toolbar --}}
                    <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div class="w-full lg:max-w-xs">
                                <x-input-label for="searchInteraksi" value="Cari Interaksi" class="sr-only" />
                                <x-text-input id="searchInteraksi" type="text"
                                    wire:model.live.debounce.300ms="searchInteraksi" placeholder="Cari interaksi..."
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
                                <x-primary-button type="button" wire:click="openCreateInteraksi">
                                    + Tambah Interaksi Baru
                                </x-primary-button>
                            </div>
                        </div>
                    </div>

                    {{-- Rekap keseluruhan --}}
                    @php $stats = $this->rekap; @endphp
                    <div class="flex items-center gap-4 px-5 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 text-xs flex-wrap">
                        <span class="px-1.5 py-0.5 rounded bg-brand-green/10 dark:bg-brand-lime/10 font-bold text-[10px] uppercase tracking-wider text-brand-green dark:text-brand-lime">Keseluruhan</span>
                        <div class="flex items-center gap-1.5" title="Jumlah kelompok interaksi">
                            <span class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Interaksi</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['totalHdr'] }}</span>
                        </div>
                        <span class="hidden sm:inline-block h-4 w-px bg-gray-300 dark:bg-gray-600"></span>
                        <div class="flex items-center gap-1.5" title="Total baris produk di seluruh interaksi">
                            <span class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Produk</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['totalDtl'] }}</span>
                        </div>
                    </div>

                    {{-- Tabel HDR — tema kartu (mirip Daftar RJ) --}}
                    <div class="flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex-1 min-h-0 px-3 overflow-x-auto overflow-y-auto rounded-t-2xl">
                            <table class="w-full min-w-full text-sm border-separate border-spacing-y-2">
                                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                    <tr class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                        <th class="px-4 py-3">Interaksi</th>
                                        <th class="px-4 py-3 w-28">Produk</th>
                                        <th class="px-4 py-3 w-32 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->interaksis as $row)
                                        @php $isActive = $selectedIntDesc === $row->int_desc; @endphp
                                        <tr wire:key="interaksi-{{ md5($row->int_desc) }}"
                                            wire:click="selectInteraksi(@js($row->int_desc))"
                                            class="cursor-pointer transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                           {{ $isActive
                                               ? 'bg-green-50 dark:bg-emerald-900/15 ring-2 ring-brand-green/50 border-l-4 border-brand-green'
                                               : 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800' }}">

                                            {{-- INTERAKSI --}}
                                            <td class="px-4 py-3 align-middle rounded-l-2xl">
                                                <div class="flex items-start gap-2">
                                                    @if ($isActive)
                                                        <svg class="w-4 h-4 mt-0.5 text-brand shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                    <span class="font-normal leading-snug {{ $isActive ? 'text-brand dark:text-brand-lime' : 'text-gray-700 dark:text-gray-200' }}">
                                                        {{ $row->int_desc }}
                                                    </span>
                                                </div>
                                            </td>

                                            {{-- PRODUK --}}
                                            <td class="px-4 py-3 align-middle">
                                                <x-badge variant="info">{{ $row->jumlah_produk }} Produk</x-badge>
                                            </td>

                                            {{-- AKSI --}}
                                            <td class="px-4 py-3 align-middle rounded-r-2xl" wire:click.stop>
                                                <div class="flex flex-wrap justify-center gap-2">
                                                    <x-secondary-button type="button"
                                                        wire:click="openEditInteraksi(@js($row->int_desc))" class="px-2 py-1 text-xs">
                                                        Edit
                                                    </x-secondary-button>
                                                    <x-confirm-button variant="danger"
                                                        :action="'requestDeleteInteraksi(' . \Illuminate\Support\Js::from($row->int_desc) . ')'"
                                                        title="Hapus Interaksi"
                                                        message="Yakin hapus interaksi '{{ $row->int_desc }}' beserta {{ $row->jumlah_produk }} produk di dalamnya?"
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
                                                Data interaksi tidak ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $this->interaksis->links() }}
                        </div>
                    </div>
                </div>

                {{-- ── DTL (PRODUK) child component ─────────────────── --}}
                <livewire:pages::master.master-interaksi-obat.interaksi-obat-dtl.master-interaksi-obat-dtl wire:key="master-interaksi-obat-dtl" />

            </div>

        </div>
    </div>

    {{-- Child: CRUD actions --}}
    <livewire:pages::master.master-interaksi-obat.interaksi-obat-hdr.master-interaksi-obat-hdr-actions wire:key="master-interaksi-obat-hdr-actions" />
    <livewire:pages::master.master-interaksi-obat.interaksi-obat-dtl.master-interaksi-obat-dtl-actions wire:key="master-interaksi-obat-dtl-actions" />

</div>
