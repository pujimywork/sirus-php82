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
    public string $filterMode = 'bulanan'; // 'bulanan' | 'harian'
    public string $filterBulan = ''; // format m/Y (mm/yyyy) — dipakai mode bulanan
    public string $filterTanggal = ''; // format d/m/Y (dd/mm/yyyy) — dipakai mode harian
    public string $filterStatus = 'L'; // default: Selesai
    public string $filterKlaim = 'BPJS'; // default: BPJS | '' | 'UMUM'
    public string $filterPoli = '';
    public string $filterDokter = '';
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedFilterMode(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
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
        // Tidak incrementVersion — wire:key remount toolbar di tengah ketik bikin
        // search input kehilangan focus, backspace berikutnya memicu browser back.
        $this->resetPage();
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
        $this->filterMode = 'bulanan';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-ugd-bulanan-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    /*
     | NOTE: Modul ini untuk casemix/admin/tu — fokus ke administrasi BPJS &
     | iDRG/INACBG. Aksi yang aktif: Kirim iDRG, Administrasi (view-only),
     | Berkas BPJS, Hapus.
     | Aksi pelayanan harian (Task ID, Rekam Medis, Modul Dokumen, Satu Sehat)
     | dan aksi mutasi data (Create, Edit) sengaja dihilangkan dari scope
     | bulanan. Administrasi DIBUKA tapi force read-only (lihat
     | openAdministrasi() — dispatch readOnly:true) supaya Casemix bisa
     | verifikasi tagihan vs klaim tanpa risiko ubah data.
     */
    private function openCreate(): void
    {
        $this->dispatch('daftar-ugd.create.open');
    }

    public function openIdrg(string $rjNo): void
    {
        $this->dispatch('daftar-ugd.idrg.open', rjNo: $rjNo);
    }

    // Buka modal Administrasi dalam mode view-only — Casemix verifikasi tagihan vs klaim.
    // Hanya dipakai dari bulanan (lihat catatan modul di atas — mutasi data dilarang dari bulanan).
    public function openAdministrasi(int $rjNo): void
    {
        $this->dispatch('emr-ugd.administrasi.open', rjNo: $rjNo, readOnly: true);
    }

    /* -------------------------
     | Berkas BPJS — dispatch ke sibling daftar-ugd-bulanan-actions
     * ------------------------- */
    public function openBerkasBpjs(int $rjNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rjNo);
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
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $statusColumn = DB::raw("NVL(h.rj_status, 'A')");

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
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.vno_sep)'), 'like', "%{$kw}%");
            });
        }

        return $query;
    }

    private function dateRange(): array
    {
        // Mode 'harian' → range satu hari (startOfDay..endOfDay)
        // Mode 'bulanan' → range satu bulan (startOfMonth..endOfMonth)
        if ($this->filterMode === 'harian') {
            try {
                $d = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
            } catch (\Exception $e) {
                $d = Carbon::now()->startOfDay();
            }
            return [$d, (clone $d)->endOfDay()];
        }

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
        // Paginate DB-level — JSON decode hanya untuk page aktif (~10 row),
        // bukan seluruh record bulan itu.
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn($row) => $this->transformRow($row))
        );

        return $paginator;
    }

    private function transformRow($row)
    {
        $json = json_decode($row->datadaftarugd_json ?? '{}', true);

        $row->admin_user = isset($json['AdministrasiRj']) ? $json['AdministrasiRj']['userLog'] ?? '✔' : '-';
        $row->administrasi_detail = $json['AdministrasiRj'] ?? null;
        $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
        $row->no_skdp_bpjs = $json['kontrol']['noSKDPBPJS'] ?? '-';

        $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode('# ', array_column($json['diagnosis'], 'icdX')) : '-';
        $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';
        $row->diagnosis_detail = $json['diagnosis'] ?? null;
        $row->procedure = isset($json['procedure']) && is_array($json['procedure']) ? implode('# ', array_column($json['procedure'], 'procedureId')) : '-';
        $row->procedure_free_text = $json['procedureFreeText'] ?? '-';
        $row->procedure_detail = $json['procedure'] ?? null;

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

        $row->status_text = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Transfer Inap'][$row->rj_status] ?? 'Pelayanan';
        $row->status_variant = ['A' => 'warning', 'L' => 'success', 'F' => 'danger', 'I' => 'brand'][$row->rj_status] ?? 'gray';

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
        // NOTE: dokterList HANYA depend pada range bulan — "semua dokter yang
        // praktek di bulan tersebut". Filter lain (status, klaim, poli, searchKeyword)
        // sengaja TIDAK dipakai supaya opsi dropdown stabil: user bisa pindah-pindah
        // status/klaim tanpa kehilangan dokter yang sudah dipilih, meskipun query
        // utama jadi kosong.
        [$start, $end] = $this->dateRange();
        return DB::table('rstxn_ugdhdrs')
            ->select(
                'rstxn_ugdhdrs.dr_id',
                DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'),
                'rstxn_ugdhdrs.poli_id',
                DB::raw('MAX(rsmst_polis.poli_desc) as poli_desc'),
                DB::raw('COUNT(DISTINCT rstxn_ugdhdrs.rj_no) as total_pasien'),
            )
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_ugdhdrs.dr_id')
            ->join('rsmst_polis', 'rsmst_polis.poli_id', '=', 'rstxn_ugdhdrs.poli_id')
            ->whereBetween('rstxn_ugdhdrs.rj_date', [$start, $end])
            ->groupBy('rstxn_ugdhdrs.dr_id', 'rstxn_ugdhdrs.poli_id')
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
};
?>

{{-- ✅ wire:key di level paling atas — seluruh halaman re-render saat filter berubah --}}
{{-- Child components aman karena punya static wire:key masing-masing              --}}
<div>
    <x-page-title
        title="Daftar Pasien UGD — Casemix"
        subtitle="Filter Bulanan (mm/yyyy) atau Harian (dd/mm/yyyy). Pencarian: No UGD / No RM / Nama / No SEP." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('daftar-ugd-bulanan-toolbar', []) }}">

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
                                placeholder="Cari No UGD / No RM / Nama Pasien / No SEP..." />
                        </div>
                    </div>

                    {{-- MODE FILTER: Bulanan / Harian --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Mode" />
                        <div class="inline-flex mt-1 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                            <button type="button" wire:click="$set('filterMode', 'bulanan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors
                                    {{ $filterMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Bulanan
                            </button>
                            <button type="button" wire:click="$set('filterMode', 'harian')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                    {{ $filterMode === 'harian' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Harian
                            </button>
                        </div>
                    </div>

                    {{-- FILTER BULAN (mm/yyyy) atau TANGGAL (dd/mm/yyyy) --}}
                    @if ($filterMode === 'bulanan')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                    class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                            </div>
                        </div>
                    @else
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
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                                    class="block w-full pl-10 sm:w-44" placeholder="dd/mm/yyyy" maxlength="10" />
                            </div>
                        </div>
                    @endif

                    {{-- FILTER STATUS — opsi berbeda berdasarkan role --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="A">Antrian</option>
                            <option value="L">Selesai</option>
                            <option value="F">Batal</option>
                            <option value="I">Transfer Inap</option>
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

                        {{-- Create disabled: modul ini read-only untuk casemix/admin/tu --}}
                    </div>

                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">

                        <thead class="sticky top-0 z-10 bg-surface-soft dark:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Poli</th>
                                <th class="px-6 py-3">Status Layanan</th>
                                <th class="px-6 py-3">Tindak Lanjut</th>
                                <th class="px-6 py-3 text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                            @forelse($this->rows as $row)
                                <tr wire:key="daftar-ugd-bulanan-{{ $row->rj_no ?? $loop->index }}" class="transition hover:bg-green-50 dark:hover:bg-gray-800/50">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-top">
                                        <div class="space-y-1">
                                            <div class="text-base font-medium text-body dark:text-gray-300">
                                                {{ $row->reg_no ?? '-' }}
                                            </div>
                                            <div class="text-lg font-semibold text-brand dark:text-white">
                                                {{ $row->reg_name ?? '-' }} /
                                                ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                            </div>
                                            <div class="text-sm text-body dark:text-gray-400">
                                                {{ $row->birth_date ?? '-' }}
                                                @if (!empty($row->umur_format) && $row->umur_format !== '-')
                                                    <span class="text-muted">({{ $row->umur_format }})</span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                {{ $row->address ?? '-' }}
                                            </div>
                                        </div>
                                    </td>

                                    {{-- POLI --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="text-sm font-semibold text-brand dark:text-emerald-400">
                                            {{ $row->poli_desc ?? '-' }}
                                        </div>
                                        <div class="text-sm text-muted dark:text-gray-400">
                                            {{ $row->dr_name ?? '-' }} / {{ $row->klaim_desc ?? '-' }}
                                        </div>
                                        <div class="font-mono text-sm text-body dark:text-gray-300">
                                            {{ $row->vno_sep ?? '-' }}
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
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm text-body dark:text-gray-400">
                                                {{ $row->rj_date_display ?? '-' }}
                                            </span>
                                            <x-badge :variant="$row->status_variant">
                                                {{ $row->status_text }}
                                            </x-badge>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3 pt-1">
                                            <div class="text-xs text-muted dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $row->diagnosis }} / {{ $row->diagnosis_free_text }}
                                            </div>

                                            <div class="text-xs text-muted dark:text-gray-400">
                                                <span class="font-semibold">Procedure:</span><br>
                                                {{ $row->procedure }} / {{ $row->procedure_free_text }}
                                            </div>
                                        </div>
                                    </td>

                                    {{-- TINDAK LANJUT --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        @if ($row->admin_user && $row->admin_user !== '-')
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                Administrasi :
                                                <span class="font-semibold text-ink dark:text-gray-200">
                                                    {{ $row->admin_user }}
                                                </span>
                                            </div>
                                        @endif

                                        @if (!empty($row->administrasi_detail['userLogDate']))
                                            <div class="text-xs text-body dark:text-gray-400">
                                                Waktu administrasi: {{ $row->administrasi_detail['userLogDate'] }}
                                            </div>
                                        @endif

                                        {{-- Tindak Lanjut + No SKDP BPJS sengaja di-hide di Casemix Daftar Bulanan
                                             — info tsb tidak relevan untuk alur casemix. --}}
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-top">
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

                                                                {{-- Kirim iDRG — Admin, Casemix, Tu; BPJS + rj_status=Selesai --}}
                                                                @hasanyrole('Admin|Casemix|Tu')
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

                                                                {{-- Administrasi (View-only) — Admin/Casemix/Tu; status=Selesai (L) --}}
                                                                @hasanyrole('Admin|Casemix|Tu')
                                                                    @if ($row->rj_status === 'L')
                                                                        <x-dropdown-link href="#"
                                                                            wire:click.prevent="openAdministrasi({{ $row->rj_no }})"
                                                                            class="px-3 py-2 text-sm rounded-lg bg-sky-50 hover:bg-sky-100 dark:bg-sky-900/30 dark:hover:bg-sky-900/40">
                                                                            <div class="flex items-start gap-2">
                                                                                <svg class="w-5 h-5 mt-0.5 shrink-0 text-sky-700"
                                                                                    fill="none" stroke="currentColor"
                                                                                    viewBox="0 0 24 24" stroke-width="2">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                                        d="M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3M3 6a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V6z" />
                                                                                </svg>
                                                                                <span>
                                                                                    Administrasi <br>
                                                                                    <span class="font-semibold">Lihat Tagihan (Read-only)</span>
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

                                                        </div>
                                                    </x-slot>
                                                </x-dropdown>

                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-16 text-center text-body dark:text-gray-400">
                                        Belum ada data
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

            {{-- Sibling action components — listen event dispatch dari main --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.idrg-ugd-actions wire:key="idrg-ugd-actions" />
            <livewire:pages::transaksi.ugd.emr-ugd.log-aktivitas.log-aktivitas-ugd wire:key="log-aktivitas-ugd" />
            <livewire:pages::transaksi.ugd.administrasi-ugd.administrasi-ugd wire:key="administrasi-ugd-readonly" />
            <livewire:pages::transaksi.ugd.daftar-ugd-bulanan.berkas-bpjs-ugd-actions
                wire:key="berkas-bpjs-ugd-actions" />

        </div>
    </div>
</div>
