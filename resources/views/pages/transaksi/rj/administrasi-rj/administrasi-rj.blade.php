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
    public string $statusKronisHdr = 'N'; // sync dari rstxn_rjhdrs.status_kronis
    public string $statusIterHdr = 'N'; // sync dari rstxn_rjhdrs.status_iter
    public string $rjStatus = 'A'; // sync dari rstxn_rjhdrs.rj_status — A/L/F/I
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
    public array $EmrMenuAdministrasi = [['ermMenuId' => 'JasaKaryawan', 'ermMenuName' => 'Jasa Karyawan'], ['ermMenuId' => 'JasaDokter', 'ermMenuName' => 'Jasa Dokter'], ['ermMenuId' => 'JasaMedis', 'ermMenuName' => 'Jasa Medis'], ['ermMenuId' => 'Obat', 'ermMenuName' => 'Obat'], ['ermMenuId' => 'Laboratorium', 'ermMenuName' => 'Laboratorium'], ['ermMenuId' => 'Radiologi', 'ermMenuName' => 'Radiologi'], ['ermMenuId' => 'LainLain', 'ermMenuName' => 'Lain-Lain'], ['ermMenuId' => 'AdminLog', 'ermMenuName' => 'Admin Log'], ['ermMenuId' => 'Kasir', 'ermMenuName' => 'Kasir']];

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
        $this->statusResep = [
            'status' => $this->dataDaftarPoliRJ['statusResep']['status'] ?? 'DITUNGGU',
            'keterangan' => $this->dataDaftarPoliRJ['statusResep']['keterangan'] ?? '',
        ];

        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $hdr = DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->select('status_kronis', 'status_iter', 'rj_status')->first();
        $this->statusKronisHdr = $hdr->status_kronis ?? 'N';
        $this->statusIterHdr = $hdr->status_iter ?? 'N';
        $this->rjStatus = $hdr->rj_status ?? 'A';

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
        $this->dispatch('open-modal', name: 'emr-rj-administrasi');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-rj-administrasi');
    }

    /* ===============================
     | BACKFILL ADMIN PRICES (NULL only)
     | Mirror logic recomputeAdminPrices() di daftar-rj-actions. Hanya isi kolom
     | yang NULL — kolom dengan nilai 0/eksplisit dihormati (asumsi: user sengaja).
     =============================== */
    private function backfillAdminPricesIfNull(int $rjNo): int
    {
        $hdr = DB::table('rstxn_rjhdrs')
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
                ->select('rs_admin', 'poli_price', 'poli_price_bpjs')
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
                    ? ($dokter->poli_price_bpjs ?? 0)
                    : ($dokter->poli_price ?? 0));
            }
        }

        if (empty($update)) {
            return 0;
        }

        DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update($update);
        return count($update);
    }

    /* ===============================
     | SUM ALL — query langsung dari DB (bukan dari JSON agar selalu akurat)
     =============================== */
    public function sumAll(): void
    {
        if (!$this->rjNo) {
            return;
        }

        $rjNo = $this->rjNo;

        // Admin dari header
        $hdr = DB::table('rstxn_rjhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $rjNo)->first();

        $this->sumRsAdmin = (int) ($hdr->rs_admin ?? 0);
        $this->sumRjAdmin = (int) ($hdr->rj_admin ?? 0);
        $this->sumPoliPrice = (int) ($hdr->poli_price ?? 0);

        $this->sumJasaKaryawan = (int) DB::table('rstxn_rjactemps')->where('rj_no', $rjNo)->sum('acte_price');
        $this->sumJasaDokter = (int) DB::table('rstxn_rjaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price');
        $this->sumJasaMedis = (int) DB::table('rstxn_rjactparams')->where('rj_no', $rjNo)->sum('pact_price');
        $this->sumObat = (int) DB::table('rstxn_rjobats')->where('rj_no', $rjNo)->selectRaw('nvl(sum(qty * price), 0) as total')->value('total');
        $this->sumLaboratorium = (int) DB::table('rstxn_rjlabs')->where('rj_no', $rjNo)->sum('lab_price');
        $this->sumRadiologi = (int) DB::table('rstxn_rjrads')->where('rj_no', $rjNo)->sum('rad_price');
        $this->sumLainLain = (int) DB::table('rstxn_rjothers')->where('rj_no', $rjNo)->sum('other_price');

        $this->sumTotalRJ = $this->sumRsAdmin + $this->sumRjAdmin + $this->sumPoliPrice + $this->sumJasaKaryawan + $this->sumJasaDokter + $this->sumJasaMedis + $this->sumObat + $this->sumLaboratorium + $this->sumRadiologi + $this->sumLainLain;
    }

    /* ===============================
     | FIND DATA — baca rs_admin, rj_admin, poli_price (read-only)
     =============================== */
    private function findData(int $rjNo): array
    {
        $data = $this->findDataRJ($rjNo) ?? [];

        $hdr = DB::table('rstxn_rjhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $rjNo)->first();

        // Nilai admin di-set sekali di pendaftaran (buildAdminPricesPayload mode create)
        // dan hanya berubah lewat saveAdminPrices() (edit manual user). Di sini
        // murni TRUST DB header — tidak auto-enforce invariant agar nilai tetap
        // konsisten setelah lunas (cegah perubahan tak terduga).
        $data['rsAdmin'] = (int) ($hdr->rs_admin ?? 0);
        $data['rjAdmin'] = (int) ($hdr->rj_admin ?? 0);
        $data['poliPrice'] = (int) ($hdr->poli_price ?? 0);

        // ── Status Resep ──
        $this->statusResep = $data['statusResep'] ?? ['status' => null, 'keterangan' => ''];
        if (!isset($data['statusResep'])) {
            $data['statusResep'] = $this->statusResep;
        }

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
                $this->lockRJRow($rjNo);

                // 2. Ambil data
                $data = $this->findData($rjNo);

                // 3. Guard: cegah duplikasi
                if (isset($data['AdministrasiRj'])) {
                    $this->dispatch('toast', type: 'error', message: 'Administrasi sudah tersimpan oleh ' . $data['AdministrasiRj']['userLog']);
                    return;
                }

                // 4. Patch hanya key AdministrasiRj
                $data['AdministrasiRj'] = [
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRJ($rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->dispatch('toast', type: 'success', message: 'Administrasi berhasil disimpan.');
            $this->sumAll();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | STATUS RESEP — AUTO-SAVE
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

        // Simpan nilai lokal sebelum findData() menimpa $this->statusResep
        $status = $this->statusResep['status'];
        $keterangan = $this->statusResep['keterangan'] ?? '';

        try {
            DB::transaction(function () use ($status, $keterangan) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Ambil data
                $data = $this->findData($this->rjNo);

                // 3. Patch key statusResep — pakai variable lokal karena findData() menimpa $this->statusResep
                $data['statusResep'] = [
                    'status' => $status,
                    'keterangan' => $keterangan,
                    'userLog' => auth()->user()->myuser_name,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRJ($this->rjNo, $data);
            });

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
     | SAVE ADMIN PRICES (rs_admin, rj_admin, poli_price)
     =============================== */
    public function saveAdminPrices(): void
    {
        if (!$this->rjNo) {
            return;
        }

        try {
            DB::transaction(function () {
                // 1. Baca nilai terkini dengan lock
                $hdr = DB::table('rstxn_rjhdrs')->select('rs_admin', 'rj_admin', 'poli_price')->where('rj_no', $this->rjNo)->lockForUpdate()->first();

                // 2. Skip jika tidak ada perubahan
                if ((int) $hdr->rs_admin === $this->editRsAdmin && (int) $hdr->rj_admin === $this->editRjAdmin && (int) $hdr->poli_price === $this->editPoliPrice) {
                    return;
                }

                // 3. Update header
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'rs_admin' => $this->editRsAdmin,
                        'rj_admin' => $this->editRjAdmin,
                        'poli_price' => $this->editPoliPrice,
                    ]);
            });

            $this->onAdministrasiUpdated();
            $this->dispatch('toast', type: 'success', message: 'Biaya admin berhasil diperbarui.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LISTENER — dari semua child (insertObat, removeLab, dst.)
     =============================== */
    #[On('administrasi-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        $this->sumAll();

        // Refresh status header (kronis, iter, rj_status) — dapat berubah saat obat di-edit/hapus / postTransaksi
        if ($this->rjNo) {
            $hdr = DB::table('rstxn_rjhdrs')->where('rj_no', $this->rjNo)->select('status_kronis', 'status_iter', 'rj_status')->first();
            $this->statusKronisHdr = $hdr->status_kronis ?? 'N';
            $this->statusIterHdr = $hdr->status_iter ?? 'N';
            $this->rjStatus = $hdr->rj_status ?? 'A';
        }

        // Cek lock state — $isFormLocked binding ke disabled inputs ter-update via Livewire diff,
        // tidak perlu incrementVersion('modal') (yang sebelumnya bikin race "request already contains"
        // saat parent re-render semua child di area modal mid-tick).
        $this->isFormLocked = $this->checkRJStatus($this->rjNo);

        // Single dispatcher ke siblings (jasa-medis/jasa-dokter/jasa-karyawan/lab/radiologi/obat/lain-lain)
        // — re-check status & sync lock state. Cegah cross-talk antar sibling.
        $this->dispatch('rj.administrasi-selesai', rjNo: $this->rjNo);

        // Refresh data (3 child yg butuh re-fetch listing setelah update)
        $this->dispatch('administrasi-obat-rj.updated');
        $this->dispatch('administrasi-lain-lain-rj.updated');
        $this->dispatch('administrasi-kasir-rj.updated');
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetakKwitansi(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->dispatch('cetak-kwitansi.open', rjNo: $this->rjNo);
    }

    public function cetakKwitansiObat(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->dispatch('cetak-kwitansi-obat.open', rjNo: $this->rjNo, mode: 'full');
    }

    public function cetakKwitansiBpjs(): void
    {
        if (!$this->rjNo) {
            return;
        }
        // Kwitansi FULL (jasa+obat+lab+rad+…) dikurangi Obat Kronis (luar paket BPJS)
        $this->dispatch('cetak-kwitansi.open', rjNo: $this->rjNo, mode: 'bpjs');
    }

    public function cetakKwitansiObatKronis(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->dispatch('cetak-kwitansi-obat.open', rjNo: $this->rjNo, mode: 'kronis');
    }

    public function cetakResepIter(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->dispatch('cetak-resep-iter-rj.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | HELPERS
     =============================== */
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
        $this->statusKronisHdr = 'N';
        $this->statusIterHdr = 'N';
        $this->rjStatus = 'A';
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

                <div class="relative space-y-3" x-data="{ expanded: true }">

                    {{-- ROW 1: Display Pasien (kayak EMR RJ) | Total Tagihan (clickable toggle) | Close --}}
                    <div class="flex items-start justify-between gap-4">
                        {{-- Display Pasien — sama dgn EMR RJ --}}
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="administrasi-rj-display-pasien-rj-header-{{ $rjNo ?? 'new' }}" />
                        </div>

                        {{-- Total Tagihan — clickable card untuk toggle rincian breakdown di ROW 2 --}}
                        <button type="button" x-on:click="expanded = !expanded"
                            :title="expanded ? 'Sembunyikan rincian biaya' : 'Tampilkan rincian biaya'"
                            class="group self-end flex-shrink-0 px-8 pt-3 pb-2 min-w-[220px] text-right transition border rounded-2xl cursor-pointer bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20 hover:bg-brand-green/20 hover:border-brand-green/40 hover:shadow-md dark:hover:bg-brand-lime/20 dark:hover:border-brand-lime/40 focus:outline-none focus:ring-2 focus:ring-brand-green/40 dark:focus:ring-brand-lime/40">
                            <p
                                class="mb-1 text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                Total Tagihan
                            </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums whitespace-nowrap">
                                Rp {{ number_format($sumTotalRJ) }}
                            </p>
                            {{-- Footer: chevron + label "Lihat Rincian" (static), gray kontras + sedikit tebal --}}
                            <div
                                class="flex items-center justify-end gap-1 pt-1.5 mt-1.5 text-xs font-semibold border-t border-brand-green/20 dark:border-brand-lime/20 text-gray-600 dark:text-gray-300 whitespace-nowrap">
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
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>

                    {{-- ROW 2: Breakdown 10 item biaya (+ Read Only badge di kiri kalau locked) — collapsible --}}
                    <div x-show="expanded" x-collapse
                        class="p-2 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-2">
                            @if ($isFormLocked)
                                <x-badge variant="danger" class="text-xs whitespace-nowrap shrink-0">Read Only</x-badge>
                            @endif
                            <div class="grid grid-cols-10 gap-1.5 flex-1 min-w-0">
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
                                        value="Rp {{ number_format($item['value'], 0, ',', '.') }}" :disabled="$isFormLocked"
                                        class="w-full text-xs font-semibold tabular-nums" />
                                </div>
                            @endforeach

                            {{-- 7 Item Read Only --}}
                            @foreach ([['label' => 'Jasa Karyawan', 'value' => $sumJasaKaryawan], ['label' => 'Jasa Dokter', 'value' => $sumJasaDokter], ['label' => 'Jasa Medis', 'value' => $sumJasaMedis], ['label' => 'Obat', 'value' => $sumObat], ['label' => 'Laboratorium', 'value' => $sumLaboratorium], ['label' => 'Radiologi', 'value' => $sumRadiologi], ['label' => 'Lain-Lain', 'value' => $sumLainLain]] as $item)
                                <div
                                    class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5 truncate">
                                        {{ $item['label'] }}</p>
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 tabular-nums">
                                        Rp {{ number_format($item['value']) }}
                                    </p>
                                </div>
                            @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <div class="grid grid-cols-1 gap-3">

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

                                <div x-show="tab === 'Obat'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
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

                                <div x-show="tab === 'AdminLog'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.rj.administrasi-rj.admin-log-rj :rjNo="$rjNo"
                                        wire:key="tab-admin-log-{{ $rjNo }}" />
                                </div>

                                <div x-show="tab === 'Kasir'" x-cloak
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <livewire:pages::transaksi.rj.administrasi-rj.kasir-rj :rjNo="$rjNo"
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
                                <x-input-label for="keteranganResep" value="Keterangan Pasien" class="mb-1" />
                                <x-text-input id="keteranganResep"
                                    wire:model.live.debounce.800ms="statusResep.keterangan"
                                    placeholder="Masukkan catatan pasien…" class="w-full text-sm" />
                            </div>
                        </div>

                        <div class="flex-shrink-0">
                            @if (isset($dataDaftarPoliRJ['AdministrasiRj']))
                                <div
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                                    text-emerald-700 dark:text-emerald-400
                                    bg-emerald-50 dark:bg-emerald-900/20
                                    border border-emerald-200 dark:border-emerald-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Selesai oleh
                                        <strong>{{ $dataDaftarPoliRJ['AdministrasiRj']['userLog'] }}</strong></span>
                                    <span class="text-xs font-normal text-emerald-500 dark:text-emerald-400">
                                        {{ $dataDaftarPoliRJ['AdministrasiRj']['userLogDate'] }}
                                    </span>
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
                                    <span wire:loading wire:target="setSelesaiAdministrasiStatus">
                                        <x-loading class="w-4 h-4" />
                                    </span>
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

                    {{-- Tombol cetak hanya muncul saat transaksi selesai (bukan A/F) --}}
                    @if (!in_array($rjStatus, ['A', 'F']))
                        {{-- Cetak Kwitansi Obat (full) --}}
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

                        {{-- Cetak Kwitansi BPJS & Obat Kronis — hanya tampil saat kunjungan punya obat split kronis --}}
                        @if ($statusKronisHdr === 'Y')
                            {{-- Cetak Kwitansi BPJS (full dikurangi Obat Kronis) --}}
                            <x-primary-button type="button" wire:click="cetakKwitansiBpjs"
                                wire:loading.attr="disabled" wire:target="cetakKwitansiBpjs" class="gap-2">
                                <span wire:loading.remove wire:target="cetakKwitansiBpjs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="cetakKwitansiBpjs"><x-loading
                                        class="w-4 h-4" /></span>
                                Cetak Kwitansi BPJS
                            </x-primary-button>

                            {{-- Cetak Kwitansi Obat Kronis (qty kronis) --}}
                            <x-primary-button type="button" wire:click="cetakKwitansiObatKronis"
                                wire:loading.attr="disabled" wire:target="cetakKwitansiObatKronis" class="gap-2">
                                <span wire:loading.remove wire:target="cetakKwitansiObatKronis">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="cetakKwitansiObatKronis"><x-loading
                                        class="w-4 h-4" /></span>
                                Cetak Obat Kronis
                            </x-primary-button>
                        @endif

                        {{-- Cetak Resep Iter — muncul saat header status_iter='Y' --}}
                        @if ($statusIterHdr === 'Y')
                            <x-primary-button type="button" wire:click="cetakResepIter" wire:loading.attr="disabled"
                                wire:target="cetakResepIter" class="gap-2">
                                <span wire:loading.remove wire:target="cetakResepIter">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="cetakResepIter"><x-loading class="w-4 h-4" /></span>
                                Cetak Resep Iter
                            </x-primary-button>
                        @endif

                        {{-- Cetak Kwitansi --}}
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

                    {{-- Tutup --}}
                    <x-secondary-button wire:click="closeModal" type="button">Tutup</x-secondary-button>

                </div>
            </div>

        </div>
    </x-modal>

    {{-- Cetak components — daftar sekali di parent/modal --}}
    <livewire:pages::components.modul-dokumen.r-j.kwitansi.cetak-kwitansi-rj wire:key="cetak-kwitansi-rj" />
    <livewire:pages::components.modul-dokumen.r-j.kwitansi.cetak-kwitansi-rj-obat wire:key="cetak-kwitansi-rj-obat" />
    <livewire:pages::components.modul-dokumen.r-j.resep-iter.cetak-resep-iter-rj wire:key="cetak-resep-iter-rj" />
</div>
