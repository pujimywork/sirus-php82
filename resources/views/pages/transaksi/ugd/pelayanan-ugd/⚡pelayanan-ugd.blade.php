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
    protected array $renderAreas = ['pelayanan-ugd-toolbar'];

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
        $this->incrementVersion('pelayanan-ugd-toolbar');
    }
    public function updatedFilterDokter(): void
    {
        $this->resetPage();
        $this->incrementVersion('pelayanan-ugd-toolbar');
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
        $this->incrementVersion('pelayanan-ugd-toolbar');
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter']);
        $this->filterStatus = 'A';
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('pelayanan-ugd-toolbar');
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers — pelayanan-only (no create/edit/idrg/delete)
     * ------------------------- */
    public function openRekamMedis(string $rjNo): void
    {
        $this->dispatch('emr-ugd.rekam-medis.open', rjNo: $rjNo);
    }

    public function openModulDokumen(int $rjNo): void
    {
        $this->dispatch('emr-ugd.modul-dokumen.open', rjNo: $rjNo);
    }

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-ugd.administrasi.open', rjNo: $rjNo);
    }

    // Cek status peserta BPJS via VClaim — petugas UGD verifikasi keaktifan
    // kartu saat pelayanan (input No Kartu 13 digit atau NIK 16 digit).
    public function openCekPesertaBpjs(): void
    {
        $this->dispatch('cek-peserta-bpjs.open');
    }

    /* -------------------------
     | Refresh setelah child save
     * ------------------------- */
    #[On('refresh-after-ugd.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('pelayanan-ugd-toolbar');
        $this->resetPage();
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

        // Pelayanan UGD fokus EMR (erm_status: A=Proses Dilayani, L=Selesai)
        $statusColumn = DB::raw("NVL(h.erm_status, 'A')");

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

    /**
     * Ambil penilaian risiko jatuh TERAKHIR dari penilaian.resikoJatuh[].
     * "Terakhir" = tglPenilaian paling baru (input manual, bisa diisi mundur —
     * urutan array tidak dijamin kronologis); fallback urutan input.
     * Return kosong jika kategori bukan Sedang/Tinggi → penanda tidak tampil.
     * Sengaja inline per komponen (bukan helper bersama) — struktur JSON tiap
     * modul bisa berubah sendiri-sendiri tanpa saling merusak.
     */
    private function hitungResikoJatuhTerakhir(array $dataEmr): array
    {
        $list = $dataEmr['penilaian']['resikoJatuh'] ?? [];
        $terakhir = null;
        $maxTimestamp = null;
        foreach (is_array($list) ? $list : [] as $entri) {
            try {
                $timestamp = Carbon::createFromFormat('d/m/Y H:i:s', trim($entri['tglPenilaian'] ?? ''))->getTimestamp();
            } catch (\Throwable) {
                $timestamp = null;
            }
            // >= : tanggal sama/tak terparse → entri yang diinput belakangan menang
            if ($terakhir === null || $timestamp === null || $maxTimestamp === null || $timestamp >= $maxTimestamp) {
                $terakhir = $entri;
                $maxTimestamp = $timestamp ?? $maxTimestamp;
            }
        }

        $kategori = $terakhir['resikoJatuh']['kategoriResiko'] ?? '';
        if (!in_array($kategori, ['Sedang', 'Tinggi'], true)) {
            return [];
        }

        return [
            'kategori' => $kategori,
            'metode' => $terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '',
            'skor' => (string) ($terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? ''),
            'tgl' => $terakhir['tglPenilaian'] ?? '',
        ];
    }

    /**
     * Ambil skrining risiko bunuh diri (C-SSRS) TERAKHIR dari penilaian.resikoBunuhDiri[].
     * Pola sama dgn hitungResikoJatuhTerakhir; return kosong jika kategori
     * bukan Rendah/Sedang/Tinggi → penanda tidak tampil.
     */
    private function hitungResikoBunuhDiriTerakhir(array $dataEmr): array
    {
        $list = $dataEmr['penilaian']['resikoBunuhDiri'] ?? [];
        $terakhir = null;
        $maxTimestamp = null;
        foreach (is_array($list) ? $list : [] as $entri) {
            try {
                $timestamp = Carbon::createFromFormat('d/m/Y H:i:s', trim($entri['tglPenilaian'] ?? ''))->getTimestamp();
            } catch (\Throwable) {
                $timestamp = null;
            }
            // >= : tanggal sama/tak terparse → entri yang diinput belakangan menang
            if ($terakhir === null || $timestamp === null || $maxTimestamp === null || $timestamp >= $maxTimestamp) {
                $terakhir = $entri;
                $maxTimestamp = $timestamp ?? $maxTimestamp;
            }
        }

        $kategori = $terakhir['kategoriResiko'] ?? '';
        if (!in_array($kategori, ['Rendah', 'Sedang', 'Tinggi'], true)) {
            return [];
        }

        return [
            'kategori' => $kategori,
            'skor' => (string) ($terakhir['skorKeparahan'] ?? ''),
            'tgl' => $terakhir['tglPenilaian'] ?? '',
        ];
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

            /* Penanda risiko jatuh (Sedang/Tinggi) — dari JSON yang sudah di-decode,
               tanpa query tambahan. Kosong = tidak tampil. */
            $row->resiko_jatuh = $this->hitungResikoJatuhTerakhir($json);

            /* Penanda risiko bunuh diri C-SSRS (Rendah/Sedang/Tinggi) */
            $row->resiko_bunuh_diri = $this->hitungResikoBunuhDiriTerakhir($json);

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

            /* Umur */
            $row->umur_format = '-';
            if (!empty($row->birth_date)) {
                try {
                    $lahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                    $diff = $lahir->diff(now());
                    $row->umur_format = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
                } catch (\Exception) {
                }
            }

            /* Status text — Pelayanan UGD fokus EMR (erm_status) */
            // Batal di-detect dari Task ID 99 OR rj_status='F' (legacy mutasi langsung)
            if (!empty($row->task_id99) || $row->rj_status === 'F') {
                $row->status_text = 'Batal';
                $row->status_variant = 'danger';
            } else {
                $statusMap = ['A' => 'Proses Dilayani', 'L' => 'Selesai'];
                $statusVariant = ['A' => 'warning', 'L' => 'success'];
                $row->status_text = $statusMap[$row->erm_status] ?? 'Pelayanan';
                $row->status_variant = $statusVariant[$row->erm_status] ?? 'gray';
            }

            /* Status transaksi (rj_status) — info status pendaftaran/pelayanan terlepas dari EMR */
            $rjStatusMap = ['A' => 'Antrian', 'L' => 'Selesai', 'I' => 'Transfer Inap', 'F' => 'Batal'];
            $rjStatusVariant = ['A' => 'alternative', 'L' => 'success', 'I' => 'brand', 'F' => 'danger'];
            $row->rj_status_text = $rjStatusMap[$row->rj_status] ?? '-';
            $row->rj_status_variant = $rjStatusVariant[$row->rj_status] ?? 'gray';

            /* Death on IGD */
            $row->is_death = ($row->death_on_igd_status ?? 'N') === 'Y';

            return $row;
        });

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
        [$start, $end] = $this->dateRange();

        return DB::table('rstxn_ugdhdrs')
            ->select(
                'rstxn_ugdhdrs.dr_id',
                DB::raw('MAX(rsmst_doctors.dr_name) as dr_name'),
                DB::raw('COUNT(DISTINCT rstxn_ugdhdrs.rj_no) as total_pasien'),
            )
            ->join('rsmst_doctors', 'rsmst_doctors.dr_id', '=', 'rstxn_ugdhdrs.dr_id')
            // whereBetween (sargable) menggantikan to_char(rj_date)= yang mematikan index tanggal.
            ->whereBetween('rstxn_ugdhdrs.rj_date', [$start, $end])
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
    <x-page-title
        title="Pelayanan UGD"
        subtitle="Kelola pelayanan &amp; EMR pasien Unit Gawat Darurat" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 pt-1 pb-2 bg-surface-soft border-b border-hairline top-16 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('pelayanan-ugd-toolbar', []) }}">

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

                    {{-- FILTER STATUS — erm_status --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-44">
                            <option value="">Semua</option>
                            <option value="A">Proses Dilayani</option>
                            <option value="L">Selesai</option>
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
                        {{-- Cek Peserta BPJS (VClaim) — verifikasi keaktifan kartu saat pelayanan --}}
                        @hasanyrole(['Perawat', 'Dokter', 'Admin', 'Casemix', 'Mr'])
                            <x-secondary-button type="button" wire:click="openCekPesertaBpjs"
                                class="whitespace-nowrap text-emerald-700 hover:bg-brand-green/10 dark:text-emerald-300 dark:hover:bg-emerald-900/30">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Cek BPJS
                            </x-secondary-button>
                        @endhasanyrole

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

                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
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
                                <tr wire:key="pelayanan-ugd-{{ $row->rj_no ?? $loop->index }}"
                                    x-data="{ expanded: false }"
                                    style="position: relative;"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           {{ $row->status_text === 'Batal'
                                               ? 'bg-error/5 dark:bg-red-900/10 hover:shadow-md hover:bg-error/10 dark:hover:bg-red-900/20 border-l-4 border-error'
                                               : ($row->erm_status === 'L'
                                                   ? 'bg-emerald-50 dark:bg-emerald-900/10 hover:shadow-md hover:bg-brand-green/10 dark:hover:bg-emerald-900/20 border-l-4 border-emerald-500'
                                                   : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-red-50 dark:hover:bg-gray-800 ' . ($row->is_death ? 'border-l-4 border-red-500' : ($row->triase_border ? 'border-l-4 ' . $row->triase_border : ''))) }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-3 align-middle">
                                        {{-- Toggle Detail chevron — absolute, bottom-center row --}}
                                        <button type="button" x-on:click="expanded = !expanded"
                                            class="absolute z-10 inline-flex items-center justify-center w-7 h-7 text-muted transition bg-canvas border border-hairline rounded-full shadow-sm hover:text-brand-green hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-emerald-900/30 dark:hover:text-brand-lime"
                                            style="left: 50%; bottom: 4px; transform: translateX(-50%);"
                                            :title="expanded ? 'Sembunyikan detail' : 'Tampilkan detail'">
                                            <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-180' : ''"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
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
                                                        <span class="inline-flex items-center px-2.5 py-1 text-sm font-bold rounded-full shadow-sm {{ $row->triase_class }}">
                                                            {{ $row->triase_label }}
                                                        </span>
                                                    @endif
                                                    @if ($row->is_death)
                                                        <x-badge variant="danger">Meninggal di IGD</x-badge>
                                                    @endif
                                                    @if (!empty($row->resiko_jatuh))
                                                        <x-badge :variant="$row->resiko_jatuh['kategori'] === 'Tinggi' ? 'danger' : 'warning'" class="gap-1"
                                                            title="Penilaian terakhir{{ $row->resiko_jatuh['tgl'] ? ' ' . $row->resiko_jatuh['tgl'] : '' }}{{ $row->resiko_jatuh['metode'] ? ' — ' . $row->resiko_jatuh['metode'] : '' }}{{ $row->resiko_jatuh['skor'] !== '' ? ' (skor ' . $row->resiko_jatuh['skor'] . ')' : '' }}">
                                                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd"
                                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                    clip-rule="evenodd" />
                                                            </svg>
                                                            Risiko Jatuh {{ $row->resiko_jatuh['kategori'] }}
                                                        </x-badge>
                                                    @endif
                                                    @if (!empty($row->resiko_bunuh_diri))
                                                        <x-badge :variant="$row->resiko_bunuh_diri['kategori'] === 'Tinggi' ? 'danger' : 'warning'" class="gap-1"
                                                            title="Skrining C-SSRS terakhir{{ $row->resiko_bunuh_diri['tgl'] ? ' ' . $row->resiko_bunuh_diri['tgl'] : '' }}{{ $row->resiko_bunuh_diri['skor'] !== '' ? ' (skor keparahan ' . $row->resiko_bunuh_diri['skor'] . ')' : '' }}">
                                                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd"
                                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                    clip-rule="evenodd" />
                                                            </svg>
                                                            Risiko Bunuh Diri {{ $row->resiko_bunuh_diri['kategori'] }}
                                                        </x-badge>
                                                    @endif
                                                </div>
                                                <div class="text-base font-medium text-body dark:text-gray-300">
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

                                    {{-- DOKTER / KLAIM --}}
                                    <td class="px-6 py-6 space-y-0.5 align-middle">
                                        <div class="text-base font-semibold text-body dark:text-gray-300 leading-tight">
                                            {{ $row->dr_name ?? '-' }}
                                        </div>
                                        <div class="text-sm text-muted dark:text-gray-400 leading-tight">
                                            {{ $row->klaim_desc ?? '-' }}
                                        </div>
                                        <div class="text-xs text-muted dark:text-gray-400 leading-tight">
                                            Cara Masuk: {{ $row->entry_desc ?? '-' }}
                                        </div>
                                        <div x-show="expanded" x-collapse class="space-y-0.5">
                                            @if (!empty($row->vno_sep) && $row->vno_sep !== '-')
                                                <div class="font-mono text-xs text-muted dark:text-gray-300">
                                                    {{ $row->vno_sep }}
                                                </div>
                                            @endif
                                            <div class="text-xs text-muted dark:text-gray-500 leading-tight">
                                                {{ $row->rj_date_display ?? '-' }} | Shift : {{ $row->shift ?? '-' }}
                                            </div>
                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-6 py-6 space-y-2 align-middle">
                                        {{-- Status transaksi (rj_status: A=Antrian, L=Selesai, I=Transfer Inap, F=Batal) --}}
                                        <x-badge :variant="$row->rj_status_variant">
                                            {{ $row->rj_status_text }}
                                        </x-badge>

                                        {{-- EMR progress --}}
                                        <div class="w-full h-1.5 bg-surface-strong rounded-full dark:bg-gray-700">
                                            <div class="h-1.5 rounded-full transition-all duration-500
                                                {{ $row->emr_percent >= 80 ? 'bg-emerald-500/80' : ($row->emr_percent >= 50 ? 'bg-amber-400/80' : 'bg-rose-400/80') }}"
                                                style="width: {{ $row->emr_percent ?? 0 }}%">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="flex items-center gap-1 text-xs text-body dark:text-gray-400">
                                                <span>EMR : {{ $row->emr_percent ?? 0 }}%</span>
                                                {{-- Tombol info kelengkapan EMR — buka modal panduan + status pasien ini --}}
                                                <button type="button"
                                                    x-on:click.stop="$dispatch('open-info-kelengkapan-emr-ugd', { rjNo: {{ $row->rj_no }} })"
                                                    class="inline-flex items-center justify-center w-4 h-4 text-muted-soft transition rounded-full hover:text-brand-green hover:bg-brand-green/10 dark:hover:bg-brand-lime/15 dark:hover:text-brand-lime"
                                                    title="Lihat status & kriteria kelengkapan EMR">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
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
                                                <div class="text-xs p-1 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
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

                                        @php
                                            $rjLabel = match ($row->rj_status) {
                                                'L' => 'Selesai Pembayaran',
                                                'I' => 'Transfer Inap',
                                                'F' => 'Batal',
                                                default => null,
                                            };
                                            $rjTextColor = match ($row->rj_status) {
                                                'L' => 'text-success dark:text-success',
                                                'I' => 'text-blue-600 dark:text-blue-400',
                                                'F' => 'text-red-600 dark:text-red-400',
                                                default => 'text-muted-soft',
                                            };
                                        @endphp
                                        @if ($rjLabel)
                                            <div class="text-xs text-muted dark:text-gray-400 leading-tight">
                                                Kasir :
                                                <span class="font-semibold {{ $rjTextColor }}">{{ $rjLabel }}</span>
                                            </div>
                                        @endif

                                        @if ($row->task_id99)
                                            <x-badge variant="danger">Batal {{ $row->task_id99 }}</x-badge>
                                        @endif

                                        {{-- Waktu UGD (datang/periksa/selesai) — selalu visible, kritis untuk emergency timing --}}
                                        <div class="text-xs text-body dark:text-gray-400 leading-tight space-y-0.5">
                                            @if ($row->waktu_datang)
                                                <div><span class="text-muted">Datang</span> {{ $row->waktu_datang }}</div>
                                            @endif
                                            @if ($row->waktu_pemeriksaan)
                                                <div><span class="text-muted">Periksa</span> {{ $row->waktu_pemeriksaan }}</div>
                                            @endif
                                            @if ($row->selesai_pemeriksaan)
                                                <div><span class="text-muted">Selesai</span> {{ $row->selesai_pemeriksaan }}</div>
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
                                            <div class="flex flex-col items-center gap-2 p-3 text-center border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/10 dark:border-red-800">
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
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path
                                                                d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        </svg>
                                                    </x-secondary-button>
                                                </x-slot>

                                                <x-slot name="content">
                                                    <div class="p-2 space-y-2">
                                                        <div class="grid grid-cols-2 gap-1">

                                                            {{-- Rekam Medis — Perawat, Dokter, Admin, Casemix, Mr (view) --}}
                                                            @hasanyrole('Perawat|Dokter|Admin|Casemix|Mr')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openRekamMedis('{{ $row->rj_no }}')"
                                                                    class="px-3 py-2 text-sm rounded-lg bg-green-50 hover:bg-green-100 dark:bg-green-900/20">
                                                                    <div class="flex items-start gap-2">
                                                                        <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                            fill="none" stroke="currentColor"
                                                                            viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                                        </svg>
                                                                        <span>Rekam Medis<br>
                                                                            <span class="font-semibold">Pasien</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Modul Dokumen — Admin, Perawat, Dokter, Casemix, Mr
                                                                (Dokter perlu untuk TTD inform consent) --}}
                                                            @hasanyrole('Admin|Perawat|Dokter|Casemix|Mr')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openModulDokumen('{{ $row->rj_no }}')"
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
                                                                            <span class="font-semibold">Formulir &amp;
                                                                                Consent Pasien</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Administrasi — Admin, Perawat, Casemix, Tu --}}
                                                            @hasanyrole('Admin|Perawat|Casemix|Tu')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openAdministrasiPasien('{{ $row->rj_no }}')"
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

                                                            {{-- Transfer ke RI — aktif HANYA saat status Antrian (rj_status='A'), selain itu disabled --}}
                                                            @hasanyrole('Admin|Tu|Perawat|Manager Umum|Supervisor Tu')
                                                                @if ($row->rj_status === 'A')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="$dispatch('open-transfer-ugd-ke-ri', { rjNo: {{ $row->rj_no }} })"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-teal-50 hover:bg-teal-100 dark:bg-teal-900/20">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M3 12h13m0 0l-4-4m4 4l-4 4m9-9v10a2 2 0 01-2 2h-3" />
                                                                            </svg>
                                                                            <span>Transfer ke RI<br>
                                                                                <span class="font-semibold">Rawat Inap</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @else
                                                                    <div title="Hanya bisa saat status Antrian"
                                                                        class="px-3 py-2 text-sm rounded-lg opacity-50 cursor-not-allowed bg-surface-soft dark:bg-gray-800">
                                                                        <div class="flex items-start gap-2 text-muted-soft">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M3 12h13m0 0l-4-4m4 4l-4 4m9-9v10a2 2 0 01-2 2h-3" />
                                                                            </svg>
                                                                            <span>Transfer ke RI<br>
                                                                                <span class="font-semibold">Rawat Inap</span>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                @endif
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

            {{-- Child components — pelayanan-only: EMR + Modul Dokumen + Administrasi + Cetak Etiket --}}
            <livewire:pages::transaksi.ugd.emr-ugd.erm-ugd wire:key="emr-ugd-actions" />
            <livewire:pages::transaksi.ugd.administrasi-ugd.administrasi-ugd wire:key="administrasi-ugd-actions" />
            <livewire:pages::transaksi.ugd.administrasi-ugd.transfer-ugd-ke-ri-actions wire:key="transfer-ugd-ke-ri-actions" />
            <livewire:pages::transaksi.ugd.pelayanan-ugd.cek-peserta-bpjs-ugd-actions wire:key="cek-peserta-bpjs-ugd-actions" />
            <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.modul-dokumen-ugd wire:key="modul-dokumen-ugd" />
            <livewire:pages::transaksi.ugd.emr-ugd.log-aktivitas.log-aktivitas-ugd wire:key="log-aktivitas-ugd" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-ugd" />

            {{-- Modal panduan kriteria kelengkapan EMR UGD (dibuka dari tombol info ⓘ samping label "EMR : x%") --}}
            <livewire:pages::transaksi.ugd.daftar-ugd.info-kelengkapan-emr wire:key="info-kelengkapan-emr-ugd" />

        </div>
    </div>
</div>
