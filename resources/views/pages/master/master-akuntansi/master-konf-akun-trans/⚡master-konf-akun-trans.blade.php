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

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
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
                    </div>
                </div>
            </div>

            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">CONF ID</th>
                                <th class="px-4 py-3 font-semibold">DESKRIPSI</th>
                                <th class="px-4 py-3 font-semibold">ACC ID</th>
                                <th class="px-4 py-3 font-semibold">NAMA AKUN</th>
                                <th class="px-4 py-3 font-semibold w-44">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="conf-{{ $row->conf_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->conf_id }}</td>
                                    <td class="px-4 py-3">{{ $row->conf_desc ?: '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->acc_id }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $row->acc_name ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->conf_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->conf_id . '\')'"
                                                title="Hapus Konfigurasi"
                                                message="Yakin hapus konfigurasi {{ $row->conf_id }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Konfigurasi tidak ditemukan.
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

            <livewire:pages::master.master-akuntansi.master-konf-akun-trans.master-konf-akun-trans-actions wire:key="master-konf-akun-trans-actions" />
        </div>
    </div>
</div>
