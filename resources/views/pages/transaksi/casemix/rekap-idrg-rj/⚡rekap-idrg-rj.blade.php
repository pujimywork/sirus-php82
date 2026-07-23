<?php
// resources/views/pages/transaksi/casemix/rekap-idrg-rj/⚡rekap-idrg-rj.blade.php
// Rekap iDRG Rawat Jalan (RJ) — Casemix. Daftar kunjungan (Bulanan/Harian) + tarik data klaim
// E-Klaim per SEP (get_claim_data). Kolom: No RM, Nama, DPJP, Poli, Diagnosa, Cara
// Keluar, Tarif INA-CBG, Tarif RS. Loop getClaimData digerakkan Alpine sekuensial.
// Standarisasi toolbar + pagination mengikuti Rekap iDRG RI — Casemix.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, iDrgTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['rekap-idrg-rj-toolbar'];

    /* Filter & pagination state — pola sama dgn rekap-idrg-ri.
     | Dipersist per-tab (Session) supaya tak reset saat pindah tab di /transaksi/casemix
     | (komponen anak di-unmount/remount oleh @if tab). Key di-namespace 'rekap-idrg-rj-*'. */
    #[Session(key: 'rekap-idrg-rj-searchKeyword')]
    public string $searchKeyword = '';
    #[Session(key: 'rekap-idrg-rj-filterMode')]
    public string $filterMode = 'bulanan'; // 'bulanan' | 'harian'
    #[Session(key: 'rekap-idrg-rj-filterBulan')]
    public string $filterBulan = ''; // m/Y (mm/yyyy)
    #[Session(key: 'rekap-idrg-rj-filterTanggal')]
    public string $filterTanggal = ''; // d/m/Y (dd/mm/yyyy)
    #[Session(key: 'rekap-idrg-rj-filterStatus')]
    public string $filterStatus = 'L'; // default: Selesai
    #[Session(key: 'rekap-idrg-rj-filterKlaim')]
    public string $filterKlaim = 'BPJS'; // BPJS | UMUM | ''
    #[Session(key: 'rekap-idrg-rj-filterDokter')]
    public string $filterDokter = '';
    #[Session(key: 'rekap-idrg-rj-itemsPerPage')]
    public int $itemsPerPage = 25;

    // Hasil get_claim_data per SEP. key = nomor SEP, value = array hasil parse (+ status/msg).
    public array $claims = [];

    // Discharge status E-Klaim (Manual 5.10.x) → label.
    private array $dischargeLabels = [
        '1' => 'Diizinkan pulang', '2' => 'Dirujuk', '3' => 'APS',
        '4' => 'Meninggal', '5' => 'Lain-lain',
    ];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        // Hanya default bila belum ada nilai tersimpan (Session) — jaga filter saat remount.
        $this->filterBulan = $this->filterBulan ?: Carbon::now()->format('m/Y');
        $this->filterTanggal = $this->filterTanggal ?: Carbon::now()->format('d/m/Y');
    }

    public function updatedFilterMode(): void { $this->resetPage(); $this->claims = []; $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedFilterBulan(): void { $this->resetPage(); $this->claims = []; $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedFilterTanggal(): void { $this->resetPage(); $this->claims = []; $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedFilterStatus(): void { $this->resetPage(); $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedFilterKlaim(): void { $this->resetPage(); $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedFilterDokter(): void { $this->resetPage(); $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedItemsPerPage(): void { $this->resetPage(); $this->incrementVersion('rekap-idrg-rj-toolbar'); }
    public function updatedSearchKeyword(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterKlaim', 'filterDokter']);
        $this->filterMode = 'bulanan';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->claims = [];
        $this->incrementVersion('rekap-idrg-rj-toolbar');
        $this->resetPage();
    }

    private function dateRange(): array
    {
        // RJ filter by rj_date (tgl kunjungan) — harian = 1 hari, bulanan = 1 bulan.
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
    public function baseQuery()
    {
        [$start, $end] = $this->dateRange();

        $query = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                'h.rj_no', 'h.reg_no', 'p.reg_name', 'd.dr_name',
                'h.poli_id', 'po.poli_desc', 'h.vno_sep', 'h.datadaftarpolirj_json',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy') as rj_date_display"),
            ])
            ->whereBetween('h.rj_date', [$start, $end])
            ->whereNotNull('h.vno_sep'); // Oracle: '' = NULL, cukup whereNotNull

        if ($this->filterStatus !== '') {
            $query->where('h.rj_status', $this->filterStatus);
        }

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
                    $q->orWhere('h.rj_no', 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
                }
                $q->orWhere(DB::raw('UPPER(h.rj_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$kw}%")
                    ->orWhere(DB::raw('UPPER(h.vno_sep)'), 'like', "%{$kw}%");
            });
        }

        return $query->orderByDesc('h.rj_date');
    }

    #[Computed]
    public function rows()
    {
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        // Lampirkan tarif iDRG dari JSON tersimpan (tanpa tarik E-Klaim).
        $paginator->getCollection()->transform(function ($row) {
            $json = json_decode($row->datadaftarpolirj_json ?? '{}', true) ?: [];
            $row->idrg = $this->idrgTarifFromJson($json);
            return $row;
        });

        return $paginator;
    }

    /**
     * Tarif iDRG dari JSON tersimpan (idrg.idrgGroup + idrgStage2). Total CW = DRG CW +
     * Σ topup CW, Total Klaim = Total CW × NBR — perhitungan sama dgn kirim-group-idrg.
     * Return drg_code + total + flag final (** = belum final, sewaktu-waktu berubah).
     */
    private function idrgTarifFromJson(array $json): array
    {
        $idrg = $json['idrg'] ?? [];
        $group = $idrg['idrgGroup'] ?? [];
        if (empty($group)) {
            return ['drg' => '-', 'total' => 0, 'final' => false, 'has' => false];
        }

        $drgCw = data_get($group, 'drg_cost_weight') ?? data_get($group, 'cost_weight') ?? 0;
        $nbr = (int) (data_get($group, 'nbr') ?? data_get($group, 'base_rate') ?? 0);

        $cwSum = is_numeric($drgCw) ? (float) $drgCw : 0.0;
        foreach ($idrg['idrgStage2']['topup'] ?? [] as $tp) {
            $cw = $tp['cost_weight'] ?? ($tp['cw'] ?? 0);
            if (is_numeric($cw)) {
                $cwSum += (float) $cw;
            }
        }

        $total = $nbr > 0 ? (int) round($nbr * $cwSum) : (int) (data_get($group, 'total_tariff') ?? data_get($group, 'tariff') ?? 0);

        return [
            'drg' => (string) (data_get($group, 'drg_code') ?? '-'),
            'total' => $total,
            'final' => !empty($idrg['idrgFinal']),
            'has' => true,
        ];
    }

    #[Computed]
    public function dokterList()
    {
        [$start, $end] = $this->dateRange();
        return DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->whereBetween('h.rj_date', [$start, $end])
            ->whereNotNull('h.vno_sep')
            ->select('h.dr_id', 'd.dr_name')->distinct()
            ->orderBy('d.dr_name')->get();
    }

    // SEP pada halaman saat ini — dipakai Alpine untuk loop tarik sekuensial.
    #[Computed]
    public function sepList(): array
    {
        return collect($this->rows->items())->pluck('vno_sep')->filter()->unique()->values()->all();
    }

    // Tarik 1 klaim dari E-Klaim (get_claim_data) lalu simpan ke $claims[$sep].
    public function tarikSatu(string $sep): void
    {
        $sep = trim($sep);
        if ($sep === '') {
            return;
        }
        try {
            $res = $this->getClaimData($sep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->claims[$sep] = ['status' => 'error', 'msg' => self::describeEklaimError($res['metadata'] ?? [], 'Get Claim Data')];
                return;
            }
            $this->claims[$sep] = $this->parseClaim($res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->claims[$sep] = ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    private function parseClaim(array $data): array
    {
        // Tarif INA-CBG (rupiah): hasil grouper. Toleran flat/wrapper + iDRG (nbr).
        $tarifIna = data_get($data, 'grouper.response_inacbg.tariff')
            ?? data_get($data, 'grouper.response_inacbg.base_tariff')
            ?? data_get($data, 'response_inacbg.tariff')
            ?? data_get($data, 'grouper.response_idrg.nbr')
            ?? 0;

        $cbgCode = (string) (data_get($data, 'grouper.response_inacbg.cbg.code')
            ?? data_get($data, 'response_inacbg.cbg.code') ?? '-');

        $tarifRs = 0;
        foreach ((array) data_get($data, 'tarif_rs', []) as $v) {
            $tarifRs += (int) $v;
        }

        $discharge = (string) data_get($data, 'discharge_status', '');

        return [
            'status' => 'ok',
            'nomor_rm' => (string) data_get($data, 'nomor_rm', '-'),
            'nama_pasien' => (string) data_get($data, 'nama_pasien', '-'),
            'nama_dokter' => (string) data_get($data, 'nama_dokter', '-'),
            'diagnosa' => (string) (data_get($data, 'diagnosa') ?: data_get($data, 'diagnosa_inagrouper') ?: '-'),
            'los' => (string) data_get($data, 'los', '-'),
            'discharge' => $this->dischargeLabels[$discharge] ?? ($discharge !== '' ? $discharge : '-'),
            'cbg_code' => $cbgCode,
            'tarif_ina' => (int) $tarifIna,
            'tarif_rs' => (int) $tarifRs,
        ];
    }

    // Ringkasan total (hanya baris yang sudah berhasil ditarik).
    #[Computed]
    public function totals(): array
    {
        $ok = collect($this->claims)->filter(fn($c) => ($c['status'] ?? '') === 'ok');
        return [
            'fetched' => $ok->count(),
            'tarif_ina' => (int) $ok->sum('tarif_ina'),
            'tarif_rs' => (int) $ok->sum('tarif_rs'),
        ];
    }
}; ?>

<div>
    <x-page-title
        title="Rekap iDRG Rawat Jalan — Casemix"
        subtitle="Filter Bulanan (mm/yyyy) atau Harian (dd/mm/yyyy). Pencarian: No RJ / No RM / Nama / No SEP." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3" wire:key="{{ $this->renderKey('rekap-idrg-rj-toolbar', []) }}">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RJ / No RM / Nama Pasien / No SEP..." />
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

                    {{-- FILTER BULAN / TANGGAL (Tgl Kunjungan) --}}
                    @if ($filterMode === 'bulanan')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan (Tgl Kunjungan)" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                                    class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                            </div>
                        </div>
                    @else
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Tanggal (Tgl Kunjungan)" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterTanggal"
                                    class="block w-full pl-10 sm:w-44" placeholder="dd/mm/yyyy" maxlength="10" />
                            </div>
                        </div>
                    @endif

                    {{-- FILTER STATUS — rj_status --}}
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

                    {{-- FILTER KLAIM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER DOKTER (DPJP) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter (DPJP)" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-end gap-2 ml-auto">
                        {{-- Tarik dari E-Klaim — loop sekuensial via Alpine (1 request per SEP) --}}
                        <div x-data="{
                                running: false, done: 0, total: 0,
                                async tarik(seps) {
                                    if (!seps.length) return;
                                    this.running = true; this.total = seps.length; this.done = 0;
                                    for (const s of seps) { try { await $wire.tarikSatu(s); } catch (e) {} this.done++; }
                                    this.running = false;
                                }
                            }">
                            <button type="button" @click="tarik(@js($this->sepList))" :disabled="running"
                                class="px-4 py-2.5 text-sm font-medium text-white rounded-lg bg-brand hover:bg-brand/90 disabled:opacity-50">
                                <span x-show="!running">⬇ Tarik dari E-Klaim ({{ count($this->sepList) }})</span>
                                <span x-show="running" x-text="`Menarik ${done}/${total}…`"></span>
                            </button>
                        </div>

                        <x-toolbar-refresh-reset :label="null" />

                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- RINGKASAN --}}
                <div class="flex flex-wrap gap-6 px-6 py-3 text-sm border-b border-hairline dark:border-gray-700">
                    <span class="text-muted dark:text-gray-300">Kunjungan (hal. ini): <b>{{ count($this->rows) }}</b></span>
                    <span class="text-muted dark:text-gray-300">Tertarik: <b>{{ $this->totals['fetched'] }}</b></span>
                    <span class="text-muted dark:text-gray-300">Total Tarif INA: <b class="text-success dark:text-success">Rp {{ number_format($this->totals['tarif_ina'], 0, ',', '.') }}</b></span>
                    <span class="text-muted dark:text-gray-300">Total Tarif RS: <b>Rp {{ number_format($this->totals['tarif_rs'], 0, ',', '.') }}</b></span>
                    @php $totalIdrg = collect($this->rows->items())->sum(fn($r) => (int) ($r->idrg['total'] ?? 0)); @endphp
                    <span class="text-muted dark:text-gray-300">Total Tarif iDRG (hal. ini): <b class="text-brand dark:text-brand-lime">Rp {{ number_format($totalIdrg, 0, ',', '.') }}</b></span>
                    @php $selisih = $this->totals['tarif_ina'] - $this->totals['tarif_rs']; @endphp
                    <span class="text-muted dark:text-gray-300">Selisih:
                        <b class="{{ $selisih < 0 ? 'text-error' : 'text-success dark:text-success' }}">Rp {{ number_format($selisih, 0, ',', '.') }}</b>
                    </span>
                </div>

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-4 py-3">Pasien &amp; DPJP</th>
                                <th class="px-4 py-3">Diagnosa</th>
                                <th class="px-4 py-3">Poli</th>
                                <th class="px-4 py-3">Cara Keluar</th>
                                <th class="px-4 py-3">CBG</th>
                                <th class="px-4 py-3 text-right">Tarif (RS vs Klaim)</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                            @forelse ($this->rows as $row)
                                @php $c = $this->claims[$row->vno_sep] ?? null; @endphp
                                <tr class="transition hover:bg-surface-soft dark:hover:bg-gray-800/50" wire:key="rekap-{{ $row->rj_no }}">
                                    @if ($c && ($c['status'] ?? '') === 'ok')
                                        <td class="px-4 py-3 align-top">
                                            <div class="text-lg font-semibold text-brand dark:text-white">{{ $c['nama_pasien'] !== '-' ? $c['nama_pasien'] : $row->reg_name }}</div>
                                            <div class="text-base text-body dark:text-gray-300">No. RM <span class="font-mono">{{ $c['nomor_rm'] !== '-' ? $c['nomor_rm'] : $row->reg_no }}</span></div>
                                            <div class="text-sm text-body dark:text-gray-300">DPJP: {{ $c['nama_dokter'] !== '-' ? $c['nama_dokter'] : ($row->dr_name ?? '-') }}</div>
                                            <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">Tgl RJ {{ $row->rj_date_display ?? '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm">{{ $c['diagnosa'] }}</td>
                                        <td class="px-4 py-3">{{ $row->poli_desc ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $c['discharge'] }}</td>
                                        <td class="px-4 py-3 font-mono text-sm">{{ $c['cbg_code'] }}</td>
                                    @else
                                        <td class="px-4 py-3 align-top">
                                            <div class="text-lg font-semibold text-brand dark:text-white">{{ $row->reg_name }}</div>
                                            <div class="text-base text-body dark:text-gray-300">No. RM <span class="font-mono">{{ $row->reg_no }}</span></div>
                                            <div class="text-sm text-body dark:text-gray-300">DPJP: {{ $row->dr_name ?? '-' }}</div>
                                            <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">Tgl RJ {{ $row->rj_date_display ?? '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-muted-soft align-top">—</td>
                                        <td class="px-4 py-3 align-top">{{ $row->poli_desc ?? '-' }}</td>
                                        <td class="px-4 py-3 text-muted-soft align-top" colspan="2">
                                            @if ($c && ($c['status'] ?? '') === 'error')
                                                <span class="text-error" title="{{ $c['msg'] ?? '' }}">⚠ {{ \Illuminate\Support\Str::limit($c['msg'] ?? 'Gagal', 60) }}</span>
                                            @else
                                                <span class="italic">Belum ditarik — SEP {{ $row->vno_sep }}</span>
                                            @endif
                                        </td>
                                    @endif
                                    {{-- Perbandingan tarif: Tarif RS (biaya) sbg dasar, lalu yang DIBAYARKAN
                                         (INA-CBG dari E-Klaim & iDRG dari grouping lokal) + selisih vs RS. --}}
                                    <td class="px-4 py-3 text-sm align-top whitespace-nowrap">
                                        @php $okClaim = $c && ($c['status'] ?? '') === 'ok'; @endphp
                                        <div class="space-y-1 min-w-[200px]">
                                            {{-- Biaya RS (dasar) --}}
                                            <div class="flex items-center justify-between gap-4 pb-1 border-b border-hairline-soft dark:border-gray-800">
                                                <span class="text-muted">Tarif RS</span>
                                                <span class="font-medium text-ink dark:text-gray-100">{{ $okClaim ? 'Rp ' . number_format($c['tarif_rs'], 0, ',', '.') : '—' }}</span>
                                            </div>
                                            {{-- INA-CBG (dibayar) --}}
                                            <div class="flex items-center justify-between gap-4">
                                                <span class="text-muted">INA-CBG</span>
                                                @if ($okClaim)
                                                    @php $selIna = $c['tarif_ina'] - $c['tarif_rs']; @endphp
                                                    <span class="text-right">
                                                        <span class="font-semibold text-ink dark:text-gray-100">Rp {{ number_format($c['tarif_ina'], 0, ',', '.') }}</span>
                                                        <span class="block text-xs {{ $selIna < 0 ? 'text-error' : 'text-success' }}">{{ $selIna < 0 ? '−' : '+' }}Rp {{ number_format(abs($selIna), 0, ',', '.') }}</span>
                                                    </span>
                                                @else
                                                    <span class="italic text-muted-soft">belum ditarik</span>
                                                @endif
                                            </div>
                                            {{-- iDRG (dibayar, dari grouping lokal) --}}
                                            <div class="flex items-center justify-between gap-4">
                                                <span class="text-muted">iDRG</span>
                                                @if ($row->idrg['has'])
                                                    <span class="text-right">
                                                        <span class="font-semibold {{ $row->idrg['final'] ? 'text-success' : 'text-warning' }}">{{ $row->idrg['final'] ? '' : '** ' }}Rp {{ number_format($row->idrg['total'], 0, ',', '.') }}</span>
                                                        @if ($okClaim)
                                                            @php $selIdrg = $row->idrg['total'] - $c['tarif_rs']; @endphp
                                                            <span class="block text-xs {{ $selIdrg < 0 ? 'text-error' : 'text-success' }}">{{ $selIdrg < 0 ? '−' : '+' }}Rp {{ number_format(abs($selIdrg), 0, ',', '.') }}</span>
                                                        @endif
                                                        <span class="block text-xs font-mono text-muted-soft">{{ $row->idrg['drg'] }}</span>
                                                    </span>
                                                @else
                                                    <span class="text-muted-soft">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" wire:click="tarikSatu('{{ $row->vno_sep }}')"
                                            wire:loading.attr="disabled" wire:target="tarikSatu('{{ $row->vno_sep }}')"
                                            class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
                                            <span wire:loading.remove wire:target="tarikSatu('{{ $row->vno_sep }}')">↻</span>
                                            <span wire:loading wire:target="tarikSatu('{{ $row->vno_sep }}')">…</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16">
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
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>

            </div>
        </div>
    </div>
</div>
