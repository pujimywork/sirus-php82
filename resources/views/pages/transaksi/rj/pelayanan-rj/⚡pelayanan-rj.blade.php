<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Rj\EmrCompletenessRJTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrCompletenessRJTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['pelayanan-rj-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     | Pelayanan RJ: fokus EMR (erm_status), tidak ada filter Klaim / Status RJ
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A'; // erm_status: A=Belum Dilayani, L=Selesai
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM' — pakai klaim_status di rsmst_klaimtypes (JM dianggap BPJS)
    public string $filterPoli = '';
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
        $this->incrementVersion('pelayanan-rj-toolbar');
    }

    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('pelayanan-rj-toolbar');
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
        $this->incrementVersion('pelayanan-rj-toolbar');
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('pelayanan-rj-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('pelayanan-rj-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterPoli', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('pelayanan-rj-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers — pelayanan-only
     * ------------------------- */
    public function openRekamMedis(string $rjNo): void
    {
        $this->dispatch('emr-rj.rekam-medis.open', rjNo: $rjNo);
    }

    public function openModulDokumen(int $rjNo): void
    {
        $this->dispatch('emr-rj.modul-dokumen.open', rjNo: $rjNo);
    }

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-rj.administrasi.open', rjNo: $rjNo);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('refresh-after-rj.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('pelayanan-rj-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries — Pelayanan RJ: status pakai erm_status (EMR)
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $statusColumn = DB::raw("NVL(h.erm_status, 'A')");

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'RJ')->where('checkup_status', '!=', 'B')->groupBy('ref_no');

        $radSub = DB::table('rstxn_rjrads')->select('rj_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rj_no');

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.erm_status', 'h.vno_sep', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status'), 'h.datadaftarpolirj_json', 'k.klaim_desc', 'k.klaim_status'])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('d.dr_name', 'desc')
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.no_antrian', 'asc');

        if ($this->filterStatus !== '') {
            $query->where($statusColumn, $this->filterStatus);
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

        if ($this->filterPoli !== '') {
            $query->where('h.poli_id', $this->filterPoli);
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
        // Pelayanan RJ: tidak include pending booking — dokter hanya lihat pasien
        // yang sudah checkin (rstxn_rjhdrs). Pending booking ada di daftar-rj (pendaftaran).
        $rjRows = $this->baseQuery()
            ->get()
            ->map(function ($row) {
                $row->is_booking_pending = false;

                // Oracle CLOB bisa di-fetch sebagai OCILob/resource alih-alih string — normalize dulu.
                $jsonRaw = $row->datadaftarpolirj_json ?? '{}';
                if (is_object($jsonRaw) && method_exists($jsonRaw, 'load')) {
                    $jsonRaw = $jsonRaw->load();
                } elseif (is_resource($jsonRaw)) {
                    $jsonRaw = stream_get_contents($jsonRaw);
                }
                $json = json_decode($jsonRaw ?: '{}', true) ?? [];

                // EMR completeness — weighted S15/O25/A25/P25/N10. Logic ada di EmrCompletenessRJTrait.
                // Field "screening" (alergi, RPD) dianggap terisi kalau non-empty (termasuk "Tidak ada"),
                // sesuai praktik klinis — dokter wajib explicit isi negatif, jangan dibiarkan kosong.
                $pct = $this->calculateEmrPercentRJ($json);
                $row->emr_percent = $pct['emr'];
                $row->emr_sections = $pct['sections']; // detail per-section (S/O/A/P/N) untuk tooltip optional
                $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;
                $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
                $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
                $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;
                $row->task_id6 = $json['taskIdPelayanan']['taskId6'] ?? null;
                $row->task_id7 = $json['taskIdPelayanan']['taskId7'] ?? null;
                $row->no_referensi = $json['noReferensi'] ?? null;

                if (isset($json['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan'])) {
                    $tglRujukan = Carbon::parse($json['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan']);
                    $batas = $tglRujukan->copy()->addMonths(3);
                    $sisaHari = (int) now()->diffInDays($batas, false);
                    $row->masa_rujukan = 'Masa berlaku Rujukan <br>' . $tglRujukan->format('d/m/Y') . ' s/d ' . $batas->format('d/m/Y') . '<br>Sisa : ' . $sisaHari . ' hari';
                } else {
                    $row->masa_rujukan = null;
                }

                $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '—';
                $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
                $row->tindak_lanjut_detail = $json['perencanaan']['tindakLanjut'] ?? null;
                $row->tgl_kontrol = $json['kontrol']['tglKontrol'] ?? '-';
                $row->no_skdp_bpjs = $json['kontrol']['noSKDPBPJS'] ?? '-';
                $row->kontrol_detail = $json['kontrol'] ?? null;

                $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode('# ', array_column($json['diagnosis'], 'icdX')) : '-';
                $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';
                $row->diagnosis_detail = $json['diagnosis'] ?? null;
                $row->procedure = isset($json['procedure']) && is_array($json['procedure']) ? implode('# ', array_column($json['procedure'], 'procedureId')) : '-';
                $row->procedure_free_text = $json['procedureFreeText'] ?? '-';
                $row->procedure_detail = $json['procedure'] ?? null;

                $row->status_resep = $json['statusResep']['status'] ?? null;
                $row->status_resep_label = $row->status_resep === 'DITUNGGU' ? 'Ditunggu' : ($row->status_resep === 'DITINGGAL' ? 'Ditinggal' : '-');
                $row->status_resep_color = $row->status_resep === 'DITUNGGU' ? 'green' : ($row->status_resep === 'DITINGGAL' ? 'yellow' : 'gray');
                $row->no_booking = $json['noBooking'] ?? ($row->nobooking ?? '-');
                $row->rj_no_json = $json['rjNo'] ?? '-';
                $row->is_json_valid = $row->rj_no == $row->rj_no_json;
                $row->bg_check_json = $row->is_json_valid ? 'bg-green-100' : 'bg-red-100';

                if (!empty($row->birth_date)) {
                    try {
                        $tglLahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                        $diff = $tglLahir->diff(now());
                        $row->umur_format = "{$row->birth_date} ({$diff->y} Thn {$diff->m} Bln {$diff->d} Hr)";
                    } catch (\Exception $e) {
                        $row->umur_format = '-';
                    }
                } else {
                    $row->umur_format = '-';
                }

                // Status berdasarkan urutan Task ID (4=Masuk Poli, 5=Keluar Poli, 6=Menunggu Resep, 7=Terima Resep, 99=Batal)
                // Batal di-detect dari Task ID 99 OR rj_status='F' (legacy dari Oracle Dev 6i / mutasi langsung)
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

                return $row;
            })
            ->sort(function ($a, $b) {
                $drCmp = strcmp($b->dr_name ?? '', $a->dr_name ?? '');
                if ($drCmp !== 0) {
                    return $drCmp;
                }
                return (int) ($a->no_antrian ?? 0) - (int) ($b->no_antrian ?? 0);
            })
            ->values();

        // Manual paginate
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage = $this->itemsPerPage;

        return new \Illuminate\Pagination\LengthAwarePaginator($rjRows->slice(($page - 1) * $perPage, $perPage)->values(), $rjRows->count(), $perPage, $page, ['path' => request()->url()]);
    }

    /* -------------------------
     | Master data for filters
     * ------------------------- */
    #[Computed]
    public function poliList()
    {
        return DB::table('rsmst_polis')->select('poli_id', 'poli_desc', 'spesialis_status')->orderBy('poli_desc')->get();
    }

    #[Computed]
    public function dokterList()
    {
        // ✅ Tanpa cache()->remember() — langsung query agar selalu fresh saat filter berubah
        $query = DB::table('rstxn_rjhdrs')->select('rstxn_rjhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), 'rstxn_rjhdrs.poli_id', DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'), DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'))->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_rjhdrs.poli_id')->where(DB::raw("to_char(rstxn_rjhdrs.rj_date, 'dd/mm/yyyy')"), '=', $this->filterTanggal);

        if (!empty($this->filterStatus)) {
            $query->where('rstxn_rjhdrs.erm_status', $this->filterStatus);
        }

        // NOTE: opsi dropdown Dokter tidak ikut di-filter oleh searchKeyword
        // (pencarian pasien) — supaya dokter yang sudah dipilih user tidak hilang
        // dari list saat user mengetik nama/no RM.

        return $query->groupBy('rstxn_rjhdrs.dr_id', 'rstxn_rjhdrs.poli_id')->orderBy('poli_desc')->orderBy('dr_name')->get();
    }

    #[Computed]
    public function klaimList()
    {
        return DB::table('rsmst_klaims')->select('klaim_id', 'klaim_name')->where('active_status', '1')->orderBy('klaim_name')->get();
    }

    public function cetakEtiket(string $regNo): void
    {
        $this->dispatch('cetak-etiket.open', regNo: $regNo);
    }

    /* Auto-print via local print agent (sirus-print-agent.exe) di PC user.
       Trigger sibling component <cetak-etiket-auto> yang generate PDF →
       encode base64 → JS fetch ke http://localhost:9999 → silent print
       ke printer "etiket". */
    public function autoPrintEtiket(string $regNo): void
    {
        $this->dispatch('cetak-etiket-auto.print', regNo: $regNo);
    }
};
?>

{{-- ✅ wire:key di level paling atas — seluruh halaman re-render saat filter berubah --}}
{{-- Child components aman karena punya static wire:key masing-masing              --}}
<div>
    <x-page-title
        title="Pelayanan Rawat Jalan"
        subtitle="Kelola pelayanan poli &amp; EMR pasien rawat jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 pt-1 pb-2 bg-white border-b border-gray-200 top-16 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('pelayanan-rj-toolbar', []) }}">

                    {{-- SEARCH — flex-1, isi sisa ruang setelah filter lain --}}
                    <div class="w-full sm:flex-1 sm:min-w-[12rem]">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
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
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- FILTER STATUS — erm_status --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-44">
                            <option value="">Semua</option>
                            <option value="A">Belum Dilayani</option>
                            <option value="L">Selesai</option>
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

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-56">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-secondary-button type="button" wire:click="resetFilters" title="Reset filter"
                            class="p-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="sr-only">Reset</span>
                        </x-secondary-button>

                        <div class="w-20">
                            <x-select-input wire:model.live="itemsPerPage" class="text-sm" title="Per halaman">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                    </div>

                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div
                    class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-base border-separate border-spacing-y-3 table-fixed">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3 w-[24%]">Pasien</th>
                                <th class="px-6 py-3 w-[20%]">Poli</th>
                                <th class="px-6 py-3 w-[16%]">Status Layanan</th>
                                <th class="px-6 py-3 w-[18%]">Tindak Lanjut</th>
                                <th class="px-6 py-3 w-[22%] text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="pelayanan-rj-row-{{ $row->rj_no }}" x-data="{ expanded: false }"
                                    style="position: relative;"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                    {{ $row->is_booking_pending
                                        ? 'bg-amber-50 dark:bg-amber-900/10 hover:shadow-md hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400'
                                        : ($row->status_text === 'Batal'
                                            ? 'bg-red-50 dark:bg-red-900/10 hover:shadow-md hover:bg-red-100 dark:hover:bg-red-900/20 border-l-4 border-red-400'
                                            : ($row->erm_status === 'L'
                                                ? 'bg-emerald-50 dark:bg-emerald-900/10 hover:shadow-md hover:bg-emerald-100 dark:hover:bg-emerald-900/20 border-l-4 border-emerald-500'
                                                : 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800')) }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-middle">
                                        {{-- Toggle Detail chevron — absolute, bottom-center row (di dalam card) --}}
                                        <button type="button" x-on:click="expanded = !expanded"
                                            class="absolute z-10 inline-flex items-center justify-center w-7 h-7 text-gray-500 transition bg-white border border-gray-200 rounded-full shadow-sm hover:text-emerald-600 hover:bg-emerald-50 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300"
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
                                                class="flex flex-col items-center justify-center w-16 h-16 rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                <span class="text-2xl font-bold leading-none">
                                                    {{ $row->no_antrian ?: '-' }}
                                                </span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    antrian
                                                </span>
                                            </div>
                                            <div class="space-y-0 leading-tight">
                                                <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $row->reg_no ?? '-' }}
                                                </div>
                                                <div class="text-lg font-semibold text-brand dark:text-white">
                                                    {{ $row->reg_name ?? '-' }}
                                                </div>
                                                <div class="text-sm font-normal text-gray-600 dark:text-gray-400">
                                                    ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $row->address ?? '-' }}
                                                </div>
                                                <div x-show="expanded" x-collapse
                                                    class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row->umur_format ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- POLI --}}
                                    <td class="px-6 py-6 space-y-0.5 align-middle">
                                        <div class="font-semibold text-brand dark:text-emerald-400 leading-tight">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>
                                        @php
                                            $isBpjs = $row->klaim_status === 'BPJS' || $row->klaim_id === 'JM';
                                        @endphp
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400 leading-tight">
                                            <span>{{ $row->dr_name ?? '-' }}</span>
                                            @if ($isBpjs)
                                                <x-badge :variant="$isBpjs ? 'info' : 'alternative'">
                                                    {{ $isBpjs ? 'BPJS' : 'UMUM' }}
                                                </x-badge>
                                            @endif
                                        </div>
                                        <div><span class="text-gray-600 dark:text-gray-400 leading-tight text-xs">Klaim
                                                : {{ $row->klaim_desc ?? '-' }}</span>

                                        </div>
                                        {{-- No Booking — hanya untuk pasien BPJS (klaim_status=BPJS atau klaim_id=JM/JKN Mobile) --}}
                                        @if ($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM')
                                            <div class="text-xs text-gray-700 dark:text-gray-400 leading-tight">
                                                No Booking: {{ $row->no_booking ?? '-' }}
                                            </div>
                                        @endif
                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            <div class="text-xs text-gray-500 dark:text-gray-500 leading-tight">
                                                {{ $row->rj_date_display ?? '-' }} | Shift : {{ $row->shift ?? '-' }}
                                            </div>
                                            <div class="flex flex-wrap gap-2">
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
                                    <td class="px-6 py-6 space-y-2 align-middle">
                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        @if (!$row->is_booking_pending)
                                            {{-- EMR progress + EMR/E-Resep % — tampil di collapsed (dokter/perawat butuh at-a-glance) --}}
                                            <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                                <div class="h-1.5 rounded-full transition-all duration-500
                                                    {{ $row->emr_percent >= 80
                                                        ? 'bg-emerald-500/80 dark:bg-emerald-400'
                                                        : ($row->emr_percent >= 50
                                                            ? 'bg-amber-400/80 dark:bg-amber-400'
                                                            : 'bg-rose-400/80 dark:bg-rose-400') }}"
                                                    style="width: {{ $row->emr_percent ?? 0 }}%">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div
                                                    class="flex items-center gap-1 text-xs text-gray-700 dark:text-gray-400">
                                                    <span>EMR : {{ $row->emr_percent ?? 0 }}%</span>
                                                    <button type="button"
                                                        x-on:click.stop="$dispatch('open-info-kelengkapan-emr-rj', { rjNo: {{ $row->rj_no }} })"
                                                        class="inline-flex items-center justify-center w-4 h-4 text-gray-400 transition rounded-full hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300"
                                                        title="Lihat status & kriteria kelengkapan EMR">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="text-xs text-gray-700 dark:text-gray-400">
                                                    E-Resep : {{ $row->eresep_percent ?? 0 }}%
                                                </div>
                                            </div>

                                            <div x-show="expanded" x-collapse class="space-y-2">
                                                @if ($row->status_resep)
                                                    <x-badge :variant="$row->status_resep_color">
                                                        Status Resep: {{ $row->status_resep_label }}
                                                    </x-badge>
                                                @endif
                                            </div>

                                            {{-- <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                            </div> --}}

                                            {{-- @if ($row->procedure !== '-' || $row->procedure_free_text !== '-')
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    <span class="font-semibold">Procedure:</span><br>
                                                    {{ $row->procedure }} / {{ $row->procedure_free_text }}
                                                </div>
                                            @endif --}}

                                            @if (!empty($row->vno_sep) && $row->vno_sep !== '-')
                                                <div class="flex items-baseline gap-1 text-xs text-gray-700 dark:text-gray-300">
                                                    <span>SEP :</span>
                                                    <span class="font-mono">{{ $row->vno_sep }}</span>
                                                </div>
                                            @endif

                                            @if (!empty($row->masa_rujukan))
                                                <div class="px-2 py-1 text-xs text-yellow-700 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 dark:text-yellow-300">
                                                    {!! $row->masa_rujukan !!}
                                                </div>
                                            @endif
                                            {{-- <div
                                                class="text-xs p-1 rounded {{ $row->bg_check_json }} dark:bg-opacity-20">
                                                <span class="font-semibold">Validasi Data:</span><br>
                                                RJ No: {{ $row->rj_no }} / {{ $row->rj_no_json }}
                                                @if (!$row->is_json_valid)
                                                    <span class="text-red-600 dark:text-red-400">(Tidak Sinkron)</span>
                                                @endif
                                            </div> --}}
                                        @else
                                            {{-- Pending booking — hanya info no booking --}}
                                            <div class="text-xs text-amber-700 dark:text-amber-400 font-mono mt-1">
                                                No Booking: {{ $row->rj_no }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-6 py-6 space-y-2 align-middle">
                                        <div class="text-xs space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id4 ? 'bg-amber-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Masuk Poli:
                                                    <span class="font-medium">{{ $row->task_id4 ?: '—' }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id5 ? 'bg-blue-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Keluar Poli:
                                                    <span class="font-medium">{{ $row->task_id5 ?: '—' }}</span>
                                                </span>
                                            </div>
                                            @php
                                                $rjLabel = match ($row->rj_status) {
                                                    'A' => 'Menunggu Pembayaran',
                                                    'L' => 'Selesai Pembayaran',
                                                    'I' => 'Trf UGD',
                                                    'F' => 'Batal',
                                                    default => null,
                                                };
                                                $rjTextColor = match ($row->rj_status) {
                                                    'A' => 'text-slate-500 dark:text-slate-400',
                                                    'L' => 'text-emerald-600 dark:text-emerald-400',
                                                    'I' => 'text-blue-600 dark:text-blue-400',
                                                    'F' => 'text-red-600 dark:text-red-400',
                                                    default => 'text-gray-400',
                                                };
                                            @endphp
                                            @if ($rjLabel)
                                                <div class="text-xs text-gray-500 dark:text-gray-500">
                                                    Kasir:
                                                    <span
                                                        class="font-medium {{ $rjTextColor }}">{{ $rjLabel }}</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id6 ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Masuk Apotek:
                                                    <span class="font-medium">{{ $row->task_id6 ?: '—' }}</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="w-2 h-2 rounded-full {{ $row->task_id7 ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Keluar Apotek:
                                                    <span class="font-medium">{{ $row->task_id7 ?: '—' }}</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            Administrasi:
                                            <span
                                                class="font-medium {{ $row->admin_user !== '—' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $row->admin_user }}
                                            </span>
                                        </div>

                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            @if ($row->tindak_lanjut && $row->tindak_lanjut !== '-')
                                                <div class="text-xs text-gray-700 dark:text-gray-400 leading-tight">
                                                    Tindak Lanjut : {{ $row->tindak_lanjut }}
                                                </div>
                                            @endif

                                            @if (($row->tindak_lanjut_detail['tindakLanjut'] ?? null) === 'Kontrol')
                                                @if (!empty($row->tgl_kontrol) && $row->tgl_kontrol !== '-')
                                                    <div
                                                        class="text-xs text-gray-700 dark:text-gray-300 leading-tight">
                                                        Tanggal Kontrol : {{ $row->tgl_kontrol }}
                                                    </div>
                                                @endif

                                                @if ($row->no_skdp_bpjs && $row->no_skdp_bpjs != '-')
                                                    <div
                                                        class="text-xs text-gray-600 dark:text-gray-400 leading-tight">
                                                        No SKDP BPJS: {{ $row->no_skdp_bpjs }}
                                                    </div>
                                                @endif

                                                @if ($row->kontrol_detail)
                                                    <div
                                                        class="text-xs text-gray-700 dark:text-gray-400 leading-tight">
                                                        Poli Kontrol:
                                                        {{ $row->kontrol_detail['poliKontrolDesc'] ?? ($row->kontrol_detail['poliKontrol'] ?? '-') }}<br>
                                                        Dokter Kontrol:
                                                        {{ $row->kontrol_detail['drKontrolDesc'] ?? ($row->kontrol_detail['drKontrol'] ?? '-') }}
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-middle">
                                        @if ($row->is_booking_pending)
                                            {{-- Pending: hanya info, belum bisa diakses --}}
                                            <div class="flex flex-col items-center gap-2 text-center">
                                                <div class="text-amber-500 dark:text-amber-400">
                                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                                                    Belum Checkin
                                                </span>
                                                <span x-show="expanded" x-collapse class="text-xs text-gray-400">
                                                    Aksi tersedia<br>setelah checkin
                                                </span>
                                            </div>
                                        @elseif ($row->status_text === 'Batal')
                                            {{-- Batal: actions tidak diakses, konfirmasi ke Pendaftaran --}}
                                            <div class="flex flex-col items-center gap-2 text-center">
                                                <div class="text-red-500 dark:text-red-400">
                                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <span class="text-xs text-red-600 dark:text-red-400 font-semibold">
                                                    Pasien Batal
                                                </span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    Konfirmasi ke<br>Pendaftaran
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-4">

                                                {{-- Cetak Etiket (download PDF) --}}
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
                                                        <x-loading />
                                                        Mencetak...
                                                    </span>
                                                </x-secondary-button>

                                                {{-- Auto-Print Etiket via sirus-print-agent (silent, ke printer "etiket") --}}
                                                {{-- <x-primary-button wire:click="autoPrintEtiket('{{ $row->reg_no }}')"
                                                    wire:loading.attr="disabled" wire:target="autoPrintEtiket"
                                                    title="Auto-print silent via local print agent ke printer 'etiket'">
                                                    <span wire:loading.remove wire:target="autoPrintEtiket"
                                                        class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                        Auto-Print
                                                    </span>
                                                    <span wire:loading wire:target="autoPrintEtiket"
                                                        class="flex items-center gap-1">
                                                        <x-loading />
                                                        Mencetak...
                                                    </span>
                                                </x-primary-button> --}}

                                                {{-- Dropdown Aksi --}}
                                                <x-dropdown position="left" width="w-[500px]">
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

                                                            {{-- Task ID 4/5 + Get — Perawat saja (Admin otomatis via super-user) --}}
                                                            @hasanyrole('Perawat|Admin')
                                                                <div class="flex space-x-1">
                                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-4
                                                                        :rjNo="$row->rj_no"
                                                                        wire:key="taskid4-{{ $row->rj_no }}" />
                                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-5
                                                                        :rjNo="$row->rj_no"
                                                                        wire:key="taskid5-{{ $row->rj_no }}" />
                                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.get-task-id
                                                                        :rjNo="$row->rj_no"
                                                                        wire:key="gettaskid-{{ $row->rj_no }}" />
                                                                </div>
                                                            @endhasanyrole

                                                            {{-- GRID 2 KOLOM --}}
                                                            <div class="grid grid-cols-2 gap-1">

                                                                {{-- Rekam Medis — Perawat, Dokter, Admin, Casemix, Mr (view) --}}
                                                                @hasanyrole('Perawat|Dokter|Admin|Casemix|Mr')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openRekamMedis('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                                            </svg>
                                                                            <span>
                                                                                Rekam Medis <br>
                                                                                <span class="font-semibold">Pasien</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Modul Dokumen — Admin, Perawat, Casemix, Mr --}}
                                                                @hasanyrole('Admin|Perawat|Casemix|Mr')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openModulDokumen('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/20 dark:hover:bg-yellow-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            <span>
                                                                                Modul Dokumen <br>
                                                                                <span class="font-semibold">Suket Sehat /
                                                                                    Sakit</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Administrasi — Admin, Perawat, Casemix, Tu --}}
                                                                @hasanyrole('Admin|Perawat|Casemix|Tu')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openAdministrasiPasien('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                                                            </svg>
                                                                            <span>
                                                                                Administrasi <br>
                                                                                <span
                                                                                    class="font-semibold">{{ $row->reg_name }}</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                            </div>

                                                        </div>
                                                    </x-slot>
                                                </x-dropdown>

                                            </div>
                                        @endif
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-gray-700 dark:text-gray-400">
                                        Belum ada data
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

            {{-- Sibling components — pelayanan-only: EMR + Modul Dokumen + Administrasi + Cetak Etiket --}}
            <livewire:pages::transaksi.rj.emr-rj.erm-rj wire:key="rm-perawat-rj-actions" />
            <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.modul-dokumen-rj wire:key="modul-dokumen-rj" />
            <livewire:pages::transaksi.rj.administrasi-rj.administrasi-rj wire:key="administrasi-rj-actions" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-pasien" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket-auto wire:key="cetak-etiket-auto-pasien" />
            <livewire:pages::transaksi.rj.daftar-rj.info-kelengkapan-emr wire:key="info-kelengkapan-emr-rj" />

        </div>
    </div>
</div>
