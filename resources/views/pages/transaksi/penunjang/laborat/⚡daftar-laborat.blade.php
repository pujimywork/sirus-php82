<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-lab-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = '';
    public string $filterLayanan = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-lab-toolbar');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-lab-toolbar');
    }

    public function updatedFilterLayanan(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-lab-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-lab-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterLayanan']);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-lab-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Open Detail (actions modal)
     * ------------------------- */
    public function openDetail($checkupNo): void
    {
        $this->dispatch('lab-actions.open', checkupNo: (string) $checkupNo);
    }

    /* -------------------------
     | Refresh after save
     * ------------------------- */
    #[On('refresh-after-lab.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-lab-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Date range helper
     * ------------------------- */
    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Exception $e) {
            $d = now()->startOfDay();
        }
        return [$d, (clone $d)->endOfDay()];
    }

    /* -------------------------
     | Base Query — RSVIEW_CHECKUPS
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rsview_checkups')
            ->select(
                'checkup_no',
                DB::raw("to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') as checkup_date_display"),
                'reg_no',
                'reg_name',
                'sex',
                DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"),
                'address',
                'checkup_status',
                'checkup_rjri',
                DB::raw("(
                    SELECT string_agg(clabitem_desc)
                    FROM lbtxn_checkupdtls a
                    JOIN lbmst_clabitems b ON a.clabitem_id = b.clabitem_id
                    WHERE a.checkup_no = rsview_checkups.checkup_no
                    AND a.price IS NOT NULL
                ) AS checkup_dtl_pasien"),
            )
            ->whereBetween('checkup_date', [$start, $end])
            ->orderBy('checkup_date', 'desc');

        if ($this->filterStatus !== '') {
            $query->where('checkup_status', $this->filterStatus);
        }

        if ($this->filterLayanan !== '') {
            $query->where('checkup_rjri', $this->filterLayanan);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('checkup_no', 'like', "%{$search}%")
                      ->orWhere('reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    /* -------------------------
     | Rows with Pagination
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    /* -------------------------
     | Stats
     * ------------------------- */
    #[Computed]
    public function statsLab()
    {
        [$start, $end] = $this->dateRange();

        $stats = DB::table('rsview_checkups')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN checkup_status = 'H' THEN 1 ELSE 0 END) as selesai"),
                DB::raw("SUM(CASE WHEN checkup_status = 'C' THEN 1 ELSE 0 END) as proses"),
                DB::raw("SUM(CASE WHEN checkup_status = 'P' THEN 1 ELSE 0 END) as terdaftar"),
            )
            ->whereBetween('checkup_date', [$start, $end])
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'selesai' => $stats->selesai ?? 0,
            'proses' => $stats->proses ?? 0,
            'terdaftar' => $stats->terdaftar ?? 0,
        ];
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Transaksi Laboratorium
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-700">
                Input hasil pemeriksaan laboratorium
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- STATS --}}
            <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
                @php $stats = $this->statsLab; @endphp
                <div class="p-3 border rounded-xl bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $stats['total'] }}</div>
                    <div class="text-xs text-gray-500">Total Pemeriksaan</div>
                </div>
                <div class="p-3 border rounded-xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                    <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ $stats['terdaftar'] }}</div>
                    <div class="text-xs text-blue-600">Terdaftar</div>
                </div>
                <div class="p-3 border rounded-xl bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800">
                    <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $stats['proses'] }}</div>
                    <div class="text-xs text-amber-600">Proses</div>
                </div>
                <div class="p-3 border rounded-xl bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $stats['selesai'] }}</div>
                    <div class="text-xs text-green-600">Selesai</div>
                </div>
            </div>

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('daftar-lab-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No Checkup / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="P">Terdaftar</option>
                            <option value="C">Proses</option>
                            <option value="H">Selesai</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER LAYANAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Layanan" />
                        <x-select-input wire:model.live="filterLayanan" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="RJ">Rawat Jalan</option>
                            <option value="UGD">UGD</option>
                            <option value="RI">Rawat Inap</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </x-secondary-button>

                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3">No</th>
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Tanggal / Layanan</th>
                                <th class="px-6 py-3">Item Pemeriksaan</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $idx => $row)
                                @php
                                    $statusCode = $row->checkup_status ?? '';
                                    $isSelesai = $statusCode === 'H';
                                    $isProses = $statusCode === 'C';
                                    $isTerdaftar = $statusCode === 'P';

                                    $statusText = $isSelesai
                                        ? 'Selesai'
                                        : ($isProses
                                            ? 'Proses'
                                            : ($isTerdaftar
                                                ? 'Terdaftar'
                                                : '-'));
                                    $statusVariant = $isSelesai
                                        ? 'success'
                                        : ($isProses
                                            ? 'warning'
                                            : 'gray');

                                    $layanan = strtoupper($row->checkup_rjri ?? '');
                                    $layananColor = match($layanan) {
                                        'RJ' => 'text-blue-700 bg-blue-100',
                                        'UGD' => 'text-red-700 bg-red-100',
                                        'RI' => 'text-purple-700 bg-purple-100',
                                        default => 'text-gray-700 bg-gray-100',
                                    };
                                @endphp

                                <tr
                                    class="transition bg-white rounded-2xl dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800">

                                    {{-- NO --}}
                                    <td class="px-6 py-4 align-top">
                                        <div class="text-sm font-mono text-gray-500">
                                            {{ $this->rows->firstItem() + $idx }}
                                        </div>
                                    </td>

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-4 space-y-1 align-top">
                                        <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                            {{ $row->reg_no ?? '-' }}
                                        </div>
                                        <div class="text-lg font-semibold text-brand dark:text-white">
                                            {{ $row->reg_name ?? '-' }} /
                                            ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                        </div>
                                        @if (!empty($row->birth_date))
                                            @php
                                                try {
                                                    $tglLahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                                                    $diff = $tglLahir->diff(now());
                                                    $umur = "{$row->birth_date} ({$diff->y} Thn {$diff->m} Bln)";
                                                } catch (\Exception $e) {
                                                    $umur = '-';
                                                }
                                            @endphp
                                            <div class="text-sm text-gray-500">{{ $umur }}</div>
                                        @endif
                                        <div class="text-sm text-gray-500">{{ $row->address ?? '-' }}</div>
                                    </td>

                                    {{-- TANGGAL / LAYANAN --}}
                                    <td class="px-6 py-4 space-y-2 align-top">
                                        <div class="font-mono text-sm text-gray-700 dark:text-gray-300">
                                            {{ $row->checkup_date_display ?? '-' }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-500">
                                            No: {{ $row->checkup_no }}
                                        </div>
                                        <span
                                            class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $layananColor }}">
                                            {{ $layanan ?: '-' }}
                                        </span>
                                    </td>

                                    {{-- ITEM PEMERIKSAAN --}}
                                    <td class="px-6 py-4 align-top">
                                        <div class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate"
                                            title="{{ $row->checkup_dtl_pasien ?? '' }}">
                                            {{ $row->checkup_dtl_pasien ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- STATUS --}}
                                    <td class="px-6 py-4 align-top">
                                        <x-badge :variant="$statusVariant">
                                            {{ $statusText }}
                                        </x-badge>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-6 py-4 text-center align-top">
                                        <x-primary-button type="button"
                                            wire:click="openDetail('{{ $row->checkup_no }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="openDetail('{{ $row->checkup_no }}')">
                                            <span wire:loading.remove
                                                wire:target="openDetail('{{ $row->checkup_no }}')"
                                                class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                                Detail Pemeriksaan
                                            </span>
                                            <span wire:loading
                                                wire:target="openDetail('{{ $row->checkup_no }}')"
                                                class="flex items-center gap-1.5">
                                                <x-loading /> Memuat...
                                            </span>
                                        </x-primary-button>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="text-sm">Tidak ada data pemeriksaan ditemukan</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                {{-- PAGINATION --}}
                @if ($this->rows->hasPages())
                    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->rows->links() }}
                    </div>
                @endif

            </div>

        </div>
    </div>

    {{-- CHILD: Lab Actions Modal --}}
    <livewire:pages::transaksi.penunjang.laborat.daftar-laborat-actions wire:key="lab-actions-modal" />
</div>
