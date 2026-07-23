<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\OracleLob;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ugd\EmrCompletenessUGDTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrCompletenessUGDTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-ugd-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A';
    public string $filterDokter = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        // Tidak incrementVersion — wire:key remount toolbar di tengah ketik bikin
        // search input kehilangan focus, backspace berikutnya memicu browser back.
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-toolbar');
    }
    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-ugd-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('daftar-ugd.create.open');
    }

    public function openEdit(string $rjNo): void
    {
        $this->dispatch('daftar-ugd.edit.open', rjNo: $rjNo);
    }

    public function openBerkasBpjs(int $rjNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rjNo);
    }


    public function requestDelete(string $rjNo): void
    {
        $this->dispatch('toast', type: 'warning', message: 'Modul UGD - Dalam Pengembangan');
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-ugd.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-ugd-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Helper role
     * ------------------------- */
    private function isDokterOrPerawat(): bool
    {
        return auth()
            ->user()
            ->hasAnyRole(['Dokter', 'Perawat']);
    }

    /* -------------------------
     | Date range helper
     * ------------------------- */
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

        $statusColumn = $this->isDokterOrPerawat() ? DB::raw("NVL(h.erm_status, 'A')") : DB::raw("NVL(h.rj_status, 'A')");

        $query = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_entryugds as e', 'e.entry_id', '=', 'h.entry_id')
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'k.klaim_desc', 'k.klaim_status', 'h.shift', 'h.rj_status', 'h.erm_status', 'h.vno_sep', 'h.entry_id', 'e.entry_desc', 'h.out_desc', 'h.status_lanjutan', 'h.death_on_igd_status', 'h.datadaftarugd_json'])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('d.dr_name', 'asc')
            ->orderBy('h.no_antrian', 'asc');

        if ($this->filterStatus !== '') {
            $query->where($statusColumn, $this->filterStatus);
        }

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rj_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.rj_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
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
            $jsonRaw = OracleLob::read($row->datadaftarugd_json ?? null, 'rstxn_ugdhdrs', 'rj_no', $row->rj_no, 'datadaftarugd_json');
            $json = json_decode($jsonRaw ?: '{}', true) ?? [];
            $row->berkas_uploaded = []; // seq_file berkas BPJS yang sudah di-upload

            /* EMR completeness — weighted S15/O20/A20/P20/N10/T15.
               Logic ada di EmrCompletenessUGDTrait. T = Triase/Screening, khusus UGD.
               Field "screening" (alergi, RPD) dianggap terisi kalau non-empty (termasuk
               "Tidak ada") — dokter wajib explicit isi negatif, jangan dibiarkan kosong. */
            $pct = $this->calculateEmrPercentUGD($json);
            $row->emr_percent = $pct['emr'];
            $row->emr_sections = $pct['sections']; // detail per-section untuk tooltip optional

            /* E-Resep */
            $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;

            /* Timestamp pelayanan UGD — sumber EMR (bukan taskId6/7 yang merupakan masuk/keluar apotek) */
            $row->waktu_datang = $json['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? null;
            $row->waktu_pemeriksaan = $json['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? null;
            $row->selesai_pemeriksaan = $json['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? null;
            $row->task_id99 = $json['taskIdPelayanan']['taskId99'] ?? null;

            /* SATUSEHAT — status kirim PER-RESOURCE (blok json 'satusehat').
               UGD 9 resource (tanpa Chief Complaint & Allergy). Nilai bisa scalar
               (encounterId/clinicalImpressionId) atau array (conditionIds/...). */
            $ss = $json['satusehat'] ?? [];
            $ssFilled = fn($v) => is_array($v) ? count($v) > 0 : !empty($v);
            $row->satusehat_items = [
                ['label' => 'Encounter',           'full' => 'Encounter (kunjungan)',            'sent' => $ssFilled($ss['encounterId'] ?? null)],
                ['label' => 'Condition',           'full' => 'Condition (diagnosa ICD-10)',      'sent' => $ssFilled($ss['conditionIds'] ?? null)],
                ['label' => 'Observation',         'full' => 'Observation (vital/lab)',          'sent' => $ssFilled($ss['observationIds'] ?? null)],
                ['label' => 'Procedure',           'full' => 'Procedure (tindakan ICD-9)',       'sent' => $ssFilled($ss['procedureIds'] ?? null)],
                ['label' => 'Medication Request',  'full' => 'Medication Request (resep)',       'sent' => $ssFilled($ss['medicationRequestIds'] ?? null)],
                ['label' => 'Medication Dispense', 'full' => 'Medication Dispense (obat pulang)', 'sent' => $ssFilled($ss['medicationDispenseIds'] ?? null)],
                ['label' => 'Penunjang Lab',       'full' => 'Penunjang Lab',                    'sent' => $ssFilled($ss['labServiceRequestIds'] ?? null) || $ssFilled($ss['labDiagnosticReportIds'] ?? null)],
                ['label' => 'Penunjang Radiologi', 'full' => 'Penunjang Radiologi',             'sent' => $ssFilled($ss['radServiceRequestIds'] ?? null) || $ssFilled($ss['radDiagnosticReportIds'] ?? null)],
                ['label' => 'Clinical Impression', 'full' => 'Clinical Impression',             'sent' => $ssFilled($ss['clinicalImpressionId'] ?? null)],
            ];

            /* Triase / Tingkat Kegawatan */
            $row->triase = $json['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? null;
            $row->triase_label = match ($row->triase) {
                'P1' => 'P1 — Kritis',
                'P2' => 'P2 — Urgent',
                'P3' => 'P3 — Minor',
                'P0' => 'P0 — Death',
                default => null,
            };
            $row->triase_class = match ($row->triase) {
                'P1' => 'bg-red-600 text-white',
                'P2' => 'bg-yellow-400 text-ink',
                'P3' => 'bg-green-600 text-white',
                'P0' => 'bg-gray-800 text-white',
                default => 'bg-gray-200 text-body',
            };
            $row->triase_border = match ($row->triase) {
                'P1' => 'border-red-500',
                'P2' => 'border-yellow-400',
                'P3' => 'border-green-500',
                'P0' => 'border-gray-700',
                default => '',
            };

            /* No Referensi */
            $row->no_referensi = $json['noReferensi'] ?? null;

            /* Diagnosis & Procedure */
            $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode('# ', array_column($json['diagnosis'], 'icdX')) : '-';
            $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';

            $row->procedure = isset($json['procedure']) && is_array($json['procedure']) ? implode('# ', array_column($json['procedure'], 'procedureId')) : '-';
            $row->procedure_free_text = $json['procedureFreeText'] ?? '-';

            /* Status Resep */
            $row->status_resep = $json['statusResep']['status'] ?? null;
            $row->status_resep_label = match ($row->status_resep) {
                'DITUNGGU' => 'Ditunggu',
                'DITINGGAL' => 'Ditinggal',
                default => '-',
            };
            $row->status_resep_color = match ($row->status_resep) {
                'DITUNGGU' => 'green',
                'DITINGGAL' => 'yellow',
                default => 'gray',
            };

            /* Administrasi */
            $row->admin_user = isset($json['AdministrasiUgd']) ? $json['AdministrasiUgd']['userLog'] ?? '✔' : '-';

            /* Tindak Lanjut */
            $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';

            /* Validasi JSON */
            $row->rj_no_json = $json['rjNo'] ?? '-';
            $row->is_json_valid = $row->rj_no == $row->rj_no_json;
            $row->bg_check_json = $row->is_json_valid ? 'bg-green-100' : 'bg-red-100';

            /* Status text berdasarkan role */
            // Batal di-detect dari Task ID 99 OR rj_status='F' (legacy mutasi langsung)
            if (!empty($row->task_id99) || $row->rj_status === 'F') {
                $row->status_text = 'Batal';
                $row->status_variant = 'danger';
            } elseif ($this->isDokterOrPerawat()) {
                $statusMap = ['A' => 'Proses Dilayani', 'L' => 'Selesai'];
                $statusVariant = ['A' => 'warning', 'L' => 'success'];
                $row->status_text = $statusMap[$row->erm_status] ?? 'Pelayanan';
                $row->status_variant = $statusVariant[$row->erm_status] ?? 'gray';
            } else {
                $statusMap = ['A' => 'Antrian', 'L' => 'Selesai', 'I' => 'Transfer Inap'];
                $statusVariant = ['A' => 'warning', 'L' => 'success', 'I' => 'brand'];
                $row->status_text = $statusMap[$row->rj_status] ?? 'Pelayanan';
                $row->status_variant = $statusVariant[$row->rj_status] ?? 'gray';
            }

            /* Death on IGD */
            $row->is_death = ($row->death_on_igd_status ?? 'N') === 'Y';

            return $row;
        });

        // Status berkas BPJS (semua slot) untuk badge.
        $rjNos = $paginator->getCollection()->pluck('rj_no')->filter()->values()->all();
        if (!empty($rjNos)) {
            $berkas = DB::table('rstxn_ugduploadbpjses')
                ->select('rj_no', 'seq_file')
                ->whereIn('rj_no', $rjNos)
                ->whereNotNull('uploadbpjs')
                ->get()
                ->groupBy('rj_no');

            $paginator->getCollection()->each(function ($row) use ($berkas) {
                $row->berkas_uploaded = collect($berkas[$row->rj_no] ?? [])->pluck('seq_file')->map(fn($seqFile) => (int) $seqFile)->all();
            });
        }

        return $paginator;
    }

    /* -------------------------
     | Master data filter dokter
     * ------------------------- */
    #[Computed]
    public function dokterList()
    {
        // NOTE: dokterList HANYA depend pada filterTanggal — "semua dokter yang
        // praktek pada tanggal tersebut". Filter lain (status, klaim, searchKeyword)
        // sengaja TIDAK dipakai supaya opsi dropdown stabil: user bisa pindah-pindah
        // status/klaim tanpa kehilangan dokter yang sudah dipilih, meskipun query
        // utama jadi kosong.
        return DB::table('rstxn_ugdhdrs')
            ->select(
                'rstxn_ugdhdrs.dr_id',
                DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'),
                DB::raw('COUNT(DISTINCT rstxn_ugdhdrs.rj_no) as total_pasien'),
            )
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_ugdhdrs.dr_id')
            ->where(DB::raw("to_char(rstxn_ugdhdrs.rj_date,'dd/mm/yyyy')"), '=', $this->filterTanggal)
            ->groupBy('rstxn_ugdhdrs.dr_id')
            ->orderBy('dr_name')
            ->get();
    }

    public function cetakEtiket(string $regNo): void
    {
        $this->dispatch('cetak-etiket.open', regNo: $regNo);
    }
};
?>

<div>
    <x-page-title title="Daftar UGD" subtitle="Kelola pendaftaran pasien Unit Gawat Darurat" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('daftar-ugd-toolbar', []) }}">

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
                                placeholder="Cari No RJ / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER TANGGAL --}}
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

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-40">
                            <option value="">Semua</option>
                            @if (auth()->user()->hasAnyRole(['Dokter', 'Perawat']))
                                <option value="A">Proses Dilayani</option>
                                <option value="L">Selesai</option>
                            @else
                                <option value="A">Antrian</option>
                                <option value="L">Selesai</option>
                                <option value="I">Transfer / Inap</option>
                            @endif
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
                        {{-- Tombol standar Refresh + Reset (komponen; tanpa label kolom) --}}
                        <x-toolbar-refresh-reset :label="null" />

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

                        {{-- Pendaftaran UGD — Mr, Admin, Supervisor Tu --}}
                        @hasanyrole(['Mr', 'Admin', 'Supervisor Tu'])
                            <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Pendaftaran UGD
                            </x-primary-button>
                        @endhasanyrole
                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div
                    class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-base -mt-3 border-separate border-spacing-y-3 table-fixed">

                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 w-[24%]">Pasien</th>
                                <th class="px-6 py-3 w-[20%]">Dokter / Klaim</th>
                                <th class="px-6 py-3 w-[16%]">Status Layanan</th>
                                <th class="px-6 py-3 w-[18%]">Tindak Lanjut</th>
                                <th class="px-6 py-3 w-[22%] text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="ugd-row-{{ $row->rj_no }}" x-data="{ expanded: false }"
                                    style="position: relative;"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           {{ $row->status_text === 'Batal'
                                               ? 'bg-error/5 dark:bg-red-900/10 hover:shadow-md hover:bg-error/10 dark:hover:bg-red-900/20 border-l-4 border-error'
                                               : ($row->erm_status === 'L'
                                                   ? 'bg-emerald-50 dark:bg-emerald-900/10 hover:shadow-md hover:bg-brand-green/10 dark:hover:bg-emerald-900/20 border-l-4 border-emerald-500'
                                                   : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-red-50 dark:hover:bg-gray-800 ' .
                                                       ($row->is_death
                                                           ? 'border-l-4 border-red-500'
                                                           : ($row->triase_border
                                                               ? 'border-l-4 ' . $row->triase_border
                                                               : ''))) }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-middle">
                                        {{-- Toggle Detail chevron — absolute, bottom-center row --}}
                                        <button type="button" x-on:click="expanded = !expanded"
                                            class="absolute z-10 inline-flex items-center justify-center w-7 h-7 text-muted transition bg-canvas border border-hairline rounded-full shadow-sm hover:text-brand-green hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-emerald-900/30 dark:hover:text-brand-lime"
                                            style="left: 50%; bottom: 4px; transform: translateX(-50%);"
                                            :title="expanded ? 'Sembunyikan detail' : 'Tampilkan detail'">
                                            <svg class="w-4 h-4 transition-transform"
                                                :class="expanded ? 'rotate-180' : ''" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>

                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex flex-col items-center justify-center w-16 h-16 rounded-xl bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">
                                                <span class="text-2xl font-bold leading-none">
                                                    {{ $row->no_antrian ?: '-' }}
                                                </span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    antrian
                                                </span>
                                            </div>
                                            <div class="space-y-0 leading-tight">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @if ($row->triase_label)
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-1 text-sm font-bold rounded-full shadow-sm {{ $row->triase_class }}">
                                                            {{ $row->triase_label }}
                                                        </span>
                                                    @endif
                                                    @if ($row->is_death)
                                                        <x-badge variant="danger">Meninggal di IGD</x-badge>
                                                    @endif
                                                </div>
                                                <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name"
                                                    :sex="$row->sex" :tglLahir="$row->birth_date"
                                                    :alamat="$row->address" :collapseUmur="true" />
                                            </div>
                                        </div>
                                    </td>

                                    {{-- DOKTER / KLAIM --}}
                                    <td class="px-6 py-6 space-y-0.5 align-middle">
                                        <div
                                            class="text-base font-semibold text-body dark:text-gray-300 leading-tight">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <div class="leading-tight">
                                            <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                        </div>
                                        <div class="text-xs text-muted dark:text-gray-400 leading-tight">
                                            Cara Masuk: {{ $row->entry_desc ?? '-' }}
                                        </div>
                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            <x-list.sep-spri :sep="$row->vno_sep" />
                                            <div class="text-xs text-muted dark:text-gray-500 leading-tight">
                                                {{ $row->rj_date_display ?? '-' }} | Shift : {{ $row->shift ?? '-' }}
                                            </div>
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-6 py-6 space-y-2 align-middle">
                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        {{-- EMR progress --}}
                                        <div class="w-full h-1.5 bg-surface-strong rounded-full dark:bg-gray-700">
                                            <div class="h-1.5 rounded-full transition-all duration-500
                                                {{ $row->emr_percent >= 80 ? 'bg-emerald-500/80' : ($row->emr_percent >= 50 ? 'bg-amber-400/80' : 'bg-rose-400/80') }}"
                                                style="width: {{ $row->emr_percent ?? 0 }}%">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div
                                                class="flex items-center gap-1 text-xs text-body dark:text-gray-400">
                                                <span>EMR : {{ $row->emr_percent ?? 0 }}%</span>
                                                {{-- Tombol info kelengkapan EMR — buka modal panduan + status pasien ini --}}
                                                <button type="button"
                                                    x-on:click.stop="$dispatch('open-info-kelengkapan-emr-ugd', { rjNo: {{ $row->rj_no }} })"
                                                    class="inline-flex items-center justify-center w-4 h-4 text-muted-soft transition rounded-full hover:text-brand-green hover:bg-brand-green/10 dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime"
                                                    title="Lihat status & kriteria kelengkapan EMR">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="text-xs text-body dark:text-gray-400">
                                                E-Resep : {{ $row->eresep_percent ?? 0 }}%
                                            </div>
                                        </div>

                                        <div x-show="expanded" x-collapse class="space-y-2">
                                            @if ($row->status_resep)
                                                <x-badge :variant="$row->status_resep_color">
                                                    Status Resep: {{ $row->status_resep_label }}
                                                </x-badge>
                                            @endif

                                            <div class="text-xs text-muted dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                            </div>

                                            @if ($row->procedure !== '-' || $row->procedure_free_text !== '-')
                                                <div class="text-xs text-muted dark:text-gray-400">
                                                    <span class="font-semibold">Procedure:</span><br>
                                                    {{ $row->procedure }} / {{ $row->procedure_free_text }}
                                                </div>
                                            @endif

                                            @if (!$row->is_json_valid)
                                                <div
                                                    class="text-xs p-1 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                                    <span class="font-semibold">⚠ JSON Tidak Sinkron:</span>
                                                    {{ $row->rj_no }} / {{ $row->rj_no_json }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-6 py-6 space-y-0.5 align-middle">
                                        @if ($row->admin_user && $row->admin_user !== '-')
                                            <div class="text-xs text-muted dark:text-gray-400 leading-tight">
                                                Administrasi :
                                                <span class="font-semibold text-ink dark:text-gray-200">
                                                    {{ $row->admin_user }}
                                                </span>
                                            </div>
                                        @endif

                                        {{-- Berkas BPJS ter-upload — badge per jenis (semua slot) --}}
                                        @php
                                            $berkasLabels = [1 => 'SEP', 2 => 'GROUPING', 3 => 'REKAM MEDIS', 4 => 'SKDP', 5 => 'LAIN-LAIN'];
                                            $berkasTerupload = $row->berkas_uploaded ?? [];
                                        @endphp
                                        @if (!empty($berkasTerupload))
                                            <div class="flex flex-wrap items-center gap-1 mt-1" title="Berkas BPJS sudah di-upload">
                                                @foreach ($berkasLabels as $seqFile => $labelBerkas)
                                                    @if (in_array($seqFile, $berkasTerupload, true))
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-brand-green/10 text-brand-green border-brand-green/30 dark:bg-brand-lime/15 dark:text-brand-lime dark:border-brand-lime/30">
                                                            <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                            {{ $labelBerkas }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($row->task_id99)
                                            <x-badge variant="danger">Batal {{ $row->task_id99 }}</x-badge>
                                        @endif

                                        {{-- Waktu UGD (datang/periksa/selesai) — selalu visible, kritis untuk emergency timing --}}
                                        <div
                                            class="text-xs text-body dark:text-gray-400 leading-tight space-y-0.5">
                                            @if ($row->waktu_datang)
                                                <div><span class="text-muted">Datang</span>
                                                    {{ $row->waktu_datang }}</div>
                                            @endif
                                            @if ($row->waktu_pemeriksaan)
                                                <div><span class="text-muted">Periksa</span>
                                                    {{ $row->waktu_pemeriksaan }}</div>
                                            @endif
                                            @if ($row->selesai_pemeriksaan)
                                                <div><span class="text-muted">Selesai</span>
                                                    {{ $row->selesai_pemeriksaan }}</div>
                                            @endif
                                        </div>

                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            @if ($row->tindak_lanjut && $row->tindak_lanjut !== '-')
                                                <div class="text-xs text-body dark:text-gray-400 leading-tight">
                                                    Tindak Lanjut : {{ $row->tindak_lanjut }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-middle">
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
                                            <div class="flex items-center gap-4">

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
                                                            <svg class="w-5 h-5" fill="currentColor"
                                                                viewBox="0 0 20 20">
                                                                <path
                                                                    d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                            </svg>
                                                        </x-secondary-button>
                                                    </x-slot>

                                                    <x-slot name="content">
                                                        <div class="p-2 space-y-2">
                                                            <div class="grid grid-cols-2 gap-1">

                                                                {{-- Pendaftaran Ubah — Mr, Admin, Supervisor Tu --}}
                                                                @hasanyrole(['Mr', 'Admin', 'Supervisor Tu'])
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openEdit('{{ $row->rj_no }}')"
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

                                                                {{-- EMR UGD khusus Diagnosa (ICD-10 + prosedur). Admin/Casemix/Mr/Dokter --}}
                                                                @hasanyrole('Admin|Casemix|Mr|Dokter')
                                                                    <x-dropdown-link href="#"
                                                                        x-on:click.prevent="$dispatch('daftar-ugd.diagnosa.open', { rjNo: {{ $row->rj_no }} })"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-indigo-700"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            <span>
                                                                                Diagnosa<br>
                                                                                <span class="font-semibold">ICD-10 (EMR khusus diagnosa)</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Kirim Satu Sehat — Admin, Mr --}}
                                                                @hasanyrole('Admin|Mr')
                                                                    <x-dropdown-link href="#"
                                                                        x-on:click.prevent="$dispatch('daftar-ugd.satu-sehat.open', { rjNo: {{ $row->rj_no }} })"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-teal-50 hover:bg-teal-100 dark:bg-teal-900/30 dark:hover:bg-teal-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-teal-700"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                            </svg>
                                                                            <span>
                                                                                Kirim Satu Sehat<br>
                                                                                <span class="font-semibold">FHIR — UGD (Emergency)</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Berkas BPJS — Admin/Casemix/Tu/Mr --}}
                                                                @hasanyrole('Admin|Casemix|Tu|Mr')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openBerkasBpjs({{ $row->rj_no }})"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-amber-700"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            <span>
                                                                                Berkas BPJS<br>
                                                                                <span class="font-semibold">SEP / Klaim / RM / SKDP / Lain</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                            </div>

                                                            {{-- DIVIDER --}}
                                                            <div
                                                                class="my-1 border-t border-hairline dark:border-gray-700">
                                                            </div>

                                                            {{-- Hapus — Admin, Manager Medis, Manager Umum --}}
                                                            @hasanyrole(['Admin', 'Manager Medis', 'Manager Umum'])
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="requestDelete('{{ $row->rj_no }}')"
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
                                                            @endhasanyrole

                                                        </div>
                                                    </x-slot>
                                                </x-dropdown>

                                                {{-- SATU SEHAT — status kirim PER-RESOURCE, di kanan tombol titik-3.
                                                     Abu-abu = belum dikirim, hijau/brand = sudah. Klik chip = buka modal Kirim Satu Sehat UGD. --}}
                                                @hasanyrole('Admin|Mr')
                                                    <div class="flex-1 min-w-0">
                                                        <x-input-label value="Satu Sehat" class="mb-1 text-[10px] uppercase tracking-wide text-muted" />
                                                        <div class="flex flex-wrap items-center gap-1">
                                                            @foreach ($row->satusehat_items as $ssItem)
                                                                <button type="button"
                                                                    x-on:click.prevent="$dispatch('daftar-ugd.satu-sehat.open', { rjNo: {{ $row->rj_no }} })"
                                                                    title="{{ $ssItem['full'] }} — {{ $ssItem['sent'] ? 'sudah dikirim' : 'belum dikirim' }} (klik untuk kelola)"
                                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded border text-[9px] font-semibold leading-none transition {{ $ssItem['sent'] ? 'bg-brand-green/10 text-brand-green border-brand-green/30 dark:bg-brand-lime/15 dark:text-brand-lime dark:border-brand-lime/30' : 'bg-surface-soft text-muted-soft border-hairline hover:bg-surface-strong hover:text-body dark:bg-gray-800 dark:text-gray-500 dark:border-gray-700 dark:hover:bg-gray-700' }}">
                                                                    @if ($ssItem['sent'])
                                                                        <svg class="w-2 h-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                                    @endif
                                                                    {{ $ssItem['label'] }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endhasanyrole

                                            </div>
                                        @endif
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data</p>
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

            {{-- Child components — pendaftaran-only (EMR/Modul Dokumen/Administrasi/iDRG pindah ke pelayanan-ugd / bulanan) --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.daftar-ugd-actions wire:key="daftar-ugd-actions" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-ugd" />
            <livewire:pages::transaksi.ugd.daftar-ugd-bulanan.berkas-bpjs-ugd-actions wire:key="berkas-bpjs-ugd-actions" />

            {{-- Modal panduan kriteria kelengkapan EMR UGD (dibuka dari tombol info ⓘ samping label "EMR : x%") --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.info-kelengkapan-emr wire:key="info-kelengkapan-emr-ugd" />

            {{-- EMR UGD khusus Diagnosa — komponen modal terpisah (listen: daftar-ugd.diagnosa.open) --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.diagnosa-ugd-actions wire:key="diagnosa-ugd-actions" />

            {{-- Kirim Satu Sehat UGD — modal 9 kartu (listen: daftar-ugd.satu-sehat.open) --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.satu-sehat-ugd-actions wire:key="satu-sehat-ugd-actions" />

        </div>
    </div>
</div>
