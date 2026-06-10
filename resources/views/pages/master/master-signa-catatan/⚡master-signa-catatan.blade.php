<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterStatus  = 'all';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void  { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('master.signa-catatan.openCreate');
    }

    public function openEdit(string $catatan): void
    {
        $this->dispatch('master.signa-catatan.openEdit', catatan: $catatan);
    }

    public function requestDelete(string $catatan): void
    {
        $this->dispatch('master.signa-catatan.requestDelete', catatan: $catatan);
    }

    public function toggleActive(string $catatan): void
    {
        $row = DB::table('rsmst_signa_catatans')->where('catatan', $catatan)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Catatan tidak ditemukan.');
            return;
        }
        $new = (string) $row->active_status === '1' ? '0' : '1';
        DB::table('rsmst_signa_catatans')->where('catatan', $catatan)->update(['active_status' => $new]);
        $this->dispatch('toast', type: 'success', message: $new === '1' ? 'Catatan diaktifkan.' : 'Catatan dinonaktifkan.');
        unset($this->rows);
    }

    #[On('master.signa-catatan.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_signa_catatans')
            ->select('catatan', 'active_status')
            ->orderBy('catatan');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(catatan) LIKE ?', ["%{$kw}%"]);
        }

        if ($this->filterStatus === 'active') {
            $q->where('active_status', '1');
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('active_status', '0');
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Master Catatan Khusus Signa"
        subtitle="LOV catatan khusus untuk signa e-resep (RJ/UGD/RI). Sumber: rsmst_signa_catatans." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col w-full gap-2 lg:flex-row lg:items-center lg:max-w-xl">
                        <div class="w-full">
                            <x-input-label for="searchKeyword" value="Cari Catatan" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari teks catatan..." class="block w-full" />
                        </div>
                        <div class="w-full lg:w-44">
                            <x-input-label for="filterStatus" value="Status" class="sr-only" />
                            <x-select-input id="filterStatus" wire:model.live="filterStatus">
                                <option value="all">Semua Status</option>
                                <option value="active">Hanya Aktif</option>
                                <option value="inactive">Hanya Nonaktif</option>
                            </x-select-input>
                        </div>
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
                            + Tambah Catatan
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
                                <th>Catatan</th>
                                <th class="w-32">Status</th>
                                <th class="ds-c w-52">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="signa-catatan-{{ md5($row->catatan) }}">
                                    <td class="ds-td-strong">{{ $row->catatan }}</td>
                                    <td class="px-6 py-4">
                                        <x-toggle
                                            :current="(string) $row->active_status"
                                            trueValue="1" falseValue="0"
                                            :wireClick="'toggleActive(' . json_encode($row->catatan) . ')'">
                                            {{ (string) $row->active_status === '1' ? 'Aktif' : 'Nonaktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit({{ json_encode($row->catatan) }})" />
                                            <x-action-delete :action="'requestDelete(' . json_encode($row->catatan) . ')'"
                                                title="Hapus Catatan" message="Yakin hapus catatan ini?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center" style="color:var(--muted)">
                                        Data catatan tidak ditemukan.
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

            <livewire:pages::master.master-signa-catatan.master-signa-catatan-actions wire:key="master-signa-catatan-actions" />
        </div>
    </div>
</div>
