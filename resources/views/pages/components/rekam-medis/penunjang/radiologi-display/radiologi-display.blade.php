<?php
// resources/views/pages/components/rekam-medis/rekam-medis/penunjang/radiologi-display/radiologi-display.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* =======================
     | Filter & Pagination
     * ======================= */
    #[Reactive]
    public string $regNo = '';
    public string $searchKeyword = '';
    public string $filterTahun = '';
    public int $itemsPerPage = 3;

    /* =======================
     | Mount
     * ======================= */
    public function mount($regNo = ''): void
    {
        $this->regNo = $regNo;
    }

    public function loadPasien($regNo): void
    {
        $this->regNo = $regNo;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterTahun']);
        $this->resetPage();
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* =======================
     | Daftar tahun filter
     * ======================= */
    #[Computed]
    public function tahunList()
    {
        if (!$this->regNo) {
            return collect();
        }

        return DB::table('rsview_rads')->select(DB::raw('DISTINCT EXTRACT(YEAR FROM rad_date) as tahun'))->where('reg_no', $this->regNo)->orderBy('tahun', 'desc')->pluck('tahun');
    }

    /* =======================
     | Base Query
     * ======================= */
    #[Computed]
    public function baseQuery()
    {
        if (!$this->regNo) {
            return collect();
        }

        $searchKeyword = trim($this->searchKeyword);

        $query = DB::table('rsview_rads')->select(DB::raw("TO_CHAR(rad_date,'dd/mm/yyyy hh24:mi:ss') AS rad_date"), DB::raw("TO_CHAR(rad_date,'yyyymmddhh24miss') AS rad_date1"), 'txn_no', 'txn_no_dtl', 'reg_no', 'reg_name', 'rad_upload_pdf', 'rad_upload_pdf_foto', 'rad_rjri', 'rad_id', 'rad_desc')->where('reg_no', $this->regNo);

        if ($this->filterTahun) {
            $query->whereYear('rad_date', $this->filterTahun);
        }

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(rad_desc) LIKE ?', ["%{$upper}%"])->orWhereRaw('UPPER(rad_rjri) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $query->orderBy('rad_date1', 'desc');
    }

    /* =======================
     | Rows dengan Pagination
     * ======================= */
    #[Computed]
    public function rows()
    {
        if (!$this->regNo) {
            return collect();
        }
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    /* =======================
     | Stats
     * ======================= */
    #[Computed]
    public function statsRadiologi()
    {
        if (!$this->regNo) {
            return ['total' => 0, 'ada_hasil' => 0, 'ada_foto' => 0, 'proses' => 0];
        }

        $stats = DB::table('rsview_rads')->select(DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN rad_upload_pdf IS NOT NULL THEN 1 ELSE 0 END) as ada_hasil'), DB::raw('SUM(CASE WHEN rad_upload_pdf_foto IS NOT NULL THEN 1 ELSE 0 END) as ada_foto'), DB::raw('SUM(CASE WHEN rad_upload_pdf IS NULL THEN 1 ELSE 0 END) as proses'))->where('reg_no', $this->regNo)->first();

        return [
            'total' => $stats->total ?? 0,
            'ada_hasil' => $stats->ada_hasil ?? 0,
            'ada_foto' => $stats->ada_foto ?? 0,
            'proses' => $stats->proses ?? 0,
        ];
    }

    /* =======================
     | Download
     * ======================= */
    public function downloadHasil(string $rad_upload_pdf): mixed
    {
        if (empty($rad_upload_pdf)) {
            $this->dispatch('toast', type: 'warning', message: 'Hasil bacaan radiologi masih dalam proses.');
            return null;
        }

        $path = storage_path('/penunjang/rad/' . $rad_upload_pdf);

        if (!file_exists($path)) {
            $this->dispatch('toast', type: 'error', message: 'File hasil radiologi tidak ditemukan.');
            return null;
        }

        return response()->streamDownload(fn() => print file_get_contents($path), basename($rad_upload_pdf));
    }

    public function downloadFoto(string $rad_upload_pdf_foto): mixed
    {
        if (empty($rad_upload_pdf_foto)) {
            $this->dispatch('toast', type: 'warning', message: 'Foto radiologi masih dalam proses.');
            return null;
        }

        $path = storage_path('/penunjang/rad/' . $rad_upload_pdf_foto);

        if (!file_exists($path)) {
            $this->dispatch('toast', type: 'error', message: 'File foto radiologi tidak ditemukan.');
            return null;
        }

        return response()->streamDownload(fn() => print file_get_contents($path), basename($rad_upload_pdf_foto));
    }
};
?>

<div>
    <div class="flex flex-col w-full">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- Tabel Pemeriksaan Radiologi --}}
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="mb-2 overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                    <thead
                                        class="text-sm text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">
                                                <div class="flex items-center space-x-2">
                                                    <span>Riwayat Pemeriksaan Radiologi</span>
                                                    @if ($regNo && $this->rows->total() > 0)
                                                        <span
                                                            class="px-2 py-0.5 text-sm bg-blue-100 rounded-full text-brand">
                                                            {{ $this->rows->total() }} Pemeriksaan
                                                        </span>
                                                    @endif
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="bg-white dark:bg-gray-800">
                                        @forelse ($this->rows as $row)
                                            <tr class="border-b group dark:border-gray-700">
                                                @php
                                                    $layanan = $row->rad_rjri ?? '';
                                                    $isRI = $layanan === 'RI';
                                                    $isUGD = $layanan === 'UGD';
                                                    $isRJ = $layanan === 'RJ';
                                                    $layananIcon = $isRI ? '🏥' : ($isUGD ? '🚑' : '🩻');
                                                    $layananClass = $isRI
                                                        ? 'text-purple-600'
                                                        : ($isUGD
                                                            ? 'text-red-600'
                                                            : 'text-blue-600');
                                                    $layananText = $isRI
                                                        ? 'Rawat Inap'
                                                        : ($isUGD
                                                            ? 'UGD'
                                                            : ($isRJ
                                                                ? 'Rawat Jalan'
                                                                : '-'));
                                                    $hasHasil = !empty($row->rad_upload_pdf);
                                                    $hasFoto = !empty($row->rad_upload_pdf_foto);
                                                @endphp

                                                <td
                                                    class="px-4 py-4 text-gray-900 transition-colors group-hover:bg-gray-50 dark:text-gray-100 dark:group-hover:bg-gray-750">

                                                    {{-- Header Row --}}
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex items-center space-x-2">
                                                            <span class="text-2xl">{{ $layananIcon }}</span>
                                                            <div>
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        class="font-bold {{ $layananClass }}">{{ $layananText }}</span>
                                                                    <span class="text-gray-400">|</span>
                                                                    <span
                                                                        class="font-medium">{{ $row->reg_name }}</span>
                                                                    @if ($hasHasil)
                                                                        <span
                                                                            class="px-2 py-0.5 text-xs font-medium rounded-full text-green-700 bg-green-100">
                                                                            ✓ Hasil Tersedia
                                                                        </span>
                                                                    @else
                                                                        <span
                                                                            class="px-2 py-0.5 text-xs font-medium rounded-full text-amber-700 bg-amber-100">
                                                                            ⏳ Proses
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="mt-0.5 text-xs text-gray-400 font-mono">
                                                                    No: {{ $row->txn_no }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-right text-gray-500 shrink-0">
                                                            <div>{{ $row->rad_date }}</div>
                                                        </div>
                                                    </div>

                                                    {{-- Item Pemeriksaan --}}
                                                    <div class="p-2 mt-3 rounded bg-gray-50 dark:bg-gray-700">
                                                        <div class="flex items-center mb-1.5 space-x-1">
                                                            <svg class="w-3 h-3 text-brand-blue" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                                            </svg>
                                                            <span
                                                                class="text-xs font-semibold text-gray-600 dark:text-gray-300">Item
                                                                Pemeriksaan:</span>
                                                        </div>
                                                        <div class="flex flex-wrap gap-1">
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-blue/10 text-brand-blue border border-brand-blue/20">
                                                                {{ $row->rad_desc }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- Actions --}}
                                                    @role(['Dokter', 'Admin', 'Perawat', 'Radiologi'])
                                                        <div class="flex items-center gap-2 mt-3">

                                                            {{-- Tombol Hasil Bacaan --}}
                                                            <x-info-button type="button"
                                                                wire:click="downloadHasil('{{ $row->rad_upload_pdf }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="downloadHasil('{{ $row->rad_upload_pdf }}')">
                                                                <span wire:loading.remove
                                                                    wire:target="downloadHasil('{{ $row->rad_upload_pdf }}')"
                                                                    class="flex items-center gap-1">
                                                                    <svg class="w-4 h-4" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                    </svg>
                                                                    Hasil Bacaan
                                                                </span>
                                                                <span wire:loading
                                                                    wire:target="downloadHasil('{{ $row->rad_upload_pdf }}')"
                                                                    class="flex items-center gap-1">
                                                                    <x-loading /> Mengunduh...
                                                                </span>
                                                            </x-info-button>

                                                            {{-- Tombol Foto Radiologi --}}
                                                            @if ($hasFoto)
                                                                <x-primary-button type="button"
                                                                    wire:click="downloadFoto('{{ $row->rad_upload_pdf_foto }}')"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="downloadFoto('{{ $row->rad_upload_pdf_foto }}')"
                                                                    class="text-sm px-3 py-1.5">
                                                                    <span wire:loading.remove
                                                                        wire:target="downloadFoto('{{ $row->rad_upload_pdf_foto }}')"
                                                                        class="flex items-center gap-1">
                                                                        <svg class="w-4 h-4" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                        </svg>
                                                                        Foto Radiologi
                                                                    </span>
                                                                    <span wire:loading
                                                                        wire:target="downloadFoto('{{ $row->rad_upload_pdf_foto }}')"
                                                                        class="flex items-center gap-1">
                                                                        <x-loading /> Mengunduh...
                                                                    </span>
                                                                </x-primary-button>
                                                            @endif

                                                        </div>
                                                    @endrole

                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="px-4 py-8 text-center">
                                                    @if ($regNo)
                                                        <svg class="w-12 h-12 mx-auto text-gray-300" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                                        </svg>
                                                        <p class="mt-2 text-gray-500">Tidak ada data pemeriksaan
                                                            radiologi</p>
                                                    @else
                                                        <p class="text-gray-500">Silakan pilih pasien terlebih dahulu
                                                        </p>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- Pagination --}}
                            @if ($regNo && $this->rows->hasPages())
                                <div class="mt-4">
                                    {{ $this->rows->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Toolbar Stats --}}
                <div
                    class="flex flex-wrap items-center justify-between gap-3 p-3 mb-3 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="text-sm font-medium">No. RM: <span
                                    class="font-semibold text-brand">{{ $regNo ?: '-' }}</span></span>
                        </div>

                        @if ($regNo)
                            <div class="flex flex-wrap gap-2">
                                <span class="px-2 py-1 text-sm rounded-full text-brand bg-brand-blue/10">
                                    {{ $this->statsRadiologi['total'] }} Total
                                </span>
                                <span class="px-2 py-1 text-sm rounded-full text-amber-700 bg-amber-100">
                                    ⏳ Proses: {{ $this->statsRadiologi['proses'] }}
                                </span>
                                <span class="px-2 py-1 text-sm text-green-700 bg-green-100 rounded-full">
                                    ✓ Ada Hasil: {{ $this->statsRadiologi['ada_hasil'] }}
                                </span>
                                <span class="px-2 py-1 text-sm text-blue-700 bg-blue-100 rounded-full">
                                    🩻 Ada Foto: {{ $this->statsRadiologi['ada_foto'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
