<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['laborat-modal'];

    /* =======================
     | Filter & Pagination
     * ======================= */
    #[Reactive]
    public string $regNo = '';
    public string $searchKeyword = '';
    public string $filterTahun = '';
    public string $filterStatus = '';
    public int $itemsPerPage = 3;

    /* =======================
     | Modal Detail
     * ======================= */
    public string $selectedCheckupNo = '';
    public string $detailLayanan = ''; // RJ / UGD / RI
    public array $selectedRows = []; // baris yang dicentang
    public array $detailTxn = [];
    public array $detailTxnLuar = [];
    public array $detailHeader = [];

    /* =======================
     | Mount
     * ======================= */
    public function mount($regNo = ''): void
    {
        $this->regNo = $regNo;
        $this->registerAreas($this->renderAreas);
    }

    /* =======================
     | Load Pasien dari luar
     * ======================= */
    public function loadPasien($regNo): void
    {
        $this->regNo = $regNo;
        $this->resetPage();
    }

    /* =======================
     | Reset filters
     * ======================= */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterTahun', 'filterStatus']);
        $this->resetPage();
    }

    /* =======================
     | Updated hooks
     * ======================= */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
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

        return DB::table('rsview_checkups')->select(DB::raw('DISTINCT EXTRACT(YEAR FROM checkup_date) as tahun'))->where('reg_no', $this->regNo)->orderBy('tahun', 'desc')->pluck('tahun');
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

        $query = DB::table('rsview_checkups')
            ->select(
                DB::raw("to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') AS checkup_date"),
                DB::raw("to_char(checkup_date,'yyyymmddhh24miss') AS checkup_date1"),
                'checkup_no',
                'reg_no',
                'reg_name',
                'sex',
                'birth_date',
                'address',
                'checkup_status',
                'checkup_rjri',
                DB::raw("(
                    SELECT string_agg(clabitem_desc)
                    FROM lbtxn_checkupdtls a
                    JOIN lbmst_clabitems b ON a.clabitem_id = b.clabitem_id
                    WHERE checkup_no = rsview_checkups.checkup_no
                    AND a.price IS NOT NULL
                ) AS checkup_dtl_pasien"),
            )
            ->where('reg_no', $this->regNo);

        if ($this->filterTahun) {
            $query->whereYear('checkup_date', $this->filterTahun);
        }

        if ($this->filterStatus) {
            $query->where('checkup_status', $this->filterStatus);
        }

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);
            $query->where(function ($q) use ($searchKeyword, $upper) {
                if (ctype_digit($searchKeyword)) {
                    $q->orWhere('checkup_no', 'like', "%{$searchKeyword}%");
                }
                $q->orWhereRaw('UPPER(checkup_dtl_pasien) LIKE ?', ["%{$upper}%"])->orWhereRaw('UPPER(checkup_rjri) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $query->orderBy('checkup_date1', 'desc');
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
     | Stats Pemeriksaan
     * ======================= */
    #[Computed]
    public function statsPemeriksaan()
    {
        if (!$this->regNo) {
            return ['total' => 0, 'selesai' => 0, 'proses' => 0, 'terdaftar' => 0];
        }

        $stats = DB::table('rsview_checkups')->select(DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN checkup_status = 'H' THEN 1 ELSE 0 END) as selesai"), DB::raw("SUM(CASE WHEN checkup_status = 'C' THEN 1 ELSE 0 END) as proses"), DB::raw("SUM(CASE WHEN checkup_status = 'P' THEN 1 ELSE 0 END) as terdaftar"))->where('reg_no', $this->regNo)->first();

        return [
            'total' => $stats->total ?? 0,
            'selesai' => $stats->selesai ?? 0,
            'proses' => $stats->proses ?? 0,
            'terdaftar' => $stats->terdaftar ?? 0,
        ];
    }

    /* =======================
     | Open Detail Modal
     * ======================= */
    public function openDetail($checkupNo, $layanan = ''): void
    {
        $this->selectedCheckupNo = $checkupNo;
        $this->detailLayanan = strtoupper($layanan);
        $this->selectedRows = [];

        $header = collect(
            DB::select(
                "
            SELECT DISTINCT a.emp_id, a.checkup_no, checkup_date, a.reg_no, reg_name,
                   a.dr_id, dr_name, sex, birth_date, c.address, emp_name,
                   waktu_selesai_pelayanan, checkup_kesimpulan
            FROM lbtxn_checkuphdrs a
            JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
            JOIN rsmst_doctors f ON a.dr_id = f.dr_id
            JOIN immst_employers g ON a.emp_id = g.emp_id
            WHERE a.checkup_no = :cno
        ",
                ['cno' => $checkupNo],
            ),
        )->first();

        $this->detailHeader = collect($header)->toArray();

        $this->detailTxn = DB::select(
            "
            SELECT a.emp_id, a.checkup_no, checkup_date, a.reg_no, reg_name, a.dr_id, dr_name,
                   sex, birth_date, c.address, emp_name, app_seq, clab_desc,
                   b.clabitem_id, ('  ' || clabitem_desc) AS clabitem_desc, checkup_kesimpulan,
                   normal_f, normal_m, lab_result, item_seq,
                   unit_desc, unit_convert, item_code,
                   high_limit_m, high_limit_f, low_limit_m, low_limit_f,
                   lowhigh_status, lab_result_status,
                   to_char(checkup_date,'dd/mm/yyyy') AS checkup_date1x,
                   WAKTU_SELESAI_PELAYANAN
            FROM lbtxn_checkuphdrs a
            JOIN lbtxn_checkupdtls b ON a.checkup_no = b.checkup_no
            JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
            JOIN lbmst_clabitems d ON b.clabitem_id = d.clabitem_id
            JOIN lbmst_clabs e ON d.clab_id = e.clab_id
            JOIN rsmst_doctors f ON a.dr_id = f.dr_id
            JOIN immst_employers g ON a.emp_id = g.emp_id
            WHERE a.checkup_no = :cno
              AND nvl(hidden_status,'N') = 'N'
            ORDER BY app_seq, item_seq, clabitem_desc
        ",
            ['cno' => $checkupNo],
        );

        $this->detailTxnLuar = DB::select(
            "
            SELECT a.emp_id, a.checkup_no, checkup_date, a.reg_no, reg_name, a.dr_id, dr_name,
                   sex, birth_date, emp_name,
                   ('  ' || labout_desc) AS labout_desc, labout_result, labout_normal
            FROM lbtxn_checkuphdrs a
            JOIN lbtxn_checkupoutdtls b ON a.checkup_no = b.checkup_no
            JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
            JOIN rsmst_doctors d ON a.dr_id = d.dr_id
            JOIN immst_employers e ON a.emp_id = e.emp_id
            WHERE a.checkup_no = :cno
            ORDER BY checkup_no, labout_dtl, labout_desc
        ",
            ['cno' => $checkupNo],
        );

        $this->incrementVersion('laborat-modal');
        $version = $this->renderVersions['laborat-modal'] ?? 0;
        $this->dispatch('open-modal', name: "laborat-detail-{$version}");
    }

    public function closeDetail(): void
    {
        $version = $this->renderVersions['laborat-modal'] ?? 0;
        $this->dispatch('close-modal', name: "laborat-detail-{$version}");
        $this->reset(['selectedCheckupNo', 'detailLayanan', 'selectedRows', 'detailTxn', 'detailTxnLuar', 'detailHeader']);
    }

    /* =======================
     | Row Selection
     * ======================= */
    public function rowSelected(string $id): void
    {
        if ($id === '') {
            return;
        }

        $index = collect($this->selectedRows)->search(fn($r) => $r['id'] === $id);

        if ($index !== false) {
            unset($this->selectedRows[$index]);
            $this->selectedRows = array_values($this->selectedRows);
        } else {
            // Lookup data dari detailTxn yang sudah ada di state
            $item = collect($this->detailTxn)->first(fn($r) => trim($r->clabitem_id ?? '') === $id);

            if ($item) {
                $this->selectedRows[] = [
                    'id' => $id,
                    'desc' => trim($item->clabitem_desc ?? ''),
                    'hasil' => $item->lab_result ?? '',
                    'unit' => $item->unit_desc ?? '',
                ];
            }
        }
    }

    public function isRowSelected(string $id): bool
    {
        return collect($this->selectedRows)->contains('id', $id);
    }

    /* =======================
     | Kirim ke Penunjang
     * ======================= */
    public function kirimKePenunjang(): void
    {
        if (empty($this->selectedRows)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        // Format teks: "HAEMOGLOBIN (11.7 g/dL)\nERITROSIT (4,900,000 /uL)"
        $text = collect($this->selectedRows)
            ->map(function ($r) {
                $desc = trim($r['desc']);
                $hasil = $r['hasil'];
                $unit = $r['unit'];

                return $hasil !== '' && $hasil !== null ? "{$desc} ({$hasil} {$unit})" : "{$desc} ( )";
            })
            ->implode("\n");

        // Kirim event ke parent saja (->up() agar tidak broadcast global dan tidak double)
        $this->dispatch('laborat-kirim-penunjang', text: $text);
        $this->dispatch('toast', type: 'success', message: count($this->selectedRows) . ' item dikirim ke Penunjang.');
        $this->closeDetail();
    }

    /* =======================
     | Cetak PDF
     * ======================= */
    public function cetakLaborat(string $checkupNo): mixed
    {
        $header = collect(
            DB::select(
                "
                SELECT DISTINCT a.emp_id, a.checkup_no,
                       to_char(checkup_date,'dd/mm/yyyy hh24:mi:ss') AS checkup_date,
                       a.reg_no, reg_name, a.dr_id, dr_name,
                       sex, birth_date, c.address, emp_name,
                       waktu_selesai_pelayanan, checkup_kesimpulan
                FROM lbtxn_checkuphdrs a
                JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
                JOIN rsmst_doctors f ON a.dr_id = f.dr_id
                JOIN immst_employers g ON a.emp_id = g.emp_id
                WHERE a.checkup_no = :cno
            ",
                ['cno' => $checkupNo],
            ),
        )->first();

        if (!$header) {
            $this->dispatch('toast', type: 'error', message: 'Data pemeriksaan tidak ditemukan.');
            return null;
        }

        $txn = DB::select(
            "
            SELECT b.clabitem_id, clabitem_desc, clab_desc, app_seq, item_seq,
                   lab_result, unit_desc, item_code,
                   normal_f, normal_m, high_limit_m, high_limit_f,
                   low_limit_m, low_limit_f, lowhigh_status, lab_result_status,
                   sex, a.dr_id, dr_name, a.emp_id, emp_name
            FROM lbtxn_checkuphdrs a
            JOIN lbtxn_checkupdtls b ON a.checkup_no = b.checkup_no
            JOIN rsmst_pasiens c ON a.reg_no = c.reg_no
            JOIN lbmst_clabitems d ON b.clabitem_id = d.clabitem_id
            JOIN lbmst_clabs e ON d.clab_id = e.clab_id
            JOIN rsmst_doctors f ON a.dr_id = f.dr_id
            JOIN immst_employers g ON a.emp_id = g.emp_id
            WHERE a.checkup_no = :cno
              AND nvl(hidden_status,'N') = 'N'
            ORDER BY app_seq, item_seq, clabitem_desc
        ",
            ['cno' => $checkupNo],
        );

        $txnLuar = DB::select(
            "
            SELECT ('  ' || labout_desc) AS labout_desc, labout_result, labout_normal
            FROM lbtxn_checkuphdrs a
            JOIN lbtxn_checkupoutdtls b ON a.checkup_no = b.checkup_no
            WHERE a.checkup_no = :cno
            ORDER BY labout_dtl, labout_desc
        ",
            ['cno' => $checkupNo],
        );

        $pdf = Pdf::loadView('pages.components.rekam-medis.rekam-medis.penunjang.laboratorium-display.laboratorium-display-print', compact('header', 'txn', 'txnLuar'))->setPaper('a4', 'portrait');

        $filename = 'laborat-' . $checkupNo . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};

?>

<div>
    <div class="flex flex-col w-full">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- Tabel Pemeriksaan Laboratorium --}}
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
                                                    <span>Riwayat Pemeriksaan Laboratorium</span>
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
                                                    // Status: P=Terdaftar, C=Proses, H=Selesai
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
                                                    $statusClass = $isSelesai
                                                        ? 'text-green-700 bg-green-100'
                                                        : ($isProses
                                                            ? 'text-amber-700 bg-amber-100'
                                                            : 'text-gray-600 bg-gray-100');
                                                    $statusIcon = $isSelesai ? '✓' : ($isProses ? '⏳' : '📋');

                                                    // Layanan: RJ, UGD, RI (exact match)
                                                    $layanan = $row->checkup_rjri ?? '';
                                                    $isRI = $layanan === 'RI';
                                                    $isUGD = $layanan === 'UGD';
                                                    $isRJ = $layanan === 'RJ';

                                                    $layananIcon = $isRI ? '🏥' : ($isUGD ? '🚑' : '🔬');
                                                    $layananClass = $isRI
                                                        ? 'text-purple-600'
                                                        : ($isUGD
                                                            ? 'text-red-600'
                                                            : 'text-teal-600');
                                                    $layananText = $isRI
                                                        ? 'Rawat Inap'
                                                        : ($isUGD
                                                            ? 'UGD'
                                                            : ($isRJ
                                                                ? 'Rawat Jalan'
                                                                : '-'));

                                                    // Pisah list item lab
                                                    $itemList = collect(explode(',', $row->checkup_dtl_pasien ?? ''))
                                                        ->map(fn($i) => trim($i))
                                                        ->filter()
                                                        ->values();
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

                                                                    @if ($isSelesai || $isProses || $isTerdaftar)
                                                                        <span
                                                                            class="px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClass }}">
                                                                            {{ $statusIcon }} {{ $statusText }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="mt-0.5 text-xs text-gray-400 font-mono">
                                                                    No: {{ $row->checkup_no }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-right text-gray-500 shrink-0">
                                                            <div>{{ $row->checkup_date }}</div>
                                                        </div>
                                                    </div>

                                                    {{-- Daftar Item Pemeriksaan --}}
                                                    <div class="p-2 mt-3 rounded bg-gray-50 dark:bg-gray-700">
                                                        <div class="flex items-center mb-1.5 space-x-1">
                                                            <svg class="w-3 h-3 text-brand-green" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                            </svg>
                                                            <span
                                                                class="text-xs font-semibold text-gray-600 dark:text-gray-300">Item
                                                                Pemeriksaan:</span>
                                                        </div>

                                                        @if ($itemList->isNotEmpty())
                                                            <div class="flex flex-wrap gap-1">
                                                                @foreach ($itemList->take(6) as $item)
                                                                    <span
                                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-green/10 text-brand-green border border-brand-green/20">
                                                                        {{ $item }}
                                                                    </span>
                                                                @endforeach
                                                                @if ($itemList->count() > 6)
                                                                    <span
                                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
                                                                        +{{ $itemList->count() - 6 }} lainnya
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <span class="text-xs italic text-gray-400">Tidak ada item
                                                                pemeriksaan</span>
                                                        @endif
                                                    </div>

                                                    {{-- Actions --}}
                                                    @role(['Dokter', 'Admin', 'Perawat', 'Laborat'])
                                                        <div class="flex items-center gap-2 mt-3">
                                                            {{-- Tombol Hasil Laboratorium --}}
                                                            <x-info-button type="button"
                                                                wire:click="openDetail('{{ $row->checkup_no }}', '{{ $row->checkup_rjri }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="openDetail('{{ $row->checkup_no }}', '{{ $row->checkup_rjri }}')">
                                                                <span wire:loading.remove
                                                                    wire:target="openDetail('{{ $row->checkup_no }}', '{{ $row->checkup_rjri }}')"
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
                                                                    Hasil Laboratorium
                                                                </span>
                                                                <span wire:loading
                                                                    wire:target="openDetail('{{ $row->checkup_no }}', '{{ $row->checkup_rjri }}')"
                                                                    class="flex items-center gap-1">
                                                                    <x-loading /> Memuat...
                                                                </span>
                                                                </x-yellow-button>

                                                                {{-- Tombol Cetak (hanya jika Selesai) --}}
                                                                @if ($isSelesai)
                                                                    <x-primary-button type="button"
                                                                        wire:click="cetakLaborat('{{ $row->checkup_no }}')"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="cetakLaborat('{{ $row->checkup_no }}')"
                                                                        class="text-sm px-3 py-1.5">
                                                                        <span wire:loading.remove
                                                                            wire:target="cetakLaborat('{{ $row->checkup_no }}')"
                                                                            class="flex items-center gap-1">
                                                                            <svg class="w-4 h-4" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                            </svg>
                                                                            Cetak
                                                                        </span>
                                                                        <span wire:loading
                                                                            wire:target="cetakLaborat('{{ $row->checkup_no }}')"
                                                                            class="flex items-center gap-1">
                                                                            <x-loading /> Mencetak...
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
                                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                        </svg>
                                                        <p class="mt-2 text-gray-500">Tidak ada data pemeriksaan
                                                            laboratorium</p>
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
                                <span class="px-2 py-1 text-sm rounded-full text-brand bg-brand-green/10">
                                    {{ $this->statsPemeriksaan['total'] }} Total
                                </span>
                                <span class="px-2 py-1 text-sm text-gray-600 bg-gray-100 rounded-full">
                                    📋 Terdaftar: {{ $this->statsPemeriksaan['terdaftar'] }}
                                </span>
                                <span class="px-2 py-1 text-sm rounded-full text-amber-700 bg-amber-100">
                                    ⏳ Proses: {{ $this->statsPemeriksaan['proses'] }}
                                </span>
                                <span class="px-2 py-1 text-sm rounded-full text-brand-green bg-brand-green/10">
                                    ✓ Selesai: {{ $this->statsPemeriksaan['selesai'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- =============================================
         Modal Detail Hasil Laboratorium
         ============================================= --}}
    <x-modal name="laborat-detail-{{ $renderVersions['laborat-modal'] ?? 0 }}" size="full" height="full"
        focusable>
        <div class="flex flex-col"
            wire:key="{{ $this->renderKey('laborat-modal', [$selectedCheckupNo ?: 'empty']) }}">

            {{-- Modal Header --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-green/15">
                                <svg class="w-5 h-5 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    🔬 Hasil Laboratorium
                                </h2>
                                @if (!empty($detailHeader))
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        No: <span
                                            class="font-mono font-medium">{{ $detailHeader['checkup_no'] ?? '-' }}</span>
                                        &nbsp;|&nbsp; {{ $detailHeader['reg_name'] ?? '-' }}
                                        &nbsp;|&nbsp; {{ $detailHeader['checkup_date'] ?? '-' }}
                                    </p>
                                    @if (!empty($detailHeader['checkup_kesimpulan']))
                                        <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                            📋 Kesimpulan: {{ $detailHeader['checkup_kesimpulan'] }}
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Close button --}}
                    <x-secondary-button type="button" wire:click="closeDetail" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 p-5 overflow-y-auto max-h-[65vh] bg-gray-50/70 dark:bg-gray-950/20">

                {{-- Hasil Lab Internal --}}
                @if (!empty($detailTxn))
                    @php
                        $groupedByPanel = collect($detailTxn)->groupBy('clab_desc');
                    @endphp

                    @foreach ($groupedByPanel as $panelName => $items)
                        <div class="mb-4">
                            {{-- Panel Header --}}
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                                <span
                                    class="px-3 py-0.5 text-xs font-bold text-brand-green bg-brand-green/10 border border-brand-green/30 rounded-full uppercase tracking-wide">
                                    {{ $panelName }}
                                </span>
                                <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                            </div>

                            {{-- Items Table --}}
                            <div class="overflow-hidden border border-gray-100 rounded-lg dark:border-gray-700">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold text-left">Pemeriksaan</th>
                                            <th class="px-3 py-2 font-semibold text-center">Hasil</th>
                                            <th class="px-3 py-2 font-semibold text-center">Satuan</th>
                                            <th class="px-3 py-2 font-semibold text-center">Nilai Normal</th>
                                            <th class="px-3 py-2 font-semibold text-center">Flag</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                        @foreach ($items as $item)
                                            @php
                                                // lab_result_status = flag teks (H, L, HH, LL, dst)
                                                $flagStatus = strtoupper(trim($item->lab_result_status ?? ''));
                                                $isHigh = in_array($flagStatus, ['H', 'HH', 'HIGH']);
                                                $isLow = in_array($flagStatus, ['L', 'LL', 'LOW']);
                                                $hasFlag = $flagStatus !== '';

                                                $rowClass = $isHigh
                                                    ? 'bg-red-50 dark:bg-red-900/10'
                                                    : ($isLow
                                                        ? 'bg-blue-50 dark:bg-blue-900/10'
                                                        : 'bg-white dark:bg-gray-800');

                                                $hasilClass = $isHigh
                                                    ? 'font-bold text-red-600'
                                                    : ($isLow
                                                        ? 'font-bold text-blue-600'
                                                        : 'text-gray-800 dark:text-gray-200');

                                                $sex = strtoupper($item->sex ?? 'L');
                                                $normalLow =
                                                    $sex === 'P' ? $item->low_limit_f ?? '' : $item->low_limit_m ?? '';
                                                $normalHigh =
                                                    $sex === 'P'
                                                        ? $item->high_limit_f ?? ''
                                                        : $item->high_limit_m ?? '';
                                                $normalRange =
                                                    $normalLow !== '' && $normalHigh !== ''
                                                        ? "{$normalLow} – {$normalHigh}"
                                                        : ($sex === 'P'
                                                            ? $item->normal_f ?? '-'
                                                            : $item->normal_m ?? '-');
                                            @endphp
                                            @php
                                                $itemId = trim($item->clabitem_id ?? '');
                                                $itemDesc = trim($item->clabitem_desc ?? '');
                                                $isChecked = $itemId ? $this->isRowSelected($itemId) : false;
                                                $isHeader = $itemId === '' || str_starts_with($itemDesc, '*');
                                            @endphp
                                            <tr wire:key="lab-row-{{ $itemId ?: 'hdr-' . $loop->index }}"
                                                @if (!$isHeader) wire:click="rowSelected('{{ $itemId }}')"
                                                    class="{{ $rowClass }} {{ $isChecked ? 'ring-2 ring-inset ring-brand-lime bg-brand-green/5 dark:bg-brand-green/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }} cursor-pointer select-none transition-colors"
                                                @else
                                                    class="{{ $rowClass }} bg-gray-50 dark:bg-gray-700/60 font-semibold" @endif>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                                    {{ $itemDesc }}
                                                </td>
                                                <td class="px-3 py-2 text-center {{ $hasilClass }}">
                                                    {{ $item->lab_result ?? '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-center text-gray-500">
                                                    {{ $item->unit_desc ?? '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-center text-gray-500">
                                                    {{ $normalRange }}
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    @if ($isHigh)
                                                        <span
                                                            class="px-1.5 py-0.5 text-xs font-bold text-red-700 bg-red-100 rounded">▲
                                                            {{ $flagStatus }}</span>
                                                    @elseif ($isLow)
                                                        <span
                                                            class="px-1.5 py-0.5 text-xs font-bold text-blue-700 bg-blue-100 rounded">▼
                                                            {{ $flagStatus }}</span>
                                                    @elseif ($hasFlag)
                                                        <span
                                                            class="px-1.5 py-0.5 text-xs font-bold text-orange-600 bg-orange-50 rounded">{{ $flagStatus }}</span>
                                                    @else
                                                        <span class="text-xs text-gray-300">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Hasil Lab Luar --}}
                @if (!empty($detailTxnLuar))
                    <div class="mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                            <span
                                class="px-3 py-0.5 text-xs font-bold text-orange-700 bg-orange-50 border border-orange-200 rounded-full uppercase tracking-wide">
                                Laboratorium Luar
                            </span>
                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                        </div>
                        <div class="overflow-hidden border border-gray-100 rounded-lg dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold text-left">Pemeriksaan</th>
                                        <th class="px-3 py-2 font-semibold text-center">Hasil</th>
                                        <th class="px-3 py-2 font-semibold text-left">Nilai Normal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    @foreach ($detailTxnLuar as $luar)
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                                {{ trim($luar->labout_desc) }}</td>
                                            <td
                                                class="px-3 py-2 font-medium text-center text-gray-800 dark:text-gray-200">
                                                {{ $luar->labout_result ?? '-' }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-500">
                                                {{ $luar->labout_normal ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (empty($detailTxn) && empty($detailTxnLuar))
                    <div class="py-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p>Belum ada data hasil pemeriksaan</p>
                    </div>
                @endif
            </div>

            {{-- Modal Footer --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">

                    {{-- Kiri: info selected rows --}}
                    <div>
                        @if (count($selectedRows) > 0)
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-brand-green bg-brand-green/10 border border-brand-green/30 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                {{ count($selectedRows) }} item dipilih
                            </span>
                        @else
                            <span class="text-xs italic text-gray-400">Klik baris untuk memilih item</span>
                        @endif
                    </div>

                    {{-- Kanan: action buttons --}}
                    <div class="flex items-center gap-3">
                        <x-secondary-button wire:click="closeDetail">
                            Tutup
                        </x-secondary-button>

                        {{-- Kirim ke Penunjang — tampil jika ada yang dipilih --}}
                        @if (count($selectedRows) > 0)
                            <x-info-button type="button" wire:click="kirimKePenunjang" wire:loading.attr="disabled"
                                wire:target="kirimKePenunjang">
                                <span wire:loading.remove wire:target="kirimKePenunjang"
                                    class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Kirim ke Penunjang
                                </span>
                                <span wire:loading wire:target="kirimKePenunjang" class="flex items-center gap-1">
                                    <x-loading /> Mengirim...
                                </span>
                            </x-info-button>
                        @endif

                        @role(['Dokter', 'Admin', 'Laborat'])
                            @if (!empty($selectedCheckupNo))
                                <x-primary-button type="button" wire:click="cetakLaborat('{{ $selectedCheckupNo }}')"
                                    wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="cetakLaborat" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                        Cetak Hasil
                                    </span>
                                    <span wire:loading wire:target="cetakLaborat" class="flex items-center gap-1">
                                        <x-loading /> Mencetak...
                                    </span>
                                </x-primary-button>
                            @endif
                        @endrole
                    </div>
                </div>
            </div>

        </div>
    </x-modal>

</div>
