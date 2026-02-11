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
    public int $itemsPerPage = 7;

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
        $this->dispatch('$refresh');
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
                'p.stockwh',
                'p.stockklinik',
                'p.stock_ok',
                'p.stock_ugd',
                'p.stock_laborat',
                'p.stock_utara',
                'p.stock_selatan',
                'p.stock_vk',
                'p.stock_tu',
                'p.stock_arm',
                'p.stock_rd',
                'p.product_status',
                'p.limit_stock',
                'p.limit_stockwh',
                'p.limit_stockklinik',
                'p.active_status',
                'p.product_number',
                'p.stock_print_number',
                'p.stockwh_print_number',
                'p.qty_per_box',
                'p.takar',
                'p.qty_box',
                'p.fornas_nonfornas_status',
                'p.product_id_satusehat',
                'p.product_name_satusehat',
                
                // Nama dari tabel relasi (untuk display)
                'u.uom_desc as uom_name',
                'c.cat_desc as cat_name',
                'g.grp_name',
                's.supp_name'
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
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Obat
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data obat & produk untuk aplikasi
            </p>
        </div>
    </header>

    {{-- CONTENT --}}
    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

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
                            + Tambah Obat
                        </x-primary-button>
                    </div>
                </div>
            </div>


            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA dengan indicator --}}
                <div
                    class="scroll-container relative overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">

                    {{-- Scroll Indicator --}}
                    <div
                        class="absolute top-0 right-0 z-20 flex items-center h-12 px-2 bg-gradient-to-l from-gray-50 to-transparent dark:from-gray-800">
                        <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                            <span>Geser ke kanan →</span>
                        </div>
                    </div>

                    <table class="min-w-full text-sm" style="min-width: 2400px;">
                        {{-- TABLE HEAD (sticky) --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                {{-- Kolom Fixed (tidak ikut scroll) --}}
                                <th class="sticky left-0 z-20 w-16 px-4 py-3 font-semibold bg-gray-50 dark:bg-gray-800">
                                    NO
                                </th>
                                <th
                                    class="sticky z-20 w-32 px-4 py-3 font-semibold left-16 bg-gray-50 dark:bg-gray-800">
                                    AKSI
                                </th>

                                {{-- Kolom Data (bisa discroll) --}}
                                <th class="px-4 py-3 font-semibold min-w-[120px]">ID PRODUK</th>
                                <th class="px-4 py-3 font-semibold min-w-[250px]">NAMA PRODUK</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">KODE</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">SATUAN</th>
                                <th class="px-4 py-3 font-semibold min-w-[150px]">KATEGORI</th>
                                <th class="px-4 py-3 font-semibold min-w-[150px]">GRUP</th>
                                <th class="px-4 py-3 font-semibold min-w-[180px]">SUPPLIER</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">HARGA BELI</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">HARGA JUAL</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">STOK WH</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">STOK KLINIK</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK OK</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK UGD</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">STOK LAB</th>
                                <th class="px-4 py-3 font-semibold min-w-[90px]">STOK UTARA</th>
                                <th class="px-4 py-3 font-semibold min-w-[90px]">STOK SELATAN</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK VK</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK TU</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK ARM</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">STOK RD</th>
                                <th class="px-4 py-3 font-semibold min-w-[90px]">LIMIT STOK</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">LIMIT STOK WH</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">LIMIT STOK KLINIK</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">NO PRODUK</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">PRINT NO</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">PRINT NO WH</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">QTY/BOX</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">TAKAR</th>
                                <th class="px-4 py-3 font-semibold min-w-[80px]">QTY BOX</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">STATUS PRODUK</th>
                                <th class="px-4 py-3 font-semibold min-w-[100px]">STATUS AKTIF</th>
                                <th class="px-4 py-3 font-semibold min-w-[120px]">FORNAS</th>
                                <th class="px-4 py-3 font-semibold min-w-[150px]">ID SATUSEHAT</th>
                                <th class="px-4 py-3 font-semibold min-w-[200px]">NAMA SATUSEHAT</th>
                            </tr>
                        </thead>

                        {{-- TABLE BODY --}}
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                            <tr wire:key="obat-row-{{ $row->product_id }}"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                {{-- Kolom Fixed --}}
                                <td
                                    class="sticky left-0 z-10 w-16 px-4 py-3 bg-white border-r border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                                    {{ $loop->iteration }}
                                </td>
                                <td
                                    class="sticky z-10 w-32 px-4 py-3 bg-white border-r border-gray-200 left-16 dark:bg-gray-900 dark:border-gray-700">
                                    <div class="flex gap-1">
                                        {{-- Edit Button --}}
                                        <x-outline-button type="button" wire:click="openEdit('{{ $row->product_id }}')"
                                            class="px-2 py-1 text-xs whitespace-nowrap">
                                            Edit
                                        </x-outline-button>

                                        {{-- Delete Button --}}
                                        <x-confirm-button variant="danger" size="xs"
                                            :action="'requestDelete(\'' . $row->product_id . '\')'" title="Hapus Obat"
                                            message="Yakin hapus data obat {{ $row->product_name }}?"
                                            confirmText="Ya, hapus" cancelText="Batal" class="whitespace-nowrap">
                                            Hapus
                                        </x-confirm-button>
                                    </div>
                                </td>

                                {{-- Kolom Data --}}
                                <td class="px-4 py-3 font-mono text-sm">{{ $row->product_id }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $row->product_name }}</td>
                                <td class="px-4 py-3">{{ $row->kode }}</td>
                                <td class="px-4 py-3">{{ $row->uom_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row->cat_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row->grp_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row->supp_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->cost_price ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->sales_price ?? 0, 0, ',', '.')
                                    }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stockwh ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stockklinik ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_ok ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_ugd ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_laborat ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_utara ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_selatan ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_vk ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_tu ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_arm ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_rd ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->limit_stock ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->limit_stockwh ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->limit_stockklinik ?? 0 }}</td>
                                <td class="px-4 py-3 font-mono text-center">{{ $row->product_number ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stock_print_number ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->stockwh_print_number ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->qty_per_box ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->takar ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->qty_box ?? '-' }}</td>

                                {{-- Status Produk --}}
                                <td class="px-4 py-3">
                                    <x-badge :variant="(string) $row->product_status === '1' ? 'success' : 'gray'"
                                        size="sm">
                                        {{ (string) $row->product_status === '1' ? 'Aktif' : 'Non' }}
                                    </x-badge>
                                </td>

                                {{-- Status Aktif --}}
                                <td class="px-4 py-3">
                                    <x-badge :variant="(string) $row->active_status === '1' ? 'success' : 'gray'"
                                        size="sm">
                                        {{ (string) $row->active_status === '1' ? 'Aktif' : 'Non' }}
                                    </x-badge>
                                </td>

                                {{-- Fornas Status --}}
                                <td class="px-4 py-3">
                                    <x-badge
                                        :variant="(string) $row->fornas_nonfornas_status === '1' ? 'primary' : 'warning'"
                                        size="sm">
                                        {{ (string) $row->fornas_nonfornas_status === '1' ? 'Fornas' : 'Non' }}
                                    </x-badge>
                                </td>

                                <td class="px-4 py-3 font-mono text-xs text-center">{{ $row->product_id_satusehat ?? '-'
                                    }}</td>
                                <td class="px-4 py-3">{{ $row->product_name_satusehat ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="38" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
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

                {{-- PAGINATION STICKY di bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-obat.master-obat-actions />
        </div>
    </div>
</div>