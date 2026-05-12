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
    protected array $renderAreas = ['antrian-apotek-ri-toolbar'];

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
        $this->incrementVersion('antrian-apotek-ri-toolbar');
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-ri-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-ri-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('antrian-apotek-ri-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Dispatch ke child actions
     * ------------------------- */
    public function openTelaah(int $slsNo): void
    {
        $this->dispatch('antrian-apotek-ri.telaah.open', slsNo: $slsNo);
    }

    public function openAdministrasi(int $slsNo, ?string $tab = null): void
    {
        $this->dispatch('administrasi-apotek-ri.open', slsNo: $slsNo, tab: $tab);
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-antrian-apotek-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('antrian-apotek-ri-toolbar');
        $this->resetPage();
    }

    #[On('refresh-after-kasir-ri.saved')]
    public function refreshAfterKasirSaved(): void
    {
        $this->incrementVersion('antrian-apotek-ri-toolbar');
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

            // Telaah & taskId (dari apotekHdr — apoteker)
            $row->telaah_resep_done = isset($apotekHdr['telaahResep']['penanggungJawab']) && !empty($apotekHdr['telaahResep']['penanggungJawab']);
            $row->telaah_obat_done = isset($apotekHdr['telaahObat']['penanggungJawab']) && !empty($apotekHdr['telaahObat']['penanggungJawab']);
            $row->telaah_resep_ttd = $apotekHdr['telaahResep']['penanggungJawab']['userLog'] ?? null;
            $row->telaah_obat_ttd = $apotekHdr['telaahObat']['penanggungJawab']['userLog'] ?? null;

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

    public function cetakEresep(int $slsNo): void
    {
        $this->dispatch('cetak-eresep-ri.open', slsNo: $slsNo);
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Antrian Apotek RI
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-400">
                Telaah Resep &amp; Pelayanan Kefarmasian Rawat Inap
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('antrian-apotek-ri-toolbar', []) }}">

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
                                placeholder="Cari No SLS / No RM / Nama Pasien / Dokter..." />
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
                            </x-select-input>
                        </div>
                    </div>
                </div>

                <div class="mt-1 text-xs text-gray-500">
                    Data Terakhir: {{ now()->format('d/m/Y H:i:s') }}
                </div>
            </div>

            {{-- AUTO REFRESH WRAPPER --}}
            @if ($autoRefresh === 'Ya')
                <div wire:poll.20s class="mt-4">
                @else
                    <div class="mt-4">
            @endif

            {{-- TABLE --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-360px)] rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-xs font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-4 py-3">Antrian &amp; Pasien</th>
                                <th class="px-4 py-3">Resep / Dokter</th>
                                <th class="px-4 py-3">Status Layanan</th>
                                <th class="px-4 py-3">Waktu Apotek</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr
                                    class="transition bg-white dark:bg-gray-900 hover:shadow-md hover:bg-blue-50 dark:hover:bg-gray-800 rounded-xl
                                    {{ $row->no_antrian > 0 ? 'border-l-4 border-l-blue-500' : '' }}">

                                    {{-- ANTRIAN & PASIEN --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex flex-col items-center justify-center w-16 h-16 rounded-xl
                                                {{ $row->no_antrian > 0
                                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                    : 'bg-gray-100 text-gray-400 dark:bg-gray-700' }}">
                                                <span class="text-2xl font-bold leading-none">
                                                    {{ $row->no_antrian ?: '-' }}
                                                </span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    {{ $row->no_antrian > 0 ? 'apotek' : 'belum' }}
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
                                                <span class="text-gray-400">·</span>
                                                <span class="text-xs text-gray-600 dark:text-gray-400">Resep
                                                    #{{ $row->resep_no }}</span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <x-badge :variant="$row->klaim_variant">
                                            {{ $row->klaim_label }}
                                        </x-badge>
                                        <x-badge :variant="$row->ri_status_variant">
                                            {{ $row->ri_status_text }}
                                        </x-badge>
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            No RI: {{ $row->rihdr_no }}
                                        </div>
                                        @if ($row->vno_sep)
                                            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row->vno_sep }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->sls_date_display }} | Shift {{ $row->shift ?? '-' }}
                                            &bull; {{ $row->item_count }} obat
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

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
                                                    class="w-2 h-2 rounded-full {{ $row->task_id6 ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Masuk Apotek:
                                                    <span
                                                        class="font-medium">{{ $row->task_id6 ?? ($row->waktu_masuk ?? '—') }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
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

                                            {{-- Masuk / Keluar Apotek (TaskID 6/7 components — pola UGD) --}}
                                            <div class="flex space-x-1">
                                                <livewire:pages::transaksi.apotek.task-id-pelayanan.task-id-6
                                                    :slsNo="$row->sls_no"
                                                    wire:key="taskid6-{{ $row->sls_no }}" />
                                                <livewire:pages::transaksi.apotek.task-id-pelayanan.task-id-7
                                                    :slsNo="$row->sls_no"
                                                    wire:key="taskid7-{{ $row->sls_no }}" />
                                            </div>

                                            {{-- Telaah Resep & Obat --}}
                                            @if ($row->telaah_resep_done && $row->telaah_obat_done)
                                                <x-success-button wire:click="openTelaah({{ $row->sls_no }})"
                                                    class="text-xs whitespace-nowrap justify-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Telaah Resep &amp; Obat ✓
                                                </x-success-button>
                                            @else
                                                <x-secondary-button wire:click="openTelaah({{ $row->sls_no }})"
                                                    class="text-xs whitespace-nowrap justify-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                    </svg>
                                                    Telaah Resep &amp; Obat
                                                </x-secondary-button>
                                            @endif

                                            {{-- Administrasi (Obat + Kasir) — pola purple ala UGD --}}
                                            @hasanyrole('Apoteker|Admin|Tu')
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
                                                        class="text-xs whitespace-nowrap justify-center !bg-purple-50 hover:!bg-purple-100 dark:!bg-purple-900/20">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                        Administrasi
                                                    </x-secondary-button>
                                                @endif
                                            @endhasanyrole

                                            {{-- Cetak E-Resep — pola UGD --}}
                                            <x-info-button wire:click="cetakEresep({{ $row->sls_no }})"
                                                wire:loading.attr="disabled" wire:target="cetakEresep"
                                                class="text-xs whitespace-nowrap justify-center">
                                                <span wire:loading.remove wire:target="cetakEresep" class="flex items-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                    Cetak E-Resep
                                                </span>
                                                <span wire:loading wire:target="cetakEresep" class="flex items-center gap-1">
                                                    <x-loading /> Menyiapkan...
                                                </span>
                                            </x-info-button>

                                        </div>
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
                                            <span>Tidak ada antrian apotek RI</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        {{-- Child action components --}}
        <livewire:pages::transaksi.apotek.antrian-apotek-ri.antrian-apotek-ri-actions
            wire:key="antrian-apotek-ri-actions" />

        <livewire:pages::transaksi.apotek.administrasi-apotek-ri.administrasi-apotek-ri
            wire:key="administrasi-apotek-ri-modal" />

        {{-- PDF dispatcher (listen 'cetak-kwitansi-ri-obat.open') --}}
        <livewire:pages::components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-obat
            wire:key="cetak-kwitansi-ri-obat" />

        {{-- PDF dispatcher etiket obat RI (listen 'cetak-etiket-obat-ri.open') --}}
        <livewire:pages::components.rekam-medis.r-i.etiket-obat.cetak-etiket-obat
            wire:key="cetak-etiket-obat-ri" />

        {{-- PDF dispatcher cetak e-resep RI (listen 'cetak-eresep-ri.open') --}}
        <livewire:pages::components.rekam-medis.r-i.cetak-eresep.cetak-eresep
            wire:key="cetak-eresep-ri" />

    </div>
</div>
