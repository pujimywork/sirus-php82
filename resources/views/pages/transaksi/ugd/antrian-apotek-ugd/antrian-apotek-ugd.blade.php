<?php
// resources/views/pages/transaksi/ugd/antrian-apotek-ugd/antrian-apotek-ugd.blade.php

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

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-toolbar');
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
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('antrian-apotek-toolbar');
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('antrian-apotek-toolbar');
        $this->resetPage();
    }

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
        $this->dispatch('emr-ugd.administrasi.open', rjNo: $rjNo);
    }

    #[On('refresh-after-apotek.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('antrian-apotek-toolbar');
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

        $query = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.vno_sep', 'h.nobooking', 'h.datadaftarugd_json', 'k.klaim_desc', 'k.klaim_status', 'h.waktu_masuk_apt', 'h.waktu_selesai_pelayanan'])
            ->whereBetween('h.rj_date', [$start, $end])
            ->where(DB::raw("NVL(h.rj_status,'A')"), $this->filterStatus)
            ->where('h.klaim_id', '!=', 'KR');

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

        $sorted = $all
            ->sortBy(function ($row) {
                $json = json_decode($row->datadaftarugd_json ?? '{}', true);
                $noAntrian = $json['noAntrianApotek']['noAntrian'] ?? 0;
                $hasAntrian = $noAntrian > 0 ? 0 : 1;
                return [$hasAntrian, $noAntrian];
            })
            ->values();

        $page = $this->getPage();
        $perPage = $this->itemsPerPage;
        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, $perPage, $page, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarugd_json ?? '{}', true);

            $row->no_antrian_apotek = $json['noAntrianApotek']['noAntrian'] ?? 0;
            $row->jenis_resep = $json['noAntrianApotek']['jenisResep'] ?? '-';
            $row->has_eresep = isset($json['eresep']) ? 1 : 0;
            $row->has_eresep_racikan = isset($json['eresepRacikan']) ? 1 : 0;
            $row->eresep_count = $row->has_eresep + $row->has_eresep_racikan;

            $row->telaah_resep_done = isset($json['telaahResep']['penanggungJawab']) && !empty($json['telaahResep']['penanggungJawab']);
            $row->telaah_obat_done = isset($json['telaahObat']['penanggungJawab']) && !empty($json['telaahObat']['penanggungJawab']);
            $row->telaah_resep_ttd = $json['telaahResep']['penanggungJawab']['userLog'] ?? null;
            $row->telaah_obat_ttd = $json['telaahObat']['penanggungJawab']['userLog'] ?? null;

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

            $statusMap = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Rujuk'];
            $statusVariant = ['A' => 'warning', 'L' => 'success', 'F' => 'danger', 'I' => 'brand'];
            $row->status_text = $statusMap[$row->rj_status] ?? '-';
            $row->status_variant = $statusVariant[$row->rj_status] ?? 'gray';

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

    public function cetakEresep(string $rjNo): void
    {
        $this->dispatch('cetak-eresep-ugd.open', rjNo: $rjNo);
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Antrian Apotek UGD
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-400">
                Telaah Resep & Pelayanan Kefarmasian Unit Gawat Darurat
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3"
                    wire:key="{{ $this->renderKey('antrian-apotek-toolbar', []) }}">

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
                                placeholder="Cari No UGD / No RM / Nama Pasien / Dokter..." />
                        </div>
                    </div>

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

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Rujuk</option>
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
                                <th class="px-4 py-3">Antrian & Pasien</th>
                                <th class="px-4 py-3">Dokter</th>
                                <th class="px-4 py-3">Status Layanan</th>
                                <th class="px-4 py-3">Waktu Apotek</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr
                                    class="transition bg-white dark:bg-gray-900 hover:shadow-md hover:bg-green-50 dark:hover:bg-gray-800 rounded-xl
                                    {{ $row->no_antrian_apotek > 0 ? 'border-l-4 border-l-emerald-500' : '' }}">

                                    {{-- ANTRIAN & PASIEN --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start gap-3">
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
                                                    {{ $row->reg_no }}</div>
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

                                    {{-- DOKTER --}}
                                    <td class="px-4 py-4 space-y-1 align-top">
                                        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <x-badge :variant="$row->klaim_variant">{{ $row->klaim_label }}</x-badge>
                                        @if ($row->vno_sep)
                                            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row->vno_sep }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            No UGD: {{ $row->rj_no }}
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-4 py-4 space-y-2 align-top">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->rj_date_display }} | Shift {{ $row->shift ?? '-' }}
                                        </div>
                                        <x-badge :variant="$row->status_variant">{{ $row->status_text }}</x-badge>
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
                                        @if ($row->status_resep)
                                            <x-badge :variant="$row->status_resep_color">{{ $row->status_resep_label }}</x-badge>
                                        @endif
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
                                                    Masuk Apotek: <span
                                                        class="font-medium">{{ $row->task_id6 ?? '—' }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Keluar Apotek: <span
                                                        class="font-medium">{{ $row->task_id7 ?? '—' }}</span>
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
                                        <div class="text-xs text-gray-500">
                                            Booking: {{ $row->nobooking ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-col gap-2">

                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex space-x-1">
                                                    <livewire:pages::transaksi.ugd.task-id-pelayanan.task-id-6
                                                        :rjNo="$row->rj_no"
                                                        wire:key="'taskid6--'.{{ $row->rj_no }}" />
                                                    <livewire:pages::transaksi.ugd.task-id-pelayanan.task-id-7
                                                        :rjNo="$row->rj_no"
                                                        wire:key="'taskid7--'.{{ $row->rj_no }}" />
                                                </div>
                                                @role('Admin')
                                                    <livewire:pages::transaksi.ugd.task-id-pelayanan.task-id-99
                                                        :rjNo="$row->rj_no" wire:key="'taskid99--'.{{ $row->rj_no }}" />
                                                @endrole
                                            </div>

                                            {{-- Telaah Resep & Obat (unified) --}}
                                            @if ($row->telaah_resep_done && $row->telaah_obat_done)
                                                <x-success-button
                                                    wire:click="openTelaah({{ $row->has_eresep }}, '{{ $row->rj_no }}')"
                                                    class="text-xs whitespace-nowrap justify-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Telaah Resep &amp; Obat ✓
                                                </x-success-button>
                                            @else
                                                <x-secondary-button
                                                    wire:click="openTelaah({{ $row->has_eresep }}, '{{ $row->rj_no }}')"
                                                    class="text-xs whitespace-nowrap justify-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                    </svg>
                                                    Telaah Resep &amp; Obat
                                                </x-secondary-button>
                                            @endif

                                            {{-- Administrasi — Admin | Perawat | Casemix --}}
                                            @hasanyrole('Admin|Perawat|Casemix')
                                                <x-secondary-button
                                                    wire:click="openAdministrasiPasien('{{ $row->rj_no }}')"
                                                    class="text-xs whitespace-nowrap justify-center !bg-purple-50 hover:!bg-purple-100 dark:!bg-purple-900/20">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                    </svg>
                                                    Administrasi
                                                </x-secondary-button>
                                            @endhasanyrole

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
                                            <span>Tidak ada data antrian apotek UGD</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <livewire:pages::components.rekam-medis.u-g-d.cetak-eresep.cetak-eresep
                        wire:key="cetak-eresep-ugd" />
                </div>

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>{{-- end auto-refresh wrapper --}}

        <livewire:pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd-actions
            wire:key="antrian-apotek-ugd-actions" />

        <livewire:pages::transaksi.ugd.administrasi-ugd.administrasi-ugd wire:key="administrasi-ugd-actions" />

    </div>
</div>
</div>
