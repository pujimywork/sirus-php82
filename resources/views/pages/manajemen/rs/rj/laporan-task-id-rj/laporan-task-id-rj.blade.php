<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterBulan = ''; // mm/yyyy
    public string $filterPoli = '';
    public string $filterDokter = '';
    public string $filterStatus = ''; // '' | A | L | F | I
    public string $filterKlaim = 'BPJS'; // default BPJS karena task-id Antrol khusus BPJS
    public bool $filterIncompleteOnly = false;
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void        { $this->resetPage(); }
    public function updatedFilterBulan(): void          { $this->resetPage(); }
    public function updatedFilterPoli(): void           { $this->resetPage(); }
    public function updatedFilterDokter(): void         { $this->resetPage(); }
    public function updatedFilterStatus(): void         { $this->resetPage(); }
    public function updatedFilterKlaim(): void          { $this->resetPage(); }
    public function updatedFilterIncompleteOnly(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void         { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterPoli', 'filterDokter', 'filterStatus', 'filterIncompleteOnly']);
        $this->filterKlaim = 'BPJS';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $d = Carbon::now()->startOfMonth();
        }
        return [$d, (clone $d)->endOfMonth()];
    }

    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                'h.rj_no',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy') as rj_date_display"),
                'h.reg_no',
                'p.reg_name',
                'po.poli_desc',
                'd.dr_name',
                'h.klaim_id',
                'k.klaim_status',
                'h.no_antrian',
                'h.rj_status',
                'h.datadaftarpolirj_json',
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.no_antrian', 'asc');

        // Default BPJS-only — task-id Antrol khusus BPJS/JKN Mobile
        if ($this->filterKlaim === 'BPJS') {
            $query->where(function ($q) {
                $q->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            });
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where(function ($q) {
                $q->where(function ($w) {
                    $w->where('k.klaim_status', '!=', 'BPJS')->orWhereNull('k.klaim_status');
                })->where('h.klaim_id', '!=', 'JM');
            });
        }

        if ($this->filterPoli !== '') {
            $query->where('h.poli_id', $this->filterPoli);
        }

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        if ($this->filterStatus !== '') {
            $query->where('h.rj_status', $this->filterStatus);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rj_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    #[Computed]
    public function mappedAll()
    {
        // Fetch semua baris dalam periode + decode JSON + cek lengkap + apply filterIncompleteOnly
        $all = $this->baseQuery()->get()->map(function ($row) {
            $json = json_decode($row->datadaftarpolirj_json ?? '{}', true) ?: [];
            $tp = $json['taskIdPelayanan'] ?? [];
            $row->no_booking = $json['noBooking'] ?? '-';

            for ($i = 1; $i <= 7; $i++) {
                $row->{"task_id{$i}"} = $tp["taskId{$i}"] ?? null;
                $row->{"task_id{$i}_status"} = $tp["taskId{$i}Status"] ?? null;
            }
            $row->task_id99 = $tp['taskId99'] ?? null;
            $row->task_id99_status = $tp['taskId99Status'] ?? null;

            $present = [];
            for ($i = 1; $i <= 7; $i++) {
                if (!empty($row->{"task_id{$i}"})) $present[] = $i;
            }
            $hasT99 = !empty($row->task_id99);
            $row->lengkap_check = $this->checkLengkap($present, $hasT99);

            // Selisih durasi inline:
            //   T1→T2 (Tunggu Admisi), T2→T3 (Layan Admisi),
            //   T3→T4 (Tunggu Poli),   T4→T5 (Layan Poli),  T6→T7 (Layan Farmasi)
            $row->delta_t1_t2 = $this->durationLabel($row->task_id1, $row->task_id2);
            $row->delta_t2_t3 = $this->durationLabel($row->task_id2, $row->task_id3);
            $row->delta_t3_t4 = $this->durationLabel($row->task_id3, $row->task_id4);
            $row->delta_t4_t5 = $this->durationLabel($row->task_id4, $row->task_id5);
            $row->delta_t6_t7 = $this->durationLabel($row->task_id6, $row->task_id7);

            return $row;
        });

        if ($this->filterIncompleteOnly) {
            $all = $all->filter(fn($r) => !$r->lengkap_check['lengkap'])->values();
        }

        return $all;
    }

    #[Computed]
    public function rows()
    {
        $all = $this->mappedAll;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage = $this->itemsPerPage;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $all->slice(($page - 1) * $perPage, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Summary stats: count + rata-rata waktu per stage (dari semua mappedAll, sebelum pagination)
     * Stage diff = task_idEND - task_idSTART. Hanya baris yang punya KEDUA timestamp yang ikut dihitung.
     */
    #[Computed]
    public function summary(): array
    {
        $all = $this->mappedAll;
        $total = $all->count();

        $stages = [
            'tunggu_admisi' => ['label' => 'Tunggu Admisi', 'from' => 'task_id1', 'to' => 'task_id2'],
            'layan_admisi'  => ['label' => 'Layan Admisi',  'from' => 'task_id2', 'to' => 'task_id3'],
            'tunggu_poli'   => ['label' => 'Tunggu Poli',   'from' => 'task_id3', 'to' => 'task_id4'],
            'layan_poli'    => ['label' => 'Layan Poli',    'from' => 'task_id4', 'to' => 'task_id5'],
            'layan_farmasi' => ['label' => 'Layan Farmasi', 'from' => 'task_id6', 'to' => 'task_id7'],
        ];

        // Untuk hitung rata-rata: keluarkan baris Batal (T99) — mereka tidak menggambarkan waktu pelayanan normal
        $forAvg = $all->filter(fn($r) => empty($r->task_id99))->values();

        foreach ($stages as &$stg) {
            [$avg, $cnt, $out, $sum] = $this->avgDurationSeconds($forAvg, $stg['from'], $stg['to']);
            $stg['avg_seconds']   = $avg;
            $stg['avg_label']     = $this->formatDuration($avg);
            $stg['total_seconds'] = $sum;
            $stg['total_label']   = $this->formatDuration($sum > 0 ? $sum : null);
            $stg['count']         = $cnt;
            $stg['outliers']      = $out;
        }
        unset($stg);

        // Total pelayanan: T7 - (T1 jika ada, kalau tidak T3) — pakai earliest available start
        [$totalSec, $totalCnt, $totalOut, $totalSum] = $this->avgTotalDuration($forAvg);

        $countLengkap = $all->filter(fn($r) => $r->lengkap_check['lengkap'] && empty($r->task_id99))->count();
        $countBatal   = $all->filter(fn($r) => !empty($r->task_id99))->count();
        $countTidak   = $all->filter(fn($r) => !$r->lengkap_check['lengkap'])->count();

        // Breakdown: berapa pasien yang punya timestamp di tiap task
        $taskCounts = [];
        for ($i = 1; $i <= 7; $i++) {
            $taskCounts[$i] = $all->filter(fn($r) => !empty($r->{"task_id{$i}"}))->count();
        }
        $taskCounts[99] = $all->filter(fn($r) => !empty($r->task_id99))->count();

        return [
            'total'         => $total,
            'total_non_batal' => $forAvg->count(),
            'lengkap'       => $countLengkap,
            'batal'         => $countBatal,
            'tidak_lengkap' => $countTidak,
            'stages'        => $stages,
            'total_pelayanan_label' => $this->formatDuration($totalSec),
            'total_pelayanan_count' => $totalCnt,
            'total_pelayanan_outliers' => $totalOut,
            'total_pelayanan_sum_label' => $this->formatDuration($totalSum > 0 ? $totalSum : null),
            'task_counts'   => $taskCounts,
        ];
    }

    /**
     * Hitung rata-rata durasi antar dua task (dalam detik).
     * SKIP baris kalau:
     *  - salah satu timestamp kosong
     *  - urutan terbalik (end < start)
     *  - durasi melebihi $maxSec (outlier — biasanya lintas hari atau data error)
     * Return [avg_seconds | null, contributing_count, outlier_count, sum_seconds].
     */
    private function avgDurationSeconds($rows, string $fromKey, string $toKey, int $maxSec = 14400): array
    {
        $deltas = [];
        $outliers = 0;
        foreach ($rows as $r) {
            $start = $r->{$fromKey} ?? null;
            $end = $r->{$toKey} ?? null;
            if (empty($start) || empty($end)) continue;
            try {
                $s = Carbon::createFromFormat('d/m/Y H:i:s', $start);
                $e = Carbon::createFromFormat('d/m/Y H:i:s', $end);
                // Pakai timestamp raw — unambiguous antar versi Carbon (2.x vs 3.x sign default berbeda)
                $diff = $e->getTimestamp() - $s->getTimestamp();
                if ($diff < 0) continue; // urutan terbalik
                if ($diff > $maxSec) {
                    $outliers++;
                    continue;
                }
                $deltas[] = $diff;
            } catch (\Exception $ex) {
                continue;
            }
        }
        $count = count($deltas);
        $sum = array_sum($deltas);
        return [$count > 0 ? $sum / $count : null, $count, $outliers, $sum];
    }

    private function avgTotalDuration($rows, int $maxSec = 28800): array
    {
        $deltas = [];
        $outliers = 0;
        foreach ($rows as $r) {
            $start = !empty($r->task_id1) ? $r->task_id1 : $r->task_id3;
            $end = $r->task_id7 ?? null;
            if (empty($start) || empty($end)) continue;
            try {
                $s = Carbon::createFromFormat('d/m/Y H:i:s', $start);
                $e = Carbon::createFromFormat('d/m/Y H:i:s', $end);
                $diff = $e->getTimestamp() - $s->getTimestamp();
                if ($diff < 0) continue;
                if ($diff > $maxSec) {
                    $outliers++;
                    continue;
                }
                $deltas[] = $diff;
            } catch (\Exception $ex) {
                continue;
            }
        }
        $count = count($deltas);
        $sum = array_sum($deltas);
        return [$count > 0 ? $sum / $count : null, $count, $outliers, $sum];
    }

    private function formatDuration(?float $sec): string
    {
        if ($sec === null) return '—';
        $sec = (int) round($sec);
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        if ($h > 0) return "{$h}j {$m}m";
        if ($m > 0) return "{$m}m {$s}d";
        return "{$s}d";
    }

    /**
     * Hitung durasi antara 2 timestamp untuk display inline di tabel.
     * Return ['label' => '+2m 35d', 'sec' => 155] atau null kalau invalid.
     */
    public function durationLabel(?string $start, ?string $end): ?array
    {
        if (empty($start) || empty($end)) return null;
        try {
            $s = Carbon::createFromFormat('d/m/Y H:i:s', $start);
            $e = Carbon::createFromFormat('d/m/Y H:i:s', $end);
            $sec = $e->getTimestamp() - $s->getTimestamp();
            if ($sec < 0) return null;
            return ['label' => '+' . $this->formatDuration($sec), 'sec' => $sec];
        } catch (\Exception $ex) {
            return null;
        }
    }

    #[Computed]
    public function poliList()
    {
        return DB::table('rsmst_polis')->select('poli_id', 'poli_desc')->orderBy('poli_desc')->get();
    }

    #[Computed]
    public function dokterList()
    {
        // Hanya dokter yang punya pasien RJ di periode terpilih (ikut filter poli)
        [$start, $end] = $this->dateRange();
        $q = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select('h.dr_id', DB::raw('MAX(d.dr_name) as dr_name'))
            ->whereBetween('h.rj_date', [$start, $end]);

        if ($this->filterPoli !== '') {
            $q->where('h.poli_id', $this->filterPoli);
        }

        return $q->groupBy('h.dr_id')->orderBy('dr_name')->get();
    }

    /**
     * Status code → variant + label untuk badge
     * Sukses: 200 atau 208 (BPJS info "sudah pernah dikirim")
     */
    private function badgeVariant($status): string
    {
        if ($status === null || $status === '') return 'gray';
        if ($status == 200 || $status == 208) return 'success';
        return 'danger';
    }

    public function taskCell($waktu, $status): array
    {
        $variant = $this->badgeVariant($status);

        if (empty($waktu)) {
            return ['variant' => 'gray', 'label' => '—', 'tooltip' => 'Belum tercatat'];
        }

        // Ambil HH:mm saja dari "dd/mm/yyyy HH:mm:ss"
        $jam = '';
        try {
            $jam = Carbon::createFromFormat('d/m/Y H:i:s', $waktu)->format('H:i');
        } catch (\Exception $e) {
            $jam = $waktu;
        }

        $tooltip = "Waktu: {$waktu}" . ($status !== null && $status !== '' ? " | Status BPJS: {$status}" : '');

        return ['variant' => $variant, 'label' => $jam, 'tooltip' => $tooltip];
    }

    /**
     * Map kode rj_status → label + variant badge
     * A=Antrian, L=Selesai, F=Batal, I=Rujuk/Inap
     */
    public function rjStatusBadge(?string $code): array
    {
        return match ($code) {
            'A' => ['label' => 'Antrian',  'variant' => 'warning'],
            'L' => ['label' => 'Selesai',  'variant' => 'success'],
            'F' => ['label' => 'Batal',    'variant' => 'danger'],
            'I' => ['label' => 'Rujuk',    'variant' => 'brand'],
            default => ['label' => $code ?: '-', 'variant' => 'gray'],
        };
    }

    /**
     * Tentukan apakah flow task-id "lengkap" sesuai aturan user:
     *  - Ada T99 (batal) → otomatis lengkap
     *  - Selain itu, task yang ada harus kontiguous (no gap)
     *  - Endpoint valid: T5 (stop di tunggu farmasi) atau T7 (obat selesai)
     *  - Endpoint T4 atau lebih awal → tidak lengkap
     *  - Endpoint T6 → tidak lengkap (kalau ada T6 harus ada T7)
     *  - Task 1,2 boleh skip (pasien lama mulai dari T3)
     */
    public function checkLengkap(array $present, bool $hasT99): array
    {
        if ($hasT99) {
            return ['lengkap' => true, 'label' => 'Batal', 'variant' => 'warning', 'tooltip' => 'Task 99 tercatat — pelayanan dibatalkan'];
        }

        if (empty($present)) {
            return ['lengkap' => false, 'label' => 'Belum Mulai', 'variant' => 'gray', 'tooltip' => 'Belum ada task ID tercatat'];
        }

        sort($present);
        $min = (int) min($present);
        $max = (int) max($present);
        $expected = range($min, $max);
        $missing = array_values(array_diff($expected, $present));

        // Cek gap dulu
        if (!empty($missing)) {
            $missingLabel = 'T' . implode(', T', $missing);
            return ['lengkap' => false, 'label' => 'Tidak Lengkap', 'variant' => 'danger', 'tooltip' => "Gap di: {$missingLabel}"];
        }

        // Endpoint validation:
        //   < 5 → belum sampai farmasi
        //   == 6 → ada T6 (mulai layan farmasi) tapi tidak ada T7 (obat selesai)
        if ($max < 5) {
            return ['lengkap' => false, 'label' => 'Tidak Lengkap', 'variant' => 'danger', 'tooltip' => "Berhenti di T{$max} — minimum harus sampai T5 (tunggu farmasi)"];
        }

        if ($max === 6) {
            return ['lengkap' => false, 'label' => 'Tidak Lengkap', 'variant' => 'danger', 'tooltip' => 'Ada T6 (mulai layan farmasi) tapi T7 (obat selesai) belum tercatat'];
        }

        $rangeLabel = "T{$min}–T{$max}";
        return ['lengkap' => true, 'label' => 'Lengkap', 'variant' => 'success', 'tooltip' => "Flow {$rangeLabel} kontiguous"];
    }
};
?>

<div>
    <x-page-title
        title="Laporan Task ID Antrian RJ"
        subtitle="Rekap pencatatan waktu pelayanan BPJS Antrol (Task ID 1–7) per bulan" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari No RJ / No RM / Nama Pasien..." class="block w-full" />
                    </div>

                    {{-- FILTER BULAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="mt-1 block w-full sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    </div>

                    {{-- FILTER POLI --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Poli" />
                        <x-select-input wire:model.live="filterPoli" class="mt-1 block w-full sm:w-44">
                            <option value="">Semua Poli</option>
                            @foreach ($this->poliList as $poli)
                                <option value="{{ $poli->poli_id }}">{{ $poli->poli_desc }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="mt-1 block w-full sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="mt-1 block w-full sm:w-32">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Rujuk</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="mt-1 block w-full sm:w-32">
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                            <option value="">Semua</option>
                        </x-select-input>
                    </div>

                    {{-- TOGGLE TIDAK LENGKAP --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Filter Cepat" />
                        <div class="mt-2">
                            <x-toggle wire:model.live="filterIncompleteOnly" :trueValue="true" :falseValue="false"
                                label="Tidak lengkap saja" />
                        </div>
                    </div>

                    {{-- ACTIONS --}}
                    <div class="ml-auto flex items-center gap-2">
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            Reset
                        </x-secondary-button>
                        <div class="w-24">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            {{-- LEGEND --}}
            <div class="mt-3 px-4 flex flex-wrap items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
                <span class="font-semibold">Keterangan Task:</span>
                <span><span class="font-mono font-bold">T1</span> Mulai tunggu admisi</span>
                <span><span class="font-mono font-bold">T2</span> Mulai layan admisi</span>
                <span><span class="font-mono font-bold">T3</span> Mulai tunggu poli</span>
                <span><span class="font-mono font-bold">T4</span> Mulai layan poli</span>
                <span><span class="font-mono font-bold">T5</span> Mulai tunggu farmasi</span>
                <span><span class="font-mono font-bold">T6</span> Mulai layan farmasi</span>
                <span><span class="font-mono font-bold">T7</span> Obat selesai</span>
                <span><span class="font-mono font-bold">T99</span> Batal</span>
            </div>

            {{-- SUMMARY PANEL (collapsible — default closed) --}}
            @php $sum = $this->summary; @endphp
            <div class="mt-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                x-data="{ open: false }">

                {{-- Header toggle: borderless row di dalam frame --}}
                <button type="button" @click="open = !open"
                    class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                           hover:bg-gray-50 dark:hover:bg-gray-800
                           focus:outline-none focus:ring-1 focus:ring-gray-300">

                    {{-- Title + summary --}}
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Ringkasan &amp; Statistik
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $sum['total'] }} pasien
                            · {{ $sum['lengkap'] }} lengkap
                            · {{ $sum['tidak_lengkap'] }} tidak lengkap
                            · {{ $sum['batal'] }} batal
                        </div>
                    </div>

                    {{-- CTA + chevron --}}
                    <span class="hidden sm:inline text-xs text-gray-500 dark:text-gray-400">
                        <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 shrink-0"
                        :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Body (collapsible) — divider top --}}
                <div x-cloak x-show="open"
                    class="px-4 pb-4 border-t border-gray-200 dark:border-gray-700"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                {{-- Count cards --}}
                <div class="p-3 bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-gray-500 uppercase">Total Pasien</div>
                    <div class="mt-1 text-xl font-bold text-gray-800 dark:text-gray-100">{{ $sum['total'] }}</div>
                </div>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs text-emerald-700 uppercase dark:text-emerald-300">Lengkap</div>
                    <div class="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-200">{{ $sum['lengkap'] }}</div>
                </div>
                <div class="p-3 bg-red-50 border border-red-200 rounded-xl dark:bg-red-900/20 dark:border-red-700">
                    <div class="text-xs text-red-700 uppercase dark:text-red-300">Tidak Lengkap</div>
                    <div class="mt-1 text-xl font-bold text-red-800 dark:text-red-200">{{ $sum['tidak_lengkap'] }}</div>
                </div>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-700">
                    <div class="text-xs text-amber-700 uppercase dark:text-amber-300">Batal (T99)</div>
                    <div class="mt-1 text-xl font-bold text-amber-800 dark:text-amber-200">{{ $sum['batal'] }}</div>
                </div>

                {{-- Total pelayanan: rata-rata + total --}}
                <div class="p-3 col-span-2 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5 lg:col-span-3">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Total Pelayanan (T1/T3 → T7)</div>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        <div>
                            <div class="text-[10px] text-gray-500 uppercase">Rata-rata</div>
                            <div class="text-xl font-bold text-brand-green dark:text-brand-lime">{{ $sum['total_pelayanan_label'] }}</div>
                        </div>
                        <div class="border-l border-brand-green/20 dark:border-brand-lime/20 pl-2">
                            <div class="text-[10px] text-gray-500 uppercase">Total</div>
                            <div class="text-xl font-bold text-brand-green dark:text-brand-lime">{{ $sum['total_pelayanan_sum_label'] }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        dari {{ $sum['total_pelayanan_count'] }}/{{ $sum['total_non_batal'] }} pasien (Batal di-exclude)
                        @if ($sum['total_pelayanan_outliers'] > 0)
                            <span class="text-amber-600 dark:text-amber-400">· {{ $sum['total_pelayanan_outliers'] }} outlier &gt; 8j di-skip</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Per-stage averages + total --}}
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($sum['stages'] as $stg)
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-900 dark:border-gray-700"
                        title="Skip baris jika salah satu timestamp kosong, urutan terbalik, atau durasi > 4 jam">
                        <div class="text-[11px] text-center text-gray-500 uppercase tracking-wide">{{ $stg['label'] }}</div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div class="text-center">
                                <div class="text-[10px] text-gray-400 uppercase">Rata-rata</div>
                                <div class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ $stg['avg_label'] }}</div>
                            </div>
                            <div class="text-center border-l border-gray-200 dark:border-gray-700">
                                <div class="text-[10px] text-gray-400 uppercase">Total</div>
                                <div class="text-base font-semibold text-emerald-700 dark:text-emerald-300">{{ $stg['total_label'] }}</div>
                            </div>
                        </div>
                        <div class="mt-2 text-[10px] text-center text-gray-400">
                            n={{ $stg['count'] }}
                            @if ($stg['outliers'] > 0)
                                <span class="text-amber-600 dark:text-amber-400">· {{ $stg['outliers'] }} outlier</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Breakdown task tercatat --}}
            <div class="mt-3 p-3 bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-2 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Task Tercatat ({{ $sum['total'] }} pasien)
                </div>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                    @foreach ($sum['task_counts'] as $taskId => $count)
                        @php
                            $pct = $sum['total'] > 0 ? round(($count / $sum['total']) * 100) : 0;
                            $tone = $count == 0 ? 'gray' : ($pct >= 80 ? 'success' : ($pct >= 30 ? 'warning' : 'danger'));
                            $textCls = match ($tone) {
                                'success' => 'text-emerald-700 dark:text-emerald-300',
                                'warning' => 'text-amber-700 dark:text-amber-300',
                                'danger'  => 'text-rose-700 dark:text-rose-300',
                                default   => 'text-gray-500 dark:text-gray-400',
                            };
                            $bgCls = match ($tone) {
                                'success' => 'bg-emerald-50 dark:bg-emerald-900/20',
                                'warning' => 'bg-amber-50 dark:bg-amber-900/20',
                                'danger'  => 'bg-rose-50 dark:bg-rose-900/20',
                                default   => 'bg-gray-50 dark:bg-gray-800/50',
                            };
                        @endphp
                        <div class="p-2 text-center rounded-lg {{ $bgCls }}">
                            <div class="text-[11px] font-bold {{ $textCls }}">T{{ $taskId }}</div>
                            <div class="text-base font-semibold {{ $textCls }}">{{ $count }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $pct }}%</div>
                        </div>
                    @endforeach
                </div>
            </div>

                </div> {{-- /Body collapsible --}}
            </div> {{-- /SUMMARY PANEL wrapper --}}

            {{-- TABLE --}}
            <div class="mt-3 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-340px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr class="text-xs font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-3 py-3 whitespace-nowrap">RJ / Tgl</th>
                                <th class="px-3 py-3 whitespace-nowrap">No Booking</th>
                                <th class="px-3 py-3">Pasien</th>
                                <th class="px-3 py-3">Poli / Dokter</th>
                                <th class="px-3 py-3 text-center whitespace-nowrap">Status RJ</th>
                                @for ($i = 1; $i <= 7; $i++)
                                    <th class="px-2 py-3 text-center whitespace-nowrap">T{{ $i }}</th>
                                @endfor
                                <th class="px-2 py-3 text-center whitespace-nowrap">T99</th>
                                <th class="px-3 py-3 text-center whitespace-nowrap">Kelengkapan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800 hover:bg-emerald-50/50 dark:hover:bg-gray-800/50">
                                    <td class="px-3 py-3 whitespace-nowrap align-top">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $row->rj_no }}</div>
                                        <div class="text-xs text-gray-500">{{ $row->rj_date_display }}</div>
                                        <div class="text-xs text-gray-400">No. {{ $row->no_antrian ?? '-' }}</div>
                                    </td>
                                    <td class="px-3 py-3 align-top font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ $row->no_booking }}
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $row->reg_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $row->reg_no }}</div>
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <div class="text-gray-700 dark:text-gray-300">{{ $row->poli_desc ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $row->dr_name ?? '-' }}</div>
                                    </td>

                                    {{-- Status RJ --}}
                                    @php $rjs = $this->rjStatusBadge($row->rj_status); @endphp
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-badge :variant="$rjs['variant']">{{ $rjs['label'] }}</x-badge>
                                    </td>

                                    @for ($i = 1; $i <= 7; $i++)
                                        @php
                                            $cell = $this->taskCell($row->{"task_id{$i}"}, $row->{"task_id{$i}_status"});
                                            // Delta dari task sebelumnya yang relevan: T2←T1, T3←T2, T4←T3, T5←T4, T7←T6
                                            $delta = match ($i) {
                                                2 => $row->delta_t1_t2,
                                                3 => $row->delta_t2_t3,
                                                4 => $row->delta_t3_t4,
                                                5 => $row->delta_t4_t5,
                                                7 => $row->delta_t6_t7,
                                                default => null,
                                            };
                                            // Threshold merah > 1 jam (3600 dtk) untuk T4 (Tunggu Poli), T5 (Layan Poli), T7 (Layan Farmasi)
                                            $isOverThreshold = $delta && in_array($i, [4, 5, 7], true) && $delta['sec'] > 3600;
                                            $deltaCls = $isOverThreshold
                                                ? 'text-rose-600 dark:text-rose-400 font-semibold'
                                                : 'text-gray-500 dark:text-gray-400';
                                        @endphp
                                        <td class="px-2 py-3 text-center align-top">
                                            <x-badge :variant="$cell['variant']" :title="$cell['tooltip']">
                                                <span class="font-mono text-xs">{{ $cell['label'] }}</span>
                                            </x-badge>
                                            @if ($delta)
                                                <div class="mt-0.5 text-[10px] font-mono {{ $deltaCls }}"
                                                    @if ($isOverThreshold) title="Lebih dari 1 jam — perlu review" @endif>
                                                    {{ $delta['label'] }}
                                                </div>
                                            @endif
                                        </td>
                                    @endfor

                                    {{-- T99 (Batal) --}}
                                    @php
                                        $cell99 = $this->taskCell($row->task_id99, $row->task_id99_status);
                                    @endphp
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->task_id99 ? 'warning' : 'gray'" :title="$cell99['tooltip']">
                                            <span class="font-mono text-xs">{{ $cell99['label'] }}</span>
                                        </x-badge>
                                    </td>

                                    {{-- Status Lengkap --}}
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-badge :variant="$row->lengkap_check['variant']" :title="$row->lengkap_check['tooltip']">
                                            {{ $row->lengkap_check['label'] }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        Belum ada data untuk periode {{ $filterBulan }}
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

        </div>
    </div>
</div>
