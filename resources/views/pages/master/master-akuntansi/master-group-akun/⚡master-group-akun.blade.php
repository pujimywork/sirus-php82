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
        $this->dispatch('master.group-akun.openCreate');
    }

    public function openEdit(string $graId): void
    {
        $this->dispatch('master.group-akun.openEdit', graId: $graId);
    }

    public function requestDelete(string $graId): void
    {
        $this->dispatch('master.group-akun.requestDelete', graId: $graId);
    }

    #[On('master.group-akun.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('tkacc_gr_accountses')
            ->select('gra_id', 'gra_desc', 'gra_status', 'dk_status')
            ->orderBy('gra_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(gra_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(gra_desc) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Master Group Akun"
        subtitle="Pengelompokan akun (mis. AKTIVA, PASIVA, MODAL, PENDAPATAN, BIAYA) — sumber: tkacc_gr_accountses." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Group Akun" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari group akun..." class="block w-full" />
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
                            + Tambah Group Akun
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr class="text-left">
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400 w-24">ID</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">Deskripsi</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-center text-gray-500 dark:text-gray-400 w-28">Debit/Kredit</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-center text-gray-500 dark:text-gray-400 w-32">Laporan</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-center text-gray-500 dark:text-gray-400 w-52">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-500 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-400">
                            @forelse ($this->rows as $row)
                                <tr wire:key="gr-akun-{{ $row->gra_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-6 py-4 font-mono text-sm text-gray-600 dark:text-gray-300">{{ $row->gra_id }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $row->gra_desc }}</td>
                                    <td class="px-6 py-4 text-center">
                                        @if ((string) $row->dk_status === 'D')
                                            <x-badge variant="info">Debit</x-badge>
                                        @elseif ((string) $row->dk_status === 'K')
                                            <x-badge variant="purple">Kredit</x-badge>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $row->dk_status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if ((string) $row->gra_status === 'N')
                                            <x-badge variant="info">Neraca</x-badge>
                                        @elseif ((string) $row->gra_status === 'L')
                                            <x-badge variant="warning">Laba-Rugi</x-badge>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $row->gra_status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->gra_id }}')" class="px-2 py-1 text-sm">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->gra_id . '\')'"
                                                title="Hapus Group Akun"
                                                message="Yakin hapus group {{ $row->gra_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-sm">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data group akun tidak ditemukan.
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

            <livewire:pages::master.master-akuntansi.master-group-akun.master-group-akun-actions wire:key="master-group-akun-actions" />
        </div>
    </div>
</div>
