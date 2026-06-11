<?php

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

    /*
     | Daftar Pasien Bulanan RI — read-only untuk casemix/admin/tu.
     | Filter bulan berdasarkan EXIT_DATE (tanggal pulang) — sesuai
     | reporting BPJS yang dihitung saat pasien discharge.
     | Aksi tersisa per row: Kirim iDRG, Berkas BPJS.
     */

    public array $renderVersions = [];
    protected array $renderAreas = ['daftar-ri-bulanan-toolbar'];

    public string $searchKeyword = '';
    public string $filterMode = 'bulanan'; // 'bulanan' | 'harian'
    public string $filterBulan = ''; // m/Y — dipakai mode bulanan
    public string $filterTanggal = ''; // d/m/Y — dipakai mode harian
    public string $filterStatus = 'P'; // default: Pulang (RI status code untuk selesai)
    public string $filterKlaim = 'BPJS'; // default: BPJS | '' | 'UMUM'
    public string $filterDokter = '';
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        // Tidak incrementVersion — wire:key remount toolbar di tengah ketik bikin
        // search input kehilangan focus, backspace berikutnya memicu browser back.
        $this->resetPage();
    }
    public function updatedFilterMode(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterTanggal(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterBulan(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterStatus(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterKlaim(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedFilterDokter(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }
    public function updatedItemsPerPage(): void { $this->resetPage(); $this->incrementVersion('daftar-ri-bulanan-toolbar'); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterDokter']);
        $this->filterMode = 'bulanan';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->incrementVersion('daftar-ri-bulanan-toolbar');
        $this->resetPage();
    }

    public function openIdrg(string $rihdrNo): void
    {
        $this->dispatch('daftar-ri.idrg.open', riHdrNo: $rihdrNo);
    }

    public function openBerkasBpjs(int $rihdrNo): void
    {
        $this->dispatch('berkas-bpjs.open', rjNo: $rihdrNo);
    }

    // Buka modal Administrasi dalam mode view-only — Casemix verifikasi tagihan vs klaim.
    // Hanya dipakai dari bulanan (mutasi data dilarang dari bulanan).
    public function openAdministrasi(int $rihdrNo): void
    {
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $rihdrNo, readOnly: true);
    }

    private function dateRange(): array
    {
        // RI filter berdasarkan exit_date (tgl pulang) — harian = 1 hari, bulanan = 1 bulan.
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
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn($row) => $this->transformRow($row))
        );

        return $paginator;
    }

    #[Computed]
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $labSub = DB::table('lbtxn_checkuphdrs')->select('ref_no', DB::raw('COUNT(*) as lab_status'))->where('status_rjri', 'RI')->where('checkup_status', '!=', 'B')->groupBy('ref_no');
        $radSub = DB::table('rstxn_riradiologs')->select('rihdr_no', DB::raw('COUNT(*) as rad_status'))->groupBy('rihdr_no');

        $query = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->leftJoinSub($labSub, 'lab', fn($j) => $j->on('lab.ref_no', '=', 'h.rihdr_no'))
            ->leftJoinSub($radSub, 'rad', fn($j) => $j->on('rad.rihdr_no', '=', 'h.rihdr_no'))
            ->select([
                'h.rihdr_no',
                DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date_display"),
                DB::raw("to_char(h.exit_date,'dd/mm/yyyy hh24:mi:ss') as exit_date_display"),
                'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'h.dr_id', 'd.dr_name',
                'h.klaim_id', 'k.klaim_desc', 'k.klaim_status',
                'h.ri_status', 'h.vno_sep',
                'r.bangsal_id', 'b.bangsal_name', 'h.room_id', 'r.room_name', 'h.bed_no',
                DB::raw('COALESCE(lab.lab_status, 0) as lab_status'),
                DB::raw('COALESCE(rad.rad_status, 0) as rad_status'),
                'h.datadaftarri_json',
            ])
            ->whereBetween('h.exit_date', [$start, $end]);

        if ($this->filterStatus !== '') {
            $query->where('h.ri_status', $this->filterStatus);
        }

        // Filter Klaim BPJS / UMUM
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

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $kw = mb_strtoupper($search);
            $query->where(function ($q) use ($search, $kw) {
                if (ctype_digit($search)) {
                    $q->orWhere('h.rihdr_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.rihdr_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.vno_sep)'), 'like', "%{$kw}%");
            });
        }

        return $query->orderByDesc('h.exit_date');
    }

    private function transformRow($row)
    {
        $json = json_decode($row->datadaftarri_json ?? '{}', true) ?? [];

        $row->admin_user = isset($json['AdministrasiRI']) ? $json['AdministrasiRI']['userLog'] ?? '✔' : '-';
        $row->administrasi_detail = $json['AdministrasiRI'] ?? null;
        $row->tindak_lanjut = $json['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
        $row->no_skdp_bpjs = $json['kontrol']['noSKDPBPJS'] ?? '-';

        $row->diagnosis = isset($json['diagnosis']) && is_array($json['diagnosis']) ? implode('# ', array_column($json['diagnosis'], 'icdX')) : '-';
        $row->diagnosis_free_text = $json['diagnosisFreeText'] ?? '-';
        $row->procedure = isset($json['procedure']) && is_array($json['procedure']) ? implode('# ', array_column($json['procedure'], 'procedureId')) : '-';
        $row->procedure_free_text = $json['procedureFreeText'] ?? '-';

        // EMR completeness + meta — disamakan dengan daftar-ri (harian)
        $pct = $this->calculateEmrPercentRI($json);
        $row->emr_percent = $pct['emr'];
        $row->emr_sections = $pct['sections'];
        $row->eresep_percent = isset($json['eresep']) || isset($json['eresepRacikan']) ? 100 : 0;
        $row->emr_bar_color = $row->emr_percent >= 80 ? 'bg-emerald-500' : ($row->emr_percent >= 50 ? 'bg-amber-400' : 'bg-rose-400');

        $row->lab_status = (int) ($row->lab_status ?? 0);
        $row->rad_status = (int) ($row->rad_status ?? 0);
        $row->no_sep     = $json['sep']['noSep'] ?? ($row->vno_sep ?? null);
        $row->no_spri    = $json['spri']['noSPRIBPJS'] ?? null;
        $row->leveling_dokter_list = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];

        $row->klaim_badge_variant = match ($row->klaim_id ?? '') {
            'UM' => 'success',
            'JM' => 'brand',
            default => 'alternative',
        };

        if (!empty($row->birth_date)) {
            try {
                $tglLahir = Carbon::createFromFormat('d/m/Y', $row->birth_date);
                $diff = $tglLahir->diff(now());
                $row->umur_format = "{$diff->y} Thn, {$diff->m} Bln, {$diff->d} Hr";
            } catch (\Exception $e) {
                $row->umur_format = '-';
            }
        } else {
            $row->umur_format = '-';
        }

        $row->status_text = ['I' => 'Dirawat', 'P' => 'Pulang', 'F' => 'Batal'][$row->ri_status] ?? 'RI';
        $row->status_variant = ['I' => 'brand', 'P' => 'success', 'F' => 'danger'][$row->ri_status] ?? 'gray';

        return $row;
    }

    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();
        return DB::table('rstxn_rihdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select('h.dr_id', DB::raw('MAX(d.dr_name) as dr_name'), DB::raw('COUNT(DISTINCT h.rihdr_no) as total_pasien'))
            ->whereBetween('h.exit_date', [$start, $end])
            ->groupBy('h.dr_id')
            ->orderBy('dr_name')
            ->get();
    }
};
?>

<div>
    <x-page-title
        title="Daftar Pasien RI — Casemix"
        subtitle="Filter tanggal pulang: Bulanan (mm/yyyy) / Harian (dd/mm/yyyy). Cari: No RI / RM / Nama / SEP." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="flex-1 min-w-[200px]">
                        <x-input-label value="Cari" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full mt-1"
                            placeholder="No RI / No RM / Nama Pasien / No SEP..." />
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

                    @if ($filterMode === 'bulanan')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan (Tgl Pulang)" />
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                class="block w-full mt-1 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                        </div>
                    @else
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Tanggal (Tgl Pulang)" />
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                                class="block w-full mt-1 sm:w-44" placeholder="dd/mm/yyyy" maxlength="10" />
                        </div>
                    @endif

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-40">
                            <option value="">Semua</option>
                            <option value="I">Dirawat</option>
                            <option value="P">Pulang</option>
                            <option value="F">Batal</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter (DPJP)" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua</option>
                            @foreach ($this->dokterList as $dr)
                                <option value="{{ $dr->dr_id }}">{{ $dr->dr_name }} ({{ $dr->total_pasien }})</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Per Halaman" />
                        <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-24">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>

                    <div class="flex items-end">
                        <x-secondary-button type="button" wire:click="resetFilters">Reset</x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                wire:key="{{ $this->renderKey('daftar-ri-bulanan-toolbar') }}">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm border-separate border-spacing-y-3">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Bangsal / Dokter</th>
                                <th class="px-6 py-3 min-w-[320px]">Status Layanan</th>
                                <th class="px-6 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $r)
                                <tr wire:key="daftar-ri-bulanan-{{ $r->rihdr_no ?? $loop->index }}"
                                    class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800/50">

                                    {{-- PASIEN — sama dengan daftar-ri (harian) --}}
                                    <td class="px-6 py-6 space-y-2 align-top">
                                        <div class="flex items-start gap-4">
                                            <div class="space-y-1 min-w-0">
                                                <div class="text-base font-medium text-body dark:text-gray-300">
                                                    {{ $r->reg_no ?? '-' }}
                                                </div>
                                                <div class="text-lg font-semibold text-brand dark:text-white">
                                                    {{ $r->reg_name ?? '-' }}
                                                    /
                                                    ({{ $r->sex === 'L' ? 'Laki-Laki' : ($r->sex === 'P' ? 'Perempuan' : '-') }})
                                                </div>
                                                <div class="text-base text-body dark:text-gray-400">
                                                    {{ $r->birth_date ?? '-' }}
                                                    @if ($r->umur_format && $r->umur_format !== '-')
                                                        <span class="text-muted">({{ $r->umur_format }})</span>
                                                    @endif
                                                </div>
                                                <div class="text-base text-muted dark:text-gray-400">
                                                    {{ $r->address ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- KAMAR / DOKTER — split 2 sub-kolom sama dengan daftar-ri (harian) --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="grid grid-cols-2 gap-x-4">

                                            {{-- Sub-kiri: Bangsal / Room / DPJP / Penerima --}}
                                            <div class="space-y-2">
                                                <div class="font-semibold text-blue-600 dark:text-blue-400">
                                                    {{ $r->bangsal_name ?? '-' }}
                                                </div>
                                                <div class="text-base text-ink dark:text-gray-200">
                                                    {{ $r->room_name ?? $r->room_id ?? '-' }}
                                                </div>
                                                @if (!empty($r->leveling_dokter_list))
                                                    <div class="space-y-0.5">
                                                        <div class="text-xs text-muted-soft">DPJP:</div>
                                                        @foreach ($r->leveling_dokter_list as $ld)
                                                            @if (!empty($ld['drName']))
                                                                <div class="text-base text-body dark:text-gray-200">
                                                                    {{ $ld['drName'] }}
                                                                    @if (!empty($ld['levelDokter']))
                                                                        <span class="text-xs text-muted">
                                                                            ({{ $ld['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $ld['levelDokter'] }})
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="text-xs italic text-muted dark:text-gray-400">
                                                    Penerima: {{ $r->dr_name ?? '-' }}
                                                </div>
                                            </div>

                                            {{-- Sub-kanan: Klaim / SEP / SPRI / Lab+Rad --}}
                                            <div class="space-y-2">
                                                <x-badge :variant="$r->klaim_badge_variant">{{ $r->klaim_desc ?? $r->klaim_id ?? '-' }}</x-badge>

                                                @if ($r->no_sep)
                                                    <div class="font-mono text-xs text-muted dark:text-gray-300">
                                                        SEP: {{ $r->no_sep }}
                                                    </div>
                                                @endif

                                                @if ($r->no_spri)
                                                    <div class="font-mono text-xs text-purple-600 dark:text-purple-400">
                                                        SPRI: {{ $r->no_spri }}
                                                    </div>
                                                @endif

                                                @if ($r->lab_status > 0 || $r->rad_status > 0)
                                                    <div class="flex gap-2 flex-wrap">
                                                        @if ($r->lab_status > 0)
                                                            <x-badge variant="brand">Laborat: {{ $r->lab_status }}</x-badge>
                                                        @endif
                                                        @if ($r->rad_status > 0)
                                                            <x-badge variant="warning">Radiologi: {{ $r->rad_status }}</x-badge>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                        </div>
                                    </td>

                                    {{-- STATUS LAYANAN — sama dengan daftar-ri (harian) --}}
                                    <td class="px-6 py-6 space-y-2 align-top min-w-[320px]">
                                        <div class="text-sm text-body dark:text-gray-400 space-y-0.5 whitespace-nowrap">
                                            <div>
                                                <span class="text-muted">Masuk:</span>
                                                {{ $r->entry_date_display ?? '-' }}
                                            </div>
                                            @if (!empty($r->exit_date_display))
                                                <div>
                                                    <span class="text-muted">Pulang:</span>
                                                    {{ $r->exit_date_display }}
                                                </div>
                                            @endif
                                        </div>

                                        <x-badge :variant="$r->status_variant">{{ $r->status_text }}</x-badge>

                                        {{-- Progress bar EMR --}}
                                        <div class="w-full h-1.5 bg-surface-strong rounded-full dark:bg-gray-700">
                                            <div class="h-1.5 rounded-full transition-all duration-500 {{ $r->emr_bar_color }}"
                                                style="width: {{ $r->emr_percent ?? 0 }}%">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="text-xs text-body dark:text-gray-400">
                                                EMR: {{ $r->emr_percent ?? 0 }}%
                                            </div>
                                            <div class="text-xs text-body dark:text-gray-400">
                                                E-Resep: {{ $r->eresep_percent ?? 0 }}%
                                            </div>
                                        </div>

                                        @if ($r->diagnosis !== '-')
                                            <div class="text-xs text-muted dark:text-gray-400">
                                                <span class="font-semibold">Diagnosa:</span><br>
                                                {{ $r->diagnosis }}
                                                @if ($r->diagnosis_free_text !== '-')
                                                    / {{ $r->diagnosis_free_text }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="flex items-center gap-4">

                                            {{-- Dropdown Aksi --}}
                                            <x-dropdown position="left" width="w-[500px]">
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

                                                        {{-- GRID 2 KOLOM --}}
                                                        <div class="grid grid-cols-2 gap-1">

                                                            {{-- Kirim iDRG — Admin, Casemix, Tu; BPJS + ri_status=Pulang (P) --}}
                                                            @hasanyrole('Admin|Casemix|Tu')
                                                                @if (($r->klaim_status === 'BPJS' || $r->klaim_id === 'JM') && $r->ri_status === 'P')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openIdrg('{{ $r->rihdr_no }}')"
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
                                                                                <span class="font-semibold">{{ $r->reg_name }}</span>
                                                                            </span>
                                                                        </div>
                                                                    </x-dropdown-link>
                                                                @endif
                                                            @endhasanyrole

                                                            {{-- Administrasi (View-only) — Admin/Casemix/Tu; status=Pulang (P) --}}
                                                            @hasanyrole('Admin|Casemix|Tu')
                                                                @if ($r->ri_status === 'P')
                                                                    <x-dropdown-link href="#"
                                                                        wire:click.prevent="openAdministrasi({{ $r->rihdr_no }})"
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
                                                                    wire:click.prevent="openBerkasBpjs({{ $r->rihdr_no }})"
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
                                    <td colspan="4" class="px-6 py-16 text-center text-body dark:text-gray-400">
                                        Tidak ada pasien RI yang pulang di bulan ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline-soft rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Sibling action components --}}
            <livewire:pages::transaksi.ri.daftar-ri.idrg-ri-actions wire:key="idrg-ri-actions" />
            <livewire:pages::transaksi.ri.emr-ri.log-aktivitas.log-aktivitas-ri wire:key="log-aktivitas-ri" />
            <livewire:pages::transaksi.ri.administrasi-ri.administrasi-ri wire:key="administrasi-ri-readonly" />
            <livewire:pages::transaksi.ri.daftar-ri-bulanan.berkas-bpjs-ri-actions wire:key="berkas-bpjs-ri-actions" />

        </div>
    </div>
</div>
