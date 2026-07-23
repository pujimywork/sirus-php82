<?php
// resources/views/pages/transaksi/ugd/antrian-kasir-ugd/antrian-kasir-ugd.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['antrian-kasir-toolbar'];

    #[Session(key: 'antrian-kasir-ugd-searchKeyword')]
    public string $searchKeyword = '';
    #[Session(key: 'antrian-kasir-ugd-filterTanggal')]
    public string $filterTanggal = '';
    #[Session(key: 'antrian-kasir-ugd-filterStatus')]
    public string $filterStatus = 'A';
    #[Session(key: 'antrian-kasir-ugd-filterKlaim')]
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM' — pakai klaim_status di rsmst_klaimtypes (JM dianggap BPJS)
    #[Session(key: 'antrian-kasir-ugd-filterDokter')]
    public string $filterDokter = '';
    #[Session(key: 'antrian-kasir-ugd-itemsPerPage')]
    public int $itemsPerPage = 10;
    public string $autoRefresh = 'Ya';

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = $this->filterTanggal ?: Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }
    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }
    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }
    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('antrian-kasir-toolbar');
        $this->resetPage();
    }

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-ugd.administrasi.open', rjNo: $rjNo);
    }

    #[On('refresh-after-kasir.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('antrian-kasir-toolbar');
        $this->resetPage();
    }

    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Exception $e) {
            $d = now()->startOfDay();
        }
        return [$d, (clone $d)->endOfDay()];
    }

    #[Computed]
    public function rows()
    {
        [$start, $end] = $this->dateRange();

        // Sub-query lab/rad untuk badge "Laborat" / "Radiologi" (selaras pelayanan-ugd)
        $labSub = DB::table('lbtxn_checkuphdrs as l')
            ->join('rstxn_ugdhdrs as hd', 'hd.rj_no', '=', 'l.ref_no')
            ->select('l.ref_no', DB::raw('COUNT(*) as lab_status'))
            ->where('l.status_rjri', 'UGD')
            ->where('l.checkup_status', '!=', 'B')
            ->whereBetween('hd.rj_date', [$start, $end])
            ->groupBy('l.ref_no');
        $radSub = DB::table('rstxn_ugdrads as r')
            ->join('rstxn_ugdhdrs as hd', 'hd.rj_no', '=', 'r.rj_no')
            ->select('r.rj_no', DB::raw('COUNT(*) as rad_status'))
            ->whereBetween('hd.rj_date', [$start, $end])
            ->groupBy('r.rj_no');

        $query = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.vno_sep', 'h.nobooking', 'h.datadaftarugd_json', 'k.klaim_desc', 'k.klaim_status', 'h.waktu_masuk_apt', 'h.waktu_selesai_pelayanan', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status')])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(DB::raw("NVL(h.rj_status,'A')"), $this->filterStatus)
            ->where('h.klaim_id', '!=', 'KR');

        // Filter Klaim BPJS / UMUM
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

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($kw, $search) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rj_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(d.dr_name)'), 'like', "%{$kw}%");
            });
        }

        $all = $query->get();

        // Sort: pasien yang sudah Administrasi paling atas (siap dibayar),
        // tie-breaker no_antrian poli asc (FIFO). UGD tidak punya taskId5 (bukan poli).
        $sorted = $all
            ->sortBy(function ($row) {
                $json = json_decode(OracleLob::read($row->datadaftarugd_json ?? null, 'rstxn_ugdhdrs', 'rj_no', $row->rj_no, 'datadaftarugd_json') ?: '{}', true);
                $hasAdmin = isset($json['AdministrasiRj']) ? 0 : 1; // 0 = sudah administrasi (atas)
                return [$hasAdmin, (int) ($row->no_antrian ?? 0)];
            })
            ->values();

        $page = $this->getPage();
        $perPage = $this->itemsPerPage;
        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $page, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode(OracleLob::read($row->datadaftarugd_json ?? null, 'rstxn_ugdhdrs', 'rj_no', $row->rj_no, 'datadaftarugd_json') ?: '{}', true);

            $row->no_antrian_apotek = $json['noAntrianApotek']['noAntrian'] ?? 0;
            $row->jenis_resep = $json['noAntrianApotek']['jenisResep'] ?? '-';
            $row->has_eresep = isset($json['eresep']) ? 1 : 0;
            $row->has_eresep_racikan = isset($json['eresepRacikan']) ? 1 : 0;
            $row->eresep_count = $row->has_eresep + $row->has_eresep_racikan;

            $row->task_id6 = $json['taskIdPelayanan']['taskId6'] ?? null;
            $row->task_id7 = $json['taskIdPelayanan']['taskId7'] ?? null;

            $row->status_resep = $json['statusResep']['status'] ?? null;
            $row->status_resep_label = match ($row->status_resep) {
                'DITUNGGU' => 'Ditunggu',
                'DITINGGAL' => 'Ditinggal',
                default => '-',
            };
            $row->status_resep_color = match ($row->status_resep) {
                'DITUNGGU' => 'success',
                'DITINGGAL' => 'warning',
                default => 'alternative',
            };

            $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';

            $statusMap = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Transfer Inap'];
            $statusVariant = ['A' => 'warning', 'L' => 'success', 'F' => 'danger', 'I' => 'brand'];
            $row->status_text = $statusMap[$row->rj_status] ?? '-';
            $row->status_variant = $statusVariant[$row->rj_status] ?? 'gray';

            // Klaim badge — selaras dgn filter Klaim: BPJS = klaim_status='BPJS' atau klaim_id='JM'
            $isBpjs = $row->klaim_id === 'JM' || $row->klaim_status === 'BPJS';
            if ($isBpjs) {
                $row->klaim_label = 'BPJS';
                $row->klaim_variant = 'info';
            } else {
                $row->klaim_label = match ($row->klaim_id) {
                    'UM' => 'UMUM',
                    'KR' => 'Kronis',
                    default => 'Asuransi Lain',
                };
                $row->klaim_variant = match ($row->klaim_id) {
                    'UM' => 'success',
                    'KR' => 'warning',
                    default => 'alternative',
                };
            }

            return $row;
        });

        return $paginator;
    }

    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();

        return DB::table('rstxn_ugdhdrs')
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_ugdhdrs.dr_id')
            ->select('rstxn_ugdhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), DB::raw('COUNT(DISTINCT rstxn_ugdhdrs.rj_no) as total_pasien'))
            ->whereBetween('rstxn_ugdhdrs.rj_date', [$start, $end])
            ->where('rstxn_ugdhdrs.klaim_id', '!=', 'KR')
            ->groupBy('rstxn_ugdhdrs.dr_id')
            ->orderBy('dr_name')
            ->get();
    }
};
?>

<div>
    <x-page-title
        title="Antrian Kasir UGD"
        subtitle="Administrasi &amp; Pembayaran Unit Gawat Darurat" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('antrian-kasir-toolbar', []) }}">

                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No UGD / No RM / Nama Pasien / Dokter..." />
                        </div>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Transfer Inap</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM — BPJS / UMUM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">
                                    {{ $dokter->dr_name }} ({{ $dokter->total_pasien }})
                                </option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Auto Refresh" />
                        <x-select-input wire:model.live="autoRefresh" class="w-full mt-1 sm:w-28">
                            <option value="Ya">Ya (20s)</option>
                            <option value="Tidak">Tidak</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Per Hal." />
                        <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-28">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>

                    {{-- AKSI — Refresh + Reset standar di kanan sendiri --}}
                    <x-toolbar-refresh-reset class="ml-auto" />

                </div>
                <div class="mt-1 text-xs text-muted">
                    Data Terakhir: {{ now()->format('d/m/Y H:i:s') }}
                </div>
            </div>

            {{-- AUTO REFRESH WRAPPER --}}
            @if ($autoRefresh === 'Ya')
                <div wire:poll.30s class="mt-4 flex flex-col flex-1 min-h-0">
                @else
                    <div class="mt-4 flex flex-col flex-1 min-h-0">
            @endif

            {{-- TABLE --}}
            <div class="flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-2 border-separate border-spacing-y-2">

                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-4 py-3">Pasien</th>
                                <th class="px-4 py-3">Dokter</th>
                                <th class="px-4 py-3">Status Layanan</th>
                                <th class="px-4 py-3">Waktu Kasir</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="antrian-kasir-ugd-{{ $row->rj_no ?? $loop->index }}"
                                    class="transition bg-canvas dark:bg-gray-900 hover:shadow-md hover:bg-surface-soft dark:hover:bg-gray-800 rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-top">
                                        <div class="space-y-1">
                                            <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name"
                                                :sex="$row->sex" :tglLahir="$row->birth_date"
                                                :alamat="$row->address" :collapseUmur="false" />
                                        </div>
                                    </td>

                                    {{-- DOKTER --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm font-semibold text-success dark:text-success">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-badge :variant="$row->klaim_variant">{{ $row->klaim_label }}</x-badge>
                                            @if ($row->vno_sep)
                                                <span class="font-mono text-xs text-muted dark:text-gray-400">
                                                    {{ $row->vno_sep }}
                                                </span>
                                            @endif
                                            <div>
                                                @if ($row->lab_status)
                                                    <x-badge variant="alternative">Laborat</x-badge>
                                                @endif
                                                @if ($row->rad_status)
                                                    <x-badge variant="brand">Radiologi</x-badge>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs text-muted dark:text-gray-400">
                                            {{ $row->rj_date_display }} | Shift {{ $row->shift ?? '-' }}
                                        </div>
                                        <x-badge :variant="$row->status_variant">{{ $row->status_text }}</x-badge>
                                        <div class="flex gap-1.5">
                                            @if ($row->eresep_count)
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    E-Resep
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                                    Tanpa Resep
                                                </span>
                                            @endif
                                            @if ($row->has_eresep_racikan)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                                    Racikan
                                                </span>
                                            @endif
                                        </div>
                                        @if ($row->status_resep)
                                            <x-badge :variant="$row->status_resep_color">{{ $row->status_resep_label }}</x-badge>
                                        @endif
                                    </td>

                                    {{-- WAKTU APOTEK --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs space-y-1">
                                            @php
                                                $rjLabel = match ($row->rj_status) {
                                                    'A' => 'Belum Bayar',
                                                    'L' => 'Selesai Pembayaran',
                                                    'I' => 'Transfer Inap',
                                                    'F' => 'Batal',
                                                    default => null,
                                                };
                                                $rjTextColor = match ($row->rj_status) {
                                                    'A' => 'text-amber-600 dark:text-amber-400',
                                                    'L' => 'text-success dark:text-success',
                                                    'I' => 'text-blue-600 dark:text-blue-400',
                                                    'F' => 'text-red-600 dark:text-red-400',
                                                    default => 'text-muted-soft',
                                                };
                                            @endphp
                                            @if ($rjLabel)
                                                <div class="text-xs text-muted dark:text-gray-500">
                                                    Kasir:
                                                    <span
                                                        class="font-medium {{ $rjTextColor }}">{{ $rjLabel }}</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id6 ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Masuk Apotek: <span
                                                        class="font-medium">{{ $row->task_id6 ?? '—' }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Keluar Apotek: <span
                                                        class="font-medium">{{ $row->task_id7 ?? '—' }}</span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-xs text-muted dark:text-gray-500">
                                            Administrasi:
                                            <span
                                                class="font-medium {{ $row->admin_user !== '-' ? 'text-success dark:text-success' : 'text-muted-soft' }}">
                                                {{ $row->admin_user }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        @if ($row->status_text === 'Batal')
                                            {{-- Batal: actions tidak diakses, konfirmasi ke Pendaftaran --}}
                                            <div
                                                class="flex flex-col items-center gap-2 p-3 text-center border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/10 dark:border-red-800">
                                                <div class="text-red-500 dark:text-red-400">
                                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs font-semibold text-red-600 dark:text-red-400">
                                                    Pasien Batal
                                                </span>
                                                <span class="text-xs text-muted dark:text-gray-400">
                                                    Konfirmasi ke<br>Pendaftaran
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex flex-col gap-2">

                                                {{-- Administrasi — Admin | Tu --}}
                                                @hasanyrole('Admin|Tu|Manager Umum|Supervisor Tu')
                                                    <x-secondary-button
                                                        wire:click="openAdministrasiPasien('{{ $row->rj_no }}')"
                                                        class="text-xs whitespace-nowrap justify-center !bg-purple-600 !text-white !border-purple-700 hover:!bg-purple-700 dark:!bg-purple-600 dark:!text-white dark:!border-purple-700 dark:hover:!bg-purple-700">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                        Administrasi
                                                    </x-secondary-button>
                                                @endhasanyrole

                                                {{-- Transfer ke RI — aktif HANYA saat status Antrian (rj_status='A'), selain itu disabled --}}
                                                @hasanyrole('Admin|Tu|Perawat|Manager Umum|Supervisor Tu')
                                                    <x-secondary-button type="button"
                                                        :disabled="$row->rj_status !== 'A'"
                                                        wire:click="$dispatch('open-transfer-ugd-ke-ri', { rjNo: {{ $row->rj_no }} })"
                                                        title="{{ $row->rj_status === 'A' ? 'Transfer pasien ke Rawat Inap' : 'Hanya bisa saat status Antrian' }}"
                                                        class="text-xs whitespace-nowrap justify-center !bg-teal-600 !text-white !border-teal-700 hover:!bg-teal-700 dark:!bg-teal-600 dark:!text-white dark:!border-teal-700 dark:hover:!bg-teal-700">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M3 12h13m0 0l-4-4m4 4l-4 4m9-9v10a2 2 0 01-2 2h-3" />
                                                        </svg>
                                                        Transfer ke RI
                                                    </x-secondary-button>
                                                @endhasanyrole

                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Tidak ada data antrian kasir UGD</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        <livewire:pages::transaksi.ugd.emr-ugd.log-aktivitas.log-aktivitas-ugd wire:key="log-aktivitas-ugd" />
        <livewire:pages::transaksi.ugd.administrasi-ugd.administrasi-ugd wire:key="administrasi-ugd-actions" />
        <livewire:pages::transaksi.ugd.administrasi-ugd.transfer-ugd-ke-ri-actions wire:key="transfer-ugd-ke-ri-actions" />

    </div>
</div>
</div>
