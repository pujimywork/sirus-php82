<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* --- Interaksi terpilih (dari event) --- */
    public ?string $selectedIntDesc = null;

    /* --- Filter produk --- */
    public string $searchProduk = '';
    public int $itemsPerPageProduk = 10;

    public function updatedSearchProduk(): void
    {
        $this->resetPage('pageProduk');
    }

    public function updatedItemsPerPageProduk(): void
    {
        $this->resetPage('pageProduk');
    }

    /* --- Terima interaksi terpilih --- */
    #[On('interaksi.selected')]
    public function onInteraksiSelected(string $intDesc): void
    {
        $this->selectedIntDesc = $intDesc;
        $this->searchProduk = '';
        $this->resetPage('pageProduk');
    }

    #[On('interaksi.cleared')]
    public function onInteraksiCleared(): void
    {
        $this->selectedIntDesc = null;
    }

    /* --- Dispatch ke actions --- */
    public function openAddProduk(): void
    {
        if (! $this->selectedIntDesc) {
            return;
        }
        $this->dispatch('master.interaksi.openAddProduk', intDesc: $this->selectedIntDesc);
    }

    public function requestDeleteProduk(string $productId): void
    {
        $this->dispatch('master.interaksi.deleteProduk', intDesc: $this->selectedIntDesc, productId: $productId);
    }

    /* --- Refresh setelah save/delete --- */
    #[On('master.interaksi.produkSaved')]
    public function afterSaved(string $intDesc = ''): void
    {
        if ($intDesc !== '' && $intDesc === $this->selectedIntDesc) {
            unset($this->computedPropertyCache);
            $this->resetPage('pageProduk');
        }
    }

    /* --- Query produk detail --- */
    #[Computed]
    public function produks()
    {
        if (! $this->selectedIntDesc) {
            return null;
        }

        $q = DB::table(DB::raw('immst_interaksi_proddtls d'))
            ->selectRaw('d.product_id, p.product_name, p.kode, p.takar')
            ->leftJoin(DB::raw('immst_products p'), 'd.product_id', '=', 'p.product_id')
            ->where('d.int_desc', $this->selectedIntDesc)
            ->orderBy('p.product_name');

        if (trim($this->searchProduk) !== '') {
            $kw = mb_strtoupper(trim($this->searchProduk));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(p.product_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(d.product_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereExists(function ($ex) use ($kw) {
                        $ex->select(DB::raw('1'))
                           ->from('immst_productcontents as pc')
                           ->leftJoin('immst_contents as c', 'pc.cont_id', '=', 'c.cont_id')
                           ->whereColumn('pc.product_id', 'd.product_id')
                           ->whereRaw('UPPER(c.cont_desc) LIKE ?', ["%{$kw}%"]);
                    });
            });
        }

        $paginator = $q->paginate($this->itemsPerPageProduk, ['*'], 'pageProduk');
        $this->attachKandungan($paginator->items());

        return $paginator;
    }

    /* --- Lampirkan daftar kandungan (zat aktif) ke tiap item produk --- */
    private function attachKandungan(array $items): void
    {
        $ids = array_values(array_unique(array_filter(array_map(fn($i) => $i->product_id ?? null, $items))));

        $map = [];
        if (! empty($ids)) {
            $rows = DB::table('immst_productcontents as pc')
                ->leftJoin('immst_contents as c', 'pc.cont_id', '=', 'c.cont_id')
                ->leftJoin('immst_uoms as u', 'pc.uom_id', '=', 'u.uom_id')
                ->whereIn('pc.product_id', $ids)
                ->orderBy('pc.product_id')
                ->orderBy('pc.prc_dtl')
                ->get(['pc.product_id', 'c.cont_desc', 'pc.prc_qty', 'u.uom_desc']);

            foreach ($rows as $r) {
                $map[$r->product_id][] = $r;
            }
        }

        foreach ($items as $item) {
            $item->kandungan_list = $map[$item->product_id] ?? [];
        }
    }
};
?>

<div class="flex flex-col h-full min-h-0">
    @if ($selectedIntDesc)
        <div wire:loading.class="opacity-60" wire:target="onInteraksiSelected" class="flex flex-col flex-1 min-h-0">

            {{-- Toolbar Produk --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-3 w-full lg:max-w-xs">
                        <x-text-input type="text" wire:model.live.debounce.300ms="searchProduk"
                            placeholder="Cari produk..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPageProduk">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openAddProduk">
                            + Tambah Produk
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- Tabel Produk — tema kartu (mirip Daftar RJ) --}}
            <div class="flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 px-3 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-sm border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-4 py-3">
                                    Produk
                                    <span class="font-normal normal-case text-brand dark:text-brand-lime ml-1">&mdash; {{ $selectedIntDesc }}</span>
                                </th>
                                <th class="px-4 py-3 w-32 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->produks as $produk)
                                <tr wire:key="produk-{{ $produk->product_id }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                           bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800">
                                    {{-- PRODUK --}}
                                    <td class="px-4 py-3 space-y-1 align-middle rounded-l-2xl">
                                        <div class="font-semibold text-base text-gray-800 dark:text-gray-100">
                                            {{ $produk->product_name ?? '(produk tidak ditemukan)' }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ $produk->product_id }}</span>
                                            @if ($produk->kode)
                                                <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold
                                                             bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                                    {{ $produk->kode }}
                                                </span>
                                            @endif
                                            @if ($produk->takar)
                                                <span class="text-gray-400 dark:text-gray-500">{{ $produk->takar }}</span>
                                            @endif
                                        </div>
                                        @if (! empty($produk->kandungan_list))
                                            <div class="flex flex-wrap items-center gap-1 pt-1">
                                                <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 dark:text-gray-500 mr-0.5">Kandungan</span>
                                                @foreach ($produk->kandungan_list as $k)
                                                    <span class="px-1.5 py-0.5 rounded text-[11px] font-medium
                                                                 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                        {{ $k->cont_desc ?? '-' }}@if ($k->prc_qty !== null && $k->prc_qty !== '')<span class="opacity-70"> {{ rtrim(rtrim(number_format((float) $k->prc_qty, 2, '.', ''), '0'), '.') }}{{ $k->uom_desc ? ' ' . $k->uom_desc : '' }}</span>@endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-3 align-middle text-center rounded-r-2xl">
                                        <x-confirm-button variant="danger"
                                            :action="'requestDeleteProduk(' . \Illuminate\Support\Js::from($produk->product_id) . ')'"
                                            title="Hapus Produk"
                                            message="Keluarkan '{{ $produk->product_name ?? $produk->product_id }}' dari interaksi ini?"
                                            confirmText="Ya, hapus" cancelText="Batal"
                                            class="px-2 py-1 text-xs">
                                            Hapus
                                        </x-confirm-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Belum ada produk pada interaksi ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->produks->links() }}
                </div>
            </div>

        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
            <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            <p class="text-sm">Pilih interaksi di sebelah kiri untuk melihat daftar produk.</p>
        </div>
    @endif
</div>
