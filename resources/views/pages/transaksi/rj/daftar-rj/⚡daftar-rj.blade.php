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
    protected array $renderAreas = ['daftar-rj-toolbar'];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    // Daftar RJ = view PENDAFTARAN (Mr/Admin/Sup Tu). Filter Status pakai rj_status.
    // Pelayanan EMR (Dokter/Perawat) dipisah ke /rawat-jalan/pelayanan.
    public string $searchKeyword = '';
    public string $filterTanggal = '';
    public string $filterStatus = 'A'; // rj_status: A=Antrian, L=Selesai, F=Batal, I=Transfer UGD
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
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedFilterKlaim(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-rj-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterPoli', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-rj-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('daftar-rj.create.open');
    }

    public function openEdit(string $rjNo): void
    {
        $this->dispatch('daftar-rj.edit.open', rjNo: $rjNo);
    }

    public function openSatuSehat(string $rjNo): void
    {
        $this->dispatch('daftar-rj.satu-sehat.open', rjNo: $rjNo);
    }

    public function openBerkasBpjs(int $rjNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rjNo);
    }

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
        $this->incrementVersion('daftar-rj-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries — Daftar RJ (Pendaftaran): status pakai rj_status
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $statusColumn = DB::raw("NVL(h.rj_status, 'A')");

        // Subquery lab/rad — scope ke RJ yg masuk date range agar tidak full-scan tabel besar.
        // JOIN ke rstxn_rjhdrs membatasi baris yg dihitung hanya untuk rj_no di hari aktif.
        $labSub = DB::table('lbtxn_checkuphdrs as l')
            ->join('rstxn_rjhdrs as hd', 'hd.rj_no', '=', 'l.ref_no')
            ->select('l.ref_no', DB::raw('COUNT(*) as lab_status'))
            ->where('l.status_rjri', 'RJ')
            ->where('l.checkup_status', '!=', 'B')
            ->whereBetween('hd.rj_date', [$start, $end])
            ->groupBy('l.ref_no');

        $radSub = DB::table('rstxn_rjrads as r')
            ->join('rstxn_rjhdrs as hd', 'hd.rj_no', '=', 'r.rj_no')
            ->select('r.rj_no', DB::raw('COUNT(*) as rad_status'))
            ->whereBetween('hd.rj_date', [$start, $end])
            ->groupBy('r.rj_no');

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rj_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rj_no', '=', 'h.rj_no'))
            ->select(['h.rj_no', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"), 'h.reg_no', 'p.reg_name', 'p.sex', 'p.address', 'p.nokartu_bpjs', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'h.no_antrian', 'h.poli_id', 'po.poli_desc', 'h.dr_id', 'd.dr_name', 'h.klaim_id', 'h.shift', 'h.rj_status', 'h.erm_status', 'h.vno_sep', DB::raw('COALESCE(lab.lab_status, 0) as lab_status'), DB::raw('COALESCE(rad.rad_status, 0) as rad_status'), 'h.datadaftarpolirj_json', 'k.klaim_desc', 'k.klaim_status'])
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
        if (!in_array($this->filterStatus, ['A', ''])) {
            return collect();
        }

        // Guard filterTanggal kosong/format invalid — fallback ke hari ini
        try {
            $tanggalPeriksa = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->format('Y-m-d');
        } catch (\Exception $e) {
            $tanggalPeriksa = now()->format('Y-m-d');
        }

        $q = DB::table('referensi_mobilejkn_bpjs as b')
            ->leftJoin('rsmst_pasiens as p', DB::raw('UPPER(p.reg_no)'), '=', DB::raw('UPPER(b.norm)'))
            ->leftJoin('rsmst_polis as pol', 'pol.kd_poli_bpjs', '=', 'b.kodepoli')
            ->leftJoin('rsmst_doctors as d', 'd.kd_dr_bpjs', '=', 'b.kodedokter')
            ->select(['b.nobooking as rj_no', DB::raw("TO_CHAR(TO_DATE(b.tanggalperiksa,'yyyy-mm-dd'),'dd/mm/yyyy') || ' ' || SUBSTR(b.jampraktek,1,5) || ':00' as rj_date_display"), DB::raw('UPPER(b.norm) as reg_no'), 'p.reg_name', 'p.sex', 'p.address', DB::raw("TO_CHAR(p.birth_date,'dd/mm/yyyy') AS birth_date"), 'b.angkaantrean as no_antrian', 'b.nomorantrean', 'pol.poli_desc', 'd.dr_name'])
            ->where('b.tanggalperiksa', $tanggalPeriksa)
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
        // DB-level paginate baseQuery — transform hanya page aktif (~10 row), bukan ratusan.
        // Pola sama dgn daftar-rj-bulanan/rows(). Pending booking (MJKN) di-append
        // hanya di page 1 supaya tidak mengganggu page count.
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $rjRows = $paginator->getCollection()->map(fn($row) => $this->transformRjRow($row));

        // ── FITUR "Menunggu Checkin" (pending booking MJKN) DINONAKTIFKAN SEMENTARA ──
        // Jangan dihapus — method queryPendingBookings()/transformPendingRow() tetap ada.
        // Aktifkan lagi dengan meng-uncomment blok di bawah.
        /*
        if ($paginator->currentPage() === 1) {
            $pendingRows = $this->queryPendingBookings(trim($this->searchKeyword))->map(fn($row) => $this->transformPendingRow($row));

            // Sort merge: dr_name DESC → no_antrian ASC (sama dgn behavior lama)
            $rjRows = $rjRows
                ->merge($pendingRows)
                ->sort(function ($a, $b) {
                    $drCmp = strcmp($b->dr_name ?? '', $a->dr_name ?? '');
                    if ($drCmp !== 0) {
                        return $drCmp;
                    }
                    return (int) ($a->no_antrian ?? 0) - (int) ($b->no_antrian ?? 0);
                })
                ->values();
        }
        */

        $paginator->setCollection($rjRows);
        return $paginator;
    }

    private function transformRjRow($row)
    {
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
        $pct = $this->calculateEmrPercentRJ($json);
        $row->emr_percent = $pct['emr'];
        $row->emr_sections = $pct['sections'];
        $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;
        $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
        $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
        $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;
        $row->task_id99 = $json['taskIdPelayanan']['taskId99'] ?? null;
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
                $row->umur_format = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
            } catch (\Exception $e) {
                $row->umur_format = '-';
            }
        } else {
            $row->umur_format = '-';
        }

        // Status berdasarkan urutan Task ID (3=Pendaftaran, 4=Masuk Poli, 5=Keluar Poli, 6=Menunggu Resep, 7=Terima Resep, 99=Batal)
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
    }

    private function transformPendingRow($row)
    {
        $row->is_booking_pending = true;
        $row->no_antrian = (int) $row->no_antrian;
        $row->emr_percent = 0;
        $row->emr_sections = ['s' => 0, 'o' => 0, 'a' => 0, 'p' => 0, 'n' => 0];
        $row->eresep_percent = 0;
        $row->task_id3 = null;
        $row->task_id4 = null;
        $row->task_id5 = null;
        $row->task_id99 = null;
        $row->no_referensi = null;
        $row->masa_rujukan = null;
        $row->admin_user = '-';
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
                $row->umur_format = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
            } catch (\Exception) {
                $row->umur_format = '-';
            }
        } else {
            $row->umur_format = '-';
        }

        return $row;
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
        // NOTE: dokterList HANYA depend pada range tanggal — "semua dokter yang
        // praktek di periode tersebut". Filter lain (status, klaim, searchKeyword)
        // sengaja TIDAK dipakai supaya opsi dropdown stabil: user bisa pindah-pindah
        // status/klaim tanpa kehilangan dokter yang sudah dipilih, meskipun query
        // utama jadi kosong.
        [$start, $end] = $this->dateRange();
        return DB::table('rstxn_rjhdrs')
            ->select(
                'rstxn_rjhdrs.dr_id',
                DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'),
                'rstxn_rjhdrs.poli_id',
                DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'),
                DB::raw('COUNT(DISTINCT rstxn_rjhdrs.rj_no) as total_pasien'),
            )
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_rjhdrs.dr_id')
            ->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_rjhdrs.poli_id')
            ->whereBetween('rstxn_rjhdrs.rj_date', [$start, $end])
            ->groupBy('rstxn_rjhdrs.dr_id', 'rstxn_rjhdrs.poli_id')
            ->orderBy('poli_desc')
            ->orderBy('dr_name')
            ->get();
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

    /* Scan Wajah BPJS via sirus-frista-agent.exe di PC pendaftaran.
       Trigger sibling component <scan-wajah-frista> yang resolve No. Kartu BPJS →
       JS fetch ke http://localhost:9998 → buka FRISTA + auto-login + ketik No. Kartu. */
    public function scanWajahFrista(string $regNo): void
    {
        $this->dispatch('scan-wajah-frista.buka', regNo: $regNo);
    }
};
?>

{{-- ✅ wire:key di level paling atas — seluruh halaman re-render saat filter berubah --}}
{{-- Child components aman karena punya static wire:key masing-masing              --}}
<div>
    <x-page-title
        title="Daftar Rawat Jalan"
        subtitle="Kelola pendaftaran pasien rawat jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('daftar-rj-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
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
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live="filterTanggal"
                                class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" />
                        </div>
                    </div>

                    {{-- FILTER STATUS — rj_status (pendaftaran) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Transfer UGD</option>
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

                        {{-- Pendaftaran Rawat Jalan — Mr, Admin, Supervisor Tu --}}
                        @hasanyrole(['Mr', 'Admin', 'Supervisor Tu'])
                            <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Pendaftaran Rawat Jalan
                            </x-primary-button>
                        @endhasanyrole
                    </div>

                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div
                    class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full -mt-2 text-sm border-separate border-spacing-y-2 table-fixed">

                        <thead class="sticky top-0 z-10">
                            <tr
                                class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 w-[24%] bg-surface-card dark:bg-gray-800">Pasien</th>
                                <th class="px-6 py-3 w-[20%] bg-surface-card dark:bg-gray-800">Poli</th>
                                <th class="px-6 py-3 w-[16%] bg-surface-card dark:bg-gray-800">Status Layanan</th>
                                <th class="px-6 py-3 w-[18%] bg-surface-card dark:bg-gray-800">Tindak Lanjut</th>
                                <th class="px-6 py-3 w-[22%] text-center bg-surface-card dark:bg-gray-800">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="rj-row-{{ $row->rj_no }}" x-data="{ expanded: false }" style="position: relative;"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                    {{-- Status warna token standar: booking=warning · batal=error · ada SEP=success · default=canvas --}}
                                    {{ $row->is_booking_pending
                                        ? 'bg-canvas dark:bg-gray-900 hover:shadow-md hover:bg-warning/10 dark:hover:bg-amber-900/10 border-l-4 border-warning'
                                        : ($row->status_text === 'Batal'
                                            ? 'bg-error/5 dark:bg-red-900/10 hover:shadow-md hover:bg-error/10 dark:hover:bg-red-900/20 border-l-4 border-error'
                                            : (!empty($row->vno_sep) && $row->vno_sep !== '-'
                                                ? 'bg-success/10 dark:bg-gray-800 hover:shadow-lg hover:bg-success/20 dark:hover:bg-gray-700'
                                                : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800')) }}">

                                    {{-- PASIEN --}}
                                    <td class="px-2 pt-2 pb-7 space-y-2 align-middle">
                                        {{-- Toggle Detail chevron — absolute, bottom-center row (di dalam card) --}}
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
                                                <div class="font-mono text-sm font-medium text-muted dark:text-gray-400">
                                                    {{ $row->reg_no ?? '-' }}
                                                </div>
                                                <div class="text-lg font-semibold text-brand dark:text-white">
                                                    {{ $row->reg_name ?? '-' }} /
                                                    ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>
                                                <div x-show="expanded" x-collapse class="text-sm text-body dark:text-gray-400">
                                                    {{ $row->birth_date ?? '-' }}
                                                    @if (!empty($row->umur_format) && $row->umur_format !== '-')
                                                        <span class="text-muted">({{ $row->umur_format }})</span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-muted dark:text-gray-400">
                                                    {{ $row->address ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- POLI --}}
                                    <td class="px-2 pt-2 pb-7 space-y-0.5 align-middle">
                                        <div class="font-semibold text-brand dark:text-emerald-400 leading-tight">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>
                                        <div class="text-sm text-muted dark:text-gray-400 leading-tight">
                                            {{ $row->dr_name ?? '-' }} / {{ $row->klaim_desc ?? '-' }}
                                        </div>
                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            <div class="text-xs text-muted dark:text-gray-500 leading-tight">
                                                {{ $row->rj_date_display ?? '-' }} | Shift : {{ $row->shift ?? '-' }}
                                            </div>
                                            @if ($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM')
                                                <div class="text-xs text-body dark:text-gray-400 leading-tight">
                                                    No Booking: {{ $row->no_booking ?? '-' }}
                                                </div>
                                            @endif
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
                                    <td class="px-2 pt-2 pb-7 space-y-2 align-middle">
                                        <x-badge :variant="$row->status_variant">
                                            {{ $row->status_text }}
                                        </x-badge>

                                        @if (!$row->is_booking_pending)
                                            {{-- EMR progress + EMR/E-Resep % — selalu tampil supaya MR bisa cek kelengkapan tanpa expand --}}
                                            <div class="w-full h-1.5 bg-surface-strong rounded-full dark:bg-gray-700">
                                                <div class="h-1.5 rounded-full transition-all duration-500
                                                    {{ $row->emr_percent >= 80
                                                        ? 'bg-success dark:bg-success'
                                                        : ($row->emr_percent >= 50
                                                            ? 'bg-warning dark:bg-warning'
                                                            : 'bg-error dark:bg-error') }}"
                                                    style="width: {{ $row->emr_percent ?? 0 }}%">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div
                                                    class="flex items-center gap-1 text-xs text-body dark:text-gray-400">
                                                    <span>EMR : {{ $row->emr_percent ?? 0 }}%</span>
                                                    <button type="button"
                                                        x-on:click.stop="$dispatch('open-info-kelengkapan-emr-rj', { rjNo: {{ $row->rj_no }} })"
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
                                            </div>

                                            {{-- <div class="text-xs text-muted dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                            </div> --}}

                                            {{-- @if ($row->procedure !== '-' || $row->procedure_free_text !== '-')
                                                <div class="text-xs text-muted dark:text-gray-400">
                                                    <span class="font-semibold">Procedure:</span><br>
                                                    {{ $row->procedure }} / {{ $row->procedure_free_text }}
                                                </div>
                                            @endif --}}

                                            @if (!empty($row->vno_sep) && $row->vno_sep !== '-')
                                                <div class="font-mono text-base text-body dark:text-gray-300">
                                                    {{ $row->vno_sep }}
                                                </div>
                                            @endif

                                            @if (!empty($row->masa_rujukan))
                                                <div x-show="expanded" x-collapse
                                                    class="px-2 py-1 text-xs text-warning rounded-lg bg-warning/10 dark:bg-warning/20">
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
                                            <div class="text-xs text-warning font-mono mt-1">
                                                No Booking: {{ $row->rj_no }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-2 pt-2 pb-7 space-y-0.5 align-middle">
                                        @if ($row->admin_user && $row->admin_user !== '-')
                                            <div class="text-xs text-muted dark:text-gray-400 leading-tight">
                                                Administrasi :
                                                <span class="font-semibold text-ink dark:text-gray-200">
                                                    {{ $row->admin_user }}
                                                </span>
                                            </div>
                                        @endif

                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            <div class="flex flex-wrap gap-1 py-0.5">
                                                @if ($row->task_id3)
                                                    <x-badge variant="success">T3 {{ $row->task_id3 }}</x-badge>
                                                @endif
                                                @if ($row->task_id4)
                                                    <x-badge variant="brand">T4 {{ $row->task_id4 }}</x-badge>
                                                @endif
                                                @if ($row->task_id5)
                                                    <x-badge variant="warning">T5 {{ $row->task_id5 }}</x-badge>
                                                @endif
                                            </div>

                                            @if ($row->tindak_lanjut && $row->tindak_lanjut !== '-')
                                                <div class="text-xs text-body dark:text-gray-400 leading-tight">
                                                    Tindak Lanjut : {{ $row->tindak_lanjut }}
                                                </div>
                                            @endif

                                            @if (($row->tindak_lanjut_detail['tindakLanjut'] ?? null) === 'Kontrol')
                                                @if (!empty($row->tgl_kontrol) && $row->tgl_kontrol !== '-')
                                                    <div
                                                        class="text-xs text-body dark:text-gray-300 leading-tight">
                                                        Tanggal Kontrol : {{ $row->tgl_kontrol }}
                                                    </div>
                                                @endif

                                                @if ($row->no_skdp_bpjs && $row->no_skdp_bpjs != '-')
                                                    <div
                                                        class="text-xs text-muted dark:text-gray-400 leading-tight">
                                                        No SKDP BPJS: {{ $row->no_skdp_bpjs }}
                                                    </div>
                                                @endif

                                                @if ($row->kontrol_detail)
                                                    <div
                                                        class="text-xs text-body dark:text-gray-400 leading-tight">
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
                                    <td class="px-2 pt-2 pb-7 align-middle">
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
                                                <span x-show="expanded" x-collapse class="text-xs text-muted-soft">
                                                    Aksi tersedia<br>setelah checkin
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex flex-col items-start gap-2">

                                                {{-- Baris atas: tombol aksi sejajar (Batal ditaruh di baris bawah) --}}
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">

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

                                                {{-- Scan Wajah (FRISTA) — HANYA pasien BPJS yang punya No. Kartu.
                                                     Via sirus-frista-agent (localhost:9998): buka FRISTA + auto-login + ketik No. Kartu. --}}
                                                @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM') && trim($row->nokartu_bpjs ?? '') !== '')
                                                    <x-secondary-button wire:click="scanWajahFrista('{{ $row->reg_no }}')"
                                                        wire:loading.attr="disabled" wire:target="scanWajahFrista"
                                                        title="Buka FRISTA & masukkan No. Kartu BPJS peserta untuk scan wajah">
                                                        <span wire:loading.remove wire:target="scanWajahFrista"
                                                            class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2m8-16h2a2 2 0 012 2v2m-4 12h2a2 2 0 002-2v-2M9 10h.01M15 10h.01M9.5 14.5a3.5 3.5 0 005 0" />
                                                            </svg>
                                                            Scan Wajah
                                                        </span>
                                                        <span wire:loading wire:target="scanWajahFrista"
                                                            class="flex items-center gap-1">
                                                            <x-loading />
                                                            Membuka...
                                                        </span>
                                                    </x-secondary-button>
                                                @endif

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

                                                {{-- Dropdown Aksi (titik-tiga) --}}
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

                                                                {{-- Pendaftaran Ubah — Mr, Admin, Supervisor Tu --}}
                                                                @hasanyrole(['Mr', 'Admin', 'Supervisor Tu'])
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openEdit('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                                                            </svg>
                                                                            <span>
                                                                                Pendaftaran Ubah <br>
                                                                                <span
                                                                                    class="font-semibold">{{ $row->reg_name }}</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Riwayat Jadwal Kontrol — Admin, Mr, Tu, Casemix --}}
                                                                @hasanyrole('Admin|Mr|Tu|Casemix')
                                                                    <x-dropdown-link href="#"
                                                                        x-on:click.prevent="$dispatch('riwayat-kontrol.open', { regNo: '{{ $row->reg_no }}', regName: '{{ addslashes($row->reg_name) }}' })"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-900/30 dark:hover:bg-cyan-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-cyan-700"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                            </svg>
                                                                            <span>
                                                                                Riwayat Kontrol <br>
                                                                                <span class="font-semibold">Jadwal SKDP RJ/RI</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endhasanyrole

                                                                {{-- Kirim Satu Sehat — Admin, Mr --}}
                                                                @hasanyrole('Admin|Mr')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openSatuSehat('{{ $row->rj_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-teal-50 hover:bg-teal-100 dark:bg-teal-900/20 dark:hover:bg-teal-900/40">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                            </svg>
                                                                            <span>
                                                                                Kirim Satu Sehat <br>
                                                                                <span
                                                                                    class="font-semibold">{{ $row->reg_name }}</span>
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
                                                                                Berkas BPJS <br>
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
                                                            @endhasanyrole

                                                        </div>
                                                    </x-slot>
                                                </x-dropdown>
                                                </div>{{-- /baris atas tombol aksi sejajar --}}

                                                {{-- Batal (Task ID 99) — baris bawah. Manager Medis/Umum (Admin otomatis via super-user) --}}
                                                @hasanyrole('Admin|Manager Medis|Manager Umum')
                                                    <livewire:pages::transaksi.rj.task-id-pelayanan.task-id-99
                                                        :rjNo="$row->rj_no"
                                                        :isDone="(bool) $row->task_id99"
                                                        wire:key="taskid99-{{ $row->rj_no }}" />
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

            {{-- Sibling components — pendaftaran: Create/Edit + Satu Sehat (Mr/Admin) + Cetak Etiket + Info Kelengkapan EMR --}}
            <livewire:pages::transaksi.rj.daftar-rj.daftar-rj-actions wire:key="daftar-rj-actions" />
            <livewire:pages::transaksi.rj.daftar-rj.satu-sehat-rj-actions wire:key="satu-sehat-rj-actions" />
            <livewire:pages::transaksi.rj.daftar-rj-bulanan.berkas-bpjs-rj-actions wire:key="berkas-bpjs-rj-actions" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-pasien" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket-auto wire:key="cetak-etiket-auto-pasien" />
            <livewire:pages::components.rekam-medis.frista.scan-wajah-frista wire:key="scan-wajah-frista" />
            <livewire:pages::transaksi.rj.daftar-rj.info-kelengkapan-emr wire:key="info-kelengkapan-emr-rj" />
            {{-- Modal Riwayat Jadwal Kontrol per pasien (listen: riwayat-kontrol.open) --}}
            <livewire:pages::components.rekam-medis.riwayat-kontrol-pasien.riwayat-kontrol-pasien wire:key="riwayat-kontrol-pasien-rj" />

        </div>
    </div>
</div>
