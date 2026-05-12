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
    protected array $renderAreas = ['daftar-ugd-bulanan-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterBulan = ''; // format m/Y (mm/yyyy)
    public string $filterStatus = '';
    public string $filterKlaim = ''; // '' | 'BPJS' | 'UMUM'
    public string $filterPoli = '';
    public string $filterDokter = '';
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedFilterBulan(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterPoli', 'filterDokter']);
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    /*
     | NOTE: Modul ini untuk casemix/admin/tu — fokus ke administrasi BPJS &
     | iDRG/INACBG. Aksi yang aktif: Kirim iDRG, Berkas BPJS, Hapus.
     | Aksi pelayanan harian (Task ID, Rekam Medis, Modul Dokumen, Satu Sehat)
     | dan aksi mutasi data (Create, Edit, Administrasi) sengaja dihilangkan
     | dari scope bulanan.
     */
    private function openCreate(): void
    {
        $this->dispatch('daftar-ugd.create.open');
    }

    public function openIdrg(string $rjNo): void
    {
        $this->dispatch('daftar-ugd.idrg.open', rjNo: $rjNo);
    }

    /* -------------------------
     | Berkas BPJS — dispatch ke sibling daftar-ugd-bulanan-actions
     * ------------------------- */
    public function openBerkasBpjs(int $rjNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rjNo);
    }

    /* -------------------------
     | Request Delete
     * ------------------------- */
    public function requestDelete(string $rjNo): void
    {
        $this->dispatch('toast', type: 'warning', message: 'Modul Rawat Jalan - Dalam Pengembangan');
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('refresh-after-rj.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Helper: apakah role Dokter/Perawat
     * ------------------------- */
    private function isDokterOrPerawat(): bool
    {
        return auth()
            ->user()
            ->hasAnyRole(['Dokter', 'Perawat']);
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $statusColumn = $this->isDokterOrPerawat() ? DB::raw("NVL(h.erm_status, 'A')") : DB::raw("NVL(h.rj_status, 'A')");

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'UGD')->where('checkup_status', '!=', 'B')->groupBy('ref_no');

        $radSub = DB::table('rstxn_ugdrads')->select('rj_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rj_no');

        $query = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.erm_status', 'h.vno_sep', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status'), 'h.datadaftarugd_json', 'k.klaim_desc', 'k.klaim_status'])
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

    /* -------------------------
     | Query pending bookings (terpisah, tidak UNION)
     * ------------------------- */
    private function queryPendingBookings(string $search): \Illuminate\Support\Collection
    {
        // Pending booking BPJS bersifat harian (per tanggalperiksa) — tidak relevan
        // untuk view bulanan. Return empty supaya merge nanti no-op.
        return collect();

        if ($this->isDokterOrPerawat() || !in_array($this->filterStatus, ['A', ''])) {
            return collect();
        }

        $q = DB::table('referensi_mobilejkn_bpjs as b')
            ->leftJoin('rsmst_pasiens as p', DB::raw('UPPER(p.reg_no)'), '=', DB::raw('UPPER(b.norm)'))
            ->leftJoin('rsmst_polis as pol', 'pol.kd_poli_bpjs', '=', 'b.kodepoli')
            ->leftJoin('rsmst_doctors as d', 'd.kd_dr_bpjs', '=', 'b.kodedokter')
            ->select(['b.nobooking as rj_no', DB::raw("TO_CHAR(TO_DATE(b.tanggalperiksa,'yyyy-mm-dd'),'dd/mm/yyyy') || ' ' || SUBSTR(b.jampraktek,1,5) || ':00' as rj_date_display"), DB::raw('UPPER(b.norm) as reg_no'), 'p.reg_name', 'p.sex', 'p.address', DB::raw("TO_CHAR(p.birth_date,'dd/mm/yyyy') AS birth_date"), 'b.angkaantrean as no_antrian', 'b.nomorantrean', 'pol.poli_desc', 'd.dr_name'])
            ->where('b.tanggalperiksa', Carbon::now()->format('Y-m-d'))
            ->where('b.status', 'Belum');

        if ($this->filterDokter !== '') {
            $kdDrBpjs = DB::table('rsmst_doctors')->where('dr_id', $this->filterDokter)->value('kd_dr_bpjs');
            $kdDrBpjs ? $q->where('b.kodedokter', $kdDrBpjs) : $q->whereRaw('1=0');
        }
        if ($this->filterPoli !== '') {
            $kdPoliBpjs = DB::table('rsmst_polis')->where('poli_id', $this->filterPoli)->value('kd_poli_bpjs');
            $kdPoliBpjs ? $q->where('b.kodepoli', $kdPoliBpjs) : $q->whereRaw('1=0');
        }
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $q->where(function ($qb) use ($kw) {
                $qb->where(DB::raw('UPPER(b.nobooking)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(b.norm)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%");
            });
        }

        return $q->orderBy(DB::raw('TO_NUMBER(b.angkaantrean)'), 'asc')->get();
    }

    private function dateRange(): array
    {
        // Format input: m/Y (mm/yyyy) → range awal bulan s/d akhir bulan
        try {
            $d = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $d = Carbon::now()->startOfMonth();
        }
        return [$d, (clone $d)->endOfMonth()];
    }

    #[Computed]
    public function rows()
    {
        $search = trim($this->searchKeyword);

        // ── 1. Fetch & transform rstxn_ugdhdrs ────────────────────────────
        $isDokterOrPerawat = $this->isDokterOrPerawat();
        $rjRows = $this->baseQuery()
            ->get()
            ->map(function ($row) use ($isDokterOrPerawat) {
                $row->is_booking_pending = false;

                $json = json_decode($row->datadaftarugd_json ?? '{}', true);

                $fields = ['anamnesa', 'pemeriksaan', 'penilaian', 'procedure', 'diagnosis', 'perencanaan'];
                $filled = 0;
                foreach ($fields as $f) {
                    if (isset($json[$f])) {
                        $filled++;
                    }
                }
                $row->emr_percent = round(($filled / 6) * 100);
                $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;
                $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
                $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
                $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;
                $row->no_referensi = $json['noReferensi'] ?? null;

                if (isset($json['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan'])) {
                    $tglRujukan = Carbon::parse($json['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan']);
                    $batas = $tglRujukan->copy()->addMonths(3);
                    $sisaHari = (int) now()->diffInDays($batas, false);
                    $row->masa_rujukan = 'Masa berlaku Rujukan <br>' . $tglRujukan->format('d/m/Y') . ' s/d ' . $batas->format('d/m/Y') . '<br>Sisa : ' . $sisaHari . ' hari';
                } else {
                    $row->masa_rujukan = null;
                }

                $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';
                $row->administrasi_detail = $json['AdministrasiRj'] ?? null;
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

                if ($isDokterOrPerawat) {
                    $row->status_text = ['A' => 'Belum Dilayani', 'L' => 'Selesai'][$row->erm_status] ?? 'Pelayanan';
                    $row->status_variant = ['A' => 'warning', 'L' => 'success'][$row->erm_status] ?? 'gray';
                } else {
                    $row->status_text = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Inap/Rujuk'][$row->rj_status] ?? 'Pelayanan';
                    $row->status_variant = ['A' => 'warning', 'L' => 'success', 'F' => 'danger', 'I' => 'brand'][$row->rj_status] ?? 'gray';
                }

                return $row;
            });

        // ── 2. Fetch & transform pending bookings ─────────────────────────
        $pendingRows = $this->queryPendingBookings($search)->map(function ($row) {
            $row->is_booking_pending = true;
            $row->no_antrian = (int) $row->no_antrian; // VARCHAR2 → int untuk sort
            $row->emr_percent = 0;
            $row->eresep_percent = 0;
            $row->task_id3 = null;
            $row->task_id4 = null;
            $row->task_id5 = null;
            $row->no_referensi = null;
            $row->masa_rujukan = null;
            $row->admin_user = '-';
            $row->administrasi_detail = null;
            $row->tindak_lanjut = '-';
            $row->tindak_lanjut_detail = null;
            $row->tgl_kontrol = '-';
            $row->no_skdp_bpjs = '-';
            $row->kontrol_detail = null;
            $row->diagnosis = '-';
            $row->diagnosis_free_text = '-';
            $row->diagnosis_detail = null;
            $row->procedure = '-';
            $row->procedure_free_text = '-';
            $row->procedure_detail = null;
            $row->status_resep = null;
            $row->status_resep_label = '-';
            $row->status_resep_color = 'gray';
            $row->no_booking = $row->rj_no;
            $row->rj_no_json = '-';
            $row->is_json_valid = true;
            $row->bg_check_json = '';
            $row->status_text = 'Menunggu Checkin';
            $row->status_variant = 'warning';
            $row->klaim_id = 'JM';
            $row->klaim_desc = 'JKN Mobile';
            $row->klaim_status = 'BPJS';
            $row->vno_sep = '-';
            $row->rj_status = 'PENDING';
            $row->erm_status = 'A';
            $row->lab_status = 0;
            $row->rad_status = 0;
            $row->shift = '-';
            $row->rj_date_display = $row->rj_date_display ?? '-';

            if (!empty($row->birth_date)) {
                try {
                    $d = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                    $diff = $d->diff(now());
                    $row->umur_format = "{$row->birth_date} ({$diff->y} Thn {$diff->m} Bln {$diff->d} Hr)";
                } catch (\Exception) {
                    $row->umur_format = '-';
                }
            } else {
                $row->umur_format = '-';
            }

            return $row;
        });

        // ── 3. Merge, sort: dr_name DESC → no_antrian ASC ─────────────────
        $allRows = $rjRows
            ->merge($pendingRows)
            ->sort(function ($a, $b) {
                $drCmp = strcmp($b->dr_name ?? '', $a->dr_name ?? '');
                if ($drCmp !== 0) {
                    return $drCmp;
                }
                return (int) ($a->no_antrian ?? 0) - (int) ($b->no_antrian ?? 0);
            })
            ->values();

        // ── 4. Manual paginate ─────────────────────────────────────────────
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage = $this->itemsPerPage;

        return new \Illuminate\Pagination\LengthAwarePaginator($allRows->slice(($page - 1) * $perPage, $perPage)->values(), $allRows->count(), $perPage, $page, ['path' => request()->url()]);
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
        // ✅ Range bulanan — gunakan whereBetween startOfMonth..endOfMonth
        [$start, $end] = $this->dateRange();
        $query = DB::table('rstxn_ugdhdrs')->select('rstxn_ugdhdrs.dr_id', DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'), 'rstxn_ugdhdrs.poli_id', DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'), DB::raw('COUNT(DISTINCT rstxn_ugdhdrs.rj_no) as total_pasien'))->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_ugdhdrs.dr_id')->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_ugdhdrs.poli_id')->whereBetween('rstxn_ugdhdrs.rj_date', [$start, $end]);

        if (!empty($this->filterStatus)) {
            $statusColumn = $this->isDokterOrPerawat() ? 'rstxn_ugdhdrs.erm_status' : 'rstxn_ugdhdrs.rj_status';
            $query->where($statusColumn, $this->filterStatus);
        }

        if (!empty($this->searchKeyword) && strlen($this->searchKeyword) >= 2) {
            $keyword = strtoupper($this->searchKeyword);
            $query->where(function ($q) use ($keyword) {
                $q->where(DB::raw('UPPER(rsmst_doctors.dr_name)'), 'LIKE', "%{$keyword}%")->orWhere(DB::raw('UPPER(rsmst_polis.poli_desc)'), 'LIKE', "%{$keyword}%");
            });
        }

        return $query->groupBy('rstxn_ugdhdrs.dr_id', 'rstxn_ugdhdrs.poli_id')->orderBy('poli_desc')->orderBy('dr_name')->get();
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
};
?>

{{-- ✅ wire:key di level paling atas — seluruh halaman re-render saat filter berubah --}}
{{-- Child components aman karena punya static wire:key masing-masing              --}}
<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Daftar Pasien Bulanan UGD
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-700">
                List pasien UGD dalam 1 bulan (filter mm/yyyy)
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('daftar-ugd-bulanan-toolbar', []) }}">

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
                                placeholder="Cari No UGD / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER BULAN (mm/yyyy) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                        </div>
                    </div>

                    {{-- FILTER STATUS — opsi berbeda berdasarkan role --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            @if (auth()->user()->hasAnyRole(['Dokter', 'Perawat']))
                                {{-- Berdasarkan erm_status --}}
                                <option value="A">Belum Dilayani</option>
                                <option value="L">Selesai</option>
                            @else
                                {{-- Berdasarkan rj_status --}}
                                <option value="A">Antrian</option>
                                <option value="L">Selesai</option>
                                <option value="F">Batal</option>
                                <option value="I">Rujuk</option>
                            @endif
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM (BPJS / UMUM) --}}
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

                        {{-- Create disabled: modul ini read-only untuk casemix/admin/tu --}}
                    </div>

                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">

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
                                    class="transition rounded-2xl
                                    {{ $row->is_booking_pending
                                        ? 'bg-amber-50 dark:bg-amber-900/10 hover:shadow-md hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400'
                                        : 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800' }}">

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
                                                    {{ $row->reg_name ?? '-' }} /
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
                                            {{ $row->dr_name ?? '-' }} / {{ $row->klaim_desc ?? '-' }}
                                        </div>
                                        <div class="font-mono text-base text-gray-700 dark:text-gray-300">
                                            {{ $row->vno_sep ?? '-' }}
                                        </div>
                                        <div class="text-xs text-gray-700 dark:text-gray-400">
                                            No Booking: {{ $row->no_booking ?? '-' }}
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if ($row->lab_status)
                                                <x-badge variant="alternative">Laborat</x-badge>
                                            @endif
                                            @if ($row->rad_status)
                                                <x-badge variant="brand">Radiologi</x-badge>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            {{ $row->rj_date_display ?? '-' }} | Shift : {{ $row->shift ?? '-' }}
                                        </div>

                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        @if (!$row->is_booking_pending)
                                            {{-- EMR progress --}}
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

                                            @if ($row->status_resep)
                                                <x-badge :variant="$row->status_resep_color">
                                                    Status Resep: {{ $row->status_resep_label }}
                                                </x-badge>
                                            @endif

                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                            </div>

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

                                            <div
                                                class="text-xs p-1 rounded {{ $row->bg_check_json }} dark:bg-opacity-20">
                                                <span class="font-semibold">Validasi Data:</span><br>
                                                RJ No: {{ $row->rj_no }} / {{ $row->rj_no_json }}
                                                @if (!$row->is_json_valid)
                                                    <span class="text-red-600 dark:text-red-400">(Tidak Sinkron)</span>
                                                @endif
                                            </div>
                                        @else
                                            {{-- Pending booking — hanya info no booking --}}
                                            <div class="text-xs text-amber-700 dark:text-amber-400 font-mono mt-1">
                                                No Booking: {{ $row->rj_no }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Administrasi :
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ $row->admin_user ?? '-' }}
                                            </span>
                                        </div>

                                        @if ($row->administrasi_detail)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Waktu: {{ $row->administrasi_detail['waktu'] ?? '-' }}<br>
                                                Log: {{ $row->administrasi_detail['userLog'] ?? '-' }}
                                            </div>
                                        @endif

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
                                            Tindak Lanjut : {{ $row->tindak_lanjut ?? '-' }}
                                        </div>

                                        @if ($row->tindak_lanjut_detail && ($row->tindak_lanjut_detail['tindakLanjut'] ?? null))
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Dokter: {{ $row->tindak_lanjut_detail['drPemeriksa'] ?? '-' }}
                                            </div>
                                        @endif

                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            Tanggal Kontrol : {{ $row->tgl_kontrol ?? '-' }}
                                        </div>

                                        @if ($row->no_skdp_bpjs && $row->no_skdp_bpjs != '-')
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                No SKDP BPJS: {{ $row->no_skdp_bpjs }}
                                            </div>
                                        @endif

                                        @if ($row->kontrol_detail)
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
                                                Poli Kontrol: {{ $row->kontrol_detail['poliKontrol'] ?? '-' }}<br>
                                                Dokter Kontrol: {{ $row->kontrol_detail['dokterKontrol'] ?? '-' }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-top">
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
                                                <span class="text-xs text-gray-400">
                                                    Aksi tersedia<br>setelah checkin
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
                                                        <x-loading />
                                                        Mencetak...
                                                    </span>
                                                </x-secondary-button>

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

                                                            {{-- GRID 2 KOLOM --}}
                                                            <div class="grid grid-cols-2 gap-1">

                                                                {{-- Kirim iDRG — Admin & Casemix, BPJS + rj_status=Selesai --}}
                                                                @hasanyrole('Admin|Casemix')
                                                                    @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM') && $row->rj_status === 'L')
                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="openIdrg('{{ $row->rj_no }}')"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-brand/5 hover:bg-brand/10 dark:bg-brand-lime/10 dark:hover:bg-brand-lime/20">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                                </svg>
                                                                                <span>
                                                                                    Kirim iDRG / INACBG <br>
                                                                                    <span
                                                                                        class="font-semibold">{{ $row->reg_name }}</span>
                                                                                </span>
                                                                            </div>
                                                                        </x-dropdown-link>
                                                                    @endif
                                                                @endhasanyrole

                                                                {{-- Berkas BPJS — Admin/Casemix/Tu --}}
                                                                @hasanyrole('Admin|Casemix|Tu')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openBerkasBpjs({{ $row->rj_no }})"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-amber-700"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            <span>
                                                                                Berkas BPJS <br>
                                                                                <span class="font-semibold">SEP / Klaim / RM / SKDP / Lain</span>
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
                                                                    wire:click.prevent="requestDelete('{{ $row->rj_no }}')"
                                                                    class="w-full px-3 py-2 text-sm font-semibold text-red-600 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50">
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

            {{-- Sibling action components — listen event dispatch dari main --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.idrg-ugd-actions wire:key="idrg-ugd-actions" />
            <livewire:pages::transaksi.rj.daftar-ugd-bulanan.berkas-bpjs-ugd-actions
                wire:key="berkas-bpjs-ugd-actions" />

        </div>
    </div>
</div>
