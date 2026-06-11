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
    protected array $renderAreas = ['antrian-kasir-ri-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A';
    public string $filterDokter = '';
    public int $itemsPerPage = 10;
    public string $autoRefresh = 'Ya';

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    /* -------------------------
     | Updated hooks
     * ------------------------- */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-ri-toolbar');
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-ri-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-kasir-ri-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('antrian-kasir-ri-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Dispatch ke child actions
     * ------------------------- */
    public function openAdministrasi(int $slsNo, ?string $tab = null): void
    {
        $this->dispatch('administrasi-kasir-ri.open', slsNo: $slsNo, tab: $tab);
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-antrian-kasir-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('antrian-kasir-ri-toolbar');
        $this->resetPage();
    }

    #[On('refresh-after-kasir-ri.saved')]
    public function refreshAfterKasirSaved(): void
    {
        $this->incrementVersion('antrian-kasir-ri-toolbar');
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

        $query = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'r.klaim_id')
            ->select(['s.sls_no', DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date_display"), 's.status', 's.rihdr_no', 's.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 's.no_antrian', 's.dr_id', 'd.dr_name', 's.shift', 'r.ri_status', 'r.klaim_id', 'k.klaim_desc', 'r.datadaftarri_json', 'r.vno_sep', DB::raw("to_char(s.waktu_masuk_pelayanan,'dd/mm/yyyy hh24:mi') as waktu_masuk"), DB::raw("to_char(s.waktu_selesai_pelayanan,'dd/mm/yyyy hh24:mi') as waktu_selesai"), DB::raw('(select count(*) from imtxn_slsdtls where sls_no = s.sls_no) as item_count')])
            ->whereNotNull('s.rihdr_no')
            ->whereBetween('s.sls_date', [$start, $end])
            ->where(DB::raw("nvl(s.status,'A')"), $this->filterStatus);

        if ($this->filterDokter !== '') {
            $query->where('s.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($kw, $search) {
                if (ctype_digit($search)) {
                    $q->orWhere('s.sls_no', 'like', "%{$search}%")->orWhere('s.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('upper(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('upper(s.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('upper(d.dr_name)'), 'like', "%{$kw}%");
            });
        }

        // Sorting custom: yang ada no_antrian di atas, lalu ascending
        $all = $query->get();

        $sorted = $all
            ->sortBy(function ($row) {
                $no = (int) ($row->no_antrian ?? 0);
                return [$no > 0 ? 0 : 1, $no];
            })
            ->values();

        $page = $this->getPage();
        $perPage = $this->itemsPerPage;
        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $page, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        $paginator->getCollection()->transform(function ($row) {
            $row->no_antrian = (int) ($row->no_antrian ?? 0);

            // Ekstrak info per resep dari JSON RI matched by slsNo
            // - eresepHdr: data dokter (untuk resepNo, jenis racikan)
            // - apotekHdr: data apoteker (telaah, taskId)
            $eresepHdr = null;
            $apotekHdr = null;
            try {
                $data = $row->datadaftarri_json ? json_decode($row->datadaftarri_json, true) : null;
                if (is_array($data)) {
                    foreach ($data['eresepHdr'] ?? [] as $h) {
                        if ((int) ($h['slsNo'] ?? 0) === (int) $row->sls_no) {
                            $eresepHdr = $h;
                            break;
                        }
                    }
                    foreach ($data['apotekHdr'] ?? [] as $h) {
                        if ((int) ($h['slsNo'] ?? 0) === (int) $row->sls_no) {
                            $apotekHdr = $h;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // ignore — fallback null
            }

            // Resep info (dari eresepHdr — dokter)
            $row->resep_no = $eresepHdr['resepNo'] ?? null;
            $row->jenis_resep = !empty($eresepHdr['eresepRacikan']) ? 'racikan' : 'non racikan';

            // taskId (dari apotekHdr — apoteker)
            $row->task_id6 = $apotekHdr['taskIdPelayanan']['taskId6'] ?? null;
            $row->task_id7 = $apotekHdr['taskIdPelayanan']['taskId7'] ?? null;

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

            // Status SLS badge
            $statusMap = ['A' => 'Antrian', 'L' => 'Selesai'];
            $statusVariant = ['A' => 'warning', 'L' => 'success'];
            $row->status_text = $statusMap[strtoupper($row->status ?? 'A')] ?? '-';
            $row->status_variant = $statusVariant[strtoupper($row->status ?? 'A')] ?? 'gray';

            // RI status
            $row->ri_status_text = match (strtoupper($row->ri_status ?? '')) {
                'A' => 'Dirawat',
                'P' => 'Sudah Pulang',
                'B' => 'Batal',
                default => $row->ri_status ?? '-',
            };
            $row->ri_status_variant = match (strtoupper($row->ri_status ?? '')) {
                'A' => 'brand',
                'P' => 'gray',
                'B' => 'danger',
                default => 'alternative',
            };

            // Klaim
            $row->klaim_label = match ($row->klaim_id) {
                'UM' => 'UMUM',
                'JM' => 'BPJS',
                'KR' => 'Kronis',
                default => $row->klaim_desc ?? 'Asuransi Lain',
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

        return DB::table('imtxn_slshdrs as s')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->select('s.dr_id', DB::raw('max(d.dr_name) as dr_name'), DB::raw('count(distinct s.sls_no) as total_resep'))
            ->whereNotNull('s.rihdr_no')
            ->whereBetween('s.sls_date', [$start, $end])
            ->groupBy('s.dr_id')
            ->orderBy('dr_name')
            ->get();
    }
};
?>

<div>
    <x-page-title
        title="Antrian Kasir RI"
        subtitle="Administrasi &amp; Pembayaran Rawat Inap" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('antrian-kasir-ri-toolbar', []) }}">

                    {{-- SEARCH --}}
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
                                placeholder="Cari No SLS / No RM / Nama Pasien / Dokter..." />
                        </div>
                    </div>

                    {{-- TANGGAL --}}
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

                    {{-- STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                        </x-select-input>
                    </div>

                    {{-- DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">
                                    {{ $dokter->dr_name }} ({{ $dokter->total_resep }})
                                </option>
                            @endforeach
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
                <div wire:poll.20s class="mt-4 flex flex-col flex-1 min-h-0">
                @else
                    <div class="mt-4 flex flex-col flex-1 min-h-0">
            @endif

            {{-- TABLE --}}
            <div class="flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-4 py-3">Antrian &amp; Pasien</th>
                                <th class="px-4 py-3">Resep / Dokter</th>
                                <th class="px-4 py-3">Status Layanan</th>
                                <th class="px-4 py-3">Waktu Kasir</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="antrian-kasir-ri-{{ $row->reg_no ?? $loop->index }}"
                                    class="transition bg-canvas dark:bg-gray-900 hover:shadow-md hover:bg-blue-50 dark:hover:bg-gray-800 rounded-xl
                                    {{ $row->no_antrian > 0 ? 'border-l-4 border-l-blue-500' : '' }}">

                                    {{-- ANTRIAN & PASIEN --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex flex-col items-center justify-center w-16 h-16 rounded-xl
                                                {{ $row->no_antrian > 0
                                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                    : 'bg-surface-soft text-muted-soft dark:bg-gray-700' }}">
                                                <span class="text-2xl font-bold leading-none">
                                                    {{ $row->no_antrian ?: '-' }}
                                                </span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    {{ $row->no_antrian > 0 ? 'kasir' : 'belum' }}
                                                </span>
                                            </div>
                                            <div class="space-y-0.5 min-w-0">
                                                <div class="text-base font-medium text-body dark:text-gray-300">
                                                    {{ $row->reg_no }}
                                                </div>
                                                <div class="text-lg font-semibold text-brand dark:text-white leading-tight">
                                                    {{ $row->reg_name ?? '-' }} /
                                                    ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>
                                                <div class="text-sm text-body dark:text-gray-400">
                                                    {{ $row->birth_date ?? '-' }}
                                                    @if (!empty($row->umur_format) && $row->umur_format !== '-')
                                                        <span class="text-muted">({{ $row->umur_format }})</span>
                                                    @endif
                                                </div>
                                                <div
                                                    class="text-sm text-muted dark:text-gray-400 truncate max-w-[200px]">
                                                    {{ $row->address }}
                                                </div>
                                                @if ($row->no_antrian > 0)
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

                                    {{-- RESEP / DOKTER --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm font-mono font-semibold text-blue-600 dark:text-blue-400">
                                            SLS #{{ $row->sls_no }}
                                            @if ($row->resep_no)
                                                <span class="text-muted-soft">·</span>
                                                <span class="text-xs text-muted dark:text-gray-400">Resep
                                                    #{{ $row->resep_no }}</span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-body dark:text-gray-300">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <x-badge :variant="$row->klaim_variant">
                                            {{ $row->klaim_label }}
                                        </x-badge>
                                        <x-badge :variant="$row->ri_status_variant">
                                            {{ $row->ri_status_text }}
                                        </x-badge>
                                        <div class="text-xs text-muted dark:text-gray-500">
                                            No RI: {{ $row->rihdr_no }}
                                        </div>
                                        @if ($row->vno_sep)
                                            <div class="font-mono text-xs text-muted dark:text-gray-400">
                                                {{ $row->vno_sep }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs text-muted dark:text-gray-400">
                                            {{ $row->sls_date_display }} | Shift {{ $row->shift ?? '-' }}
                                            &bull; {{ $row->item_count }} obat
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                    </td>

                                    {{-- WAKTU APOTEK --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id6 ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Masuk Apotek:
                                                    <span
                                                        class="font-medium">{{ $row->task_id6 ?? ($row->waktu_masuk ?? '—') }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Keluar Apotek:
                                                    <span
                                                        class="font-medium">{{ $row->task_id7 ?? ($row->waktu_selesai ?? '—') }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-col gap-2">

                                            {{-- Administrasi (Obat + Kasir) — pola purple ala UGD --}}
                                            @hasanyrole('Admin|Tu|Manager Umum|Supervisor Tu')
                                                @if ($row->status === 'L')
                                                    <x-success-button
                                                        wire:click="openAdministrasi({{ $row->sls_no }}, 'kasir')"
                                                        class="text-xs whitespace-nowrap justify-center">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Lihat / Cetak
                                                    </x-success-button>
                                                @else
                                                    <x-secondary-button
                                                        wire:click="openAdministrasi({{ $row->sls_no }}, 'obat')"
                                                        class="text-xs whitespace-nowrap justify-center !bg-purple-600 !text-white !border-purple-700 hover:!bg-purple-700 dark:!bg-purple-600 dark:!text-white dark:!border-purple-700 dark:hover:!bg-purple-700">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                        Administrasi
                                                    </x-secondary-button>
                                                @endif
                                            @endhasanyrole

                                        </div>
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
                                            <span>Tidak ada antrian kasir RI</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        {{-- Child action components --}}
        <livewire:pages::transaksi.kasir.administrasi-kasir-ri.administrasi-kasir-ri
            wire:key="administrasi-kasir-ri-modal" />

        {{-- PDF dispatcher (listen 'cetak-kwitansi-ri-obat.open') --}}
        <livewire:pages::components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-obat
            wire:key="cetak-kwitansi-ri-obat" />

        {{-- PDF dispatcher etiket obat RI (listen 'cetak-etiket-obat-ri.open') --}}
        <livewire:pages::components.rekam-medis.r-i.etiket-obat.cetak-etiket-obat
            wire:key="cetak-etiket-obat-ri" />

    </div>
</div>
