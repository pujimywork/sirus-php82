<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterBulan = '';
    public string $filterDokter = '';
    public string $filterStatus = '';
    public string $filterKlaim = 'BPJS';
    public bool $filterIncompleteOnly = false;
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void        { $this->resetPage(); }
    public function updatedFilterBulan(): void          { $this->resetPage(); }
    public function updatedFilterDokter(): void         { $this->resetPage(); }
    public function updatedFilterStatus(): void         { $this->resetPage(); }
    public function updatedFilterKlaim(): void          { $this->resetPage(); }
    public function updatedFilterIncompleteOnly(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void         { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterDokter', 'filterStatus', 'filterIncompleteOnly']);
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

    /**
     * Sumber data UGD — 5 milestone, semua dari JSON datadaftarugd_json:
     *   1. Datang          ← anamnesa.pengkajianPerawatan.jamDatang
     *   2. Periksa         ← perencanaan.pengkajianMedis.waktuPemeriksaan
     *   3. Selesai Periksa ← perencanaan.pengkajianMedis.selesaiPemeriksaan
     *   4. Masuk Apotek    ← taskIdPelayanan.taskId6
     *   5. Obat Selesai    ← taskIdPelayanan.taskId7
     *   Batal              ← taskIdPelayanan.taskId99
     *
     * Note: kolom DB (waktu_pasien_datang, waktu_pasien_dilayani, waktu_masuk_apt,
     * waktu_selesai_pelayanan) tetap di-update parallel oleh modul pelayanan,
     * tapi laporan ini selalu baca JSON sebagai single source of truth.
     */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                'h.rj_no',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy') as rj_date_display"),
                'h.reg_no',
                'p.reg_name',
                'd.dr_name',
                'h.klaim_id',
                'k.klaim_status',
                'h.no_antrian',
                'h.rj_status',
                'h.nobooking',
                'h.datadaftarugd_json',
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.no_antrian', 'asc');

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
        $all = $this->baseQuery()->get()->map(function ($row) {
            $json = json_decode($row->datadaftarugd_json ?? '{}', true) ?: [];
            $tp = $json['taskIdPelayanan'] ?? [];
            $row->no_booking = $row->nobooking ?? '-';
            $row->task_id99 = $tp['taskId99'] ?? null;

            // 5 milestone — semua dari JSON (single source of truth)
            $row->t_datang          = $json['anamnesa']['pengkajianPerawatan']['jamDatang']         ?? null;
            $row->t_periksa         = $json['perencanaan']['pengkajianMedis']['waktuPemeriksaan']   ?? null;
            $row->t_selesai_periksa = $json['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? null;
            $row->t_apt             = $tp['taskId6'] ?? null;
            $row->t_selesai         = $tp['taskId7'] ?? null;

            // Triase (P1/P2/P3) — untuk SLA tunggu periksa per Kepmenkes 129/2008
            $row->triase = $json['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? null;

            $hasT99 = !empty($row->task_id99);
            $row->lengkap_check = $this->checkLengkap(
                $row->t_datang, $row->t_periksa, $row->t_selesai_periksa,
                $row->t_apt, $row->t_selesai, $hasT99
            );

            // Inline delta — 4 stage
            $row->delta_tunggu_periksa = $this->durationLabel($row->t_datang, $row->t_periksa);
            $row->delta_layan_periksa  = $this->durationLabel($row->t_periksa, $row->t_selesai_periksa);
            $row->delta_tunggu_apt     = $this->durationLabel($row->t_selesai_periksa, $row->t_apt);
            $row->delta_layan_apt      = $this->durationLabel($row->t_apt, $row->t_selesai);

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

    #[Computed]
    public function summary(): array
    {
        $all = $this->mappedAll;
        $total = $all->count();
        $forAvg = $all->filter(fn($r) => empty($r->task_id99))->values();

        $stages = [
            'tunggu_periksa' => ['label' => 'Tunggu Periksa', 'from' => 't_datang',         'to' => 't_periksa'],
            'layan_periksa'  => ['label' => 'Layan Periksa',  'from' => 't_periksa',        'to' => 't_selesai_periksa'],
            'tunggu_apt'     => ['label' => 'Tunggu Apotek',  'from' => 't_selesai_periksa','to' => 't_apt'],
            'layan_apt'      => ['label' => 'Layan Apotek',   'from' => 't_apt',            'to' => 't_selesai'],
        ];

        foreach ($stages as &$stg) {
            [$avg, $cnt, $out, $sum] = $this->avgDurationSeconds($forAvg, $stg['from'], $stg['to']);
            $stg['avg_label']   = $this->formatDuration($avg);
            $stg['total_label'] = $this->formatDuration($sum > 0 ? $sum : null);
            $stg['count']       = $cnt;
            $stg['outliers']    = $out;
        }
        unset($stg);

        // Total = Selesai - Datang
        [$totalSec, $totalCnt, $totalOut, $totalSum] = $this->avgDurationSeconds($forAvg, 't_datang', 't_selesai', 28800);

        $countLengkap = $all->filter(fn($r) => $r->lengkap_check['lengkap'] && empty($r->task_id99))->count();
        $countBatal   = $all->filter(fn($r) => !empty($r->task_id99))->count();
        $countTidak   = $all->filter(fn($r) => !$r->lengkap_check['lengkap'])->count();

        $milestoneCounts = [
            'Datang'          => $all->filter(fn($r) => !empty($r->t_datang))->count(),
            'Periksa'         => $all->filter(fn($r) => !empty($r->t_periksa))->count(),
            'Selesai Periksa' => $all->filter(fn($r) => !empty($r->t_selesai_periksa))->count(),
            'Masuk Apotek'    => $all->filter(fn($r) => !empty($r->t_apt))->count(),
            'Obat Selesai'    => $all->filter(fn($r) => !empty($r->t_selesai))->count(),
            'Batal (T99)'     => $all->filter(fn($r) => !empty($r->task_id99))->count(),
        ];

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
            'milestone_counts' => $milestoneCounts,
        ];
    }

    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();
        return DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select('h.dr_id', DB::raw('MAX(d.dr_name) as dr_name'))
            ->whereBetween('h.rj_date', [$start, $end])
            ->groupBy('h.dr_id')
            ->orderBy('dr_name')
            ->get();
    }

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

    /**
     * Aturan kelengkapan UGD (5 milestone):
     *  - Ada T99 → Batal (lengkap)
     *  - Semua 5 milestone tercatat → Lengkap
     *  - Ada milestone tapi tidak komplit → Tidak Lengkap
     *  - Semua kosong → Belum Mulai
     */
    public function checkLengkap(
        ?string $tDatang, ?string $tPeriksa, ?string $tSelesaiPeriksa,
        ?string $tApt, ?string $tSelesai, bool $hasT99
    ): array {
        if ($hasT99) {
            return ['lengkap' => true, 'label' => 'Batal', 'variant' => 'warning', 'tooltip' => 'Pelayanan dibatalkan (T99)'];
        }

        $milestones = [
            'Datang'          => !empty($tDatang),
            'Periksa'         => !empty($tPeriksa),
            'Selesai Periksa' => !empty($tSelesaiPeriksa),
            'Masuk Apotek'    => !empty($tApt),
            'Obat Selesai'    => !empty($tSelesai),
        ];
        $missing = array_keys(array_filter($milestones, fn($v) => !$v));
        $present = array_keys(array_filter($milestones));

        if (empty($present)) {
            return ['lengkap' => false, 'label' => 'Belum Mulai', 'variant' => 'gray', 'tooltip' => 'Belum ada milestone tercatat'];
        }
        if (empty($missing)) {
            return ['lengkap' => true, 'label' => 'Lengkap', 'variant' => 'success', 'tooltip' => '5 milestone tercatat'];
        }
        return ['lengkap' => false, 'label' => 'Tidak Lengkap', 'variant' => 'danger', 'tooltip' => 'Belum ada: ' . implode(', ', $missing)];
    }

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
     * Triase label + variant — sesuai pola pengkajian-perawatan-tab-dokter-view
     */
    public function triaseBadge(?string $code): ?array
    {
        return match ($code) {
            'P1' => ['label' => 'P1 · Kritis (Merah)',     'variant' => 'danger',  'cls' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200'],
            'P2' => ['label' => 'P2 · Urgent (Kuning)',    'variant' => 'warning', 'cls' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-200'],
            'P3' => ['label' => 'P3 · Minor (Hijau)',      'variant' => 'success', 'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-200'],
            'P4' => ['label' => 'P4 · Non-darurat',        'variant' => 'gray',    'cls' => 'bg-surface-soft text-body dark:bg-gray-800 dark:text-gray-200'],
            default => null,
        };
    }

    /**
     * Threshold detik untuk Tunggu Periksa (Datang → Periksa) per triase
     * Sumber: Kepmenkes 129/2008 + standar IGD nasional
     *   P1 (Merah)  : ≤ 5 menit
     *   P2 (Kuning) : ≤ 15 menit
     *   P3 (Hijau)  : ≤ 30 menit
     *   P4 / N/A    : ≤ 30 menit (default)
     */
    public function tungguPeriksaThreshold(?string $triase): int
    {
        return match ($triase) {
            'P1' => 300,
            'P2' => 900,
            'P3' => 1800,
            default => 1800,
        };
    }

    public function formatJam(?string $waktu): string
    {
        if (empty($waktu)) return '—';
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $waktu)->format('H:i');
        } catch (\Exception $e) {
            return $waktu;
        }
    }
};
?>

<div>
    <x-page-title
        title="Laporan Task ID Antrian UGD"
        subtitle="Rekap waktu pelayanan UGD — Datang → Periksa → Selesai Periksa → Apotek → Obat Selesai" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari No UGD / No RM / Nama Pasien..." class="block w-full" />
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="mt-1 block w-full sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="mt-1 block w-full sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="mt-1 block w-full sm:w-32">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Transfer Inap</option>
                        </x-select-input>
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="mt-1 block w-full sm:w-32">
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                            <option value="">Semua</option>
                        </x-select-input>
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Filter Cepat" />
                        <div class="mt-2">
                            <x-toggle wire:model.live="filterIncompleteOnly" :trueValue="true" :falseValue="false"
                                label="Tidak lengkap saja" />
                        </div>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">Reset</x-secondary-button>
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
            <div class="mt-3 px-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted dark:text-gray-400">
                <span class="font-semibold">Milestone:</span>
                <span>Datang &rarr; Periksa &rarr; Selesai Periksa &rarr; Masuk Apotek &rarr; Obat Selesai</span>
                <span><span class="font-mono font-bold">T99</span> Batal</span>
            </div>
            <div class="mt-1 px-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted dark:text-gray-500">
                <span class="font-semibold">SLA Tunggu Periksa</span>
                <span class="text-muted-soft">(Kepmenkes 129/2008):</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span>P1 &le; 5m</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400"></span>P2 &le; 15m</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span>P3 &le; 30m</span>
                <span class="text-muted-soft">· Lainnya: 1j</span>
            </div>

            {{-- SUMMARY PANEL (collapsible — default closed) --}}
            @php $sum = $this->summary; @endphp
            <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                x-data="{ open: false }">

                <button type="button" @click="open = !open"
                    class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                           hover:bg-surface-soft dark:hover:bg-gray-800
                           focus:outline-none focus:ring-1 focus:ring-gray-300">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-body dark:text-gray-200">
                            Ringkasan &amp; Statistik
                        </div>
                        <div class="text-xs text-muted dark:text-gray-400">
                            {{ $sum['total'] }} pasien
                            · {{ $sum['lengkap'] }} lengkap
                            · {{ $sum['tidak_lengkap'] }} tidak lengkap
                            · {{ $sum['batal'] }} batal
                        </div>
                    </div>
                    <span class="hidden sm:inline text-xs text-muted dark:text-gray-400">
                        <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
                    </span>
                    <svg class="w-4 h-4 text-muted-soft transition-transform duration-200 shrink-0"
                        :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-cloak x-show="open"
                    class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                        <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="text-xs text-muted uppercase">Total Pasien</div>
                            <div class="mt-1 text-xl font-bold text-ink dark:text-gray-100">{{ $sum['total'] }}</div>
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

                        <div class="p-3 col-span-2 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5 lg:col-span-3">
                            <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Total Pelayanan (Datang &rarr; Selesai)</div>
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <div>
                                    <div class="text-[10px] text-muted uppercase">Rata-rata</div>
                                    <div class="text-xl font-bold text-brand-green dark:text-brand-lime">{{ $sum['total_pelayanan_label'] }}</div>
                                </div>
                                <div class="border-l border-brand-green/20 dark:border-brand-lime/20 pl-2">
                                    <div class="text-[10px] text-muted uppercase">Total</div>
                                    <div class="text-xl font-bold text-brand-green dark:text-brand-lime">{{ $sum['total_pelayanan_sum_label'] }}</div>
                                </div>
                            </div>
                            <div class="mt-1 text-xs text-muted dark:text-gray-400">
                                dari {{ $sum['total_pelayanan_count'] }}/{{ $sum['total_non_batal'] }} pasien (Batal di-exclude)
                                @if ($sum['total_pelayanan_outliers'] > 0)
                                    <span class="text-amber-600 dark:text-amber-400">· {{ $sum['total_pelayanan_outliers'] }} outlier &gt; 8j di-skip</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 4 Stage averages --}}
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($sum['stages'] as $stg)
                            <div class="p-3 bg-surface-soft border border-hairline rounded-lg dark:bg-gray-900 dark:border-gray-700"
                                title="Skip baris jika salah satu timestamp kosong, urutan terbalik, atau durasi > 4 jam">
                                <div class="text-[11px] text-center text-muted uppercase tracking-wide">{{ $stg['label'] }}</div>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <div class="text-center">
                                        <div class="text-[10px] text-muted-soft uppercase">Rata-rata</div>
                                        <div class="text-base font-semibold text-ink dark:text-gray-100">{{ $stg['avg_label'] }}</div>
                                    </div>
                                    <div class="text-center border-l border-hairline dark:border-gray-700">
                                        <div class="text-[10px] text-muted-soft uppercase">Total</div>
                                        <div class="text-base font-semibold text-emerald-700 dark:text-emerald-300">{{ $stg['total_label'] }}</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-[10px] text-center text-muted-soft">
                                    n={{ $stg['count'] }}
                                    @if ($stg['outliers'] > 0)
                                        <span class="text-amber-600 dark:text-amber-400">· {{ $stg['outliers'] }} outlier</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Breakdown milestone tercatat --}}
                    <div class="mt-3 p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="mb-2 text-xs font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                            Milestone Tercatat ({{ $sum['total'] }} pasien)
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                            @foreach ($sum['milestone_counts'] as $label => $count)
                                @php
                                    $pct = $sum['total'] > 0 ? round(($count / $sum['total']) * 100) : 0;
                                    $tone = $count == 0 ? 'gray' : ($pct >= 80 ? 'success' : ($pct >= 30 ? 'warning' : 'danger'));
                                    $textCls = match ($tone) {
                                        'success' => 'text-emerald-700 dark:text-emerald-300',
                                        'warning' => 'text-amber-700 dark:text-amber-300',
                                        'danger'  => 'text-rose-700 dark:text-rose-300',
                                        default   => 'text-muted dark:text-gray-400',
                                    };
                                    $bgCls = match ($tone) {
                                        'success' => 'bg-emerald-50 dark:bg-emerald-900/20',
                                        'warning' => 'bg-amber-50 dark:bg-amber-900/20',
                                        'danger'  => 'bg-rose-50 dark:bg-rose-900/20',
                                        default   => 'bg-surface-soft dark:bg-gray-800/50',
                                    };
                                @endphp
                                <div class="p-2 text-center rounded-lg {{ $bgCls }}">
                                    <div class="text-[11px] font-bold {{ $textCls }}">{{ $label }}</div>
                                    <div class="text-base font-semibold {{ $textCls }}">{{ $count }}</div>
                                    <div class="text-[10px] text-muted dark:text-gray-400">{{ $pct }}%</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-3 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-surface-soft dark:bg-gray-800">
                            <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-3 py-3 whitespace-nowrap">UGD / Tgl</th>
                                <th class="px-3 py-3 whitespace-nowrap">No Booking</th>
                                <th class="px-3 py-3">Pasien</th>
                                <th class="px-3 py-3">Dokter</th>
                                <th class="px-3 py-3 text-center whitespace-nowrap">Status UGD</th>
                                <th class="px-2 py-3 text-center whitespace-nowrap">Datang</th>
                                <th class="px-2 py-3 text-center whitespace-nowrap">Periksa</th>
                                <th class="px-2 py-3 text-center whitespace-nowrap">Selesai Periksa</th>
                                <th class="px-2 py-3 text-center whitespace-nowrap">Masuk Apotek</th>
                                <th class="px-2 py-3 text-center whitespace-nowrap">Obat Selesai</th>
                                <th class="px-2 py-3 text-center whitespace-nowrap">T99</th>
                                <th class="px-3 py-3 text-center whitespace-nowrap">Kelengkapan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-emerald-50/50 dark:hover:bg-gray-800/50">
                                    <td class="px-3 py-3 whitespace-nowrap align-top">
                                        <div class="font-semibold text-ink dark:text-gray-100">{{ $row->rj_no }}</div>
                                        <div class="text-xs text-muted">{{ $row->rj_date_display }}</div>
                                        <div class="text-xs text-muted-soft">No. {{ $row->no_antrian ?? '-' }}</div>
                                    </td>
                                    <td class="px-3 py-3 align-top font-mono text-xs text-body dark:text-gray-300">
                                        {{ $row->no_booking }}
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <div class="font-semibold text-ink dark:text-gray-100">{{ $row->reg_name }}</div>
                                        <div class="text-xs text-muted">{{ $row->reg_no }}</div>
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <div class="text-xs text-muted">{{ $row->dr_name ?? '-' }}</div>
                                    </td>

                                    @php
                                        $rjs = $this->rjStatusBadge($row->rj_status);
                                        $tri = $this->triaseBadge($row->triase);
                                    @endphp
                                    <td class="px-3 py-3 text-center align-top">
                                        <div class="flex flex-col items-center gap-1">
                                            <x-badge :variant="$rjs['variant']">{{ $rjs['label'] }}</x-badge>
                                            @if ($tri)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $tri['cls'] }}">
                                                    {{ $tri['label'] }}
                                                </span>
                                            @else
                                                <span class="text-[10px] text-muted-soft">— triase</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Datang --}}
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->t_datang ? 'success' : 'gray'" :title="$row->t_datang ?? 'Belum tercatat'">
                                            <span class="font-mono text-xs">{{ $this->formatJam($row->t_datang) }}</span>
                                        </x-badge>
                                    </td>

                                    {{-- Periksa (delta = Tunggu Periksa) — threshold per triase --}}
                                    @php
                                        $d1 = $row->delta_tunggu_periksa;
                                        $threshold1 = $this->tungguPeriksaThreshold($row->triase);
                                        $isOver1 = $d1 && $d1['sec'] > $threshold1;
                                        $cls1 = $isOver1 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-muted dark:text-gray-400';
                                        $threshLabel1 = match ($row->triase) {
                                            'P1' => '5m (P1)',
                                            'P2' => '15m (P2)',
                                            'P3' => '30m (P3)',
                                            default => '30m (default)',
                                        };
                                    @endphp
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->t_periksa ? 'success' : 'gray'" :title="$row->t_periksa ?? 'Belum tercatat'">
                                            <span class="font-mono text-xs">{{ $this->formatJam($row->t_periksa) }}</span>
                                        </x-badge>
                                        @if ($d1)
                                            <div class="mt-0.5 text-[10px] font-mono {{ $cls1 }}"
                                                @if ($isOver1) title="Lebih dari SLA {{ $threshLabel1 }} — perlu review" @else title="SLA {{ $threshLabel1 }}" @endif>
                                                {{ $d1['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Selesai Periksa (delta = Layan Periksa) --}}
                                    @php
                                        $d2 = $row->delta_layan_periksa;
                                        $isOver2 = $d2 && $d2['sec'] > 3600;
                                        $cls2 = $isOver2 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-muted dark:text-gray-400';
                                    @endphp
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->t_selesai_periksa ? 'success' : 'gray'" :title="$row->t_selesai_periksa ?? 'Belum tercatat'">
                                            <span class="font-mono text-xs">{{ $this->formatJam($row->t_selesai_periksa) }}</span>
                                        </x-badge>
                                        @if ($d2)
                                            <div class="mt-0.5 text-[10px] font-mono {{ $cls2 }}"
                                                @if ($isOver2) title="Layan Periksa > 1 jam — perlu review" @endif>
                                                {{ $d2['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Masuk Apotek (delta = Tunggu Apotek) --}}
                                    @php
                                        $d3 = $row->delta_tunggu_apt;
                                        $isOver3 = $d3 && $d3['sec'] > 3600;
                                        $cls3 = $isOver3 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-muted dark:text-gray-400';
                                    @endphp
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->t_apt ? 'success' : 'gray'" :title="$row->t_apt ?? 'Belum tercatat'">
                                            <span class="font-mono text-xs">{{ $this->formatJam($row->t_apt) }}</span>
                                        </x-badge>
                                        @if ($d3)
                                            <div class="mt-0.5 text-[10px] font-mono {{ $cls3 }}"
                                                @if ($isOver3) title="Tunggu Apotek > 1 jam — perlu review" @endif>
                                                {{ $d3['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Obat Selesai (delta = Layan Apotek) --}}
                                    @php
                                        $d4 = $row->delta_layan_apt;
                                        $isOver4 = $d4 && $d4['sec'] > 3600;
                                        $cls4 = $isOver4 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-muted dark:text-gray-400';
                                    @endphp
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->t_selesai ? 'success' : 'gray'" :title="$row->t_selesai ?? 'Belum tercatat'">
                                            <span class="font-mono text-xs">{{ $this->formatJam($row->t_selesai) }}</span>
                                        </x-badge>
                                        @if ($d4)
                                            <div class="mt-0.5 text-[10px] font-mono {{ $cls4 }}"
                                                @if ($isOver4) title="Layan Apotek > 1 jam — perlu review" @endif>
                                                {{ $d4['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- T99 --}}
                                    <td class="px-2 py-3 text-center align-top">
                                        <x-badge :variant="$row->task_id99 ? 'warning' : 'gray'" :title="$row->task_id99 ?? 'Belum batal'">
                                            <span class="font-mono text-xs">{{ $this->formatJam($row->task_id99) }}</span>
                                        </x-badge>
                                    </td>

                                    {{-- Kelengkapan --}}
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-badge :variant="$row->lengkap_check['variant']" :title="$row->lengkap_check['tooltip']">
                                            {{ $row->lengkap_check['label'] }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        Belum ada data untuk periode {{ $filterBulan }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>
    </div>
</div>
