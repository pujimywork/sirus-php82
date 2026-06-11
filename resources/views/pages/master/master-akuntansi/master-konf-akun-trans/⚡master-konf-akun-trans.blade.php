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
        $this->dispatch('master.konf-akun-trans.openCreate');
    }

    public function openEdit(string $confId): void
    {
        $this->dispatch('master.konf-akun-trans.openEdit', confId: $confId);
    }

    public function requestDelete(string $confId): void
    {
        $this->dispatch('master.konf-akun-trans.requestDelete', confId: $confId);
    }

    #[On('master.konf-akun-trans.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        $q = DB::table('tkacc_confacctxns as c')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'c.acc_id')
            ->select('c.conf_id', 'c.conf_desc', 'c.acc_id', 'a.acc_name')
            ->orderBy('c.conf_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(c.conf_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(c.conf_desc) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(c.acc_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Master Konfigurasi Akun Transaksi"
        subtitle="Mapping akun-default per jenis transaksi. Sumber: tkacc_confacctxns. Tiap CONF_ID menunjuk ke satu ACC_ID di acmst_accounts." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-1 gap-2">
                        <x-text-input type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari konfigurasi (kode/desc/akun)..." class="flex-1 max-w-md" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Konfigurasi
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
                                <th>Conf ID</th>
                                <th>Deskripsi</th>
                                <th>Acc ID</th>
                                <th>Nama Akun</th>
                                <th class="ds-c w-44">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="conf-{{ $row->conf_id }}">
                                    <td class="ds-td-token">{{ $row->conf_id }}</td>
                                    <td class="ds-td-strong">{{ $row->conf_desc ?: '—' }}</td>
                                    <td class="ds-td-token">{{ $row->acc_id }}</td>
                                    <td class="px-6 py-4" style="color:var(--muted)">
                                        {{ $row->acc_name ?: '—' }}
                                    </td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->conf_id }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->conf_id . '\')'"
                                                title="Hapus Konfigurasi"
                                                message="Yakin hapus konfigurasi {{ $row->conf_id }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Konfigurasi tidak ditemukan.</p>
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

            <livewire:pages::master.master-akuntansi.master-konf-akun-trans.master-konf-akun-trans-actions wire:key="master-konf-akun-trans-actions" />
        </div>
    </div>
</div>
