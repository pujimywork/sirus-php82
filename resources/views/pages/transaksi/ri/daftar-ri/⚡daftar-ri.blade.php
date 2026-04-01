<?php
// resources/views/pages/transaksi/ri/daftar-ri/daftar-ri.blade.php

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
    protected array $renderAreas = ['daftar-ri-toolbar'];

    /* -------------------------
     | Filter & Pagination
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'I';
    public string $filterDokter = '';
    public string $filterBangsal = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ri-toolbar');
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ri-toolbar');
    }
    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ri-toolbar');
    }
    public function updatedFilterBangsal(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ri-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ri-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter', 'filterBangsal']);
        $this->filterStatus = 'I';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-ri-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('daftar-ri.openCreate');
    }

    public function openEdit(string $riHdrNo): void
    {
        $this->dispatch('daftar-ri.openEdit', riHdrNo: $riHdrNo);
    }

    public function openRekamMedis(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.rekam-medis.open', riHdrNo: $riHdrNo);
    }

    public function openModulDokumen(int $riHdrNo): void
    {
        $this->dispatch('emr-ri.modul-dokumen.open', riHdrNo: $riHdrNo);
    }

    public function openAdministrasiPasien(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $riHdrNo);
    }

    public function openPindahKamar(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.pindah-kamar.open', riHdrNo: $riHdrNo);
    }

    public function requestDelete(string $riHdrNo): void
    {
        $this->dispatch('toast', type: 'warning', message: 'Modul RI - Fitur Hapus dalam Pengembangan');
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-ri-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Helpers
     * ------------------------- */
    private function isDokterOrPerawat(): bool
    {
        return auth()
            ->user()
            ->hasAnyRole(['Dokter', 'Perawat']);
    }

    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
        } catch (\Exception) {
            $d = now()->startOfDay();
        }
        return [$d, (clone $d)->endOfDay()];
    }

    /* -------------------------
     | Computed: baseQuery
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $statusColumn = $this->isDokterOrPerawat() ? DB::raw("NVL(h.erm_status,'A')") : DB::raw("NVL(h.ri_status,'I')");

        $query = DB::table('rstxn_rihdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'h.bangsal_id')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->select([
                'h.rihdr_no',
                DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date_display"),
                DB::raw("to_char(h.entry_date,'yyyymmddhh24miss') as entry_date_sort"),
                'h.reg_no',
                'p.reg_name',
                'p.sex',
                'p.address',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                DB::raw("
                    CASE
                        WHEN p.birth_date IS NOT NULL THEN
                            trunc(months_between(sysdate, p.birth_date) / 12) || ' Thn, ' ||
                            trunc(mod(months_between(sysdate, p.birth_date), 12)) || ' Bln, ' ||
                            trunc(sysdate - add_months(p.birth_date, trunc(months_between(sysdate, p.birth_date)))) || ' Hr'
                        ELSE '0 Thn, 0 Bln, 0 Hr'
                    END AS thn_umur
                "),
                'h.no_antrian',
                'h.dr_id',
                'd.dr_name',
                'h.klaim_id',
                'k.klaim_desc',
                'k.klaim_status',
                'h.shift',
                'h.ri_status',
                'h.erm_status',
                'h.vno_sep',
                'h.entry_id',
                'h.bangsal_id',
                'b.bangsal_name',
                'h.room_id',
                'r.room_name',
                'h.bed_no',
                DB::raw("(select count(*) from lbtxn_checkuphdrs where status_rjri='RI' and checkup_status!='B' and ref_no = h.rihdr_no) as lab_status"),
                DB::raw('(select count(*) from rstxn_riradiologs where rihdr_no = h.rihdr_no) as rad_status'),
                'h.datadaftarri_json',
            ])
            ->whereBetween('h.entry_date', [$start, $end])
            ->orderBy('entry_date_sort', 'desc')
            ->orderBy('b.bangsal_name', 'asc')
            ->orderBy('h.bed_no', 'asc');

        if ($this->filterStatus !== '') {
            $query->where($statusColumn, $this->filterStatus);
        }

        if ($this->filterDokter !== '') {
            // Filter dokter via JSON levelingDokter — pakai whereIn dari collection
            $ids = DB::table('rstxn_rihdrs as hh')
                ->select('hh.rihdr_no', 'hh.datadaftarri_json')
                ->whereBetween('hh.entry_date', [$start, $end])
                ->get()
                ->filter(function ($item) {
                    $json = json_decode($item->datadaftarri_json ?? '{}', true) ?? [];
                    $levelingDokter = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
                    foreach ($levelingDokter as $ld) {
                        if (($ld['drId'] ?? '') === $this->filterDokter) {
                            return true;
                        }
                    }
                    return false;
                })
                ->pluck('rihdr_no')
                ->toArray();

            // Gabung: dr_id langsung atau via leveling
            $query->where(function ($q) use ($ids) {
                $q->where('h.dr_id', $this->filterDokter)->orWhereIn('h.rihdr_no', $ids);
            });
        }

        if ($this->filterBangsal !== '') {
            $query->where('h.bangsal_id', $this->filterBangsal);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rihdr_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.vno_sep)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(d.dr_name)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    /* -------------------------
     | Computed: rows (dengan transform JSON)
     * ------------------------- */
    #[Computed]
    public function rows()
    {
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarri_json ?? '{}', true) ?? [];

            /* EMR progress RI */
            $fields = ['pengkajianAwal', 'anamnesa', 'pemeriksaan', 'diagnosis', 'perencanaan', 'asuhan'];
            $filled = count(array_filter($fields, fn($f) => isset($json[$f])));
            $row->emr_percent = round(($filled / 6) * 100);

            /* E-Resep */
            $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;

            /* Lab & Radiologi */
            $row->lab_status = (int) ($row->lab_status ?? 0);
            $row->rad_status = (int) ($row->rad_status ?? 0);

            /* SEP */
            $row->no_sep = $json['sep']['noSep'] ?? ($row->vno_sep ?? null);

            /* SPRI */
            $row->no_spri = $json['spri']['noSPRIBPJS'] ?? null;

            /* Leveling Dokter */
            $levelingDokter = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
            $row->leveling_dokter_list = $levelingDokter;
            $row->leveling_dokter = collect($levelingDokter)->map(fn($ld) => ($ld['drDesc'] ?? '-') . ' (' . ($ld['levelingDesc'] ?? '-') . ')')->implode(', ');

            /* Diagnosis */
            $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode(' | ', array_column($json['diagnosis'], 'icdX')) : '-';
            $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';

            /* No Referensi */
            $row->no_referensi = $json['noReferensi'] ?? null;

            /* Tindak Lanjut */
            $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
            $row->tindak_lanjut_detail = $json['perencanaan']['tindakLanjut'] ?? null;

            /* Administrasi */
            $row->admin_user = isset($json['AdministrasiRI']) ? $json['AdministrasiRI']['userLog'] ?? '✔' : '-';

            /* Task ID */
            $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
            $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
            $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;

            /* Validasi JSON */
            $row->rihdr_no_json = $json['riHdrNo'] ?? '-';
            $row->is_json_valid = (string) $row->rihdr_no === (string) $row->rihdr_no_json;
            $row->bg_check_json = $row->is_json_valid ? 'bg-green-100' : 'bg-red-100';

            /* Umur (gunakan oracle computed atau fallback Carbon) */
            $row->umur_format = $row->thn_umur ?? '-';
            if (empty($row->umur_format) || $row->umur_format === '-') {
                if (!empty($row->birth_date)) {
                    try {
                        $lahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                        $diff = $lahir->diff(now());
                        $row->umur_format = "{$diff->y} Thn, {$diff->m} Bln, {$diff->d} Hr";
                    } catch (\Exception) {
                    }
                }
            }

            /* Status text berdasarkan role */
            if ($this->isDokterOrPerawat()) {
                $statusMap = ['A' => 'Belum Dilayani', 'L' => 'Selesai'];
                $statusVariant = ['A' => 'warning', 'L' => 'success'];
                $row->status_text = $statusMap[$row->erm_status] ?? 'Pelayanan';
                $row->status_variant = $statusVariant[$row->erm_status] ?? 'gray';
            } else {
                $statusMap = ['I' => 'Dirawat', 'L' => 'Pulang', 'P' => 'Pindah Kamar'];
                $statusVariant = ['I' => 'brand', 'L' => 'success', 'P' => 'warning'];
                $row->status_text = $statusMap[$row->ri_status] ?? 'RI';
                $row->status_variant = $statusVariant[$row->ri_status] ?? 'gray';
            }

            /* Klaim badge color */
            $row->klaim_badge_variant = match ($row->klaim_id ?? '') {
                'UM' => 'success',
                'JM' => 'brand',
                'KR' => 'warning',
                default => 'danger',
            };

            return $row;
        });

        return $paginator;
    }

    /* -------------------------
     | Filter master data
     * ------------------------- */
    #[Computed]
    public function dokterList()
    {
        return DB::table('rstxn_rihdrs as h')->select('h.dr_id', DB::raw('MAX(d.dr_name) as dr_name'), DB::raw('COUNT(DISTINCT h.rihdr_no) as total_pasien'))->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')->where(DB::raw("to_char(h.entry_date,'dd/mm/yyyy')"), '=', $this->filterTanggal)->groupBy('h.dr_id')->orderBy('dr_name')->get();
    }

    #[Computed]
    public function bangsalList()
    {
        return DB::table('rsmst_bangsals')->select('bangsal_id', 'bangsal_name')->orderBy('bangsal_name')->get();
    }

    public function cetakEtiket(string $regNo): void
    {
        $this->dispatch('cetak-etiket.open', regNo: $regNo);
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Daftar Rawat Inap
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-400">
                Kelola pendaftaran dan pelayanan pasien Rawat Inap
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('daftar-ri-toolbar', []) }}">

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
                                placeholder="Cari No RI / No RM / Nama Pasien / No SEP / Dokter..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL MASUK --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tanggal Masuk" />
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

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-40">
                            <option value="">Semua</option>
                            @if (auth()->user()->hasAnyRole(['Dokter', 'Perawat']))
                                <option value="A">Belum Dilayani</option>
                                <option value="L">Selesai</option>
                            @else
                                <option value="I">Dirawat</option>
                                <option value="L">Pulang</option>
                                <option value="P">Pindah Kamar</option>
                            @endif
                        </x-select-input>
                    </div>

                    {{-- FILTER BANGSAL --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bangsal" />
                        <x-select-input wire:model.live="filterBangsal" class="w-full mt-1 sm:w-44">
                            <option value="">Semua Bangsal</option>
                            @foreach ($this->bangsalList as $bangsal)
                                <option value="{{ $bangsal->bangsal_id }}">{{ $bangsal->bangsal_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
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
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        @hasanyrole('Mr|Admin')
                            <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Daftar Baru
                            </x-primary-button>
                        @endhasanyrole
                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Kamar / Dokter</th>
                                <th class="px-6 py-3">Status Layanan</th>
                                <th class="px-6 py-3">Tindak Lanjut</th>
                                <th class="px-6 py-3 text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr
                                    class="transition bg-white dark:bg-gray-900
                                           hover:shadow-lg hover:bg-blue-50 dark:hover:bg-gray-800 rounded-2xl">

                                    {{-- ========================
                                         KOLOM: PASIEN
                                         ======================== --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="text-5xl font-bold text-gray-700 dark:text-gray-200 tabular-nums">
                                                {{ $row->no_antrian ?? '-' }}
                                            </div>
                                            <div class="space-y-1 min-w-0">
                                                <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $row->reg_no ?? '-' }}
                                                </div>
                                                <div class="text-lg font-semibold text-brand dark:text-white">
                                                    {{ $row->reg_name ?? '-' }}
                                                    /
                                                    ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>
                                                <div class="text-base text-gray-700 dark:text-gray-400">
                                                    {{ $row->birth_date ?? '-' }}
                                                    @if ($row->umur_format && $row->umur_format !== '-')
                                                        <span class="text-gray-500">({{ $row->umur_format }})</span>
                                                    @endif
                                                </div>
                                                <div class="text-base text-gray-600 dark:text-gray-400">
                                                    {{ $row->address ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- ========================
                                         KOLOM: KAMAR / DOKTER
                                         ======================== --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        {{-- Bangsal & Bed --}}
                                        <div class="font-semibold text-blue-600 dark:text-blue-400">
                                            {{ $row->bangsal_name ?? '-' }}
                                        </div>
                                        <div class="text-base text-gray-800 dark:text-gray-200">
                                            {{ $row->room_name ?? '-' }}
                                            / Bed: <span class="font-semibold">{{ $row->bed_no ?? '-' }}</span>
                                        </div>

                                        {{-- DPJP Utama --}}
                                        <div class="text-base text-gray-600 dark:text-gray-400">
                                            Dr. {{ $row->dr_name ?? '-' }}
                                        </div>

                                        {{-- Leveling Dokter --}}
                                        @if (!empty($row->leveling_dokter_list))
                                            <div class="space-y-0.5">
                                                @foreach ($row->leveling_dokter_list as $ld)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $ld['drDesc'] ?? '-' }}
                                                        <span
                                                            class="text-gray-400">({{ $ld['levelingDesc'] ?? '-' }})</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Klaim --}}
                                        <x-badge :variant="$row->klaim_badge_variant">
                                            {{ $row->klaim_desc ?? '-' }}
                                        </x-badge>

                                        {{-- SEP --}}
                                        @if ($row->no_sep)
                                            <div class="font-mono text-xs text-gray-600 dark:text-gray-300">
                                                SEP: {{ $row->no_sep }}
                                            </div>
                                        @endif

                                        {{-- SPRI --}}
                                        @if ($row->no_spri)
                                            <div class="font-mono text-xs text-purple-600 dark:text-purple-400">
                                                SPRI: {{ $row->no_spri }}
                                            </div>
                                        @endif

                                        {{-- Lab & Rad --}}
                                        @if ($row->lab_status > 0 || $row->rad_status > 0)
                                            <div class="flex gap-2">
                                                @if ($row->lab_status > 0)
                                                    <x-badge variant="brand">Lab: {{ $row->lab_status }}</x-badge>
                                                @endif
                                                @if ($row->rad_status > 0)
                                                    <x-badge variant="warning">Rad: {{ $row->rad_status }}</x-badge>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ========================
                                         KOLOM: STATUS LAYANAN
                                         ======================== --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            {{ $row->entry_date_display ?? '-' }} | Shift: {{ $row->shift ?? '-' }}
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        {{-- EMR progress --}}
                                        <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                            <div class="h-1.5 rounded-full transition-all duration-500
                                                {{ $row->emr_percent >= 80 ? 'bg-emerald-500/80' : ($row->emr_percent >= 50 ? 'bg-amber-400/80' : 'bg-rose-400/80') }}"
                                                style="width: {{ $row->emr_percent ?? 0 }}%">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="text-base text-gray-700 dark:text-gray-400">
                                                EMR: {{ $row->emr_percent ?? 0 }}%
                                            </div>
                                            <div class="text-base text-gray-700 dark:text-gray-400">
                                                E-Resep: {{ $row->eresep_percent ?? 0 }}%
                                            </div>
                                        </div>

                                        @if ($row->diagnosis !== '-')
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }}
                                                @if ($row->diagnosis_free_text !== '-')
                                                    / {{ $row->diagnosis_free_text }}
                                                @endif
                                            </div>
                                        @endif

                                        @if ($row->no_referensi)
                                            <div class="text-base text-gray-700 dark:text-gray-400">
                                                No Ref: {{ $row->no_referensi }}
                                            </div>
                                        @endif

                                        <div class="text-xs p-1 rounded {{ $row->bg_check_json }} dark:bg-opacity-20">
                                            <span class="font-semibold">Validasi JSON:</span>
                                            RI No: {{ $row->rihdr_no }} / {{ $row->rihdr_no_json }}
                                            @if (!$row->is_json_valid)
                                                <span class="text-red-600 dark:text-red-400">(Tidak Sinkron)</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- ========================
                                         KOLOM: TINDAK LANJUT
                                         ======================== --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Administrasi:
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ $row->admin_user ?? '-' }}
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 space-y-1">
                                            @if ($row->task_id3)
                                                <x-badge variant="success">TaskId3 {{ $row->task_id3 }}</x-badge>
                                            @endif
                                            @if ($row->task_id4)
                                                <x-badge variant="brand">TaskId4 {{ $row->task_id4 }}</x-badge>
                                            @endif
                                            @if ($row->task_id5)
                                                <x-badge variant="warning">TaskId5 {{ $row->task_id5 }}</x-badge>
                                            @endif
                                        </div>

                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            Rencana: {{ $row->tindak_lanjut ?? '-' }}
                                        </div>

                                        @if ($row->tindak_lanjut_detail && ($row->tindak_lanjut_detail['tindakLanjut'] ?? null))
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Dokter: {{ $row->tindak_lanjut_detail['drPemeriksa'] ?? '-' }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ========================
                                         KOLOM: ACTION
                                         ======================== --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="flex items-center gap-3">

                                            {{-- Cetak Etiket --}}
                                            <x-secondary-button wire:click="cetakEtiket('{{ $row->reg_no }}')"
                                                wire:loading.attr="disabled" wire:target="cetakEtiket">
                                                <span wire:loading.remove wire:target="cetakEtiket"
                                                    class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                    Etiket
                                                </span>
                                                <span wire:loading wire:target="cetakEtiket"
                                                    class="flex items-center gap-1">
                                                    <x-loading /> Mencetak...
                                                </span>
                                            </x-secondary-button>

                                            {{-- Dropdown Aksi --}}
                                            <x-dropdown position="left" width="w-[440px]">
                                                <x-slot name="trigger">
                                                    <x-secondary-button type="button" class="p-2">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path
                                                                d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </x-secondary-button>
                                                </x-slot>

                                                <x-slot name="content">
                                                    <div class="p-2 space-y-2">
                                                        <div class="grid grid-cols-2 gap-1">

                                                            {{-- Ubah Pendaftaran — Mr | Admin --}}
                                                            @hasanyrole('Mr|Admin')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openEdit('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                                                        </svg>
                                                                        <span>
                                                                            Pendaftaran Ubah<br>
                                                                            <span
                                                                                class="font-semibold">{{ $row->reg_name }}</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Rekam Medis — Perawat | Dokter | Admin --}}
                                                            @hasanyrole('Perawat|Dokter|Admin')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openRekamMedis('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-green-50 hover:bg-green-100 dark:bg-green-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                                        </svg>
                                                                        <span>Rekam Medis RI<br>
                                                                            <span
                                                                                class="font-semibold">{{ $row->reg_name }}</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Modul Dokumen — Admin | Perawat | Casemix --}}
                                                            @hasanyrole('Admin|Perawat|Casemix')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openModulDokumen('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                        <span>Modul Dokumen<br>
                                                                            <span class="font-semibold">General Consent
                                                                                dll</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Administrasi — Admin | Perawat | Casemix --}}
                                                            @hasanyrole('Admin|Perawat|Casemix')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openAdministrasiPasien('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                                        </svg>
                                                                        <span>Administrasi<br>
                                                                            <span
                                                                                class="font-semibold">{{ $row->reg_name }}</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Pindah Kamar — Mr | Admin --}}
                                                            @hasanyrole('Mr|Admin')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openPindahKamar('{{ $row->rihdr_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                                        </svg>
                                                                        <span>Pindah Kamar<br>
                                                                            <span class="font-semibold">
                                                                                {{ $row->room_name ?? '-' }} /
                                                                                {{ $row->bed_no ?? '-' }}
                                                                            </span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                        </div>

                                                        {{-- DIVIDER --}}
                                                        <div
                                                            class="my-1 border-t border-gray-200 dark:border-gray-700">
                                                        </div>

                                                        {{-- Hapus — Admin only --}}
                                                        @role('Admin')
                                                            <x-dropdown-link href="#"
                                                                wire:click.prevent="requestDelete('{{ $row->rihdr_no }}')"
                                                                class="w-full px-3 py-2 text-sm font-semibold text-red-600 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400">
                                                                <div class="flex items-center justify-center gap-2">
                                                                    <svg class="w-5 h-5" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24"
                                                                        stroke-width="2">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round"
                                                                            d="M6 7h12M9 7V5a3 3 0 016 0v2m-9 0l1 12h8l1-12" />
                                                                    </svg>
                                                                    <span>Hapus</span>
                                                                </div>
                                                            </x-dropdown-link>
                                                        @endrole

                                                    </div>
                                                </x-slot>
                                            </x-dropdown>

                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-gray-700 dark:text-gray-400">
                                        Belum ada data Rawat Inap
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl
                            dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>

            </div>

            {{-- Child components --}}
            <livewire:pages::transaksi.ri.daftar-ri.daftar-ri-actions wire:key="daftar-ri-actions" />
            <livewire:pages::transaksi.ri.emr-ri.erm-ri wire:key="emr-ri-actions" />
            <livewire:pages::transaksi.ri.administrasi-ri.administrasi-ri wire:key="administrasi-ri-actions" />
            <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.modul-dokumen-ri wire:key="modul-dokumen-ri" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-ri" />

        </div>
    </div>
</div>
