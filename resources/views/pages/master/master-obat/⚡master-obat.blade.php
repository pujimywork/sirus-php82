<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* -------------------------
     | Filter & Pagination State
     * ------------------------- */
    public string $searchKeyword = '';
    public int $itemsPerPage = 10;

    /* -------------------------
     | Update Search Keyword
             * Fungsi: Reset halaman saat keyword berubah
     * ------------------------- */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Update Items Per Page
             * Fungsi: Reset halaman saat jumlah item per halaman berubah
     * ------------------------- */
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Reset Filters
             * Fungsi: Reset keyword pencarian & per-halaman ke default
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    /* -------------------------
     | Open Create Modal
             * Fungsi: Trigger modal create di child component
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.obat.openCreate');
    }

    /* -------------------------
     | Open Edit Modal
             * Fungsi: Trigger modal edit di child component
     * ------------------------- */
    public function openEdit(string $productId): void
    {
        $this->dispatch('master.obat.openEdit', productId: $productId);
    }

    /* -------------------------
     | Request Delete
             * Fungsi: Delegate proses delete ke child component (actions)
     * ------------------------- */
    public function requestDelete(string $productId): void
    {
        $this->dispatch('master.obat.requestDelete', productId: $productId);
    }

    /* -------------------------
     | Refresh After Saved
             * Fungsi: Refresh grid setelah data disimpan dari child component
     * ------------------------- */
    #[On('master.obat.saved')]
    public function refreshAfterSaved(): void
    {
        // resetPage kadang tidak trigger kalau sudah di page 1 → paksa refresh
        $this->resetPage();
    }

    /* -------------------------
     | Base Query
             * Fungsi: Query builder dasar dengan filter search
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('immst_products as p')
            ->leftJoin('immst_uoms as u', 'p.uom_id', '=', 'u.uom_id')
            ->leftJoin('immst_catproducts as c', 'p.cat_id', '=', 'c.cat_id')
            ->leftJoin('immst_groupproducts as g', 'p.grp_id', '=', 'g.grp_id')
            ->leftJoin('immst_suppliers as s', 'p.supp_id', '=', 's.supp_id')
            ->select(
                // Semua kolom dari immst_products
                'p.product_id',
                'p.product_name',
                'p.kode',
                'p.uom_id',
                'p.cat_id',
                'p.grp_id',
                'p.supp_id',
                'p.cost_price',
                'p.sales_price',
                'p.stock',
                'p.product_id_satusehat',

                // Nama dari tabel relasi (untuk display)
                'u.uom_desc as uom_name',
                'c.cat_desc as cat_name',
                'g.grp_name',
                's.supp_name',
            )
            ->orderBy('p.product_name', 'asc');

        // Filter berdasarkan keyword pencarian
        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                // Jika keyword adalah angka, cari di product_id
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('p.product_id', $searchKeyword);
                }

                // Cari di semua kolom text
                $subQuery
                    ->orWhereRaw('UPPER(p.product_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(p.kode) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(u.uom_desc) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(c.cat_desc) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(s.supp_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(g.grp_name) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    /* -------------------------
     | Rows (Paginated Data)
             * Fungsi: Data obat dengan pagination
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>

<div>
    {{-- Custom Scrollbar Style --}}
    <style>
        .scroll-container {
            scroll-behavior: smooth;
        }

        .scroll-container::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .scroll-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .scroll-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .dark .scroll-container::-webkit-scrollbar-track {
            background: #374151;
        }

        .dark .scroll-container::-webkit-scrollbar-thumb {
            background: #6b7280;
        }

        .dark .scroll-container::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>

    {{-- HEADER --}}
    <x-page-title
        title="Master Obat"
        subtitle="Kelola data obat &amp; produk untuk aplikasi" />

    {{-- CONTENT --}}
    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Obat" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari obat..." class="block w-full" />
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        {{-- Per Page Selector --}}
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        {{-- Tambah Obat Button --}}
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Obat Baru
                        </x-primary-button>

                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>


            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="min-w-[60px]">No</th>
                                <th class="min-w-[260px]">Produk</th>
                                <th class="min-w-[100px]">Satuan</th>
                                <th class="min-w-[150px]">Kategori</th>
                                <th class="min-w-[180px]">Supplier</th>
                                <th class="text-right min-w-[140px]">Harga</th>
                                <th class="ds-c min-w-[120px]">Aksi</th>
                            </tr>
                        </thead>

                        {{-- TABLE BODY --}}
                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="obat-row-{{ $row->product_id }}">

                                    <td class="ds-td-token">
                                        {{ ($this->rows->currentPage() - 1) * $this->rows->perPage() + $loop->iteration }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $row->product_name }}</div>
                                        <div class="font-mono text-xs" style="color:var(--muted)">
                                            {{ $row->product_id }}@if (!empty($row->kode)) · {{ $row->kode }}@endif
                                        </div>
                                        @if (!empty($row->product_id_satusehat))
                                            <div class="mt-1">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300"
                                                    title="Kode Satu Sehat (KFA)">
                                                    Satu Sehat
                                                    <span class="font-mono">{{ $row->product_id_satusehat }}</span>
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">{{ $row->uom_name ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <div>{{ $row->cat_name ?? '-' }}</div>
                                        @if (!empty($row->grp_name))
                                            <div class="text-xs" style="color:var(--muted)">{{ $row->grp_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">{{ $row->supp_name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="font-medium text-gray-900 dark:text-white">Rp {{ number_format($row->sales_price ?? 0, 0, ',', '.') }}</div>
                                        <div class="text-xs" style="color:var(--muted)">Beli: Rp {{ number_format($row->cost_price ?? 0, 0, ',', '.') }}</div>
                                    </td>

                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->product_id }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->product_id . '\')'"
                                                title="Hapus Obat"
                                                message="Yakin hapus data obat {{ $row->product_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center" style="color:var(--muted)">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span>Data obat belum ada.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
            {{-- PAGINATION STICKY di bawah card --}}
            <div
                class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                {{ $this->rows->links() }}
            </div>
        </div>

        {{-- Child actions component (modal CRUD) --}}
        <livewire:pages::master.master-obat.master-obat-actions wire:key="master-obat-actions" />
    </div>
</div>
</div>
