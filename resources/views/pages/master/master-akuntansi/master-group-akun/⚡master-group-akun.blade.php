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

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="w-24">ID</th>
                                <th>Deskripsi</th>
                                <th class="ds-c w-28">Debit/Kredit</th>
                                <th class="ds-c w-32">Laporan</th>
                                <th class="ds-c w-52">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="gr-akun-{{ $row->gra_id }}">
                                    <td class="ds-td-token">{{ $row->gra_id }}</td>
                                    <td class="ds-td-strong">{{ $row->gra_desc }}</td>
                                    <td class="ds-c px-6 py-4">
                                        @if ((string) $row->dk_status === 'D')
                                            <x-badge variant="info">Debit</x-badge>
                                        @elseif ((string) $row->dk_status === 'K')
                                            <x-badge variant="purple">Kredit</x-badge>
                                        @else
                                            <span class="text-xs" style="color:var(--muted)">{{ $row->dk_status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="ds-c px-6 py-4">
                                        @if ((string) $row->gra_status === 'N')
                                            <x-badge variant="info">Neraca</x-badge>
                                        @elseif ((string) $row->gra_status === 'L')
                                            <x-badge variant="warning">Laba-Rugi</x-badge>
                                        @else
                                            <span class="text-xs" style="color:var(--muted)">{{ $row->gra_status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->gra_id }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->gra_id . '\')'"
                                                title="Hapus Group Akun"
                                                message="Yakin hapus group {{ $row->gra_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center" style="color:var(--muted)">
                                        Data group akun tidak ditemukan.
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

            <livewire:pages::master.master-akuntansi.master-group-akun.master-group-akun-actions wire:key="master-group-akun-actions" />
        </div>
    </div>
</div>
