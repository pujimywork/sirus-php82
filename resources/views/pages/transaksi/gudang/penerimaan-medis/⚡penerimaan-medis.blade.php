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
    public string $filterStatus = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

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

    public function requestBatal(string $rcvNo): void
    {
        $this->dispatch('penerimaan-medis.requestBatal', rcvNo: $rcvNo);
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
                'a.supp_id',
                'b.supp_name',
                'a.emp_id',
                'c.emp_name',
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

        if ($this->filterStatus !== '') {
            $query->where('a.rcv_status', $this->filterStatus);
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
                Obat dari PBF
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pencatatan penerimaan obat dari PBF / Supplier
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari supplier / keterangan / no..." />
                        </div>
                    </div>

                    {{-- BULAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.300ms="filterBulan" placeholder="mm/yyyy"
                            class="mt-1 sm:w-32" />
                    </div>

                    {{-- STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-44">
                            <option value="">Semua Status</option>
                            <option value="A">Daftar Tunggu</option>
                            <option value="H">Hutang</option>
                            <option value="L">Lunas</option>
                            <option value="F">Batal</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT: per halaman + tambah --}}
                    <div class="flex items-end gap-2 ml-auto">
                        <div class="w-24">
                            <x-input-label value="Per hal." />
                            <x-select-input wire:model.live="itemsPerPage" class="mt-1">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap"
                            title="Catat penerimaan obat baru dari PBF / Supplier">
                            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Obat dari PBF
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
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
                                        $ppn = ($setelahDiskon * ($row->rcv_ppn ?? 0)) / 100;
                                    }
                                    $grandTotal = $setelahDiskon + $ppn + ($row->rcv_materai ?? 0);
                                @endphp
                                <tr wire:key="rcv-row-{{ $row->rcv_no }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
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
                                    <td class="px-4 py-3 font-mono text-right whitespace-nowrap">Rp
                                        {{ number_format($grandTotal) }}</td>
                                    <td class="px-4 py-3">
                                        <div>{{ $row->emp_name ?? ($row->emp_id ?? '-') }}</div>
                                        @php
                                            $st = (string) ($row->rcv_status ?? '');
                                            [$stLabel, $stVariant] = match ($st) {
                                                'L' => ['Lunas', 'success'],
                                                'H' => ['Hutang', 'warning'],
                                                'A' => ['Daftar Tunggu', 'alternative'],
                                                'F' => ['Batal', 'danger'],
                                                default => [$st ?: '-', 'gray'],
                                            };
                                        @endphp
                                        <x-badge :variant="$stVariant" class="mt-1">{{ $stLabel }}</x-badge>
                                    </td>
                                    {{-- Kolom AKSI — sementara disembunyikan.
                                         Buka tombol Edit/Lihat via klik baris atau reinstate kolom ini kalau perlu. --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $editable = $st === 'A';
                                            $canDelete = in_array($st, ['A', 'F'], true);
                                        @endphp
                                        <div class="flex flex-wrap gap-2">
                                            @if ($editable)
                                                <x-outline-button type="button"
                                                    wire:click="openEdit('{{ $row->rcv_no }}')"
                                                    class="px-2 py-1 text-xs">
                                                    Ubah Data
                                                </x-outline-button>
                                            @else
                                                <x-secondary-button type="button"
                                                    wire:click="openEdit('{{ $row->rcv_no }}')"
                                                    class="px-2 py-1 text-xs">
                                                    Lihat Data
                                                </x-secondary-button>
                                            @endif
                                            {{-- @hasanyrole('Admin|Tu')
                                                @if ($canDelete)
                                                    <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->rcv_no . '\')'"
                                                        title="Hapus Penerimaan"
                                                        message="Yakin ingin menghapus penerimaan #{{ $row->rcv_no }}?"
                                                        confirmText="Ya, hapus" cancelText="Batal"
                                                        class="px-2 py-1 text-xs">
                                                        Hapus
                                                    </x-confirm-button>
                                                @endif
                                            @endhasanyrole --}}
                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::transaksi.gudang.penerimaan-medis.penerimaan-medis-actions
                wire:key="penerimaan-medis-actions" />
        </div>
    </div>
</div>
