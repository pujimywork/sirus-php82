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

    // View PDF (modal + iframe)
    public string $viewFilePDF = '';
    public string $viewFileTitle = '';

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

        $rows = $this->baseQuery()->paginate($this->itemsPerPage);

        // Halaman di luar jangkauan (ganti pasien via :regNo reactive / filter) → reset.
        if ($rows->currentPage() > $rows->lastPage() && $rows->lastPage() >= 1) {
            $this->resetPage();
            $rows = $this->baseQuery()->paginate($this->itemsPerPage);
        }

        return $rows;
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
     | Resolve URL file radiologi
     |
     | Standar: file di SMB share, di-mount ke storage/app/private/mount/penunjang/radiologi/
     | → akses via route('files.show', path: 'mount/penunjang/radiologi/<filename>').
     |
     | Backward-compat: row lama menyimpan full path 'Radiologi/Foto/x.pdf' (public legacy)
     | → fallback ke asset('storage/' . $name).
     |
     | Pola identik dengan upload-radiologi.blade.php (sumber-of-truth).
     * ======================= */
    public function resolveFileUrl(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        return str_contains($name, '/') ? asset('storage/' . $name) : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $name]);
    }

    /* =======================
     | Open / Close PDF Viewer — modal + iframe pakai URL files.show
     * ======================= */
    public function openViewPDF(?string $file, string $title = 'Hasil Radiologi'): void
    {
        $url = $this->resolveFileUrl($file);

        if (!$url) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }

        $this->viewFilePDF = $url;
        $this->viewFileTitle = $title;
        $this->dispatch('open-modal', name: 'view-radiologi-pdf');
    }

    public function closeViewPDF(): void
    {
        $this->viewFilePDF = '';
        $this->viewFileTitle = '';
        $this->dispatch('close-modal', name: 'view-radiologi-pdf');
    }
};
?>

<div>
    <div class="flex flex-col w-full">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- Tabel Pemeriksaan Radiologi --}}
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="mb-2 overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-muted table-auto dark:text-gray-400">
                                    <thead
                                        class="text-sm text-body uppercase bg-surface-soft dark:bg-gray-700 dark:text-gray-400">
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

                                    <tbody class="bg-canvas dark:bg-gray-800">
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
                                                    class="px-4 py-4 text-ink transition-colors group-hover:bg-surface-soft dark:text-gray-100 dark:group-hover:bg-gray-750">

                                                    {{-- Header Row --}}
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex items-center space-x-2">
                                                            <span class="text-2xl">{{ $layananIcon }}</span>
                                                            <div>
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        class="font-bold {{ $layananClass }}">{{ $layananText }}</span>
                                                                    <span class="text-muted-soft">|</span>
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
                                                                <div class="mt-0.5 text-xs text-muted-soft font-mono">
                                                                    No: {{ $row->txn_no }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-right text-muted shrink-0">
                                                            <div>{{ $row->rad_date }}</div>
                                                        </div>
                                                    </div>

                                                    {{-- Item Pemeriksaan --}}
                                                    <div class="p-2 mt-3 rounded bg-surface-soft dark:bg-gray-700">
                                                        <div class="flex items-center mb-1.5 space-x-1">
                                                            <svg class="w-3 h-3 text-brand-blue" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                                            </svg>
                                                            <span
                                                                class="text-xs font-semibold text-muted dark:text-gray-300">Item
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

                                                            {{-- Tombol Hasil Bacaan — outline-button (brand-green, sama dengan lab-luar) --}}
                                                            @if ($hasHasil)
                                                                <x-outline-button type="button"
                                                                    wire:click="openViewPDF({{ json_encode($row->rad_upload_pdf) }}, 'Hasil Bacaan Radiologi')">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                    Hasil Bacaan
                                                                </x-outline-button>
                                                            @endif

                                                            {{-- Tombol Foto Radiologi — info-button (solid blue) untuk bedakan tipe --}}
                                                            @if ($hasFoto)
                                                                <x-info-button type="button"
                                                                    wire:click="openViewPDF({{ json_encode($row->rad_upload_pdf_foto) }}, 'Foto Radiologi')">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                    Foto Radiologi
                                                                </x-info-button>
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
                                                        <p class="mt-2 text-muted">Tidak ada data pemeriksaan
                                                            radiologi</p>
                                                    @else
                                                        <p class="text-muted">Silakan pilih pasien terlebih dahulu
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
                    class="flex flex-wrap items-center justify-between gap-3 p-3 mb-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    {{-- ──────────────────────────────────────────────────────────────────
         MODAL: PDF Viewer (iframe pakai URL files.show, bukan base64)
    ────────────────────────────────────────────────────────────────────── --}}
    <x-modal name="view-radiologi-pdf" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-radiologi-{{ $viewFilePDF }}">
            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700">
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                    {{ $viewFileTitle ?: 'Lihat Hasil Radiologi' }}
                </h2>
                <div class="flex items-center gap-2">
                    @if ($viewFilePDF)
                        <a href="{{ $viewFilePDF }}" target="_blank" rel="noopener"
                            class="px-3 py-1.5 text-xs font-medium text-body bg-surface-soft rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Buka di Tab Baru
                        </a>
                    @endif
                    <x-icon-button color="gray" type="button" wire:click="closeViewPDF">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — iframe streaming via route files.show --}}
            <div class="flex-1 p-2 bg-surface-soft dark:bg-gray-900">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0"
                        type="application/pdf"></iframe>
                @endif
            </div>
        </div>
    </x-modal>
</div>
