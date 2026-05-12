<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->dispatch('master.obat-kronis.openCreate');
    }

    public function openEdit(string $productId): void
    {
        $this->dispatch('master.obat-kronis.openEdit', productId: $productId);
    }

    public function requestDelete(string $productId): void
    {
        $this->dispatch('master.obat-kronis.requestDelete', productId: $productId);
    }

    #[On('master.obat-kronis.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_listobatbpjses as k')
            ->leftJoin('immst_products as p', 'k.product_id', '=', 'p.product_id')
            ->select(
                'k.product_id',
                'k.maxqty',
                'k.tarif_klaim',
                'k.obat_kronis_bpjs',
                'p.product_name',
            )
            ->orderBy('p.product_name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(k.product_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(k.obat_kronis_bpjs) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(p.product_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Obat Kronis BPJS
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Daftar obat kronis BPJS — max qty per resep & tarif klaim. Sumber: <span class="font-mono">rsmst_listobatbpjses</span>.
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Obat Kronis" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari kode/nama obat / nama BPJS..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Obat Kronis
                        </x-primary-button>
                    </div>
                </div>
            </div>

            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold w-32">PRODUCT ID</th>
                                <th class="px-4 py-3 font-semibold">NAMA OBAT</th>
                                <th class="px-4 py-3 font-semibold">NAMA BPJS</th>
                                <th class="px-4 py-3 font-semibold w-28 text-right">MAX QTY</th>
                                <th class="px-4 py-3 font-semibold w-32 text-right">TARIF KLAIM</th>
                                <th class="px-4 py-3 font-semibold w-40">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="obat-kronis-{{ $row->product_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->product_id }}</td>
                                    <td class="px-4 py-3">{{ $row->product_name ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $row->obat_kronis_bpjs ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ $row->maxqty !== null ? rtrim(rtrim(number_format((float) $row->maxqty, 2, ',', '.'), '0'), ',') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ $row->tarif_klaim !== null ? number_format((float) $row->tarif_klaim, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->product_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->product_id . '\')'"
                                                title="Hapus Obat Kronis"
                                                message="Yakin hapus {{ $row->product_id }} ({{ $row->product_name }})?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data obat kronis tidak ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-obat-kronis.master-obat-kronis-actions wire:key="master-obat-kronis-actions" />
        </div>
    </div>
</div>
