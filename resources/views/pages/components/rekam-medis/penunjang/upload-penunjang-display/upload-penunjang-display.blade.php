<?php
// resources/views/pages/components/rekam-medis/penunjang/upload-penunjang-display/upload-penunjang-display.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithPagination;

    /* =======================
     | Props & Filter
     * ======================= */
    #[Reactive]
    public string $regNo = '';

    public string $searchKeyword = '';
    public string $filterTahun = '';
    public int $itemsPerPage = 3;

    // View PDF
    public string $viewFilePDF = '';

    /* =======================
     | Mount
     * ======================= */
    public function mount(string $regNo = ''): void
    {
        $this->regNo = $regNo;
    }

    public function loadPasien(string $regNo): void
    {
        $this->regNo = $regNo;
        $this->viewFilePDF = '';
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
     | Daftar Tahun Filter
     * ======================= */
    #[Computed]
    public function tahunList()
    {
        if (!$this->regNo) {
            return collect();
        }

        return DB::table('rstxn_rjhdrs as rj')->join('rsview_rjkasir as v', 'v.rj_no', '=', 'rj.rj_no')->where('v.reg_no', $this->regNo)->whereNotNull('rj.datadaftarpolirj_json')->select(DB::raw('DISTINCT EXTRACT(YEAR FROM rj.rj_date) as tahun'))->orderByDesc('tahun')->pluck('tahun');
    }

    /* =======================
     | Semua Items — flat dari semua kunjungan
                                     *
                                     * Struktur array per item (dari datadaftarpolirj_json):
                                     *   pemeriksaan.uploadHasilPenunjang[]:
                                     *     file        → path Storage::disk('local')
                                     *     desc        → keterangan
                                     *     tglUpload   → tanggal upload
                                     *     penanggungJawab:
                                     *       userLog      → nama petugas
                                     *       userLogDate  → tanggal log
                                     *       userLogCode  → kode petugas
     * ======================= */
    #[Computed]
    public function allItems(): array
    {
        if (!$this->regNo) {
            return [];
        }

        $rows = DB::table('rstxn_rjhdrs as rj')
            ->join('rsview_rjkasir as v', 'v.rj_no', '=', 'rj.rj_no')
            ->where('v.reg_no', $this->regNo)
            ->whereNotNull('rj.datadaftarpolirj_json')
            ->select(['rj.rj_no', DB::raw("TO_CHAR(rj.rj_date, 'dd/mm/yyyy hh24:mi:ss') as rj_date"), 'v.reg_name', 'v.poli_desc', 'v.dr_name', 'rj.datadaftarpolirj_json'])
            ->when($this->filterTahun, fn($q) => $q->whereYear('rj.rj_date', $this->filterTahun))
            ->orderByDesc('rj.rj_date')
            ->get();

        $items = [];
        $keyword = mb_strtoupper(trim($this->searchKeyword));

        foreach ($rows as $row) {
            try {
                $json = json_decode($row->datadaftarpolirj_json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }

            $uploads = $json['pemeriksaan']['uploadHasilPenunjang'] ?? [];

            if (empty($uploads)) {
                continue;
            }

            foreach ($uploads as $upload) {
                if ($keyword !== '') {
                    $haystack = mb_strtoupper(($upload['desc'] ?? '') . ' ' . ($upload['penanggungJawab']['userLog'] ?? ''));
                    if (!str_contains($haystack, $keyword)) {
                        continue;
                    }
                }

                $items[] = [
                    'rjNo' => $row->rj_no,
                    'rjDate' => $row->rj_date,
                    'regName' => $row->reg_name,
                    'poliDesc' => $row->poli_desc,
                    'drName' => $row->dr_name,
                    // ── dari array uploadHasilPenunjang ──────────────────
                    'file' => $upload['file'] ?? '',
                    'desc' => $upload['desc'] ?? '-',
                    'tglUpload' => $upload['tglUpload'] ?? '-',
                    'userLog' => $upload['penanggungJawab']['userLog'] ?? '-',
                    'userLogDate' => $upload['penanggungJawab']['userLogDate'] ?? '-',
                    'userLogCode' => $upload['penanggungJawab']['userLogCode'] ?? '',
                ];
            }
        }

        return $items;
    }

    /* =======================
     | Stats
     * ======================= */
    #[Computed]
    public function statsUpload(): array
    {
        if (!$this->regNo) {
            return ['total' => 0, 'ada_file' => 0, 'total_kunjungan' => 0];
        }

        $all = $this->allItems;

        return [
            'total' => count($all),
            'ada_file' => collect($all)->filter(fn($i) => !empty($i['file']) && Storage::disk('local')->exists($i['file']))->count(),
            'total_kunjungan' => collect($all)->pluck('rjNo')->unique()->count(),
        ];
    }

    /* =======================
     | Rows — manual pagination karena data dari JSON bukan query langsung
     * ======================= */
    #[Computed]
    public function rows()
    {
        $all = $this->allItems;
        $page = $this->getPage();
        $per = $this->itemsPerPage;

        return new \Illuminate\Pagination\LengthAwarePaginator(array_slice($all, ($page - 1) * $per, $per), count($all), $per, $page, ['path' => request()->url()]);
    }

    /* =======================
     | Open / Close PDF Viewer
     * ======================= */
    public function openViewPDF(string $file): void
    {
        if (empty($file)) {
            $this->dispatch('toast', type: 'warning', message: 'File tidak tersedia.');
            return;
        }

        $fullPath = storage_path('/penunjang/upload/' . ltrim($file, '/'));

        if (!file_exists($fullPath)) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }

        $this->viewFilePDF = 'data:application/pdf;base64,' . base64_encode(file_get_contents($fullPath));
        $this->dispatch('open-modal', name: 'view-upload-penunjang-pdf');
    }

    public function closeViewPDF(): void
    {
        $this->viewFilePDF = '';
        $this->dispatch('close-modal', name: 'view-upload-penunjang-pdf');
    }
};
?>

<div>
    <div class="flex flex-col w-full">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- ── TABEL ───────────────────────────────────────────── --}}
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
                                                    <span>Riwayat Upload Hasil Penunjang</span>
                                                    @if ($regNo && count($this->allItems) > 0)
                                                        <span
                                                            class="px-2 py-0.5 text-sm bg-primary/10 text-primary rounded-full">
                                                            {{ count($this->allItems) }} File
                                                        </span>
                                                    @endif
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="bg-white dark:bg-gray-800">

                                        @forelse ($this->rows as $item)
                                            @php
                                                $fileExists =
                                                    !empty($item['file']) &&
                                                    file_exists(
                                                        storage_path('penunjang/upload/' . ltrim($item['file'], '/')),
                                                    );
                                            @endphp

                                            <tr class="border-b group dark:border-gray-700">
                                                <td
                                                    class="px-4 py-4 text-gray-900 transition-colors group-hover:bg-gray-50 dark:text-gray-100 dark:group-hover:bg-gray-750">

                                                    {{-- Header Row --}}
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex items-center space-x-2">

                                                            {{-- Icon PDF --}}
                                                            <div
                                                                class="flex items-center justify-center w-10 h-10 rounded-xl
                                                                        bg-yellow-400/10 dark:bg-yellow-400/15 shrink-0">
                                                                <svg class="w-5 h-5 text-yellow-500"
                                                                    xmlns="http://www.w3.org/2000/svg"
                                                                    fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0
                                                                           002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8 13h8v1.5H8V13zm0
                                                                           3h8v1.5H8V16zm0-6h3v1.5H8V10z" />
                                                                </svg>
                                                            </div>

                                                            <div>
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        class="font-bold text-gray-800 dark:text-gray-200">
                                                                        {{ $item['desc'] }}
                                                                    </span>
                                                                    @if ($fileExists)
                                                                        <span
                                                                            class="px-2 py-0.5 text-xs font-medium rounded-full text-green-700 bg-green-100">
                                                                            ✓ File Tersedia
                                                                        </span>
                                                                    @else
                                                                        <span
                                                                            class="px-2 py-0.5 text-xs font-medium rounded-full text-red-700 bg-red-100">
                                                                            ✗ File Tidak Ditemukan
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="mt-0.5 text-xs text-gray-400 font-mono">
                                                                    RJ No: {{ $item['rjNo'] }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Tgl Upload --}}
                                                        <div class="text-right text-gray-500 shrink-0">
                                                            <div class="text-xs font-mono">{{ $item['tglUpload'] }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Info Kunjungan --}}
                                                    <div class="p-2 mt-3 rounded bg-gray-50 dark:bg-gray-700">
                                                        <div class="flex items-center mb-1.5 space-x-1">
                                                            <svg class="w-3 h-3 text-primary" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            <span
                                                                class="text-xs font-semibold text-gray-600 dark:text-gray-300">Info
                                                                Kunjungan:</span>
                                                        </div>
                                                        <div class="flex flex-wrap gap-1">
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                                                {{ $item['rjDate'] }}
                                                            </span>
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200 dark:bg-gray-600 dark:text-gray-300">
                                                                {{ $item['poliDesc'] }}
                                                            </span>
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200 dark:bg-gray-600 dark:text-gray-300">
                                                                {{ $item['drName'] }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- Petugas --}}
                                                    <div class="mt-2 flex items-center gap-1 text-xs text-gray-400">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        <span>Diupload oleh:</span>
                                                        <span
                                                            class="font-medium text-gray-600 dark:text-gray-300">{{ $item['userLog'] }}</span>
                                                        <span class="font-mono">— {{ $item['userLogDate'] }}</span>
                                                    </div>

                                                    {{-- Actions --}}
                                                    @role(['Dokter', 'Admin', 'Perawat'])
                                                        <div class="flex items-center gap-2 mt-3">
                                                            <x-info-button type="button"
                                                                wire:click="openViewPDF('{{ $item['file'] }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="openViewPDF('{{ $item['file'] }}')">
                                                                <span wire:loading.remove
                                                                    wire:target="openViewPDF('{{ $item['file'] }}')"
                                                                    class="flex items-center gap-1">
                                                                    <svg class="w-4 h-4" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                    Lihat PDF
                                                                </span>
                                                                <span wire:loading
                                                                    wire:target="openViewPDF('{{ $item['file'] }}')"
                                                                    class="flex items-center gap-1">
                                                                    <svg class="w-4 h-4 animate-spin" fill="none"
                                                                        viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12"
                                                                            cy="12" r="10" stroke="currentColor"
                                                                            stroke-width="4" />
                                                                        <path class="opacity-75" fill="currentColor"
                                                                            d="M4 12a8 8 0 018-8v8z" />
                                                                    </svg>
                                                                    Memuat...
                                                                </span>
                                                            </x-info-button>
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
                                                                d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        <p class="mt-2 text-gray-500">
                                                            Belum ada file penunjang yang diupload untuk pasien ini.
                                                        </p>
                                                    @else
                                                        <p class="text-gray-500">Silakan pilih pasien terlebih dahulu.
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

                {{-- ── TOOLBAR STATS ───────────────────────────────────── --}}
                <div
                    class="flex flex-wrap items-center justify-between gap-3 p-3 mb-3 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">

                    {{-- Kiri: No. RM + Stats --}}
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="text-sm font-medium">
                                No. RM: <span class="font-semibold text-primary">{{ $regNo ?: '-' }}</span>
                            </span>
                        </div>

                        @if ($regNo)
                            <div class="flex flex-wrap gap-2">
                                <span class="px-2 py-1 text-sm rounded-full text-primary bg-primary/10">
                                    {{ $this->statsUpload['total'] }} Total File
                                </span>
                                <span class="px-2 py-1 text-sm text-green-700 bg-green-100 rounded-full">
                                    ✓ Tersedia: {{ $this->statsUpload['ada_file'] }}
                                </span>
                                <span class="px-2 py-1 text-sm text-blue-700 bg-blue-100 rounded-full">
                                    📋 Kunjungan: {{ $this->statsUpload['total_kunjungan'] }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Kanan: Filter --}}
                    @if ($regNo)
                        <div class="flex flex-wrap items-center gap-2">

                            {{-- Search --}}
                            <div class="relative">
                                <svg class="absolute left-2.5 top-2.5 w-4 h-4 text-gray-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input type="text" wire:model.live.debounce.400ms="searchKeyword"
                                    placeholder="Cari keterangan / petugas..."
                                    class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg
                                           focus:ring-2 focus:ring-primary focus:border-primary
                                           dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                            </div>

                            {{-- Filter Tahun --}}
                            <select wire:model.live="filterTahun"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg
                                       focus:ring-2 focus:ring-primary focus:border-primary
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Semua Tahun</option>
                                @foreach ($this->tahunList as $tahun)
                                    <option value="{{ $tahun }}">{{ $tahun }}</option>
                                @endforeach
                            </select>

                            {{-- Items per page --}}
                            <select wire:model.live="itemsPerPage"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg
                                       focus:ring-2 focus:ring-primary focus:border-primary
                                       dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="3">3 / hal</option>
                                <option value="5">5 / hal</option>
                                <option value="10">10 / hal</option>
                            </select>

                            {{-- Reset --}}
                            @if ($searchKeyword || $filterTahun)
                                <x-secondary-button wire:click="resetFilters" type="button">
                                    Reset
                                </x-secondary-button>
                            @endif

                        </div>
                    @endif

                </div>

            </div>
        </div>
    </div>

    {{-- ── MODAL LIHAT PDF ─────────────────────────────────────── --}}
    <x-modal name="view-upload-penunjang-pdf" size="full" height="full" focusable>

        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-upload-penunjang-{{ $viewFilePDF }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);
                           background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl
                                   bg-yellow-400/10 dark:bg-yellow-400/15">
                            <svg class="w-5 h-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1
                                         1.5L18.5 9H13V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16zm0-6h3v1.5H8V10z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Hasil Penunjang
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                Preview file PDF hasil penunjang pasien
                            </p>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeViewPDF" class="!p-2">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0
                                   111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414
                                   1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586
                                   10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY — iframe via base64 data URI, tidak perlu route --}}
            <div class="flex-1 min-h-0 bg-gray-100 dark:bg-gray-950">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0"
                        type="application/pdf"></iframe>
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="shrink-0 px-6 py-4 bg-white border-t border-gray-200
                       dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        File dibuka dalam mode preview — tidak dapat diedit.
                    </p>
                    <x-secondary-button type="button" wire:click="closeViewPDF">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>

    </x-modal>
</div>
