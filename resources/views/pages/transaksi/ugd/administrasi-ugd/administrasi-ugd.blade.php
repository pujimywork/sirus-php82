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
    public string $rjStatus = 'A'; // sync dari rstxn_ugdhdrs.rj_status — A/L/F/I

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
    public function openAdministrasiPasien(int $rjNo, bool $readOnly = false): void
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

        // $readOnly = dibuka dari bulanan (view-only untuk Casemix verifikasi tagihan vs klaim).
        if ($this->checkUGDStatus($rjNo) || $readOnly) {
            $this->isFormLocked = true;
        }

        $this->rjStatus = DB::table('rstxn_ugdhdrs')->where('rj_no', $rjNo)->value('rj_status') ?? 'A';

        // Auto-backfill rs_admin/rj_admin/poli_price kalau NULL (entry dari Oracle Dev 6i
        // atau historis). NULL = belum pernah di-set; 0 = user explicit set → hormati.
        // Hanya entry aktif yang di-backfill agar data historis (lunas/transfer) tidak diubah.
        if ($this->rjStatus === 'A') {
            $backfilled = $this->backfillAdminPricesIfNull($rjNo);
            if ($backfilled > 0) {
                $this->dispatch('toast', type: 'info',
                    message: "Tarif admin di-backfill dari master ({$backfilled} kolom). Periksa & sesuaikan kalau perlu.");
            }
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
        $this->rjStatus = 'A';
        $this->sumRsAdmin = $this->sumRjAdmin = $this->sumPoliPrice = 0;
        $this->sumJasaKaryawan = $this->sumJasaDokter = $this->sumJasaMedis = 0;
        $this->sumObat = $this->sumLaboratorium = $this->sumRadiologi = 0;
        $this->sumLainLain = $this->sumtrfRJ = $this->sumTotalRJ = 0;
        $this->statusResep = ['status' => 'DITUNGGU', 'keterangan' => ''];
    }

    /* ===============================
     | SUM ALL — query langsung dari DB
     =============================== */
    /* ===============================
     | BACKFILL ADMIN PRICES (NULL only)
     | Mirror logic recomputeAdminPrices() di daftar-ugd-actions. Hanya isi kolom
     | yang NULL — kolom dengan nilai 0/eksplisit dihormati (asumsi: user sengaja).
     | UGD pakai ugd_price/ugd_price_bpjs dari rsmst_doctors (bukan poli_price).
     =============================== */
    private function backfillAdminPricesIfNull(int $rjNo): int
    {
        $hdr = DB::table('rstxn_ugdhdrs')
            ->select('rs_admin', 'rj_admin', 'poli_price', 'dr_id', 'klaim_id', 'pass_status')
            ->where('rj_no', $rjNo)
            ->first();

        if (!$hdr) {
            return 0;
        }
        if (!is_null($hdr->rs_admin) && !is_null($hdr->rj_admin) && !is_null($hdr->poli_price)) {
            return 0;
        }

        $klaimId    = $hdr->klaim_id ?? 'UM';
        $drId       = $hdr->dr_id ?? '';
        $passStatus = $hdr->pass_status ?? 'O';
        $update     = [];

        if ($klaimId === 'KR' || empty($drId)) {
            // Kronis atau dokter kosong → semua 0
            if (is_null($hdr->rs_admin)) {
                $update['rs_admin'] = 0;
            }
            if (is_null($hdr->rj_admin)) {
                $update['rj_admin'] = 0;
            }
            if (is_null($hdr->poli_price)) {
                $update['poli_price'] = 0;
            }
        } else {
            $dokter = DB::table('rsmst_doctors')
                ->select('rs_admin', 'ugd_price', 'ugd_price_bpjs')
                ->where('dr_id', $drId)
                ->first();

            $klaimStatus = DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $klaimId)->value('klaim_status') ?? 'UMUM';

            if (is_null($hdr->rs_admin)) {
                $update['rs_admin'] = (int) ($dokter->rs_admin ?? 0);
            }
            if (is_null($hdr->rj_admin)) {
                $update['rj_admin'] = $passStatus === 'N'
                    ? (int) (DB::table('rsmst_parameters')->where('par_id', 1)->value('par_value') ?? 0)
                    : 0;
            }
            if (is_null($hdr->poli_price)) {
                $update['poli_price'] = (int) ($klaimStatus === 'BPJS'
                    ? ($dokter->ugd_price_bpjs ?? 0)
                    : ($dokter->ugd_price ?? 0));
            }
        }

        if (empty($update)) {
            return 0;
        }

        DB::table('rstxn_ugdhdrs')->where('rj_no', $rjNo)->update($update);
        return count($update);
    }

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
     | FIND DATA — baca rs_admin, rj_admin, poli_price (read-only) + status resep
     =============================== */
    private function findData(int $rjNo): array
    {
        $data = $this->findDataUGD($rjNo) ?? [];

        $hdr = DB::table('rstxn_ugdhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $rjNo)->first();

        // Nilai admin di-set sekali di pendaftaran (buildAdminPricesPayload mode create)
        // dan hanya berubah lewat saveAdminPrices() (edit manual user). Di sini
        // murni TRUST DB header — tidak auto-enforce invariant agar nilai tetap
        // konsisten setelah lunas (cegah perubahan tak terduga).
        $data['rsAdmin']   = (int) ($hdr->rs_admin ?? 0);
        $data['rjAdmin']   = (int) ($hdr->rj_admin ?? 0);
        $data['poliPrice'] = (int) ($hdr->poli_price ?? 0);

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

        if ($this->rjNo) {
            $this->rjStatus = DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->value('rj_status') ?? 'A';
        }

        // Cek lock state — $isFormLocked binding ke disabled inputs ter-update via Livewire diff,
        // tidak perlu incrementVersion('modal') (yang sebelumnya bikin race "request already contains"
        // saat parent re-render semua child di area modal mid-tick).
        $this->isFormLocked = $this->checkUGDStatus($this->rjNo);

        // Single dispatcher ke siblings (jasa-medis/jasa-dokter/jasa-karyawan/lab/radiologi/obat/lain-lain/transfer)
        // — re-check status & sync lock state. Cegah cross-talk antar sibling.
        $this->dispatch('ugd.administrasi-selesai', rjNo: $this->rjNo);

        // Refresh data (3 child yg butuh re-fetch listing setelah update)
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

    /* Buka Log Aktivitas — dispatch ke komponen log-aktivitas-ugd yang sudah
       di-mount sibling di halaman pelayanan-ugd (tidak mount ulang). */
    public function openLogAktivitas(int $rjNo): void
    {
        $this->dispatch('emr-ugd.log-aktivitas.open', rjNo: $rjNo);
    }
};
?>

<div>
    <x-modal name="emr-ugd-administrasi" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative space-y-3" x-data="{ expanded: true }">

                    {{-- ROW 1: Display Pasien (kayak EMR UGD) | Total Tagihan (clickable toggle) | Close --}}
                    <div class="flex items-start justify-between gap-4">
                        {{-- Display Pasien --}}
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="administrasi-ugd-display-pasien-ugd-header-{{ $rjNo ?? 'new' }}" />
                        </div>

                        {{-- Total Tagihan — clickable card untuk toggle rincian breakdown di ROW 2 --}}
                        <button type="button" x-on:click="expanded = !expanded"
                            :title="expanded ? 'Sembunyikan rincian biaya' : 'Tampilkan rincian biaya'"
                            class="group self-end flex-shrink-0 px-8 pt-3 pb-2 min-w-[220px] text-right transition border rounded-2xl cursor-pointer bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20 hover:bg-brand-green/20 hover:border-brand-green/40 hover:shadow-md dark:hover:bg-brand-lime/20 dark:hover:border-brand-lime/40 focus:outline-none focus:ring-2 focus:ring-brand-green/40 dark:focus:ring-brand-lime/40">
                            <p
                                class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                Total Tagihan
                            </p>
                            <p class="text-2xl font-bold text-ink dark:text-white tabular-nums whitespace-nowrap">
                                Rp {{ number_format($sumTotalRJ) }}
                            </p>
                            {{-- Footer: chevron + label "Lihat Rincian" (static), gray kontras + sedikit tebal --}}
                            <div
                                class="flex items-center justify-end gap-1 pt-1.5 mt-1.5 text-xs font-semibold border-t border-brand-green/20 dark:border-brand-lime/20 text-muted dark:text-gray-300 whitespace-nowrap">
                                <span>Lihat Rincian</span>
                                <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>

                        {{-- Close --}}
                        <x-icon-button color="gray" type="button" wire:click="closeModal" class="flex-shrink-0">
                            <span class="sr-only">Close</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>

                    {{-- ROW 2: Breakdown 11 item biaya (+ Read Only badge di kiri kalau locked) — collapsible --}}
                    <div x-show="expanded" x-collapse
                        class="p-2 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                        <div class="flex items-center gap-2">
                            @if ($isFormLocked)
                                <x-badge variant="danger" class="text-xs whitespace-nowrap shrink-0">Read Only</x-badge>
                            @endif
                            <div class="grid grid-cols-11 gap-1.5 flex-1 min-w-0">
                                {{-- 3 Item Editable --}}
                                @foreach ([['label' => 'RS Admin', 'model' => 'editRsAdmin', 'value' => $editRsAdmin], ['label' => 'Admin OB', 'model' => 'editRjAdmin', 'value' => $editRjAdmin], ['label' => 'Uang Periksa', 'model' => 'editPoliPrice', 'value' => $editPoliPrice]] as $item)
                                    <div
                                        class="px-2.5 py-1.5 bg-canvas border border-brand-green/40 rounded-xl dark:bg-gray-900 dark:border-brand-lime/30">
                                        <p class="text-xs text-muted dark:text-gray-400 mb-0.5 truncate">
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

                                {{-- 8 Item Read Only (termasuk Transfer untuk UGD) --}}
                                @foreach ([['label' => 'Jasa Karyawan', 'value' => $sumJasaKaryawan], ['label' => 'Jasa Dokter', 'value' => $sumJasaDokter], ['label' => 'Jasa Medis', 'value' => $sumJasaMedis], ['label' => 'Obat', 'value' => $sumObat], ['label' => 'Laboratorium', 'value' => $sumLaboratorium], ['label' => 'Radiologi', 'value' => $sumRadiologi], ['label' => 'Lain-Lain', 'value' => $sumLainLain], ['label' => 'Transfer', 'value' => $sumtrfRJ]] as $item)
                                    <div
                                        class="px-2.5 py-1.5 bg-canvas border border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                        <p class="text-xs text-muted dark:text-gray-400 mb-0.5 truncate">
                                            {{ $item['label'] }}</p>
                                        <p class="text-xs font-semibold text-ink dark:text-gray-200 tabular-nums">
                                            Rp {{ number_format($item['value']) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    <div class="grid grid-cols-1 gap-3">

                        {{-- SUB-TAB --}}
                        <div x-data="{ tab: @entangle('activeTabAdministrasi') }"
                            class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                            <div class="flex flex-wrap p-2 border-b border-hairline dark:border-gray-700">
                                @foreach ($EmrMenuAdministrasi as $menu)
                                    <button type="button" x-on:click="tab = '{{ $menu['ermMenuId'] }}'"
                                        x-bind:class="tab === '{{ $menu['ermMenuId'] }}'
                                            ?
                                            'border-b-2 border-brand-green text-brand-green dark:border-brand-lime dark:text-brand-lime font-semibold bg-brand-green/5 dark:bg-brand-lime/5' :
                                            'border-b-2 border-transparent text-muted dark:text-gray-400 hover:text-body dark:hover:text-gray-200 hover:bg-surface-soft dark:hover:bg-gray-800/50'"
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
                        class="flex items-end justify-between gap-4 p-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

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
                                    text-success dark:text-success bg-emerald-50 dark:bg-emerald-900/20
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
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">

                    {{-- KIRI: Log Aktivitas — slate solid, manager ke atas (pola footer EMR/Administrasi RI) --}}
                    <div class="flex items-center gap-2">
                        @hasanyrole('Admin|Manager Umum|Manager Medis')
                            <x-primary-button type="button" wire:click="openLogAktivitas({{ $rjNo }})"
                                wire:loading.attr="disabled" wire:target="openLogAktivitas"
                                class="gap-1 !bg-slate-600 hover:!bg-slate-700 !text-white focus:!ring-slate-300 dark:!bg-slate-600 dark:!text-white dark:hover:!bg-slate-700 dark:focus:!ring-slate-900">
                                <span wire:loading.remove wire:target="openLogAktivitas" class="flex items-center gap-1">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    Log Aktivitas
                                </span>
                                <span wire:loading wire:target="openLogAktivitas" class="flex items-center gap-1">
                                    <x-loading /> Memuat...
                                </span>
                            </x-primary-button>
                        @endhasanyrole
                    </div>

                    {{-- KANAN: Cetak + Tutup --}}
                    <div class="flex items-center gap-2">

                    {{-- Tombol cetak hanya muncul saat transaksi selesai (bukan A/F) --}}
                    @if (!in_array($rjStatus, ['A', 'F']))
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
                    @endif

                    <x-secondary-button wire:click="closeModal" type="button">Tutup</x-secondary-button>
                    </div>

                </div>
            </div>

        </div>
    </x-modal>

    <livewire:pages::components.modul-dokumen.u-g-d.kwitansi.cetak-kwitansi-ugd wire:key="cetak-kwitansi-ugd" />
    <livewire:pages::components.modul-dokumen.u-g-d.kwitansi.cetak-kwitansi-ugd-obat
        wire:key="cetak-kwitansi-ugd-obat" />

</div>
