<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /** Kode lokasi sumber yang diperbolehkan untuk transfer (only). */
    public const WAREHOUSE_SL_CODE = '04';   // Gudang Medis
    public const APOTEK_SL_CODE    = '02';   // Apotek

    public string $searchKeyword = '';
    public int $itemsPerPage = 10;
    public string $filterBulan = '';
    public string $filterStatus = '';

    /** Tab sumber: '04' (Gudang) atau '02' (Apotek). */
    public string $filterFromSource = self::WAREHOUSE_SL_CODE;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterFromSource(): void { $this->resetPage(); }

    public function setFromSource(string $slCode): void
    {
        if (!in_array($slCode, [self::WAREHOUSE_SL_CODE, self::APOTEK_SL_CODE], true)) {
            return;
        }
        $this->filterFromSource = $slCode;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('transfer-stock.openCreate', slCodeFrom: $this->filterFromSource);
    }

    public function openEdit(int $trfNo): void
    {
        $this->dispatch('transfer-stock.openEdit', trfNo: $trfNo);
    }

    public function requestBatal(int $trfNo): void
    {
        $this->dispatch('transfer-stock.requestBatal', trfNo: $trfNo);
    }

    #[On('transfer-stock.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('imtxn_trfhdrs as h')
            ->leftJoin('immst_stocklocations as lf', 'h.sl_codefrom', '=', 'lf.sl_code')
            ->leftJoin('immst_stocklocations as lt', 'h.sl_codeto', '=', 'lt.sl_code')
            ->leftJoin('immst_employers as e', 'h.emp_id', '=', 'e.emp_id')
            ->select([
                'h.trf_no',
                DB::raw("to_char(h.trf_date,'dd/mm/yyyy hh24:mi:ss') as trf_date_display"),
                'h.sl_codefrom',
                'h.sl_codeto',
                'h.trf_status',
                'h.emp_id',
                DB::raw('lf.sl_name as sl_name_from'),
                DB::raw('lt.sl_name as sl_name_to'),
                DB::raw('e.emp_name as emp_name'),
                DB::raw('(SELECT COUNT(*) FROM imtxn_trfdtls d WHERE d.trf_no = h.trf_no) as item_count'),
                DB::raw('(SELECT NVL(SUM(d.qty),0) FROM imtxn_trfdtls d WHERE d.trf_no = h.trf_no) as qty_total'),
            ])
            ->orderByDesc('h.trf_date')
            ->orderByDesc('h.trf_no');

        if ($this->searchKeyword !== '') {
            $upper = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->where('h.trf_no', 'like', "%{$this->searchKeyword}%")
                    ->orWhereRaw('UPPER(lf.sl_name) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(lt.sl_name) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(e.emp_name) LIKE ?', ["%{$upper}%"]);
            });
        }

        if ($this->filterBulan !== '') {
            $query->whereRaw("TO_CHAR(h.trf_date,'MM/YYYY') = ?", [$this->filterBulan]);
        }

        if ($this->filterStatus !== '') {
            $query->where('h.trf_status', $this->filterStatus);
        }

        $query->where('h.sl_codefrom', $this->filterFromSource);

        return $query->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <x-page-title
        title="Transfer Stok Antar Ruang"
        subtitle="Catat perpindahan obat / alkes antar lokasi. Sumber transfer hanya dari Gudang Medis atau Apotek." />

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
                                placeholder="Cari no transfer / lokasi / petugas..." />
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
                            <option value="A">Belum Diproses</option>
                            <option value="L">Sudah Diproses</option>
                            <option value="F">Dibatalkan</option>
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
                            title="Buat transfer stok baru">
                            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Buat Transfer Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TAB SUMBER --}}
            <x-scrollable-tabs class="mt-4 border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-nowrap whitespace-nowrap -mb-px text-sm font-medium text-gray-500 dark:text-gray-400">
                    <li class="mr-2">
                        <button type="button" wire:click="setFromSource('{{ self::WAREHOUSE_SL_CODE }}')"
                            @class([
                                'inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors',
                                'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' => $filterFromSource === self::WAREHOUSE_SL_CODE,
                                'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300' => $filterFromSource !== self::WAREHOUSE_SL_CODE,
                            ])>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 7l9-4 9 4M5 9v10a2 2 0 002 2h10a2 2 0 002-2V9M9 21V12h6v9" />
                            </svg>
                            Dari Gudang Medis
                            <span class="px-2 py-0.5 text-xs font-mono bg-white border border-gray-300 rounded dark:bg-gray-900 dark:border-gray-600">04</span>
                        </button>
                    </li>
                    <li class="mr-2">
                        <button type="button" wire:click="setFromSource('{{ self::APOTEK_SL_CODE }}')"
                            @class([
                                'inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors',
                                'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' => $filterFromSource === self::APOTEK_SL_CODE,
                                'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300' => $filterFromSource !== self::APOTEK_SL_CODE,
                            ])>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Dari Apotek
                            <span class="px-2 py-0.5 text-xs font-mono bg-white border border-gray-300 rounded dark:bg-gray-900 dark:border-gray-600">02</span>
                        </button>
                    </li>
                </ul>
            </x-scrollable-tabs>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">NO</th>
                                <th class="px-4 py-3 font-semibold">TANGGAL</th>
                                <th class="px-4 py-3 font-semibold">DARI</th>
                                <th class="px-4 py-3 font-semibold">KE</th>
                                <th class="px-4 py-3 font-semibold text-right">ITEM</th>
                                <th class="px-4 py-3 font-semibold text-right">TOTAL QTY</th>
                                <th class="px-4 py-3 font-semibold">PETUGAS</th>
                                <th class="px-4 py-3 font-semibold">STATUS</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                @php
                                    $st = (string) ($row->trf_status ?? '');
                                    [$stLabel, $stVariant] = match ($st) {
                                        'A' => ['Belum Diproses', 'alternative'],
                                        'L' => ['Sudah Diproses', 'success'],
                                        'F' => ['Dibatalkan', 'danger'],
                                        'I' => ['Transfer UGD', 'warning'],
                                        default => [$st ?: '-', 'gray'],
                                    };
                                    $editable = $st === 'A';
                                @endphp
                                <tr wire:key="trf-row-{{ $row->trf_no }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $row->trf_no }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->trf_date_display ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $row->sl_name_from ?? '-' }}</div>
                                        <div class="font-mono text-xs text-gray-400">{{ $row->sl_codefrom }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $row->sl_name_to ?? '-' }}</div>
                                        <div class="font-mono text-xs text-gray-400">{{ $row->sl_codeto }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-right whitespace-nowrap">
                                        {{ $row->item_count ?? 0 }}</td>
                                    <td class="px-4 py-3 font-mono text-right whitespace-nowrap">
                                        {{ rtrim(rtrim(number_format((float) ($row->qty_total ?? 0), 2, ',', '.'), '0'), ',') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $row->emp_name ?? ($row->emp_id ?? '-') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="$stVariant">{{ $stLabel }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @if ($editable)
                                                <x-outline-button type="button"
                                                    wire:click="openEdit({{ $row->trf_no }})"
                                                    class="px-2 py-1 text-xs">
                                                    Edit / Proses
                                                </x-outline-button>
                                                <x-confirm-button variant="danger"
                                                    :action="'requestBatal(' . $row->trf_no . ')'"
                                                    title="Hapus Transfer"
                                                    message="Yakin hapus transfer #{{ $row->trf_no }}? Header & detail akan dihapus permanen — hanya draft yang bisa dihapus."
                                                    confirmText="Ya, hapus" cancelText="Tidak"
                                                    class="px-2 py-1 text-xs">
                                                    Hapus
                                                </x-confirm-button>
                                            @else
                                                <x-secondary-button type="button"
                                                    wire:click="openEdit({{ $row->trf_no }})"
                                                    class="px-2 py-1 text-xs">
                                                    Lihat
                                                </x-secondary-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada data transfer.
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

            {{-- Modal CRUD --}}
            <livewire:pages::transaksi.gudang.transfer-stock.transfer-stock-actions
                wire:key="transfer-stock-actions" />

        </div>
    </div>
</div>
