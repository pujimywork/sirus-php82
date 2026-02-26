<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A';
    public string $filterPoli = '';
    public string $filterDokter = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterPoli', 'filterDokter']);
        $this->filterStatus = '1';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('daftar-rj.openCreate');
    }

    public function openEdit(string $rjNo): void
    {
        $this->dispatch('daftar-rj.openEdit', rjNo: $rjNo);
    }

    public function openRekamMedisDokter(string $rjNo): void
    {
        $this->dispatch('daftar-rj.rekam-medis.openDokter', rjNo: $rjNo);
    }

    public function openRekamMedisPerawat(string $rjNo): void
    {
        $this->dispatch('daftar-rj.rekam-medis.openPerawat', rjNo: $rjNo);
    }

    /* -------------------------
    | Request Delete (delegate ke actions)
    * ------------------------- */
    public function requestDelete(string $rjNo): void
    {
        $this->dispatch('toast', type: 'warning', message: 'Modul Rawat Jalan - Dalam Pengembangan');
        // $this->dispatch('daftar-rj.requestDelete', rjNo: $rjNo);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('refresh-after-rj.saved')]
    public function refreshAfterSaved(): void
    {
        $this->dispatch('$refresh');
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'RJ')->where('checkup_status', '!=', 'B')->groupBy('ref_no');

        $radSub = DB::table('rstxn_rjrads')->select('rj_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rj_no');

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.vno_sep', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status'), 'h.datadaftarpolirj_json', 'k.klaim_desc', 'k.klaim_status'])
            ->whereBetween('h.rj_date', [$start, $end])
            ->orderBy('d.dr_name', 'desc')
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.no_antrian', 'asc');

        if ($this->filterStatus !== '') {
            $query->where('h.rj_status', $this->filterStatus);
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
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarpolirj_json ?? '{}', true);

            /* =======================
        | EMR
        ======================= */
            $fields = ['anamnesa', 'pemeriksaan', 'penilaian', 'procedure', 'diagnosis', 'perencanaan'];
            $filled = 0;

            foreach ($fields as $f) {
                if (isset($json[$f])) {
                    $filled++;
                }
            }

            $row->emr_percent = round(($filled / 6) * 100);

            /* =======================
        | E-RESEP
        ======================= */
            $hasEresep = isset($json['eresep']) || isset($json['eresepRacikan']);
            $row->eresep_percent = $hasEresep ? 100 : 0;

            /* =======================
        | TASK ID
        ======================= */
            $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
            $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
            $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;

            /* =======================
        | NO REFERENSI
        ======================= */
            $row->no_referensi = $json['noReferensi'] ?? null;

            /* =======================
        | MASA RUJUKAN
        ======================= */
            if (isset($json['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan'])) {
                $tglRujukan = Carbon::parse($json['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan']);
                $batas = $tglRujukan->copy()->addMonths(3);
                $sisaHari = (int) now()->diffInDays($batas, false);

                $row->masa_rujukan = 'Masa berlaku Rujukan <br>' . $tglRujukan->format('d/m/Y') . ' s/d ' . $batas->format('d/m/Y') . '<br>Sisa : ' . $sisaHari . ' hari';
            } else {
                $row->masa_rujukan = null;
            }

            /* =======================
        | ADMINISTRASI
        ======================= */
            $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';
            $row->administrasi_detail = $json['AdministrasiRj'] ?? null;

            /* =======================
        | TINDAK LANJUT & KONTROL
        ======================= */
            $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
            $row->tindak_lanjut_detail = $json['perencanaan']['tindakLanjut'] ?? null;
            $row->tgl_kontrol = $json['kontrol']['tglKontrol'] ?? '-';
            $row->no_skdp_bpjs = $json['kontrol']['noSKDPBPJS'] ?? '-';
            $row->kontrol_detail = $json['kontrol'] ?? null;

            /* =======================
        | DIAGNOSIS & PROCEDURE
        ======================= */
            // Diagnosis array ICD
            $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode('# ', array_column($json['diagnosis'], 'icdX')) : '-';
            $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';
            $row->diagnosis_detail = $json['diagnosis'] ?? null;

            // Procedure array
            $row->procedure = isset($json['procedure']) && is_array($json['procedure']) ? implode('# ', array_column($json['procedure'], 'procedureId')) : '-';
            $row->procedure_free_text = $json['procedureFreeText'] ?? '-';
            $row->procedure_detail = $json['procedure'] ?? null;

            /* =======================
        | STATUS RESEP
        ======================= */
            $row->status_resep = $json['statusResep']['status'] ?? null;
            $row->status_resep_label = $row->status_resep === 'DITUNGGU' ? 'Ditunggu' : ($row->status_resep === 'DITINGGAL' ? 'Ditinggal' : '-');
            $row->status_resep_color = $row->status_resep === 'DITUNGGU' ? 'green' : ($row->status_resep === 'DITINGGAL' ? 'yellow' : 'gray');

            /* =======================
        | INFORMASI TAMBAHAN
        ======================= */
            $row->no_booking = $json['noBooking'] ?? ($row->nobooking ?? '-');

            /* =======================
        | VALIDASI DATA
        ======================= */
            $row->rj_no_json = $json['rjNo'] ?? '-';
            $row->is_json_valid = $row->rj_no == $row->rj_no_json;
            $row->bg_check_json = $row->is_json_valid ? 'bg-green-100' : 'bg-red-100';

            /* =======================
        | UMUR
        ======================= */
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

            /* =======================
        | STATUS TEXT
        ======================= */
            $statusMap = [
                'A' => 'Antrian',
                'L' => 'Selesai',
                'F' => 'Batal',
                'I' => 'Inap/Rujuk',
            ];
            $statusVariant = [
                'A' => 'warning', // kuning
                'L' => 'success', // hijau
                'F' => 'danger', // merah
                'I' => 'brand', // emerald
            ];

            $row->status_text = $statusMap[$row->rj_status] ?? 'Pelayanan';
            $row->status_variant = $statusVariant[$row->rj_status] ?? 'gray';

            return $row;
        });

        return $paginator;
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
        return cache()->remember(
            "dokterList:{$this->filterTanggal}:{$this->filterStatus}:{$this->searchKeyword}",
            60, // 60 detik
            function () {
                $filterDate = $this->filterTanggal;

                $query = DB::table('rstxn_rjhdrs')
                    ->select('rstxn_rjhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), 'rstxn_rjhdrs.poli_id', DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'), DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'))
                    ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')
                    ->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_rjhdrs.poli_id') // ✅ FILTER TANGGAL (WAJIB)
                    ->where(DB::raw("to_char(rstxn_rjhdrs.rj_date, 'dd/mm/yyyy')"), '=', $filterDate);

                // ✅ FILTER POLI (JIKA ADA)
                // if (!empty($this->filterPoli)) {
                //     $query->where('rstxn_rjhdrs.poli_id', $this->filterPoli);
                // }

                // ✅ FILTER DOKTER (JIKA ADA) - UNTUK DETAIL DOKTER
                // if (!empty($this->filterDokter)) {
                //     $query->where('rstxn_rjhdrs.dr_id', $this->filterDokter);
                // }

                // ✅ FILTER STATUS (JIKA ADA)
                if (!empty($this->filterStatus)) {
                    $query->where('rstxn_rjhdrs.rj_status', $this->filterStatus);
                }

                // ✅ FILTER SEARCH (JIKA ADA)
                if (!empty($this->searchKeyword) && strlen($this->searchKeyword) >= 2) {
                    $keyword = strtoupper($this->searchKeyword);
                    $query->where(function ($q) use ($keyword) {
                        $q->where(DB::raw('UPPER(rsmst_doctors.dr_name)'), 'LIKE', "%{$keyword}%")->orWhere(DB::raw('UPPER(rsmst_polis.poli_desc)'), 'LIKE', "%{$keyword}%");
                    });
                }

                return $query->groupBy('rstxn_rjhdrs.dr_id', 'rstxn_rjhdrs.poli_id')->orderBy('poli_desc')->orderBy('dr_name')->get();
            },
        );
    }

    #[Computed]
    public function klaimList()
    {
        return DB::table('rsmst_klaims')->select('klaim_id', 'klaim_name')->where('active_status', '1')->orderBy('klaim_name')->get();
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Daftar Rawat Jalan
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-700">
                Kelola pendaftaran pasien rawat jalan
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
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
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" x-mask="99/99/9999" />
                        </div>
                    </div>

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Rujuk</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER POLI --}}
                    {{-- <div class="w-full sm:w-auto">
                        <x-input-label value="Poliklinik" />
                        <x-select-input wire:model.live="filterPoli" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Poli</option>
                            @foreach ($this->poliList as $poli)
                                <option value="{{ $poli->poli_id }}">
                                    {{ $poli->poli_desc }}
                                    @if ($poli->spesialis_status == '1')
                                        (Spesialis)
                                    @endif
                                </option>
                            @endforeach
                        </x-select-input>
                    </div> --}}

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
                            <x-input-label value="Per halaman" class="sr-only" />
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Daftar Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">

                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-base font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Poli</th>
                                <th class="px-6 py-3">Status Layanan</th>
                                <th class="px-6 py-3">Tindak Lanjut</th>
                                <th class="px-6 py-3 text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr
                                    class="transition bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800 rounded-2xl">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-top">

                                        <div class="flex items-start gap-4">

                                            <div class="text-5xl font-bold text-gray-700 dark:text-gray-200">
                                                {{ $row->no_antrian ?? '-' }}
                                            </div>

                                            <div class="space-y-1">

                                                <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $row->reg_no ?? '-' }}
                                                </div>

                                                <div class="text-lg font-semibold text-brand dark:text-white">
                                                    {{ $row->reg_name ?? '-' }}
                                                    /
                                                    ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>

                                                <div class="text-base text-gray-700 dark:text-gray-400">
                                                    {{ $row->umur_format ?? '-' }}
                                                </div>

                                                <div class="text-base text-gray-600 dark:text-gray-400">
                                                    {{ $row->address ?? '-' }}
                                                </div>

                                            </div>
                                        </div>
                                    </td>

                                    {{-- POLI --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="font-semibold text-brand dark:text-emerald-400">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>

                                        <div class="text-base text-gray-600 dark:text-gray-400">
                                            {{ $row->dr_name ?? '-' }} /
                                            {{ $row->klaim_desc ?? '-' }}
                                        </div>

                                        <div class="font-mono text-base text-gray-700 dark:text-gray-300">
                                            {{ $row->vno_sep ?? '-' }}
                                        </div>

                                        {{-- No Booking - TAMBAHKAN DISINI --}}
                                        <div class="text-xs text-gray-700 dark:text-gray-400">
                                            No Booking: {{ $row->no_booking ?? '-' }}
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            @if ($row->lab_status)
                                                <x-badge variant="alternative">
                                                    Laborat
                                                </x-badge>
                                            @endif

                                            @if ($row->rad_status)
                                                <x-badge variant="brand">
                                                    Radiologi
                                                </x-badge>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-6 py-6 space-y-2 align-top">

                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            {{ $row->rj_date_display ?? '-' }} |
                                            Shift : {{ $row->shift ?? '-' }}
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>


                                        {{-- EMR --}}
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
                                            <div class="text-base text-gray-700 dark:text-gray-400">
                                                EMR : {{ $row->emr_percent ?? 0 }}%
                                            </div>
                                            <div class="text-base text-gray-700 dark:text-gray-400">
                                                E-Resep : {{ $row->eresep_percent ?? 0 }}%
                                            </div>
                                        </div>

                                        {{-- STATUS RESEP - TAMBAHKAN DISINI --}}
                                        @if ($row->status_resep)
                                            <div>
                                                <x-badge :variant="$row->status_resep_color">
                                                    Status Resep: {{ $row->status_resep_label }}
                                                </x-badge>
                                            </div>
                                        @endif

                                        {{-- DIAGNOSIS - TAMBAHKAN DISINI --}}
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">Diagnosa:</span><br>
                                            {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                        </div>

                                        {{-- PROCEDURE - TAMBAHKAN DISINI --}}
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">Procedure:</span><br>
                                            {{ $row->procedure }} / {{ $row->procedure_free_text }}
                                        </div>

                                        @if (!empty($row->no_referensi))
                                            <div class="text-base text-gray-700 dark:text-gray-400">
                                                No Ref : {{ $row->no_referensi }}
                                            </div>
                                        @endif

                                        @if (!empty($row->masa_rujukan))
                                            <div
                                                class="px-2 py-1 text-sm text-yellow-700 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 dark:text-yellow-300">
                                                {!! $row->masa_rujukan !!}
                                            </div>
                                        @endif

                                        {{-- VALIDASI JSON - TAMBAHKAN DISINI --}}
                                        <div class="text-xs p-1 rounded {{ $row->bg_check_json }} dark:bg-opacity-20">
                                            <span class="font-semibold">Validasi Data:</span><br>
                                            RJ No: {{ $row->rj_no }} / {{ $row->rj_no_json }}
                                            @if (!$row->is_json_valid)
                                                <span class="text-red-600 dark:text-red-400">(Tidak Sinkron)</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-6 py-6 space-y-2 align-top">

                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Administrasi :
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ $row->admin_user ?? '-' }}
                                            </span>
                                        </div>

                                        {{-- DETAIL ADMINISTRASI - TAMBAHKAN DISINI --}}
                                        @if ($row->administrasi_detail)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Waktu: {{ $row->administrasi_detail['waktu'] ?? '-' }}<br>
                                                Log: {{ $row->administrasi_detail['userLog'] ?? '-' }}
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-1 space-y-1">
                                            @if ($row->task_id3)
                                                <x-badge variant="success">
                                                    TaskId3 {{ $row->task_id3 }}
                                                </x-badge>
                                            @endif
                                            @if ($row->task_id4)
                                                <x-badge variant="brand">
                                                    TaskId4 {{ $row->task_id4 }}
                                                </x-badge>
                                            @endif
                                            @if ($row->task_id5)
                                                <x-badge variant="warning">
                                                    TaskId5 {{ $row->task_id5 }}
                                                </x-badge>
                                            @endif
                                        </div>

                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            Tindak Lanjut : {{ $row->tindak_lanjut ?? '-' }}
                                        </div>

                                        {{-- DETAIL TINDAK LANJUT - TAMBAHKAN DISINI --}}
                                        @if ($row->tindak_lanjut_detail && $row->tindak_lanjut_detail['tindakLanjut'] ?? null)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Dokter: {{ $row->tindak_lanjut_detail['drPemeriksa'] ?? '-' }}
                                            </div>
                                        @endif

                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            Tanggal Kontrol : {{ $row->tgl_kontrol ?? '-' }}
                                        </div>

                                        {{-- NO SKDP BPJS - TAMBAHKAN DISINI --}}
                                        @if ($row->no_skdp_bpjs && $row->no_skdp_bpjs != '-')
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                No SKDP BPJS: {{ $row->no_skdp_bpjs }}
                                            </div>
                                        @endif

                                        {{-- DETAIL KONTROL - TAMBAHKAN DISINI --}}
                                        @if ($row->kontrol_detail)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Poli Kontrol: {{ $row->kontrol_detail['poliKontrol'] ?? '-' }}<br>
                                                Dokter Kontrol: {{ $row->kontrol_detail['dokterKontrol'] ?? '-' }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="flex items-center justify-center gap-2">

                                            @if (false)
                                                <div class="flex space-x-1">
                                                    {{-- Tombol TaskId3 Masuk Antrian --}}
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-3
                                                        :rjNo="$row->rj_no" wire:key="'taskid3-'.$row->rj_no" />

                                                    {{-- Tombol TaskId4 Selesai Pelayanan --}}
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-4
                                                        :rjNo="$row->rj_no" wire:key="'taskid4-'.$row->rj_no" />

                                                    {{-- Tombol TaskId5 Panggil Antrian --}}
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-5
                                                        :rjNo="$row->rj_no" wire:key="'taskid5-'.$row->rj_no" />

                                                    {{-- Tombol TaskId6 Masuk Apotek --}}
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-6
                                                        :rjNo="$row->rj_no" wire:key="'taskid6-'.$row->rj_no" />

                                                    {{-- Tombol TaskId7 Keluar Apotek --}}
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-7
                                                        :rjNo="$row->rj_no" wire:key="'taskid7-'.$row->rj_no" />

                                                    {{-- Tombol TaskId99 (Batal) --}}
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-99
                                                        :rjNo="$row->rj_no" wire:key="'taskid99-'.$row->rj_no" />
                                                </div>
                                            @endif

                                            {{-- Dropdown Action --}}
                                            <x-dropdown position="left" width="w-56">



                                                {{-- Trigger --}}
                                                <x-slot name="trigger">
                                                    <x-secondary-button type="button" class="p-2">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path
                                                                d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </x-secondary-button>
                                                </x-slot>


                                                {{-- Content --}}
                                                <x-slot name="content">

                                                    <div class="py-1 space-y-0.5">
                                                        @if ($row->lab_status || $row->rad_status)
                                                            <div class="flex space-x-1">

                                                                {{-- Tombol TaskId4 Selesai Pelayanan --}}
                                                                <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-4
                                                                    :rjNo="$row->rj_no"
                                                                    wire:key="'taskid4--'.$row->rj_no" />

                                                                {{-- Tombol TaskId5 Panggil Antrian --}}
                                                                <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-5
                                                                    :rjNo="$row->rj_no"
                                                                    wire:key="'taskid5--'.$row->rj_no" />
                                                        @endif


                                                        {{-- Ubah --}}
                                                        <x-dropdown-link href="#"
                                                            wire:click.prevent="openEdit('{{ $row->rj_no }}')"
                                                            class="px-3 py-1.5 text-md ">

                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-6 h-6 mt-0.5" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                                    stroke-width="2">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                                                </svg>

                                                                <span>
                                                                    Pendaftaran Ubah <br>
                                                                    <span class="font-semibold">
                                                                        {{ $row->reg_name }}
                                                                    </span>
                                                                </span>
                                                            </div>

                                                        </x-dropdown-link>

                                                        {{-- Rekam Medis Perawat --}}
                                                        <x-dropdown-link href="#"
                                                            wire:click.prevent="openRekamMedisPerawat('{{ $row->rj_no }}')"
                                                            class="px-3 py-1.5 text-md">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-6 h-6 mt-0.5" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                                    stroke-width="2">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="M19 10l2 2-2 2M5 10l-2 2 2 2" />
                                                                </svg>
                                                                <span>
                                                                    RM Perawat <br>
                                                                    <span class="font-semibold">Asuhan
                                                                        Keperawatan</span>
                                                                </span>
                                                            </div>
                                                        </x-dropdown-link>

                                                        {{-- Rekam Medis Dokter --}}
                                                        <x-dropdown-link href="#"
                                                            wire:click.prevent="openRekamMedisDokter('{{ $row->rj_no }}')"
                                                            class="px-3 py-1.5 text-md">
                                                            <div class="flex items-start gap-2">
                                                                <svg class="w-6 h-6 mt-0.5" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                                    stroke-width="2">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="M9 12h6m-6 4h6m2-10v16M7 6h10" />
                                                                </svg>
                                                                <span>
                                                                    RM Dokter <br>
                                                                    <span class="font-semibold">Asuhan Medis</span>
                                                                </span>
                                                            </div>
                                                        </x-dropdown-link>

                                                        <div class="py-4 space-y-2"></div>
                                                        {{-- Hapus --}}
                                                        <x-dropdown-link href="#"
                                                            wire:click.prevent="requestDelete('{{ $row->rj_no }}')"
                                                            class="px-3 py-1.5 font-semibold text-md text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 mb-10">
                                                            <div class="flex items-center gap-2">
                                                                <svg class="w-6 h-6" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                                    stroke-width="2">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        d="M6 7h12M9 7V5a3 3 0 016 0v2m-9 0l1 12h8l1-12" />
                                                                </svg>

                                                                <span>Hapus</span>
                                                            </div>

                                                        </x-dropdown-link>

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

            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::transaksi.rj.daftar-rj.daftar-rj-actions wire:key="daftar-rj-actions" />

            {{-- Untuk Perawat --}}
            <livewire:pages::transaksi.rj.daftar-rj.daftar-rj-actions-rm-perawat
                wire:key="daftar-rj-actions-perawat" />


        </div>
    </div>
</div>
