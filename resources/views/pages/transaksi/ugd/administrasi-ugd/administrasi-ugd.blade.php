<?php
// resources/views/pages/transaksi/ugd/administrasi-ugd/administrasi-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

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
    public int $sumtrfRJ = 0;
    public int $sumTotalRJ = 0;

    public int $editRsAdmin = 0;
    public int $editRjAdmin = 0;
    public int $editPoliPrice = 0;

    // ── Status Resep ──
    public array $statusResep = [
        'status' => 'DITUNGGU',
        'keterangan' => '',
    ];

    // ── Sub-Tab ──
    public string $activeTabAdministrasi = 'JasaKaryawan';
    public array $EmrMenuAdministrasi = [['ermMenuId' => 'JasaKaryawan', 'ermMenuName' => 'Jasa Karyawan'], ['ermMenuId' => 'JasaDokter', 'ermMenuName' => 'Jasa Dokter'], ['ermMenuId' => 'JasaMedis', 'ermMenuName' => 'Jasa Medis'], ['ermMenuId' => 'Obat', 'ermMenuName' => 'Obat'], ['ermMenuId' => 'Laboratorium', 'ermMenuName' => 'Laboratorium'], ['ermMenuId' => 'Radiologi', 'ermMenuName' => 'Radiologi'], ['ermMenuId' => 'LainLain', 'ermMenuName' => 'Lain-Lain'], ['ermMenuId' => 'Transfer', 'ermMenuName' => 'Transfer'], ['ermMenuId' => 'Kasir', 'ermMenuName' => 'Kasir']];

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
    #[On('emr-ugd.administrasi.open')]
    public function openAdministrasiPasien(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;
        $this->statusResep = [
            'status' => $this->dataDaftarUGD['statusResep']['status'] ?? 'DITUNGGU',
            'keterangan' => $this->dataDaftarUGD['statusResep']['keterangan'] ?? '',
        ];

        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->sumAll();

        $this->editRsAdmin = $this->sumRsAdmin;
        $this->editRjAdmin = $this->sumRjAdmin;
        $this->editPoliPrice = $this->sumPoliPrice;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'emr-ugd-administrasi');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-ugd-administrasi');
    }

    /* ===============================
     | RESET FORM
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD']);
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->sumRsAdmin = $this->sumRjAdmin = $this->sumPoliPrice = 0;
        $this->sumJasaKaryawan = $this->sumJasaDokter = $this->sumJasaMedis = 0;
        $this->sumObat = $this->sumLaboratorium = $this->sumRadiologi = 0;
        $this->sumLainLain = $this->sumtrfRJ = $this->sumTotalRJ = 0;
        $this->statusResep = ['status' => 'DITUNGGU', 'keterangan' => ''];
    }

    /* ===============================
     | SUM ALL — query langsung dari DB
     =============================== */
    public function sumAll(): void
    {
        if (!$this->rjNo) {
            return;
        }

        $rjNo = $this->rjNo;

        $hdr = DB::table('rstxn_ugdhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $rjNo)->first();

        $this->sumRsAdmin = (int) ($hdr->rs_admin ?? 0);
        $this->sumRjAdmin = (int) ($hdr->rj_admin ?? 0);
        $this->sumPoliPrice = (int) ($hdr->poli_price ?? 0);

        $this->sumJasaKaryawan = (int) DB::table('rstxn_ugdactemps')->where('rj_no', $rjNo)->sum('acte_price');
        $this->sumJasaDokter = (int) DB::table('rstxn_ugdaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price');
        $this->sumJasaMedis = (int) DB::table('rstxn_ugdactparams')->where('rj_no', $rjNo)->sum('pact_price');

        $this->sumObat = (int) DB::table('rstxn_ugdobats')->where('rj_no', $rjNo)->selectRaw('nvl(sum(qty * price), 0) as total')->value('total');

        $this->sumLaboratorium = (int) DB::table('rstxn_ugdlabs')->where('rj_no', $rjNo)->sum('lab_price');
        $this->sumRadiologi = (int) DB::table('rstxn_ugdrads')->where('rj_no', $rjNo)->sum('rad_price');
        $this->sumLainLain = (int) DB::table('rstxn_ugdothers')->where('rj_no', $rjNo)->sum('other_price');

        $this->sumtrfRJ = (int) DB::table('rstxn_ugdtempadmins')->where('rj_no', $rjNo)->selectRaw('nvl(sum(rj_admin + poli_price + acte_price + actp_price + actd_price + obat + lab + rad + other + rs_admin), 0) as total')->value('total');

        $this->sumTotalRJ = $this->sumRsAdmin + $this->sumRjAdmin + $this->sumPoliPrice + $this->sumJasaKaryawan + $this->sumJasaDokter + $this->sumJasaMedis + $this->sumObat + $this->sumLaboratorium + $this->sumRadiologi + $this->sumLainLain + $this->sumtrfRJ;
    }

    /* ===============================
     | FIND DATA (admin prices + status resep)
     |
     | ⚠️  Dipanggil DI DALAM DB::transaction setelah lockUGDRow().
     =============================== */
    private function findData(int $rjNo): array
    {
        $data = $this->findDataUGD($rjNo) ?? [];

        $hdr = DB::table('rstxn_ugdhdrs')->select('rs_admin', 'rj_admin', 'poli_price', 'klaim_id', 'pass_status')->where('rj_no', $rjNo)->first();

        // ── RJ Admin ──
        if ($hdr->pass_status === 'N') {
            $data['rjAdmin'] = isset($data['rjAdmin']) ? (int) $hdr->rj_admin : (int) DB::table('rsmst_parameters')->where('par_id', 1)->value('par_value');
            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $rjNo)
                ->update(['rj_admin' => $data['rjAdmin']]);
        } else {
            $data['rjAdmin'] = 0;
        }

        // ── RS Admin ──
        $dokter = DB::table('rsmst_doctors')
            ->select('rs_admin', 'ugd_price', 'ugd_price_bpjs')
            ->where('dr_id', $data['drId'] ?? '')
            ->first();

        $data['rsAdmin'] = isset($data['rsAdmin']) ? (int) ($hdr->rs_admin ?? 0) : (int) ($dokter->rs_admin ?? 0);

        if (!isset($data['rsAdmin'])) {
            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $rjNo)
                ->update(['rs_admin' => $data['rsAdmin']]);
        }

        // ── UGD Price (Uang Periksa) ──
        $klaimStatus =
            DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $data['klaimId'] ?? '')
                ->value('klaim_status') ?? 'UMUM';

        $dokterUgdPrice = $klaimStatus === 'BPJS' ? $dokter->ugd_price_bpjs ?? 0 : $dokter->ugd_price ?? 0;

        $data['poliPrice'] = isset($data['poliPrice']) ? (int) ($hdr->poli_price ?? 0) : (int) $dokterUgdPrice;

        if (!isset($data['poliPrice'])) {
            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $rjNo)
                ->update(['poli_price' => $data['poliPrice']]);
        }

        // ── Kronis ──
        if ($hdr->klaim_id === 'KR') {
            $data['rjAdmin'] = $data['rsAdmin'] = $data['poliPrice'] = 0;
            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $rjNo)
                ->update([
                    'rj_admin' => 0,
                    'rs_admin' => 0,
                    'poli_price' => 0,
                ]);
        }

        // ── Status Resep ──
        $this->statusResep = $data['statusResep'] ?? ['status' => null, 'keterangan' => ''];
        $data['statusResep'] ??= $this->statusResep;

        return $data;
    }

    /* ===============================
     | SELESAI ADMINISTRASI
     =============================== */
    public function setSelesaiAdministrasiStatus(int $rjNo): void
    {
        try {
            DB::transaction(function () use ($rjNo) {
                // 1. Lock row dulu
                $this->lockUGDRow($rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findData($rjNo);

                // 3. Guard — sudah selesai
                if (isset($data['AdministrasiRj'])) {
                    throw new \RuntimeException('Administrasi sudah tersimpan oleh ' . $data['AdministrasiRj']['userLog']);
                }

                // 4. Set tanda selesai administrasi
                $data['AdministrasiRj'] = [
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonUGD($rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 5. Notify + sumAll — di luar transaksi
            $this->dispatch('toast', type: 'success', message: 'Administrasi berhasil disimpan.');
            $this->sumAll();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | STATUS RESEP AUTO-SAVE
     =============================== */
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

        $status = $this->statusResep['status'];
        $keterangan = $this->statusResep['keterangan'] ?? '';

        try {
            DB::transaction(function () use ($status, $keterangan) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findData($this->rjNo);

                // 3. Set status resep
                $data['statusResep'] = [
                    'status' => $status,
                    'keterangan' => $keterangan,
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 4. Notify — di luar transaksi
            $this->dispatch('toast', type: 'success', message: 'Status resep "' . $status . '" berhasil disimpan.');
            if (!empty($keterangan)) {
                $this->dispatch('toast', type: 'success', message: 'Keterangan "' . $keterangan . '" berhasil disimpan.');
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan status resep: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE ADMIN PRICES
     =============================== */
    public function saveAdminPrices(): void
    {
        if (!$this->rjNo) {
            return;
        }

        try {
            $hdr = DB::table('rstxn_ugdhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $this->rjNo)->first();

            // Skip jika tidak ada perubahan
            if ((int) $hdr->rs_admin === $this->editRsAdmin && (int) $hdr->rj_admin === $this->editRjAdmin && (int) $hdr->poli_price === $this->editPoliPrice) {
                return;
            }

            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'rs_admin' => $this->editRsAdmin,
                    'rj_admin' => $this->editRjAdmin,
                    'poli_price' => $this->editPoliPrice,
                ]);

            $this->onAdministrasiUpdated();
            $this->dispatch('toast', type: 'success', message: 'Biaya admin berhasil diperbarui.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LISTENER — dari semua child
     =============================== */
    #[On('administrasi-ugd.updated')]
    public function onAdministrasiUpdated(): void
    {
        $this->sumAll();

        if ($this->checkUGDStatus($this->rjNo)) {
            $this->isFormLocked = true;
            $this->incrementVersion('modal');
        } else {
            $this->isFormLocked = false;
        }

        $this->dispatch('administrasi-obat-ugd.updated');
        $this->dispatch('administrasi-lain-lain-ugd.updated');
        $this->dispatch('administrasi-kasir-ugd.updated');
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetakKwitansi(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->dispatch('cetak-kwitansi-ugd.open', rjNo: $this->rjNo);
    }

    public function cetakKwitansiObat(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->dispatch('cetak-kwitansi-ugd-obat.open', rjNo: $this->rjNo);
    }
};
?>

<div>
    <x-modal name="emr-ugd-administrasi" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-center justify-between gap-4">

                    {{-- KIRI: Logo + Judul --}}
                    <div class="flex items-center flex-shrink-0 gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                class="block w-6 h-6 dark:hidden" />
                            <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                class="hidden w-6 h-6 dark:block" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Administrasi Pasien
                                </h2>
                                <x-badge variant="brand" class="flex items-center gap-1.5 px-2 py-0.5 text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    UGD
                                </x-badge>
                                @if ($isFormLocked)
                                    <x-badge variant="danger" class="text-xs">Read Only</x-badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Kelola administrasi dan berkas
                                pasien unit gawat darurat</p>
                        </div>
                    </div>

                    {{-- TENGAH: Ringkasan Biaya --}}
                    <div
                        class="flex-1 p-2 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-3">
                            <div class="grid flex-1 grid-cols-5 gap-1.5">

                                {{-- 3 Item Editable --}}
                                @foreach ([['label' => 'RS Admin', 'model' => 'editRsAdmin', 'value' => $editRsAdmin], ['label' => 'Admin OB', 'model' => 'editRjAdmin', 'value' => $editRjAdmin], ['label' => 'Uang Periksa', 'model' => 'editPoliPrice', 'value' => $editPoliPrice]] as $item)
                                    <div
                                        class="px-2.5 py-1.5 bg-white border border-brand-green/40 rounded-xl dark:bg-gray-900 dark:border-brand-lime/30">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">
                                            {{ $item['label'] }}</p>
                                        <x-text-input type="text" x-data x-ref="input_{{ $loop->index }}"
                                            x-on:focus="$el.value = $el.value.replace('Rp ', '').replace(/\./g, '')"
                                            x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                                            x-on:keydown.enter="$el.blur()"
                                            x-on:blur="
                                                let raw = parseInt($el.value.replace(/\./g, '')) || 0;
                                                $wire.set('{{ $item['model'] }}', raw).then(() => {
                                                    $wire.saveAdminPrices();
                                                    $el.value = 'Rp ' + new Intl.NumberFormat('id-ID').format(raw);
                                                })
                                            "
                                            value="Rp {{ number_format($item['value'], 0, ',', '.') }}"
                                            :disabled="$isFormLocked" class="w-full text-xs font-semibold tabular-nums" />
                                    </div>
                                @endforeach

                                {{-- 8 Item Read Only --}}
                                @foreach ([['label' => 'Jasa Karyawan', 'value' => $sumJasaKaryawan], ['label' => 'Jasa Dokter', 'value' => $sumJasaDokter], ['label' => 'Jasa Medis', 'value' => $sumJasaMedis], ['label' => 'Obat', 'value' => $sumObat], ['label' => 'Laboratorium', 'value' => $sumLaboratorium], ['label' => 'Radiologi', 'value' => $sumRadiologi], ['label' => 'Lain-Lain', 'value' => $sumLainLain], ['label' => 'Transfer', 'value' => $sumtrfRJ]] as $item)
                                    <div
                                        class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">
                                            {{ $item['label'] }}</p>
                                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 tabular-nums">
                                            Rp {{ number_format($item['value']) }}</p>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Total Tagihan --}}
                            <div
                                class="flex-shrink-0 px-5 py-3 text-right border rounded-2xl bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20">
                                <p
                                    class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                    Total Tagihan</p>
                                <p
                                    class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums whitespace-nowrap">
                                    Rp {{ number_format($sumTotalRJ) }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- KANAN: Close --}}
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="flex-shrink-0">
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
                    <div class="grid grid-cols-1 gap-3">

                        {{-- Info Pasien --}}
                        <div>
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="display-pasien-ugd-{{ $rjNo }}" />
                        </div>

                        {{-- SUB-TAB --}}
                        <div x-data="{ tab: @entangle('activeTabAdministrasi') }"
                            class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                            <div class="flex flex-wrap p-2 border-b border-gray-200 dark:border-gray-700">
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
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.jasa-karyawan-ugd :rjNo="$rjNo"
                                        wire:key="tab-jasa-karyawan-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'JasaDokter'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.jasa-dokter-ugd :rjNo="$rjNo"
                                        wire:key="tab-jasa-dokter-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'JasaMedis'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.jasa-medis-ugd :rjNo="$rjNo"
                                        wire:key="tab-jasa-medis-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'Obat'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.obat-ugd :rjNo="$rjNo"
                                        wire:key="tab-obat-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'Laboratorium'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.laboratorium-ugd :rjNo="$rjNo"
                                        wire:key="tab-laboratorium-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'Radiologi'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.radiologi-ugd :rjNo="$rjNo"
                                        wire:key="tab-radiologi-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'LainLain'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.lain-lain-ugd :rjNo="$rjNo"
                                        wire:key="tab-lain-lain-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'Transfer'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.transfer-ugd :rjNo="$rjNo"
                                        wire:key="tab-transfer-{{ $rjNo }}" />
                                </div>
                                <div x-show="tab === 'Kasir'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.ugd.administrasi-ugd.kasir-ugd :rjNo="$rjNo"
                                        wire:key="tab-kasir-{{ $rjNo }}" />
                                </div>
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
                                <x-input-label value="Keterangan Pasien" class="mb-1" />
                                <x-text-input wire:model.live.debounce.800ms="statusResep.keterangan"
                                    placeholder="Masukkan catatan pasien…" class="w-full text-sm" />
                            </div>
                        </div>

                        <div class="flex-shrink-0">
                            @if (isset($dataDaftarUGD['AdministrasiRj']))
                                <div
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                                    text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20
                                    border border-emerald-200 dark:border-emerald-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Selesai oleh
                                        <strong>{{ $dataDaftarUGD['AdministrasiRj']['userLog'] }}</strong></span>
                                    <span
                                        class="text-xs font-normal text-emerald-500 dark:text-emerald-400">{{ $dataDaftarUGD['AdministrasiRj']['userLogDate'] }}</span>
                                </div>
                            @else
                                <x-primary-button type="button"
                                    wire:click.prevent="setSelesaiAdministrasiStatus({{ $rjNo }})"
                                    wire:loading.attr="disabled" wire:target="setSelesaiAdministrasiStatus"
                                    class="gap-2">
                                    <span wire:loading.remove wire:target="setSelesaiAdministrasiStatus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                    <span wire:loading wire:target="setSelesaiAdministrasiStatus"><x-loading
                                            class="w-4 h-4" /></span>
                                    Administrasi Selesai
                                </x-primary-button>
                            @endif
                        </div>

                    </div>

                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">

                    <x-primary-button type="button" wire:click="cetakKwitansiObat" wire:loading.attr="disabled"
                        wire:target="cetakKwitansiObat" class="gap-2">
                        <span wire:loading.remove wire:target="cetakKwitansiObat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="cetakKwitansiObat"><x-loading class="w-4 h-4" /></span>
                        Cetak Kwitansi Obat
                    </x-primary-button>

                    <x-primary-button type="button" wire:click="cetakKwitansi" wire:loading.attr="disabled"
                        wire:target="cetakKwitansi" class="gap-2">
                        <span wire:loading.remove wire:target="cetakKwitansi">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="cetakKwitansi"><x-loading class="w-4 h-4" /></span>
                        Cetak Kwitansi
                    </x-primary-button>

                    <x-secondary-button wire:click="closeModal" type="button">Tutup</x-secondary-button>

                </div>
            </div>

        </div>
    </x-modal>

    <livewire:pages::components.modul-dokumen.u-g-d.kwitansi.cetak-kwitansi-ugd wire:key="cetak-kwitansi-ugd" />
    <livewire:pages::components.modul-dokumen.u-g-d.kwitansi.cetak-kwitansi-ugd-obat
        wire:key="cetak-kwitansi-ugd-obat" />

</div>
