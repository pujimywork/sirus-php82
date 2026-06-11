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

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

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
    <x-page-title
        title="Master Obat Kronis BPJS"
        subtitle="Daftar obat kronis BPJS — max qty per resep &amp; tarif klaim. Sumber: rsmst_listobatbpjses." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="w-32">Product ID</th>
                                <th>Nama Obat</th>
                                <th>Nama BPJS</th>
                                <th class="w-28 text-right">Max Qty</th>
                                <th class="w-32 text-right">Tarif Klaim</th>
                                <th class="ds-c w-40">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="obat-kronis-{{ $row->product_id }}">
                                    <td class="ds-td-token">{{ $row->product_id }}</td>
                                    <td class="ds-td-strong">{{ $row->product_name ?: '—' }}</td>
                                    <td class="px-6 py-4">{{ $row->obat_kronis_bpjs ?: '—' }}</td>
                                    <td class="px-6 py-4 text-right tabular-nums">
                                        {{ $row->maxqty !== null ? rtrim(rtrim(number_format((float) $row->maxqty, 2, ',', '.'), '0'), ',') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums">
                                        {{ $row->tarif_klaim !== null ? number_format((float) $row->tarif_klaim, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->product_id }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->product_id . '\')'"
                                                title="Hapus Obat Kronis"
                                                message="Yakin hapus {{ $row->product_id }} ({{ $row->product_name }})?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data obat kronis tidak ditemukan.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-obat-kronis.master-obat-kronis-actions wire:key="master-obat-kronis-actions" />
        </div>
    </div>
</div>
