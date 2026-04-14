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
        $this->dispatch('penerimaan-medis.openCreate');
    }

    public function openEdit(string $rcvNo): void
    {
        $this->dispatch('penerimaan-medis.openEdit', rcvNo: $rcvNo);
    }

    public function requestDelete(string $rcvNo): void
    {
        $this->dispatch('penerimaan-medis.requestDelete', rcvNo: $rcvNo);
    }

    #[On('penerimaan-medis.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* ── Query ── */
    #[Computed]
    public function baseQuery()
    {
        $query = DB::table('imtxn_receivehdrs as a')
            ->leftJoin('immst_suppliers as b', 'a.supp_id', '=', 'b.supp_id')
            ->leftJoin('immst_employers as c', 'a.emp_id', '=', 'c.emp_id')
            ->select([
                'a.rcv_no',
                DB::raw("to_char(a.rcv_date,'dd/mm/yyyy hh24:mi:ss') as rcv_date_display"),
                'a.shift',
                'a.rcv_desc',
                'a.supp_id', 'b.supp_name',
                'a.emp_id', 'c.emp_name',
                'a.rcv_status',
                'a.sp_no',
                DB::raw("(SELECT SUM(
                    (NVL(d.qty,0)*NVL(d.cost_price,0))
                    - ((NVL(d.qty,0)*NVL(d.cost_price,0)) * NVL(d.dtl_persen,0)/100)
                    - NVL(d.dtl_diskon,0)
                    - (((NVL(d.qty,0)*NVL(d.cost_price,0))
                        - ((NVL(d.qty,0)*NVL(d.cost_price,0)) * NVL(d.dtl_persen,0)/100)
                        - NVL(d.dtl_diskon,0))
                      * NVL(d.dtl_persen1,0)/100)
                    - NVL(d.dtl_diskon1,0)
                ) FROM imtxn_receivedtls d WHERE d.rcv_no = a.rcv_no) as total_detail"),
                'a.rcv_diskon',
                'a.rcv_ppn',
                'a.rcv_ppn_status',
                'a.rcv_materai',
            ])
            ->orderByDesc('a.rcv_date')
            ->orderByDesc('a.shift');

        if ($this->searchKeyword !== '') {
            $upper = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(a.rcv_desc) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(b.supp_name) LIKE ?', ["%{$upper}%"])
                  ->orWhere('a.rcv_no', 'like', "%{$this->searchKeyword}%");
            });
        }

        if ($this->filterBulan !== '') {
            $query->whereRaw("TO_CHAR(a.rcv_date,'MM/YYYY') = ?", [$this->filterBulan]);
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
                Penerimaan Obat
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pencatatan penerimaan obat dari PBF / Supplier
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
                                placeholder="Cari supplier / keterangan / no..." class="block w-full" />
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
                            + Tambah Penerimaan
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
                                <th class="px-4 py-3 font-semibold">SUPPLIER</th>
                                <th class="px-4 py-3 font-semibold">KETERANGAN</th>
                                <th class="px-4 py-3 font-semibold text-right">TOTAL</th>
                                <th class="px-4 py-3 font-semibold">INFO</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                @php
                                    $totalDetail = $row->total_detail ?? 0;
                                    $diskon = $row->rcv_diskon ?? 0;
                                    $setelahDiskon = $totalDetail - $diskon;
                                    $ppn = 0;
                                    if (($row->rcv_ppn_status ?? '1') === '1') {
                                        $ppn = $setelahDiskon * ($row->rcv_ppn ?? 0) / 100;
                                    }
                                    $grandTotal = $setelahDiskon + $ppn + ($row->rcv_materai ?? 0);
                                @endphp
                                <tr wire:key="rcv-row-{{ $row->rcv_no }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $row->rcv_no }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div>{{ $row->rcv_date_display ?? '-' }}</div>
                                        <div class="text-gray-400">Shift {{ $row->shift ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $row->supp_name ?? '-' }}</div>
                                        <div class="text-gray-400">{{ $row->supp_id }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $row->rcv_desc ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-right whitespace-nowrap">Rp {{ number_format($grandTotal) }}</td>
                                    <td class="px-4 py-3">
                                        <div>{{ $row->emp_name ?? $row->emp_id ?? '-' }}</div>
                                        <x-badge :variant="($row->rcv_status ?? '') === 'L' ? 'success' : 'warning'" class="mt-1">
                                            {{ ($row->rcv_status ?? '') === 'L' ? 'Posted' : 'Draft' }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-outline-button type="button" wire:click="openEdit('{{ $row->rcv_no }}')">
                                                Buka
                                            </x-outline-button>
                                            @hasanyrole('Admin|Tu')
                                                <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->rcv_no . '\')'"
                                                    title="Hapus Penerimaan" message="Yakin ingin menghapus penerimaan #{{ $row->rcv_no }}?"
                                                    confirmText="Ya, hapus" cancelText="Batal">
                                                    Hapus
                                                </x-confirm-button>
                                            @endhasanyrole
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data penerimaan obat.
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
            <livewire:pages::transaksi.gudang.penerimaan-medis.penerimaan-medis-actions wire:key="penerimaan-medis-actions" />
        </div>
    </div>
</div>
