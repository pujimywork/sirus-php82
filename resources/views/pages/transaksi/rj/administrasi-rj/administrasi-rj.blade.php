<?php
use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    // ── Sum Biaya ──
    public int $sumRsAdmin = 0;
    public int $sumRjAdmin = 0;
    public int $sumPoliPrice = 0;
    public int $sumJasaKaryawan = 0;
    public int $sumJasaDokter = 0;
    public int $sumJasaMedis = 0;
    public int $sumObat = 0;
    public int $sumLaboratorium = 0;
    public int $sumRadiologi = 0;
    public int $sumLainLain = 0;
    public int $sumTotalRJ = 0;

    // ── Status Resep ──
    public array $statusResep = [
        'status' => 'DITUNGGU',
        'keterangan' => '',
    ];

    // ── Sub-Tab ──
    public string $activeTabAdministrasi = 'JasaKaryawan';
    public array $EmrMenuAdministrasi = [['ermMenuId' => 'JasaKaryawan', 'ermMenuName' => 'Jasa Karyawan'], ['ermMenuId' => 'JasaDokter', 'ermMenuName' => 'Jasa Dokter'], ['ermMenuId' => 'JasaMedis', 'ermMenuName' => 'Jasa Medis'], ['ermMenuId' => 'Obat', 'ermMenuName' => 'Obat'], ['ermMenuId' => 'Laboratorium', 'ermMenuName' => 'Laboratorium'], ['ermMenuId' => 'Radiologi', 'ermMenuName' => 'Radiologi'], ['ermMenuId' => 'LainLain', 'ermMenuName' => 'Lain-Lain'], ['ermMenuId' => 'Kasir', 'ermMenuName' => 'Kasir']];

    /* ═══════════════════════════════════════
     | OPEN MODAL
    ═══════════════════════════════════════ */
    #[On('emr-rj.administrasi.open')]
    public function openAdministrasiPasien(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->sumAll();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'emr-rj-administrasi');
    }

    /* ═══════════════════════════════════════
     | CLOSE MODAL
    ═══════════════════════════════════════ */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-rj-administrasi');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->sumRsAdmin = $this->sumRjAdmin = $this->sumPoliPrice = 0;
        $this->sumJasaKaryawan = $this->sumJasaDokter = $this->sumJasaMedis = 0;
        $this->sumObat = $this->sumLaboratorium = $this->sumRadiologi = 0;
        $this->sumLainLain = $this->sumTotalRJ = 0;
        $this->statusResep = ['status' => 'DITUNGGU', 'keterangan' => ''];
    }

    /* ═══════════════════════════════════════
     | SUM ALL — query langsung dari DB
     | (bukan dari JSON agar selalu akurat)
    ═══════════════════════════════════════ */
    public function sumAll(): void
    {
        if (!$this->rjNo) {
            return;
        }

        $rjNo = $this->rjNo;

        // ── Admin dari header ──
        $hdr = DB::table('rstxn_rjhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $rjNo)->first();

        $this->sumRsAdmin = (int) ($hdr->rs_admin ?? 0);
        $this->sumRjAdmin = (int) ($hdr->rj_admin ?? 0);
        $this->sumPoliPrice = (int) ($hdr->poli_price ?? 0);

        // ── Jasa Karyawan ── rstxn_rjactemps
        $this->sumJasaKaryawan = (int) DB::table('rstxn_rjactemps')->where('rj_no', $rjNo)->sum('acte_price');

        // ── Jasa Dokter ── rstxn_rjaccdocs
        $this->sumJasaDokter = (int) DB::table('rstxn_rjaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price');

        // ── Jasa Medis ── rstxn_rjactparams
        $this->sumJasaMedis = (int) DB::table('rstxn_rjactparams')->where('rj_no', $rjNo)->sum('pact_price');

        // ── Obat ── qty × price
        $this->sumObat = (int) DB::table('rstxn_rjobats')->where('rj_no', $rjNo)->selectRaw('nvl(sum(qty * price), 0) as total')->value('total');

        // ── Laboratorium ──
        $this->sumLaboratorium = (int) DB::table('rstxn_rjlabs')->where('rj_no', $rjNo)->sum('lab_price');

        // ── Radiologi ──
        $this->sumRadiologi = (int) DB::table('rstxn_rjrads')->where('rj_no', $rjNo)->sum('rad_price');

        // ── Lain-lain ── rstxn_rjothers
        $this->sumLainLain = (int) DB::table('rstxn_rjothers')->where('rj_no', $rjNo)->sum('other_price');

        // ── Grand Total ──
        $this->sumTotalRJ = $this->sumRsAdmin + $this->sumRjAdmin + $this->sumPoliPrice + $this->sumJasaKaryawan + $this->sumJasaDokter + $this->sumJasaMedis + $this->sumObat + $this->sumLaboratorium + $this->sumRadiologi + $this->sumLainLain;
    }

    /* ═══════════════════════════════════════
     | FIND DATA (untuk keperluan admin/resep)
    ═══════════════════════════════════════ */
    private function findData(int $rjNo): array
    {
        $data = $this->findDataRJ($rjNo) ?? [];

        $hdr = DB::table('rstxn_rjhdrs')->select('rs_admin', 'rj_admin', 'poli_price', 'klaim_id', 'pass_status')->where('rj_no', $rjNo)->first();

        // ── RJ Admin ──
        if ($hdr->pass_status === 'N') {
            $data['rjAdmin'] = isset($data['rjAdmin']) ? (int) $hdr->rj_admin : (int) DB::table('rsmst_parameters')->where('par_id', 1)->value('par_value');
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update(['rj_admin' => $data['rjAdmin']]);
        } else {
            $data['rjAdmin'] = 0;
        }

        // ── RS Admin ──
        $dokter = DB::table('rsmst_doctors')
            ->select('rs_admin', 'poli_price', 'poli_price_bpjs')
            ->where('dr_id', $data['drId'] ?? '')
            ->first();

        $data['rsAdmin'] = isset($data['rsAdmin']) ? (int) ($hdr->rs_admin ?? 0) : (int) ($dokter->rs_admin ?? 0);

        if (!isset($data['rsAdmin'])) {
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update(['rs_admin' => $data['rsAdmin']]);
        }

        // ── Poli Price ──
        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $data['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';

        $dokterPoliPrice = $klaimStatus === 'BPJS' ? $dokter->poli_price_bpjs ?? 0 : $dokter->poli_price ?? 0;

        $data['poliPrice'] = isset($data['poliPrice']) ? (int) ($hdr->poli_price ?? 0) : (int) $dokterPoliPrice;

        if (!isset($data['poliPrice'])) {
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update(['poli_price' => $data['poliPrice']]);
        }

        // ── Kronis ──
        if ($hdr->klaim_id === 'KR') {
            $data['rjAdmin'] = $data['rsAdmin'] = $data['poliPrice'] = 0;
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'rj_admin' => 0,
                    'rs_admin' => 0,
                    'poli_price' => 0,
                ]);
        }

        // ── Status Resep ──
        $this->statusResep = $data['statusResep'] ?? ['status' => null, 'keterangan' => ''];
        if (!isset($data['statusResep'])) {
            $data['statusResep'] = $this->statusResep;
        }

        return $data;
    }

    /* ═══════════════════════════════════════
     | SELESAI ADMINISTRASI
    ═══════════════════════════════════════ */
    public function setSelesaiAdministrasiStatus(int $rjNo): void
    {
        $data = $this->findData($rjNo);

        if (isset($data['AdministrasiRj'])) {
            $this->dispatch('toast', type: 'error', message: 'Administrasi sudah tersimpan oleh ' . $data['AdministrasiRj']['userLog']);
            return;
        }

        $data['AdministrasiRj'] = [
            'userLog' => auth()->user()->myuser_name,
            'userLogDate' => Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y H:i:s'),
        ];

        $this->updateJsonRJ($rjNo, $data);
        $this->dispatch('toast', type: 'success', message: 'Administrasi berhasil disimpan.');
        $this->sumAll();
    }

    /* ═══════════════════════════════════════
     | STATUS RESEP AUTO-SAVE
    ═══════════════════════════════════════ */
    public function updatedStatusResepStatus(): void
    {
        $this->autoSaveStatusResep();
    }

    public function updatedStatusResepKeterangan(): void
    {
        $this->autoSaveStatusResep();
    }

    protected function autoSaveStatusResep(): void
    {
        if (!$this->rjNo || empty($this->statusResep['status'])) {
            return;
        }

        $data = $this->findData($this->rjNo);
        $data['statusResep'] = [
            'status' => $this->statusResep['status'],
            'keterangan' => $this->statusResep['keterangan'] ?? '',
            'userLog' => auth()->user()->myuser_name,
            'userLogDate' => Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y H:i:s'),
        ];

        $this->updateJsonRJ($this->rjNo, $data);
        $this->dispatch('toast', type: 'success', message: 'Status resep "' . $this->statusResep['status'] . '" disimpan.');
    }

    /* ═══════════════════════════════════════
     | LISTENER — dari semua child
     | insertObat, removeObat, insertLab, removeLab,
     | insertRad, removeRad, insertJasaKaryawan, dst.
    ═══════════════════════════════════════ */
    #[On(event: 'administrasi-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        $this->sumAll();
        $this->dispatch('administrasi-obat-rj.updated');
        $this->dispatch('administrasi-lain-lain-rj.updated');
    }

    /* ═══════════════════════════════════════
     | LIFECYCLE
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }
};
?>

<div>
    <x-modal name="emr-rj-administrasi" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Administrasi Pasien
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola administrasi dan berkas pasien rawat jalan
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>

                <div class="flex gap-1 mt-4">
                    <x-badge variant="brand" class="flex items-center gap-1.5 px-3 py-1 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Administrasi
                    </x-badge>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <div class="grid grid-cols-2 gap-2">
                        {{-- Info Pasien --}}
                        <div
                            class="col-span-1 p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="display-pasien-rj-{{ $rjNo }}" />
                        </div>

                        {{-- RINGKASAN BIAYA --}}
                        <div
                            class="col-span-1 p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            <div class="flex items-start justify-between gap-4">

                                <div class="grid flex-1 grid-cols-3 gap-2">
                                    @foreach ([['label' => 'RS Admin', 'value' => $sumRsAdmin], ['label' => 'Admin OB', 'value' => $sumRjAdmin], ['label' => 'Uang Periksa', 'value' => $sumPoliPrice], ['label' => 'Jasa Karyawan', 'value' => $sumJasaKaryawan], ['label' => 'Jasa Dokter', 'value' => $sumJasaDokter], ['label' => 'Jasa Medis', 'value' => $sumJasaMedis], ['label' => 'Obat', 'value' => $sumObat], ['label' => 'Laboratorium', 'value' => $sumLaboratorium], ['label' => 'Radiologi', 'value' => $sumRadiologi], ['label' => 'Lain-Lain', 'value' => $sumLainLain]] as $item)
                                        <div
                                            class="px-3 py-2 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                                                {{ $item['label'] }}
                                            </p>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                                Rp {{ number_format($item['value']) }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>

                                <div
                                    class="flex-shrink-0 min-w-[180px] px-5 py-3 rounded-2xl text-right
                                        bg-brand-green/10 dark:bg-brand-lime/10
                                        border border-brand-green/20 dark:border-brand-lime/20">
                                    <p
                                        class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime">
                                        Total Tagihan
                                    </p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                        Rp {{ number_format($sumTotalRJ) }}
                                    </p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- SUB-TAB --}}
                    <div x-data="{ tab: @entangle('activeTabAdministrasi') }"
                        class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                        <div class="flex flex-wrap px-4 pt-3 pb-0 border-b border-gray-200 dark:border-gray-700">
                            @foreach ($EmrMenuAdministrasi as $menu)
                                <button type="button" x-on:click="tab = '{{ $menu['ermMenuId'] }}'"
                                    x-bind:class="tab === '{{ $menu['ermMenuId'] }}'
                                        ?
                                        'border-b-2 border-brand-green text-brand-green dark:border-brand-lime dark:text-brand-lime font-semibold bg-brand-green/5 dark:bg-brand-lime/5' :
                                        'border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                                    class="px-4 py-2.5 -mb-px text-sm transition-all whitespace-nowrap rounded-t-lg">
                                    {{ $menu['ermMenuName'] }}
                                </button>
                            @endforeach
                        </div>

                        <div class="p-4 min-h-[300px]">

                            <div x-show="tab === 'JasaKaryawan'" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.jasa-karyawan-rj :rjNo="$rjNo"
                                    wire:key="tab-jasa-karyawan-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'JasaDokter'" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.jasa-dokter-rj :rjNo="$rjNo"
                                    wire:key="tab-jasa-dokter-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'JasaMedis'" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.jasa-medis-rj :rjNo="$rjNo"
                                    wire:key="tab-jasa-medis-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'Obat'" x-cloak x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.obat-rj :rjNo="$rjNo"
                                    wire:key="tab-obat-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'Laboratorium'" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.laboratorium-rj :rjNo="$rjNo"
                                    wire:key="tab-laboratorium-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'Radiologi'" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.radiologi-rj :rjNo="$rjNo"
                                    wire:key="tab-radiologi-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'LainLain'" x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.lain-lain-rj :rjNo="$rjNo"
                                    wire:key="tab-lain-lain-{{ $rjNo }}" />
                            </div>

                            <div x-show="tab === 'Kasir'" x-cloak x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <livewire:pages::transaksi.rj.administrasi-rj.kasir-rj :rjNo="$rjNo"
                                    wire:key="tab-kasir-{{ $rjNo }}" />
                            </div>

                        </div>
                    </div>

                    {{-- STATUS RESEP + SELESAI --}}
                    <div
                        class="flex items-end justify-between gap-4 p-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                        <div class="grid flex-1 grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Status Pengambilan Obat" class="mb-2" />
                                <x-select-input wire:model.live="statusResep.status">
                                    <option value="">-- Pilih Status --</option>
                                    <option value="DITUNGGU">Ditunggu</option>
                                    <option value="DITINGGAL">Ditinggal</option>
                                </x-select-input>
                            </div>

                            <div>
                                <x-input-label for="keteranganResep" value="Keterangan Pasien" class="mb-1" />
                                <x-text-input id="keteranganResep"
                                    wire:model.live.debounce.800ms="statusResep.keterangan"
                                    placeholder="Masukkan catatan pasien…" class="w-full text-sm" />
                            </div>
                        </div>

                        <div class="flex-shrink-0">
                            <button type="button"
                                wire:click.prevent="setSelesaiAdministrasiStatus({{ $rjNo }})"
                                wire:loading.attr="disabled" wire:target="setSelesaiAdministrasiStatus"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                                       text-white shadow-sm transition
                                       bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60">
                                <span wire:loading.remove wire:target="setSelesaiAdministrasiStatus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="setSelesaiAdministrasiStatus">
                                    <x-loading class="w-4 h-4" />
                                </span>
                                Administrasi Selesai
                            </button>
                        </div>

                    </div>

                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end">
                    <x-secondary-button wire:click="closeModal" type="button">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
