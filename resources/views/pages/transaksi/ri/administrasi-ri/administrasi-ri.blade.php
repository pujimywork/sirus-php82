<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    // ── Sum Biaya ──
    public int $sumAdminAge    = 0;
    public int $sumAdminStatus = 0;
    public int $sumRiVisit     = 0;
    public int $sumRiKonsul    = 0;
    public int $sumRiJasaMedis = 0;
    public int $sumRiJasaDokter = 0;
    public int $sumRiLab       = 0;
    public int $sumRiRad       = 0;
    public int $sumRiTrfUgdRj  = 0;
    public int $sumRiLainLain  = 0;
    public int $sumRiOk        = 0;
    public int $sumRiRoom      = 0;
    public int $sumRiCService  = 0;
    public int $sumRiPerawatan = 0;
    public int $sumRiBonResep  = 0;
    public int $sumRiRtnObat   = 0;
    public int $sumRiObatPinjam = 0;
    public int $sumTotalRI     = 0;

    // ── Sub-Tab ──
    public string $activeTabAdministrasi = 'RiVisit';
    public array $EmrMenuAdministrasi = [
        ['ermMenuId' => 'RiVisit',     'ermMenuName' => 'Visit'],
        ['ermMenuId' => 'RiKonsul',    'ermMenuName' => 'Konsul'],
        ['ermMenuId' => 'RiJasaMedis', 'ermMenuName' => 'Jasa Medis'],
        ['ermMenuId' => 'RiJasaDokter','ermMenuName' => 'Jasa Dokter'],
        ['ermMenuId' => 'RiLab',       'ermMenuName' => 'Laboratorium'],
        ['ermMenuId' => 'RiRad',       'ermMenuName' => 'Radiologi'],
        ['ermMenuId' => 'RiTrfUgdRj',  'ermMenuName' => 'Trf UGD/RJ'],
        ['ermMenuId' => 'RiLainLain',  'ermMenuName' => 'Lain-Lain'],
        ['ermMenuId' => 'RiOk',        'ermMenuName' => 'Operasi (OK)'],
        ['ermMenuId' => 'RiRoom',      'ermMenuName' => 'Kamar'],
        ['ermMenuId' => 'RiBonResep',  'ermMenuName' => 'Bon Resep'],
        ['ermMenuId' => 'RiRtnObat',   'ermMenuName' => 'Return Obat'],
        ['ermMenuId' => 'RiObatPinjam','ermMenuName' => 'Obat Pinjam'],
        ['ermMenuId' => 'RiAdminLog',  'ermMenuName' => 'Admin Log'],
        ['ermMenuId' => 'RiKasir',    'ermMenuName' => 'Kasir Pulang'],
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    #[On('emr-ri.administrasi.open')]
    public function openAdministrasiPasien(int $riHdrNo): void
    {
        $this->resetForm();
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $dataDaftarRI = $this->findDataRI($riHdrNo);
        if (!$dataDaftarRI) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        $this->dataDaftarRI = $dataDaftarRI;

        if ($this->checkRIStatus($riHdrNo)) {
            $this->isFormLocked = true;
        }

        $this->sumAll();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'emr-ri-administrasi');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-ri-administrasi');
    }

    /* ===============================
     | SUM ALL — query langsung dari DB agar selalu akurat
     =============================== */
    public function sumAll(): void
    {
        if (!$this->riHdrNo) {
            return;
        }

        $n = $this->riHdrNo;

        $hdr = DB::table('rstxn_rihdrs')
            ->select('admin_age', 'admin_status')
            ->where('rihdr_no', $n)
            ->first();

        $this->sumAdminAge    = (int) ($hdr->admin_age    ?? 0);
        $this->sumAdminStatus = (int) ($hdr->admin_status ?? 0);

        $this->sumRiVisit = (int) DB::table('rstxn_rivisits')
            ->where('rihdr_no', $n)->sum('visit_price');

        $this->sumRiKonsul = (int) DB::table('rstxn_rikonsuls')
            ->where('rihdr_no', $n)->sum('konsul_price');

        $this->sumRiJasaMedis = (int) DB::table('rstxn_riactparams')
            ->where('rihdr_no', $n)->selectRaw('nvl(sum(actp_price * actp_qty),0) as total')->value('total');

        $this->sumRiJasaDokter = (int) DB::table('rstxn_riactdocs')
            ->where('rihdr_no', $n)->selectRaw('nvl(sum(actd_price * actd_qty),0) as total')->value('total');

        $this->sumRiLab = (int) DB::table('rstxn_rilabs')
            ->where('rihdr_no', $n)->sum('lab_price');

        $this->sumRiRad = (int) DB::table('rstxn_riradiologs')
            ->where('rihdr_no', $n)->sum('rirad_price');

        $this->sumRiTrfUgdRj = (int) DB::table('rstxn_ritempadmins')
            ->where('rihdr_no', $n)
            ->selectRaw('nvl(sum(
                nvl(rj_admin,0) + nvl(poli_price,0) + nvl(acte_price,0) +
                nvl(actp_price,0) + nvl(actd_price,0) + nvl(obat,0) +
                nvl(rad,0) + nvl(lab,0) + nvl(other,0) + nvl(rs_admin,0)
            ),0) as total')
            ->value('total');

        $this->sumRiLainLain = (int) DB::table('rstxn_riothers')
            ->where('rihdr_no', $n)->sum('other_price');

        $this->sumRiOk = (int) DB::table('rstxn_rioks')
            ->where('rihdr_no', $n)->sum('ok_price');

        $room = DB::table('rsmst_trfrooms')
            ->where('rihdr_no', $n)
            ->selectRaw("nvl(sum(room_price * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as room_total")
            ->selectRaw("nvl(sum(common_service * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as cs_total")
            ->selectRaw("nvl(sum(perawatan_price * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as perwt_total")
            ->first();

        $this->sumRiRoom      = (int) ($room->room_total  ?? 0);
        $this->sumRiCService  = (int) ($room->cs_total    ?? 0);
        $this->sumRiPerawatan = (int) ($room->perwt_total ?? 0);

        $this->sumRiBonResep = (int) DB::table('rstxn_ribonobats')
            ->where('rihdr_no', $n)->sum('ribon_price');

        $this->sumRiRtnObat = (int) DB::table('rstxn_riobatrtns')
            ->where('rihdr_no', $n)->selectRaw('nvl(sum(riobat_qty * riobat_price),0) as total')->value('total');

        $this->sumRiObatPinjam = (int) DB::table('rstxn_riobats')
            ->where('rihdr_no', $n)->selectRaw('nvl(sum(riobat_qty * riobat_price),0) as total')->value('total');

        $this->sumTotalRI =
            $this->sumRiVisit +
            $this->sumRiKonsul +
            $this->sumRiJasaMedis +
            $this->sumRiJasaDokter +
            $this->sumRiLab +
            $this->sumRiRad +
            $this->sumRiTrfUgdRj +
            $this->sumRiLainLain +
            $this->sumRiOk +
            $this->sumRiRoom +
            $this->sumRiCService +
            $this->sumRiPerawatan +
            $this->sumRiBonResep +
            $this->sumRiObatPinjam +
            $this->sumAdminAge +
            $this->sumAdminStatus -
            $this->sumRiRtnObat;
    }

    /* ===============================
     | LISTENER — dari semua child
     =============================== */
    #[On('administrasi-ri.updated')]
    public function onAdministrasiUpdated(): void
    {
        $this->sumAll();

        if ($this->checkRIStatus($this->riHdrNo)) {
            $this->isFormLocked = true;
            $this->incrementVersion('modal');
        } else {
            $this->isFormLocked = false;
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['riHdrNo', 'dataDaftarRI']);
        $this->resetVersion();
        $this->isFormLocked     = false;
        $this->activeTabAdministrasi = 'RiVisit';
        $this->sumAdminAge      = $this->sumAdminStatus   = 0;
        $this->sumRiVisit       = $this->sumRiKonsul      = 0;
        $this->sumRiJasaMedis   = $this->sumRiJasaDokter  = 0;
        $this->sumRiLab         = $this->sumRiRad         = 0;
        $this->sumRiTrfUgdRj    = $this->sumRiLainLain    = 0;
        $this->sumRiOk          = $this->sumRiRoom        = 0;
        $this->sumRiCService    = $this->sumRiPerawatan   = 0;
        $this->sumRiBonResep    = $this->sumRiRtnObat     = 0;
        $this->sumRiObatPinjam  = $this->sumTotalRI       = 0;
    }
};
?>

<div>
    <x-modal name="emr-ri-administrasi" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$riHdrNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>

                <div class="relative flex items-center justify-between gap-4">

                    {{-- KIRI: Logo + Judul --}}
                    <div class="flex items-center flex-shrink-0 gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah" class="block w-6 h-6 dark:hidden" />
                            <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah" class="hidden w-6 h-6 dark:block" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Administrasi Rawat Inap</h2>
                                <x-badge variant="brand" class="flex items-center gap-1.5 px-2 py-0.5 text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    RI
                                </x-badge>
                                @if ($isFormLocked)
                                    <x-badge variant="danger" class="text-xs">Read Only</x-badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                Kelola administrasi dan tagihan pasien rawat inap
                            </p>
                        </div>
                    </div>

                    {{-- TENGAH: Ringkasan Biaya --}}
                    <div class="flex-1 p-2 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-3">

                            {{-- Grid biaya --}}
                            <div class="grid flex-1 grid-cols-4 gap-1.5 md:grid-cols-7">
                                @foreach ([
                                    ['label' => 'Visit',        'value' => $sumRiVisit],
                                    ['label' => 'Konsul',       'value' => $sumRiKonsul],
                                    ['label' => 'Jasa Medis',   'value' => $sumRiJasaMedis],
                                    ['label' => 'Jasa Dokter',  'value' => $sumRiJasaDokter],
                                    ['label' => 'Lab',          'value' => $sumRiLab],
                                    ['label' => 'Radiologi',    'value' => $sumRiRad],
                                    ['label' => 'Kamar',        'value' => $sumRiRoom + $sumRiCService + $sumRiPerawatan],
                                    ['label' => 'Lain-Lain',    'value' => $sumRiLainLain],
                                    ['label' => 'Operasi (OK)', 'value' => $sumRiOk],
                                    ['label' => 'Bon Resep',    'value' => $sumRiBonResep],
                                    ['label' => 'Obat Pinjam',  'value' => $sumRiObatPinjam],
                                    ['label' => 'Trf UGD/RJ',   'value' => $sumRiTrfUgdRj],
                                    ['label' => 'Admin',        'value' => $sumAdminAge + $sumAdminStatus],
                                    ['label' => 'Rtn Obat (-)', 'value' => $sumRiRtnObat],
                                ] as $item)
                                    <div class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">{{ $item['label'] }}</p>
                                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 tabular-nums">
                                            Rp {{ number_format($item['value']) }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Total Tagihan --}}
                            <div class="flex-shrink-0 px-5 py-3 text-right border rounded-2xl bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20">
                                <p class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                    Total Tagihan
                                </p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums whitespace-nowrap">
                                    Rp {{ number_format($sumTotalRI) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- KANAN: Close --}}
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="flex-shrink-0">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Info Pasien --}}
                    <div>
                        <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                            wire:key="display-pasien-ri-admin-{{ $riHdrNo }}" />
                    </div>

                    {{-- SUB-TAB --}}
                    <div x-data="{ tab: @entangle('activeTabAdministrasi') }"
                        class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                        {{-- Tab Nav --}}
                        <div class="flex flex-wrap p-2 border-b border-gray-200 dark:border-gray-700">
                            @foreach ($EmrMenuAdministrasi as $menu)
                                <button type="button" x-on:click="tab = '{{ $menu['ermMenuId'] }}'"
                                    x-bind:class="tab === '{{ $menu['ermMenuId'] }}'
                                        ? 'border-b-2 border-brand-green text-brand-green dark:border-brand-lime dark:text-brand-lime font-semibold bg-brand-green/5 dark:bg-brand-lime/5'
                                        : 'border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                                    class="px-4 py-2.5 -mb-px text-sm transition-all whitespace-nowrap rounded-t-lg">
                                    {{ $menu['ermMenuName'] }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Tab Content --}}
                        <div class="p-4 min-h-[300px]">

                            <div x-show="tab === 'RiVisit'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.visit-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-visit-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiKonsul'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.konsul-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-konsul-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiJasaMedis'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.jasa-medis-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-jasa-medis-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiJasaDokter'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.jasa-dokter-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-jasa-dokter-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiLab'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.laboratorium-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-lab-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiRad'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.radiologi-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-rad-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiTrfUgdRj'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.trf-ugd-rj-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-trf-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiLainLain'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.lain-lain-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-lain-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiOk'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.o-k-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-ok-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiRoom'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.room-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-room-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiBonResep'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.bon-resep-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-bon-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiRtnObat'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.rtn-obat-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-rtn-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiObatPinjam'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.obat-pinjam-ri :riHdrNo="$riHdrNo" :isFormLocked="$isFormLocked"
                                    wire:key="tab-obat-pinjam-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiAdminLog'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.admin-log-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-adminlog-ri-{{ $riHdrNo }}" />
                            </div>

                            <div x-show="tab === 'RiKasir'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.ri.administrasi-ri.kasir-ri :riHdrNo="$riHdrNo"
                                    wire:key="tab-kasir-ri-{{ $riHdrNo }}" />
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </div>
    </x-modal>
</div>
