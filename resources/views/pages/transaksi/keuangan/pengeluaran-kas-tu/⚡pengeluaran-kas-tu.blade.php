<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int $itemsPerPage = 10;
    public string $filterBulan = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }

    /* ── Child modal triggers ── */
    public function openCreate(): void
    {
        $this->dispatch('pengeluaran-kas.openCreate');
    }

    public function openEdit(string $tucashkNo): void
    {
        $this->dispatch('pengeluaran-kas.openEdit', tucashkNo: $tucashkNo);
    }

    public function requestDelete(string $tucashkNo): void
    {
        $this->dispatch('pengeluaran-kas.requestDelete', tucashkNo: $tucashkNo);
    }

    #[On('pengeluaran-kas.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* ── Query ── */
    #[Computed]
    public function baseQuery()
    {
        $query = DB::table('rstxn_tucashks as a')
            ->leftJoin('acmst_accounts as b', 'a.acc_id', '=', 'b.acc_id')
            ->leftJoin('acmst_accounts as c', 'a.acc_id_kas', '=', 'c.acc_id')
            ->leftJoin('immst_employers as d', 'a.emp_id', '=', 'd.emp_id')
            ->select([
                'a.tucashk_no',
                DB::raw("to_char(a.tucashk_date,'dd/mm/yyyy hh24:mi:ss') as tucashk_date_display"),
                'a.shift',
                'a.tucashk_desc',
                'a.tucashk_nominal',
                'a.acc_id', 'b.acc_name',
                'a.acc_id_kas', 'c.acc_name as acc_name_kas',
                'a.emp_id', 'd.emp_name',
                'a.tucashk_status',
            ])
            ->orderByDesc('a.tucashk_date')
            ->orderByDesc('a.shift');

        if ($this->searchKeyword !== '') {
            $upper = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(a.tucashk_desc) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(b.acc_name) LIKE ?', ["%{$upper}%"])
                  ->orWhere('a.tucashk_no', 'like', "%{$this->searchKeyword}%");
            });
        }

        if ($this->filterBulan !== '') {
            $query->whereRaw("TO_CHAR(a.tucashk_date,'MM/YYYY') = ?", [$this->filterBulan]);
        }

        return $query;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Pengeluaran Kas TU"
        subtitle="Pencatatan pengeluaran kas (Cash-Out) di luar transaksi pelayanan RS" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-40">
                            <x-input-label value="Bulan" class="sr-only" />
                            <x-text-input type="text" wire:model.live.debounce.300ms="filterBulan" placeholder="mm/yyyy" class="block w-full" />
                        </div>
                        <div class="w-full lg:max-w-md">
                            <x-input-label value="Cari" class="sr-only" />
                            <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari keterangan / akun..." class="block w-full" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label value="Per halaman" class="sr-only" />
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Pengeluaran Kas
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">NO</th>
                                <th class="px-4 py-3 font-semibold">TANGGAL</th>
                                <th class="px-4 py-3 font-semibold">KETERANGAN</th>
                                <th class="px-4 py-3 font-semibold text-right">NOMINAL</th>
                                <th class="px-4 py-3 font-semibold">KAS</th>
                                <th class="px-4 py-3 font-semibold">INFO</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="co-row-{{ $row->tucashk_no }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-sm whitespace-nowrap">{{ $row->tucashk_no }}</td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                                        <div>{{ $row->tucashk_date_display ?? '-' }}</div>
                                        <div class="text-muted-soft">Shift {{ $row->shift ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div>{{ $row->tucashk_desc ?? '-' }}</div>
                                        <div class="text-muted-soft">{{ $row->acc_id }} - {{ $row->acc_name ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-right whitespace-nowrap">Rp {{ number_format($row->tucashk_nominal ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div>{{ $row->acc_name_kas ?? '-' }}</div>
                                        <div class="text-muted-soft">{{ $row->acc_id_kas }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div>{{ $row->emp_name ?? $row->emp_id ?? '-' }}</div>
                                        <x-badge variant="success" class="mt-1">Posted</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->tucashk_no }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            @hasanyrole('Admin|Tu')
                                                <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->tucashk_no . '\')'"
                                                    title="Hapus Transaksi" message="Yakin ingin menghapus transaksi #{{ $row->tucashk_no }}?"
                                                    confirmText="Ya, hapus" cancelText="Batal"
                                                    class="px-2 py-1 text-xs">
                                                    Hapus
                                                </x-confirm-button>
                                            @endhasanyrole
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                        Tidak ada data pengeluaran kas.
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

            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::transaksi.keuangan.pengeluaran-kas-tu.pengeluaran-kas-tu-actions wire:key="pengeluaran-kas-tu-actions" />
        </div>
    </div>
</div>
