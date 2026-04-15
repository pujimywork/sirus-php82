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
        $this->dispatch('penerimaan-kas.openCreate');
    }

    public function openEdit(string $tucashkNo): void
    {
        $this->dispatch('penerimaan-kas.openEdit', tucashkNo: $tucashkNo);
    }

    public function requestDelete(string $tucashkNo): void
    {
        $this->dispatch('penerimaan-kas.requestDelete', tucashkNo: $tucashkNo);
    }

    #[On('penerimaan-kas.saved')]
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
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Penerimaan Kas TU
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pencatatan penerimaan kas (Cash-In) di luar transaksi pelayanan RS
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
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
                            + Tambah Penerimaan Kas
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
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
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="ci-row-{{ $row->tucashk_no }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-sm whitespace-nowrap">{{ $row->tucashk_no }}</td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                                        <div>{{ $row->tucashk_date_display ?? '-' }}</div>
                                        <div class="text-gray-400">Shift {{ $row->shift ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div>{{ $row->tucashk_desc ?? '-' }}</div>
                                        <div class="text-gray-400">{{ $row->acc_id }} - {{ $row->acc_name ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-right whitespace-nowrap">Rp {{ number_format($row->tucashk_nominal ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div>{{ $row->acc_name_kas ?? '-' }}</div>
                                        <div class="text-gray-400">{{ $row->acc_id_kas }}</div>
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
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data penerimaan kas.
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

            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::transaksi.keuangan.penerimaan-kas-tu.penerimaan-kas-tu-actions wire:key="penerimaan-kas-tu-actions" />
        </div>
    </div>
</div>
