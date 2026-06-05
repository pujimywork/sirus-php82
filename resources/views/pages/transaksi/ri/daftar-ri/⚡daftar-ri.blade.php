<?php
// resources/views/pages/transaksi/ri/daftar-ri/daftar-ri.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ri\EmrCompletenessRITrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrCompletenessRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-ri-toolbar'];

    public string $searchKeyword = '';
    public string $filterStatus = 'I';
    public string $filterDokter = '';
    public string $filterBangsal = '';
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
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

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterDokter', 'filterBangsal']);
        $this->filterStatus = 'I';
        $this->incrementVersion('daftar-ri-toolbar');
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('daftar-ri.create.open');
    }
    public function openEdit(string $riHdrNo): void
    {
        $this->dispatch('daftar-ri.edit.open', riHdrNo: $riHdrNo);
    }
    public function openRekamMedis(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.rekam-medis.open', riHdrNo: $riHdrNo);
    }
    public function openModulDokumen(string $riHdrNo): void
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
    public function openIdrg(string $riHdrNo): void
    {
        $this->dispatch('daftar-ri.idrg.open', riHdrNo: $riHdrNo);
    }
    public function openBerkasBpjs(int $rihdrNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rihdrNo);
    }

    #[On('refresh-after-ri.saved')]
    public function refreshAfterSaved(): void
    {
        $this->incrementVersion('daftar-ri-toolbar');
        $this->resetPage();
    }

    private function isDokterOrPerawat(): bool
    {
        return auth()
            ->user()
            ->hasAnyRole(['Dokter', 'Perawat']);
    }

    #[Computed]
    public function baseQuery()
    {
        $statusColumn = $this->isDokterOrPerawat() ? DB::raw("NVL(rv.ri_status,'I')") : DB::raw("NVL(rv.ri_status,'I')");

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'RI')->where('checkup_status', '!=', 'B')->groupBy('ref_no');

        $radSub = DB::table('rstxn_riradiologs')->select('rihdr_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rihdr_no');

        $query = DB::table('rsview_rihdrs as rv')
            ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'rv.klaim_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'rv.rihdr_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rihdr_no', '=', 'rv.rihdr_no'))
            ->select([
                'rv.rihdr_no',
                DB::raw("to_char(rv.entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date_display"),
                DB::raw("to_char(rv.entry_date,'yyyymmddhh24miss') as entry_date_sort"),
                DB::raw("to_char(rv.exit_date,'dd/mm/yyyy hh24:mi:ss') as exit_date_display"),
                'rv.reg_no',
                'rv.reg_name',
                'rv.sex',
                'rv.address',
                DB::raw("to_char(rv.birth_date,'dd/mm/yyyy') as birth_date"),
                DB::raw("
                    CASE WHEN rv.birth_date IS NOT NULL THEN
                        trunc(months_between(sysdate, rv.birth_date) / 12) || ' Thn, ' ||
                        trunc(mod(months_between(sysdate, rv.birth_date), 12)) || ' Bln, ' ||
                        trunc(sysdate - add_months(rv.birth_date, trunc(months_between(sysdate, rv.birth_date)))) || ' Hr'
                    ELSE '0 Thn, 0 Bln, 0 Hr' END AS thn_umur
                "),
                'rv.dr_id',
                'rv.dr_name',
                'rv.klaim_id',
                'kt.klaim_desc',
                'kt.klaim_status',
                'rv.ri_status',
                'rv.erm_status',
                'rv.vno_sep',
                'rv.entry_id',
                'rv.bangsal_id',
                'rv.bangsal_name',
                'rv.room_id',
                'rv.room_name',
                'rv.bed_no',
                DB::raw('COALESCE(lab.lab_status, 0) as lab_status'),
                DB::raw('COALESCE(rad.rad_status, 0) as rad_status'),
                'rv.datadaftarri_json',
            ])
            ->orderBy('entry_date_sort', 'desc');

        if ($this->filterStatus !== '') {
            $query->where($statusColumn, $this->filterStatus);
        }

        if ($this->filterBangsal !== '') {
            $query->where('rv.bangsal_id', $this->filterBangsal);
        }

        if ($this->filterDokter !== '') {
            $ids = DB::table('rsview_rihdrs')
                ->select('rihdr_no', 'datadaftarri_json')
                ->where(DB::raw("NVL(ri_status,'I')"), 'I')
                ->get()
                ->filter(function ($item) {
                    $json = json_decode($item->datadaftarri_json ?? '{}', true) ?? [];
                    foreach ($json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [] as $ld) {
                        if (($ld['drId'] ?? '') === $this->filterDokter) {
                            return true;
                        }
                    }
                    return false;
                })
                ->pluck('rihdr_no')
                ->toArray();

            $query->where(function ($q) use ($ids) {
                $q->where('rv.dr_id', $this->filterDokter)->orWhereIn('rv.rihdr_no', $ids);
            });
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('rv.rihdr_no', 'like', "%{$search}%")->orWhere('rv.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(rv.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.vno_sep)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(rv.dr_name)'), 'like', "%{$kw}%");
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

    #[Computed]
    public function rows()
    {
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarri_json ?? '{}', true) ?? [];

            // EMR completeness — weighted S15/O15/A15/P10/N5/C20/K20.
            // C (CPPT) & K (Askep) khas RI — bobot besar karena engine monitoring harian.
            // Logic ada di EmrCompletenessRITrait. Field "screening" boleh "Tidak ada".
            $pct = $this->calculateEmrPercentRI($json);
            $row->emr_percent = $pct['emr'];
            $row->emr_sections = $pct['sections']; // detail per-section untuk modal info
            $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;
            $row->lab_status = (int) ($row->lab_status ?? 0);
            $row->rad_status = (int) ($row->rad_status ?? 0);
            $row->no_sep = $json['sep']['noSep'] ?? ($row->vno_sep ?? null);
            $row->no_spri = $json['spri']['noSPRIBPJS'] ?? null;

            $row->leveling_dokter_list = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];

            // Penanda risiko jatuh (Sedang/Tinggi) — dari JSON yang sudah di-decode,
            // tanpa query tambahan. Kosong = tidak tampil.
            $row->resiko_jatuh = $this->hitungResikoJatuhTerakhir($json);

            $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode(' | ', array_column($json['diagnosis'], 'icdX')) : '-';
            $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';
            $row->no_referensi = $json['noReferensi'] ?? null;
            $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
            $row->tindak_lanjut_detail = $json['perencanaan']['tindakLanjut'] ?? null;
            $row->admin_user = isset($json['AdministrasiRI']) ? $json['AdministrasiRI']['userLog'] ?? '✔' : '-';
            $row->task_id3 = $json['taskIdPelayanan']['taskId3'] ?? null;
            $row->task_id4 = $json['taskIdPelayanan']['taskId4'] ?? null;
            $row->task_id5 = $json['taskIdPelayanan']['taskId5'] ?? null;

            $row->rihdr_no_json = $json['riHdrNo'] ?? '-';
            $row->is_json_valid = (string) $row->rihdr_no === (string) $row->rihdr_no_json;
            $row->bg_check_json = $row->is_json_valid ? 'bg-green-100' : 'bg-red-100';

            /* EMR progress bar color — tanpa opacity modifier */
            $row->emr_bar_color = $row->emr_percent >= 80 ? 'bg-emerald-500' : ($row->emr_percent >= 50 ? 'bg-amber-400' : 'bg-rose-400');

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

            if ($this->isDokterOrPerawat()) {
                $statusMap = ['A' => 'Belum Dilayani', 'L' => 'Selesai'];
                $statusVariant = ['A' => 'warning', 'L' => 'success'];
                $row->status_text = $statusMap[$row->erm_status] ?? 'Pelayanan';
                $row->status_variant = $statusVariant[$row->erm_status] ?? 'gray';
            } else {
                $statusMap = ['I' => 'Dirawat', 'P' => 'Pulang'];
                $statusVariant = ['I' => 'brand', 'P' => 'success'];
                $row->status_text = $statusMap[$row->ri_status] ?? 'RI';
                $row->status_variant = $statusVariant[$row->ri_status] ?? 'gray';
            }

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

    #[Computed]
    public function dokterList()
    {
        return DB::table('rsview_rihdrs')->select('dr_id', DB::raw('MAX(dr_name) as dr_name'), DB::raw('COUNT(DISTINCT rihdr_no) as total_pasien'))->where(DB::raw("NVL(ri_status,'I')"), 'I')->groupBy('dr_id')->orderBy('dr_name')->get();
    }

    #[Computed]
    public function bangsalList()
    {
        return DB::table('rsview_rihdrs')->select('bangsal_id', DB::raw('MAX(bangsal_name) as bangsal_name'))->where(DB::raw("NVL(ri_status,'I')"), 'I')->whereNotNull('bangsal_id')->groupBy('bangsal_id')->orderBy('bangsal_name')->get();
    }

    public function cetakEtiket(string $regNo): void
    {
        $this->dispatch('cetak-etiket.open', regNo: $regNo);
    }
};
?>

<div>
    <x-page-title
        title="Daftar Rawat Inap"
        subtitle="Kelola pendaftaran dan pelayanan pasien Rawat Inap" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

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

                    {{-- FILTER STATUS --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-44">
                            <option value="">Semua</option>
                            <option value="I">Dirawat</option>
                            <option value="P">Pulang</option>
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
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-52">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        {{-- Refresh data tanpa mengubah filter — ghost biru --}}
                        <button type="button" wire:click="$refresh" title="Refresh data"
                            class="inline-flex items-center justify-center p-2 text-blue-600 transition bg-transparent border border-blue-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-300 dark:text-blue-400 dark:border-blue-800/60 dark:hover:bg-blue-900/20 dark:focus:ring-blue-900">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                wire:loading.class="animate-spin" wire:target="$refresh">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="sr-only">Refresh</span>
                        </button>

                        <x-secondary-button type="button" wire:click="resetFilters" title="Reset filter" class="p-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            <span class="sr-only">Reset filter</span>
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

                        {{-- Pendaftaran Rawat Inap — Mr, Admin, Supervisor Tu --}}
                        @hasanyrole(['Mr', 'Admin', 'Supervisor Tu'])
                            <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Pendaftaran Rawat Inap
                            </x-primary-button>
                        @endhasanyrole
                    </div>

                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">

                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Kamar / Dokter</th>
                                <th class="px-6 py-3 min-w-[320px]">Status Layanan</th>
                                <th class="px-6 py-3 text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr class="transition bg-white dark:bg-gray-900
                                       rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                       hover:shadow-lg hover:bg-blue-50 dark:hover:bg-gray-800"
                                    wire:key="ri-row-{{ $row->rihdr_no }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="flex items-start gap-4">
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

                                    {{-- KAMAR / DOKTER (split 2 sub-kolom: kiri Kamar+DPJP, kanan Klaim+SEP+SPRI+Lab+Rad) --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="grid grid-cols-2 gap-x-4">

                                            {{-- ── Sub-kiri: Bangsal / Room / DPJP / Penerima ── --}}
                                            <div class="space-y-2">
                                                <div class="font-semibold text-blue-600 dark:text-blue-400">
                                                    {{ $row->bangsal_name ?? '-' }}
                                                </div>
                                                <div class="text-base text-gray-800 dark:text-gray-200">
                                                    {{ $row->room_name ?? '-' }}
                                                </div>
                                                @if (!empty($row->leveling_dokter_list))
                                                    <div class="space-y-0.5">
                                                        <div class="text-xs text-gray-400">DPJP:</div>
                                                        @foreach ($row->leveling_dokter_list as $ld)
                                                            @if (!empty($ld['drName']))
                                                                <div class="text-base text-gray-700 dark:text-gray-200">
                                                                    {{ $ld['drName'] }}
                                                                    @if (!empty($ld['levelDokter']))
                                                                        <span class="text-xs text-gray-500">
                                                                            ({{ $ld['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $ld['levelDokter'] }})
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="text-xs italic text-gray-500 dark:text-gray-400">
                                                    Penerima: {{ $row->dr_name ?? '-' }}
                                                </div>
                                            </div>

                                            {{-- ── Sub-kanan: Klaim / Risiko Jatuh / SEP / SPRI / Lab+Rad ── --}}
                                            <div class="space-y-2">
                                                <x-badge :variant="$row->klaim_badge_variant">{{ $row->klaim_desc ?? $row->klaim_id ?? '-' }}</x-badge>

                                                @if (!empty($row->resiko_jatuh))
                                                    <div class="inline-flex items-center gap-1 border rounded-full px-2.5 py-0.5 text-xs font-bold {{ $row->resiko_jatuh['kategori'] === 'Tinggi' ? 'bg-red-100 text-red-700 border-red-300' : 'bg-yellow-100 text-yellow-700 border-yellow-300' }}"
                                                        title="Penilaian terakhir{{ $row->resiko_jatuh['tgl'] ? ' ' . $row->resiko_jatuh['tgl'] : '' }}{{ $row->resiko_jatuh['metode'] ? ' — ' . $row->resiko_jatuh['metode'] : '' }}{{ $row->resiko_jatuh['skor'] !== '' ? ' (skor ' . $row->resiko_jatuh['skor'] . ')' : '' }}">
                                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Risiko Jatuh {{ $row->resiko_jatuh['kategori'] }}
                                                    </div>
                                                @endif

                                                @if ($row->no_sep)
                                                    <div class="font-mono text-xs text-gray-600 dark:text-gray-300">
                                                        SEP: {{ $row->no_sep }}
                                                    </div>
                                                @endif

                                                @if ($row->no_spri)
                                                    <div class="font-mono text-xs text-purple-600 dark:text-purple-400">
                                                        SPRI: {{ $row->no_spri }}
                                                    </div>
                                                @endif

                                                @if ($row->lab_status > 0 || $row->rad_status > 0)
                                                    <div class="flex gap-2 flex-wrap">
                                                        @if ($row->lab_status > 0)
                                                            <x-badge variant="brand">Laborat: {{ $row->lab_status }}</x-badge>
                                                        @endif
                                                        @if ($row->rad_status > 0)
                                                            <x-badge variant="warning">Radiologi: {{ $row->rad_status }}</x-badge>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN --}}
                                    <td class="px-6 py-6 space-y-2 align-top min-w-[320px]">
                                        <div class="text-sm text-gray-700 dark:text-gray-400 space-y-0.5 whitespace-nowrap">
                                            <div>
                                                <span class="text-gray-500">Masuk:</span>
                                                {{ $row->entry_date_display ?? '-' }}
                                            </div>
                                            @if (!empty($row->exit_date_display))
                                                <div>
                                                    <span class="text-gray-500">Pulang:</span>
                                                    {{ $row->exit_date_display }}
                                                </div>
                                            @endif
                                        </div>

                                        <x-badge :variant="$row->status_variant">{{ $row->status_text }}</x-badge>

                                        {{-- Progress bar — warna solid tanpa opacity modifier --}}
                                        <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                            <div class="h-1.5 rounded-full transition-all duration-500 {{ $row->emr_bar_color }}"
                                                style="width: {{ $row->emr_percent ?? 0 }}%">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="flex items-center gap-1 text-xs text-gray-700 dark:text-gray-400">
                                                <span>EMR: {{ $row->emr_percent ?? 0 }}%</span>
                                                {{-- Tombol info kelengkapan EMR — buka modal panduan + status pasien ini --}}
                                                <button type="button"
                                                    x-on:click.stop="$dispatch('open-info-kelengkapan-emr-ri', { riHdrNo: {{ $row->rihdr_no }} })"
                                                    class="inline-flex items-center justify-center w-4 h-4 text-gray-400 transition rounded-full hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300"
                                                    title="Lihat status & kriteria kelengkapan EMR">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="text-xs text-gray-700 dark:text-gray-400">
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

                                        {{-- Validasi JSON — sementara di-comment, kalau perlu debug bisa di-uncomment
                                        <div class="text-xs p-1 rounded {{ $row->bg_check_json }}">
                                            <span class="font-semibold">Validasi JSON:</span>
                                            RI No: {{ $row->rihdr_no }} / {{ $row->rihdr_no_json }}
                                            @if (!$row->is_json_valid)
                                                <span class="text-red-600 dark:text-red-400">(Tidak Sinkron)</span>
                                            @endif
                                        </div>
                                        --}}
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="flex items-center gap-3">

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

                                                            {{-- Pendaftaran Ubah — Mr, Admin, Supervisor Tu --}}
                                                            @hasanyrole(['Mr', 'Admin', 'Supervisor Tu'])
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
                                                                        <span>Pendaftaran Ubah<br>
                                                                            <span
                                                                                class="font-semibold">{{ $row->reg_name }}</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Rekam Medis RI — Perawat, Dokter, Admin, Casemix, Apoteker, Gizi (Casemix untuk verifikasi koding; Apoteker & Gizi untuk isi CPPT, lihat Pengkajian Dokter/Perawat). MR cek kelengkapan via tombol info ⓘ di row, bukan via EMR. --}}
                                                            @hasanyrole('Perawat|Dokter|Admin|Casemix|Apoteker|Gizi')
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

                                                            {{-- Modul Dokumen — Admin, Perawat, Dokter, Casemix, Mr, Gizi --}}
                                                            @hasanyrole('Admin|Perawat|Dokter|Casemix|Mr|Gizi')
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
                                                                            <span class="font-semibold">Formulir &amp;
                                                                                Consent Pasien</span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Administrasi — Admin, Perawat, Casemix, Tu --}}
                                                            @hasanyrole('Admin|Perawat|Casemix|Tu')
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

                                                            @hasanyrole('Mr|Admin|Perawat|Tu')
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
                                                                                {{ $row->room_name ?? '-' }}
                                                                            </span>
                                                                        </span>
                                                                    </div>
                                                                </x-dropdown-link>
                                                            @endhasanyrole

                                                            {{-- Kirim iDRG — Admin, Casemix, Tu; BPJS + ri_status=Pulang --}}
                                                            @hasanyrole('Admin|Casemix|Tu')
                                                                @if (($row->klaim_status === 'BPJS' || $row->klaim_id === 'JM') && $row->ri_status === 'P')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openIdrg('{{ $row->rihdr_no }}')"
                                                                        class="px-3 py-2 text-sm rounded-lg bg-brand/5 hover:bg-brand/10 dark:bg-brand-lime/10 dark:hover:bg-brand-lime/20">
                                                                        <div class="flex items-start gap-2">
                                                                            <svg class="w-5 h-5 mt-0.5 shrink-0 text-brand dark:text-brand-lime"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round"
                                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            <span
                                                                                class="text-brand dark:text-brand-lime font-semibold">Kirim
                                                                                iDRG / INACBG<br>
                                                                                <span
                                                                                    class="text-xs font-normal opacity-80">E-Klaim
                                                                                    Kemenkes</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endif
                                                            @endhasanyrole

                                                            {{-- Berkas BPJS — Admin/Casemix/Tu/Mr --}}
                                                            @hasanyrole('Admin|Casemix|Tu|Mr')
                                                                <x-dropdown-link href="#"
                                                                    wire:click.prevent="openBerkasBpjs({{ $row->rihdr_no }})"
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

                                                        <div
                                                            class="my-1 border-t border-gray-200 dark:border-gray-700">
                                                        </div>

                                                        {{-- Hapus — Admin, Manager Medis, Manager Umum --}}
                                                        @hasanyrole(['Admin', 'Manager Medis', 'Manager Umum'])
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
                                                        @endhasanyrole

                                                    </div>
                                                </x-slot>
                                            </x-dropdown>

                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"
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
            <livewire:pages::transaksi.ri.administrasi-ri.pindah-kamar-ri wire:key="pindah-kamar-ri" />

            {{-- iDRG/INACBG Modal (sibling, listen ke event daftar-ri.idrg.open) --}}
            <livewire:pages::transaksi.ri.daftar-ri.idrg-ri-actions wire:key="idrg-ri-actions" />
            <livewire:pages::transaksi.ri.daftar-ri-bulanan.berkas-bpjs-ri-actions wire:key="berkas-bpjs-ri-actions" />

            <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.modul-dokumen-ri wire:key="modul-dokumen-ri" />
            <livewire:pages::transaksi.ri.emr-ri.log-aktivitas.log-aktivitas-ri wire:key="log-aktivitas-ri" />
            <livewire:pages::components.rekam-medis.etiket.cetak-etiket wire:key="cetak-etiket-ri" />

            {{-- Modal panduan kriteria kelengkapan EMR RI (dibuka dari tombol info ⓘ samping label "EMR: x%") --}}
            <livewire:pages::transaksi.ri.daftar-ri.info-kelengkapan-emr wire:key="info-kelengkapan-emr-ri" />

        </div>
    </div>
</div>
