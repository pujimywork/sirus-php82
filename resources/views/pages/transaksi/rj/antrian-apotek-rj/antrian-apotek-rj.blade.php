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
    protected array $renderAreas = ['antrian-apotek-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterTaskId7 = 'N'; // '' | 'Y' (sudah serah obat) | 'N' (belum, default)
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM' — pakai klaim_status (JM dianggap BPJS)
    public string $filterDokter = '';
    public int $itemsPerPage = 10;
    public string $autoRefresh = 'Ya';

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    /* -------------------------
     | Updated hooks — reset page & bump toolbar
     * ------------------------- */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-toolbar');
    }

    public function updatedFilterTaskId7(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-toolbar');
    }

    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterTaskId7', 'filterKlaim', 'filterDokter']);
        $this->filterTaskId7 = 'N';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('antrian-apotek-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Dispatch ke child actions
     * ------------------------- */

    public function openTelaah(int $hasEresep, string $rjNo): void
    {
        if (!$hasEresep) {
            $this->dispatch('toast', type: 'error', message: 'E-Resep tidak ditemukan untuk pasien ini.');
            return;
        }
        $this->dispatch('antrian-apotek.telaah-resep.open', rjNo: $rjNo);
    }

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-rj.administrasi.open', rjNo: $rjNo);
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-apotek.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('antrian-apotek-toolbar');
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
     | Computed: main query
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.vno_sep', 'h.nobooking', 'h.datadaftarpolirj_json', 'k.klaim_desc', 'k.klaim_status', 'po.spesialis_status', 'h.waktu_masuk_apt', 'h.waktu_selesai_pelayanan', 'h.status_kronis', 'h.status_iter'])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR');

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        // Filter Klaim BPJS / UMUM
        // BPJS = klaim_status='BPJS' (di rsmst_klaimtypes) ATAU klaim_id='JM' (JKN Mobile)
        // UMUM = bukan keduanya
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

        // Ambil semua dulu untuk sorting custom (antrian apotek)
        $all = $query->get();

        // Filter task_id7 (sudah serah obat / belum) — nested JSON, harus di PHP
        if ($this->filterTaskId7 !== '') {
            $wantHas = $this->filterTaskId7 === 'Y';
            $all = $all->filter(function ($row) use ($wantHas) {
                $json = json_decode($row->datadaftarpolirj_json ?? '{}', true);
                $hasT7 = !empty($json['taskIdPelayanan']['taskId7']);
                return $wantHas ? $hasT7 : !$hasT7;
            });
        }

        // Sort 5-level:
        //   1. hasAntrian (0 = punya noAntrianApotek → atas, 1 = belum)
        //   2. noAntrian desc (terbesar dulu) — dalam group "ada antrian" (pakai -noAntrian)
        //   3. tanpaResep (0 = punya e-resep → atas, 1 = tanpa resep → bawah)
        //   4. taskId5 asc (earliest first; empty = last) — Keluar Poli, FIFO
        //   5. taskId6 asc (earliest first; empty = last) — Menunggu Resep, FIFO
        // taskId5/6 disimpan format "d/m/Y H:i:s" → str_replace '/'→'-' biar strtotime parse benar (DD-MM-YYYY).
        $sorted = $all
            ->sortBy(function ($row) {
                $json = json_decode($row->datadaftarpolirj_json ?? '{}', true);
                $noAntrian = $json['noAntrianApotek']['noAntrian'] ?? 0;
                $hasAntrian = $noAntrian > 0 ? 0 : 1;

                // Tanpa resep (tidak ada e-resep) → taruh di urutan bawah
                $tanpaResep = isset($json['eresep']) ? 0 : 1;

                $taskId5 = $json['taskIdPelayanan']['taskId5'] ?? '';
                $taskId6 = $json['taskIdPelayanan']['taskId6'] ?? '';
                $t5 = $taskId5 !== '' ? strtotime(str_replace('/', '-', $taskId5)) : PHP_INT_MAX;
                $t6 = $taskId6 !== '' ? strtotime(str_replace('/', '-', $taskId6)) : PHP_INT_MAX;

                return [$hasAntrian, -$noAntrian, $tanpaResep, $t5, $t6];
            })
            ->values();

        // Manual paginate
        $page = $this->getPage();
        $perPage = $this->itemsPerPage;
        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $page, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        // Transform rows
        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarpolirj_json ?? '{}', true);

            // Antrian apotek
            $row->no_antrian_apotek = $json['noAntrianApotek']['noAntrian'] ?? 0;
            $row->jenis_resep = $json['noAntrianApotek']['jenisResep'] ?? '-';

            // E-Resep
            $row->has_eresep = isset($json['eresep']) ? 1 : 0;
            $row->has_eresep_racikan = isset($json['eresepRacikan']) ? 1 : 0;
            $row->eresep_count = $row->has_eresep + $row->has_eresep_racikan;

            // Telaah status
            $row->telaah_resep_done = isset($json['telaahResep']['penanggungJawab']) && !empty($json['telaahResep']['penanggungJawab']);
            $row->telaah_obat_done = isset($json['telaahObat']['penanggungJawab']) && !empty($json['telaahObat']['penanggungJawab']);

            $row->telaah_resep_ttd = $json['telaahResep']['penanggungJawab']['userLog'] ?? null;
            $row->telaah_obat_ttd = $json['telaahObat']['penanggungJawab']['userLog'] ?? null;

            // Task IDs
            $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;
            $row->task_id6 = $json['taskIdPelayanan']['taskId6'] ?? null;
            $row->task_id7 = $json['taskIdPelayanan']['taskId7'] ?? null;

            // Status resep
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

            // Administrasi
            $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';

            // Umur
            if (!empty($row->birth_date)) {
                try {
                    $tglLahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                    $diff = $tglLahir->diff(now());
                    $row->umur_format = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
                } catch (\Exception $e) {
                    $row->umur_format = '-';
                }
            } else {
                $row->umur_format = '-';
            }

            // Status badge — unified berdasarkan urutan Task ID flow
            // Batal di-detect dari Task ID 99 OR rj_status='F' (legacy mutasi langsung)
            $tasks = $json['taskIdPelayanan'] ?? [];
            if (!empty($tasks['taskId99']) || $row->rj_status === 'F') {
                $row->status_text = 'Batal';
                $row->status_variant = 'danger';
            } elseif (!empty($tasks['taskId7'])) {
                $row->status_text = 'Pasien Menerima Resep';
                $row->status_variant = 'success';
            } elseif (!empty($tasks['taskId6'])) {
                $row->status_text = 'Menunggu Resep';
                $row->status_variant = 'warning';
            } elseif (!empty($tasks['taskId5'])) {
                $row->status_text = 'Keluar Poli';
                $row->status_variant = 'brand';
            } elseif (!empty($tasks['taskId4'])) {
                $row->status_text = 'Masuk Poli';
                $row->status_variant = 'warning';
            } elseif (!empty($tasks['taskId3'])) {
                $row->status_text = 'Pendaftaran';
                $row->status_variant = 'alternative';
            } else {
                $row->status_text = 'Belum Dilayani';
                $row->status_variant = 'gray';
            }

            // Klaim badge
            $row->klaim_label = match ($row->klaim_id) {
                'UM' => 'UMUM',
                'JM' => 'BPJS',
                'KR' => 'Kronis',
                default => 'Asuransi Lain',
            };
            $row->klaim_variant = match ($row->klaim_id) {
                'UM' => 'success',
                'JM' => 'brand',
                'KR' => 'warning',
                default => 'alternative',
            };

            return $row;
        });

        return $paginator;
    }

    /* -------------------------
     | Computed: dokter filter list
     * ------------------------- */
    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();

        return DB::table('rstxn_rjhdrs')
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')
            ->select('rstxn_rjhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'))
            ->whereBetween('rstxn_rjhdrs.rj_date', [$start, $end])
            ->where('rstxn_rjhdrs.klaim_id', '!=', 'KR')
            ->groupBy('rstxn_rjhdrs.dr_id')
            ->orderBy('dr_name')
            ->get();
    }

    public function cetakEresep(string $rjNo): void
    {
        $this->dispatch('cetak-eresep-rj.open', rjNo: $rjNo);
    }
};
?>

<div>
    <x-page-title
        title="Apotek Rawat Jalan"
        subtitle="Kelola telaah resep &amp; pelayanan kefarmasian pasien rawat jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('antrian-apotek-toolbar', []) }}">

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
                                placeholder="Cari No RJ / No RM / Nama Pasien / Dokter..." />
                        </div>
                    </div>

                    {{-- TANGGAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- OBAT DISERAHKAN (task_id7) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Obat Diserahkan" />
                        <x-select-input wire:model.live="filterTaskId7" class="w-full mt-1 sm:w-48">
                            <option value="">Semua</option>
                            <option value="N">Belum Diserahkan</option>
                            <option value="Y">Sudah Diserahkan</option>
                        </x-select-input>
                    </div>

                    {{-- DOKTER --}}
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

                    {{-- KLAIM — BPJS / UMUM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    {{-- AUTO REFRESH --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Auto Refresh" />
                        <x-select-input wire:model.live="autoRefresh" class="w-full mt-1 sm:w-28">
                            <option value="Ya">Ya (20s)</option>
                            <option value="Tidak">Tidak</option>
                        </x-select-input>
                    </div>

                    {{-- PER HALAMAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Per Halaman" />
                        <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-24">
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        {{-- Refresh data tanpa reset filter (biru, ikon reload) --}}
                        <x-info-button type="button" wire:click="$refresh" class="whitespace-nowrap">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                wire:loading.class="animate-spin" wire:target="$refresh">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh
                        </x-info-button>
                        {{-- Reset filter (abu, ikon silang) --}}
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Reset
                        </x-secondary-button>
                    </div>

                </div>

                {{-- Timestamp refresh --}}
                <div class="mt-1 text-xs text-gray-500">
                    Data Terakhir: {{ now()->format('d/m/Y H:i:s') }}
                </div>
            </div>

            {{-- AUTO REFRESH WRAPPER --}}
            @if ($autoRefresh === 'Ya')
                <div wire:poll.20s class="mt-4 flex flex-col flex-1 min-h-0">
                @else
                    <div class="mt-4 flex flex-col flex-1 min-h-0">
            @endif

            {{-- TABLE --}}
            <div class="flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-2">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-4 py-3">Antrian & Pasien</th>
                                <th class="px-4 py-3">Poli / Dokter</th>
                                <th class="px-4 py-3">Status Layanan</th>
                                <th class="px-4 py-3">Waktu Apotek</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr
                                    wire:key="antrian-apotek-rj-row-{{ $row->rj_no }}"
                                    class="transition bg-white dark:bg-gray-900 hover:shadow-md hover:bg-green-50 dark:hover:bg-gray-800 rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                    {{ $row->no_antrian_apotek > 0 ? 'border-l-4 border-l-emerald-500' : '' }}">

                                    {{-- ANTRIAN & PASIEN --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start gap-3">
                                            {{-- Nomor antrian apotek --}}
                                            <div
                                                class="flex flex-col items-center justify-center w-16 h-16 rounded-xl
                                                {{ $row->no_antrian_apotek > 0
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                    : 'bg-gray-100 text-gray-400 dark:bg-gray-700' }}">
                                                <span class="text-2xl font-bold leading-none">
                                                    {{ $row->no_antrian_apotek ?: '-' }}
                                                </span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    {{ $row->no_antrian_apotek > 0 ? 'apotek' : 'belum' }}
                                                </span>
                                            </div>

                                            <div class="space-y-0.5 min-w-0">
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row->reg_no }}
                                                </div>
                                                <div
                                                    class="text-sm font-semibold text-gray-900 dark:text-white truncate max-w-[180px]">
                                                    {{ $row->reg_name }}
                                                </div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }}
                                                    &bull; {{ $row->umur_format }}
                                                </div>
                                                <div
                                                    class="text-xs text-gray-500 dark:text-gray-500 truncate max-w-[200px]">
                                                    {{ $row->address }}
                                                </div>
                                                {{-- Jenis resep badge --}}
                                                @if ($row->no_antrian_apotek > 0)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium
                                                        {{ $row->jenis_resep === 'racikan'
                                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }}">
                                                        {{ ucfirst($row->jenis_resep) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- POLI / DOKTER --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1">
                                            <x-badge :variant="$row->klaim_variant">
                                                {{ $row->klaim_label }}
                                            </x-badge>
                                            @if (($row->status_kronis ?? 'N') === 'Y')
                                                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                                                      title="Kunjungan ini punya obat dengan split kronis (BPJS InaCBG + Kronis luar paket)">
                                                    KRONIS
                                                </span>
                                            @endif
                                            @if (($row->status_iter ?? 'N') === 'Y')
                                                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200"
                                                      title="Kunjungan ini punya obat iter (resep boleh diulang)">
                                                    ITER
                                                </span>
                                            @endif
                                        </div>
                                        @if ($row->vno_sep)
                                            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row->vno_sep }}
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            No RJ: {{ $row->rj_no }}
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->rj_date_display }} | Shift {{ $row->shift ?? '-' }}
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        {{-- E-Resep indicator --}}
                                        <div class="flex gap-1.5">
                                            @if ($row->has_eresep)
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

                                        {{-- Status resep --}}
                                        @if ($row->status_resep)
                                            <x-badge :variant="$row->status_resep_color">
                                                {{ $row->status_resep_label }}
                                            </x-badge>
                                        @endif

                                        {{-- Telaah status --}}
                                        <div class="space-y-1 text-xs">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->telaah_resep_done ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span
                                                    class="{{ $row->telaah_resep_done ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500' }}">
                                                    Telaah Resep
                                                    @if ($row->telaah_resep_ttd)
                                                        &mdash; {{ $row->telaah_resep_ttd }}
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->telaah_obat_done ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span
                                                    class="{{ $row->telaah_obat_done ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500' }}">
                                                    Telaah Obat
                                                    @if ($row->telaah_obat_ttd)
                                                        &mdash; {{ $row->telaah_obat_ttd }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- WAKTU APOTEK --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id5 ? 'bg-blue-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Keluar Poli:
                                                    <span class="font-medium">{{ $row->task_id5 ?? '—' }}</span>
                                                </span>
                                            </div>
                                            @php
                                                $rjLabel = match ($row->rj_status) {
                                                    'A' => 'Belum Bayar',
                                                    'L' => 'Selesai Pembayaran',
                                                    'I' => 'Transfer UGD',
                                                    'F' => 'Batal',
                                                    default => null,
                                                };
                                                $rjTextColor = match ($row->rj_status) {
                                                    'A' => 'text-amber-600 dark:text-amber-400',
                                                    'L' => 'text-emerald-600 dark:text-emerald-400',
                                                    'I' => 'text-blue-600 dark:text-blue-400',
                                                    'F' => 'text-red-600 dark:text-red-400',
                                                    default => 'text-gray-400',
                                                };
                                            @endphp
                                            @if ($rjLabel)
                                                <div class="text-xs text-gray-500 dark:text-gray-500">
                                                    Kasir:
                                                    <span class="font-medium {{ $rjTextColor }}">{{ $rjLabel }}</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id6 ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Masuk Apotek:
                                                    <span class="font-medium">{{ $row->task_id6 ?? '—' }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Keluar Apotek:
                                                    <span class="font-medium">{{ $row->task_id7 ?? '—' }}</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            Administrasi:
                                            <span
                                                class="font-medium {{ $row->admin_user !== '-' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $row->admin_user }}
                                            </span>
                                        </div>

                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        @if ($row->status_text === 'Batal')
                                            {{-- Batal: actions tidak diakses, konfirmasi ke Pendaftaran --}}
                                            <div class="flex flex-col items-center gap-2 p-3 text-center border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/10 dark:border-red-800">
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
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    Konfirmasi ke<br>Pendaftaran
                                                </span>
                                            </div>
                                        @else
                                        <div class="flex flex-col gap-2">

                                            {{-- Row 1: [grid T6 T7] | [Telaah] — outer grid 2 kolom sejajar --}}
                                            <div class="grid grid-cols-2 gap-2">
                                                {{-- Group T6+T7 (sub-grid 2 kolom) --}}
                                                <div class="grid grid-cols-2 gap-1">
                                                    @hasanyrole('Apoteker|Admin')
                                                        <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-6
                                                            :rjNo="$row->rj_no"
                                                            :isDone="(bool) $row->task_id6"
                                                            wire:key="taskid6-{{ $row->rj_no }}" />
                                                        <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-7
                                                            :rjNo="$row->rj_no"
                                                            :isDone="(bool) $row->task_id7"
                                                            wire:key="taskid7-{{ $row->rj_no }}" />
                                                    @endhasanyrole
                                                </div>

                                                {{-- Telaah --}}
                                                @if ($row->telaah_resep_done && $row->telaah_obat_done)
                                                    <x-secondary-button
                                                        wire:click="openTelaah({{ $row->has_eresep }}, '{{ $row->rj_no }}')"
                                                        class="text-xs whitespace-nowrap justify-center !opacity-60"
                                                        title="Telaah Resep & Obat sudah ditelaah, klik untuk lihat detail">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Telaah Resep &amp; Obat ✓
                                                    </x-secondary-button>
                                                @else
                                                    <x-secondary-button
                                                        wire:click="openTelaah({{ $row->has_eresep }}, '{{ $row->rj_no }}')"
                                                        class="text-xs whitespace-nowrap justify-center !bg-teal-600 !text-white !border-teal-700 hover:!bg-teal-700 dark:!bg-teal-600 dark:!text-white dark:!border-teal-700 dark:hover:!bg-teal-700">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                        </svg>
                                                        Telaah Resep &amp; Obat
                                                    </x-secondary-button>
                                                @endif
                                            </div>

                                            {{-- Row 2: Cetak E-Resep + Administrasi (grid agar sejajar) --}}
                                            <div class="grid grid-cols-2 gap-2">
                                                @if ($row->has_eresep || $row->has_eresep_racikan)
                                                    <x-info-button wire:click="cetakEresep('{{ $row->rj_no }}')"
                                                        wire:loading.attr="disabled" wire:target="cetakEresep"
                                                        class="text-xs whitespace-nowrap justify-center">
                                                        <span wire:loading.remove wire:target="cetakEresep"
                                                            class="flex items-center">
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak E-Resep
                                                        </span>
                                                        <span wire:loading wire:target="cetakEresep"
                                                            class="flex items-center gap-1">
                                                            <x-loading /> Menyiapkan...
                                                        </span>
                                                    </x-info-button>
                                                @else
                                                    <div></div>
                                                @endif

                                                @hasanyrole('Admin|Perawat|Casemix|Apoteker')
                                                    <x-secondary-button
                                                        wire:click="openAdministrasiPasien('{{ $row->rj_no }}')"
                                                        class="text-xs whitespace-nowrap justify-center !bg-purple-600 !text-white !border-purple-700 hover:!bg-purple-700 dark:!bg-purple-600 dark:!text-white dark:!border-purple-700 dark:hover:!bg-purple-700">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                        Administrasi
                                                    </x-secondary-button>
                                                @endhasanyrole
                                            </div>

                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Tidak ada data antrian apotek</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{-- Di luar loop, sekali saja (sejajar child actions lain) --}}
                    <livewire:pages::components.rekam-medis.r-j.cetak-eresep.cetak-eresep wire:key="cetak-eresep-rj" />
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        {{-- Child action components --}}

        <livewire:pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj-actions
            wire:key="antrian-apotek-rj-actions" />

        <livewire:pages::transaksi.rj.emr-rj.log-aktivitas.log-aktivitas-rj wire:key="log-aktivitas-rj" />
        <livewire:pages::transaksi.rj.administrasi-rj.administrasi-rj wire:key="administrasi-rj-actions" />

    </div>
</div>
</div>
