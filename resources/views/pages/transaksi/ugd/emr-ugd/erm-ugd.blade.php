<?php
// resources/views/pages/transaksi/ugd/emr-ugd/emr-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\iCareTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, iCareTrait;

    // i-Care
    public string $icareUrlResponse = '';

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-emr-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-emr-ugd']);
    }

    /* ===============================
     | i-Care — Open/Close
     =============================== */
    public function openModalicare(): void
    {
        $this->dispatch('open-modal', name: 'icare-modal-ugd');
    }

    public function closeModalicare(): void
    {
        $this->icareUrlResponse = '';
        $this->dispatch('close-modal', name: 'icare-modal-ugd');
    }

    public function myiCare(string $noSep): void
    {
        if (!$noSep) {
            $this->dispatch('toast', type: 'error', message: 'Belum Terbit SEP.');
            return;
        }

        $regNo = $this->dataDaftarUGD['regNo'] ?? '';
        $dataMasterPasien = $this->findDataMasterPasien($regNo);
        $nokartuBpjs = $dataMasterPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (!$nokartuBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS tidak ditemukan.');
            return;
        }

        $drId = $this->dataDaftarUGD['drId'] ?? '';
        if (!$drId) {
            $this->dispatch('toast', type: 'error', message: 'Data dokter tidak ditemukan.');
            return;
        }

        $kodeDokter = DB::table('rsmst_doctors')->select('kd_dr_bpjs')->where('dr_id', $drId)->first();

        if (!$kodeDokter || !$kodeDokter->kd_dr_bpjs) {
            $this->dispatch('toast', type: 'error', message: 'Dokter tidak memiliki hak akses untuk I-Care.');
            return;
        }

        try {
            $response = $this->icare($nokartuBpjs, $kodeDokter->kd_dr_bpjs)->getOriginalContent();

            if (($response['metadata']['code'] ?? 400) == 200) {
                $this->icareUrlResponse = $response['response']['url'] ?? '';
                $this->openModalicare();
            } else {
                $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal mengakses i-Care');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengakses i-Care: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN REKAM MEDIS UGD
     =============================== */
    #[On('emr-ugd.rekam-medis.open')]
    public function openRekamMedis(int $rjNo): void
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

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dispatch('open-modal', name: 'rm-ugd-actions');
        $this->dispatch('open-rm-anamnesa-ugd', $rjNo);
        $this->dispatch('open-rm-pemeriksaan-ugd', $rjNo);
        $this->dispatch('open-rm-penilaian-ugd', $rjNo);
        $this->dispatch('open-rm-diagnosa-ugd', $rjNo);
        $this->dispatch('open-rm-perencanaan-ugd', $rjNo);
        $this->dispatch('open-rm-observasi-ugd', $rjNo);
        $this->dispatch('open-rm-obat-dan-cairan-ugd', $rjNo);
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-ugd-actions');
    }

    /* ===============================
     | ADMINISTRASI
     =============================== */
    public function openAdministrasiPasien(int $rjNo): void
    {
        $this->dispatch('emr-ugd.administrasi.open', rjNo: $rjNo);
    }

    public function openLogAktivitas(int $rjNo): void
    {
        $this->dispatch('emr-ugd.log-aktivitas.open', rjNo: $rjNo);
    }

    public function openEresep(int $rjNo): void
    {
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }
        $this->dispatch('emr-ugd.eresep.open', rjNo: $rjNo);
        $this->dispatch('open-eresep-non-racikan-ugd', rjNo: $rjNo);
        $this->dispatch('open-eresep-racikan-ugd', rjNo: $rjNo);
    }

    public function cetakEresep(string $rjNo): void
    {
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }
        $this->dispatch('cetak-eresep-ugd.open', rjNo: $rjNo);
    }

    public function hasEresep(): bool
    {
        return !empty($this->dataDaftarUGD['eresep']) || !empty($this->dataDaftarUGD['eresepRacikan']);
    }

    /* ===============================
     | SAVE — dispatch ke semua child
     =============================== */
    public function save(): void
    {
        $this->dispatch('save-rm-anamnesa-ugd');
        $this->dispatch('save-rm-pemeriksaan-ugd');
        $this->dispatch('save-rm-diagnosa-ugd');
        $this->dispatch('save-rm-perencanaan-ugd');
        // Observasi & Obat-Cairan: save per-item (add/remove), tidak perlu global save
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="rm-ugd-actions" size="full" height="full" focusable>
        <x-dirty-modal-content name="rm-ugd-actions" event="refresh-after-ugd.saved" label="EMR UGD" :save-events="[
            'save-rm-anamnesa-ugd',
            'save-rm-pemeriksaan-ugd',
            'save-rm-diagnosa-ugd',
            'save-rm-perencanaan-ugd',
        ]"
            :wireKey="$this->renderKey('modal-emr-ugd', [$rjNo ?? 'new'])">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    {{-- Data Pasien UGD (kanan + kiri 1 card) menggantikan judul "Rekam Medis UGD" --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="emr-ugd-display-pasien-ugd-header-{{ $rjNo }}" />
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 pb-4 bg-surface-soft/70 dark:bg-gray-950/20 text-base">
                <div class="max-w-full mx-auto">
                    <div class="space-y-6">

                        {{-- Screening UGD (x-modal, dibuka via tombol / dispatch) --}}
                        <livewire:pages::transaksi.ugd.emr-ugd.screening.rm-screening-ugd-actions :rjNo="$rjNo"
                            wire:key="screening-ugd-{{ $rjNo }}" />

                        {{-- Row 1: S | O --}}
                        <div class="grid grid-cols-2 gap-2">
                            {{-- ANAMNESA — S: Subjective --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-blue-100 dark:border-blue-700">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-base font-bold dark:bg-blue-900/40 dark:text-blue-300">S</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Subjective —
                                        Anamnesa</span>
                                </div>
                                <livewire:pages::transaksi.ugd.emr-ugd.anamnesa.rm-anamnesa-ugd-actions
                                    :rjNo="$rjNo" wire:key="anamnesa-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- PEMERIKSAAN — O: Objective --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-emerald-100 dark:border-emerald-700">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 text-base font-bold dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Objective —
                                        Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.rm-pemeriksaan-ugd-actions
                                    :rjNo="$rjNo" wire:key="pemeriksaan-ugd-{{ $rjNo }}" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            {{-- DIAGNOSA — A: Assessment --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-amber-100 dark:border-amber-700">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-700 text-base font-bold dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Assessment
                                        — Diagnosis</span>
                                </div>
                                <livewire:pages::transaksi.ugd.emr-ugd.diagnosa.rm-diagnosa-ugd-actions
                                    :rjNo="$rjNo" wire:key="diagnosa-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- PERENCANAAN — P: Plan --}}
                            <div>
                                <div
                                    class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-rose-100 dark:border-rose-700">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-rose-100 text-error text-base font-bold dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                    <span class="text-base font-semibold text-body dark:text-gray-300">Plan —
                                        Perencanaan</span>
                                </div>
                                <livewire:pages::transaksi.ugd.emr-ugd.perencanaan.rm-perencanaan-ugd-actions
                                    :rjNo="$rjNo" wire:key="perencanaan-ugd-{{ $rjNo }}" />
                            </div>

                        </div>

                        {{-- R: Rekam Medis — sebelah kanan kelompok AP --}}
                        <div>
                            <div
                                class="mb-2 p-2 flex items-center gap-2 rounded-t-lg border-2 bg-surface-soft dark:border-gray-600">
                                <span
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-surface-soft text-muted text-base font-bold dark:bg-gray-700 dark:text-gray-300">R</span>
                                <span class="text-base font-semibold text-body dark:text-gray-300">Rekam
                                    Medis</span>
                            </div>
                            <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                :regNo="$dataDaftarUGD['regNo'] ?? ''"
                                wire:key="emr-ugd.rekam-medis-display-ugd-{{ $dataDaftarUGD['regNo'] ?? 'new' }}" />
                        </div>

                    </div>

                    {{-- TAB GROUP N | L | T (Penilaian / Observasi / Obat-Cairan)

                         wire:ignore WAJIB: beda dari grup SOAP (S/O/A/P) yg di-stack polos spt EMR RJ,
                         grup ini dibungkus Alpine (x-data activeTab + x-show). Saat parent (erm-ugd)
                         morph — mis. setelah Simpan EMR mem-broadcast save-rm-* — subtree Alpine ini
                         bikin morphdom melepas/menyusun-ulang node → komponen anak Livewire (penilaian/
                         observasi/obat-cairan) RE-MOUNT & kehilangan state ($rjNo balik null, "Data UGD
                         belum dimuat"). wire:ignore menyuruh morph parent MELEWATI subtree ini; komponen
                         anak tetap hidup & update sendiri (island), Alpine tetap urus pindah tab.
                         Aman utk ganti pasien: seluruh isi modal (dirty-modal-content) ber-wire:key
                         renderKey('modal-emr-ugd',[rjNo]) → dibuat ulang saat rjNo berganti. --}}
                    <div wire:ignore wire:key="nlt-tab-group-{{ $rjNo }}" x-data="{ activeTab: 'penilaian' }"
                        class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-2 border-b border-hairline dark:border-gray-700">
                            <div class="flex flex-nowrap gap-2 -mb-px">
                                <x-tab variant="underline" active-expr="activeTab === 'penilaian'"
                                    x-on:click="activeTab = 'penilaian'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-sm font-bold dark:bg-purple-900/40 dark:text-purple-300">N</span>
                                    Penilaian — Nyeri / Risiko Jatuh / Dekubitus / Gizi
                                </x-tab>
                                <x-tab variant="underline" active-expr="activeTab === 'observasi'"
                                    x-on:click="activeTab = 'observasi'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-sm font-bold dark:bg-amber-900/40 dark:text-amber-300">L</span>
                                    Observasi Lanjutan
                                </x-tab>
                                <x-tab variant="underline" active-expr="activeTab === 'terapi'"
                                    x-on:click="activeTab = 'terapi'" class="inline-flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-teal-100 text-teal-700 text-sm font-bold dark:bg-teal-900/40 dark:text-teal-300">T</span>
                                    Pemberian Obat &amp; Cairan
                                </x-tab>
                            </div>
                        </div>

                        <div class="p-3">
                            {{-- wire:key pada wrapper x-show: tanpa ini, morph Livewire (mis. setelah Simpan EMR
                                 yang me-morph modul EMR) bisa memindah/re-parent div tab tak-ber-key → komponen
                                 anak (penilaian/observasi/obat-cairan) re-init & kehilangan state (mis. productId
                                 obat-cairan hilang → form balik ke Fase 1). --}}
                            <div wire:key="tab-penilaian-{{ $rjNo }}" x-show="activeTab === 'penilaian'" x-cloak>
                                <livewire:pages::transaksi.ugd.emr-ugd.penilaian.rm-penilaian-ugd-actions :rjNo="$rjNo"
                                    wire:key="penilaian-ugd-{{ $rjNo }}" />
                            </div>

                            <div wire:key="tab-observasi-{{ $rjNo }}" x-show="activeTab === 'observasi'" x-cloak>
                                <livewire:pages::transaksi.ugd.emr-ugd.observasi.rm-observasi-ugd-actions :rjNo="$rjNo"
                                    wire:key="observasi-ugd-{{ $rjNo }}" />
                            </div>

                            <div wire:key="tab-terapi-{{ $rjNo }}" x-show="activeTab === 'terapi'" x-cloak>
                                <livewire:pages::transaksi.ugd.emr-ugd.obat-dan-cairan.rm-obat-dan-cairan-ugd-actions
                                    :rjNo="$rjNo" wire:key="obat-dan-cairan-ugd-{{ $rjNo }}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER (justify-between: Tutup | aksi | Simpan) ═══════════ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-2 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KIRI: Status + Action buttons --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge variant="danger">UGD / IGD</x-badge>

                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif

                        @role(['Perawat', 'Dokter', 'Admin'])
                            {{-- Screening UGD — indigo (buka form skrining) --}}
                            <x-primary-button type="button"
                                wire:click="$dispatch('open-rm-screening-ugd', { rjNo: {{ $rjNo }} })"
                                class="gap-1 text-sm !bg-indigo-600 hover:!bg-indigo-700 !text-white focus:!ring-indigo-300 dark:!bg-indigo-600 dark:!text-white dark:hover:!bg-indigo-700 dark:focus:!ring-indigo-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                Screening
                            </x-primary-button>
                        @endrole

                        @role(['Dokter', 'Admin'])
                            @if (!empty($dataDaftarUGD['sep']['noSep']))
                                <x-primary-button type="button"
                                    wire:click="myiCare('{{ $dataDaftarUGD['sep']['noSep'] }}')"
                                    wire:loading.attr="disabled" wire:target="myiCare"
                                    class="gap-1 !bg-emerald-600 hover:!bg-emerald-700 !text-white focus:!ring-emerald-300 dark:!bg-emerald-600 dark:!text-white dark:hover:!bg-emerald-700 dark:focus:!ring-emerald-900">
                                    <span wire:loading.remove wire:target="myiCare" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        i-Care
                                    </span>
                                    <span wire:loading wire:target="myiCare" class="flex items-center gap-1">
                                        <x-loading /> Memuat...
                                    </span>
                                </x-primary-button>
                            @endif
                        @endrole

                        @hasanyrole('Admin|Perawat|Casemix')
                            <x-primary-button type="button" wire:click="openAdministrasiPasien({{ $rjNo }})"
                                wire:loading.attr="disabled" wire:target="openAdministrasiPasien"
                                class="gap-1 !bg-teal-600 hover:!bg-teal-700 !text-white focus:!ring-teal-300 dark:!bg-teal-600 dark:!text-white dark:hover:!bg-teal-700 dark:focus:!ring-teal-900">
                                <span wire:loading.remove wire:target="openAdministrasiPasien"
                                    class="flex items-center gap-1">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                    </svg>
                                    Administrasi
                                </span>
                                <span wire:loading wire:target="openAdministrasiPasien" class="flex items-center gap-1">
                                    <x-loading /> Memuat...
                                </span>
                            </x-primary-button>
                        @endhasanyrole

                        @hasanyrole('Admin|Perawat|Dokter|Casemix|Mr')
                            {{-- Dokumen — indigo solid (modal modul dokumen ada di page pelayanan yang sama) --}}
                            <x-primary-button type="button"
                                wire:click="$dispatch('emr-ugd.modul-dokumen.open', { rjNo: {{ $rjNo }} })"
                                class="gap-1 !bg-indigo-600 hover:!bg-indigo-700 !text-white focus:!ring-indigo-300 dark:!bg-indigo-600 dark:!text-white dark:hover:!bg-indigo-700 dark:focus:!ring-indigo-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>Modul Dokumen
                            </x-primary-button>
                        @endhasanyrole

                        @hasanyrole('Admin|Manager Umum|Manager Medis')
                            {{-- Log Aktivitas — slate solid (manager ke atas) --}}
                            <x-primary-button type="button" wire:click="openLogAktivitas({{ $rjNo }})"
                                wire:loading.attr="disabled" wire:target="openLogAktivitas"
                                class="order-first gap-1 !bg-slate-600 hover:!bg-slate-700 !text-white focus:!ring-slate-300 dark:!bg-slate-600 dark:!text-white dark:hover:!bg-slate-700 dark:focus:!ring-slate-900">
                                <span wire:loading.remove wire:target="openLogAktivitas"
                                    class="flex items-center gap-1">
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

                        @hasanyrole('Dokter|Admin|Perawat')
                            <x-primary-button type="button" class="gap-1" x-data="{
                                loadingEresep: false,
                                async openEresepWithSave(rjNo) {
                                    if (this.loadingEresep) return;
                                    this.loadingEresep = true;
                                    try {
                                        if (!$wire.isFormLocked) {
                                            const events = [
                                                'save-rm-anamnesa-ugd',
                                                'save-rm-pemeriksaan-ugd',
                                                'save-rm-diagnosa-ugd',
                                                'save-rm-perencanaan-ugd',
                                            ];
                                            let saved = 0;
                                            const onSaved = () => saved++;
                                            window.addEventListener('refresh-after-ugd.saved', onSaved);
                                            try {
                                                events.forEach(e => Livewire.dispatch(e, { silent: true }));
                                                const deadline = Date.now() + 3000;
                                                while (saved < events.length && Date.now() < deadline) {
                                                    await new Promise(r => setTimeout(r, 50));
                                                }
                                            } finally {
                                                window.removeEventListener('refresh-after-ugd.saved', onSaved);
                                            }
                                        }
                                        await $wire.openEresep(rjNo);
                                    } finally {
                                        this.loadingEresep = false;
                                    }
                                }
                            }"
                                x-bind:disabled="loadingEresep"
                                x-on:click.prevent="openEresepWithSave({{ $rjNo }})">
                                <span x-show="!loadingEresep" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>E-Resep
                                </span>
                                <span x-show="loadingEresep" x-cloak class="flex items-center gap-1">
                                    <x-loading /> Menyimpan & memuat...
                                </span>
                            </x-primary-button>
                        @endhasanyrole

                        @hasanyrole('Perawat|Dokter|Casemix|Manager Medis|Manager Umum|Admin')
                            @if ($this->hasEresep())
                                <x-outline-button type="button" wire:click="cetakEresep('{{ $rjNo }}')"
                                    wire:loading.attr="disabled" wire:target="cetakEresep">
                                    <span wire:loading.remove wire:target="cetakEresep" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                        Cetak E-Resep
                                    </span>
                                    <span wire:loading wire:target="cetakEresep" class="flex items-center gap-1">
                                        <x-loading /> Memuat...
                                    </span>
                                </x-outline-button>
                            @endif
                        @endhasanyrole
                    </div>

                    {{-- KANAN: Tutup + Simpan sebelahan --}}
                    <div class="flex items-center gap-2">
                        <x-secondary-button x-on:click="tryClose()">Tutup</x-secondary-button>

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan
                                </span>
                                <span wire:loading><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>

    {{-- Modal i-Care --}}
    <x-modal name="icare-modal-ugd" size="full" height="full" focusable padding="p-0">
        <div class="flex flex-col h-full">

            <div
                class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <h3 class="text-lg font-semibold text-ink dark:text-gray-100">i-Care BPJS</h3>
                <x-icon-button color="gray" type="button" wire:click="closeModalicare">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            <div class="flex-1 min-h-0">
                @if ($icareUrlResponse)
                    <iframe src="{{ $icareUrlResponse }}" class="w-full h-full border-0"></iframe>
                @else
                    <p class="py-10 text-base text-center text-muted-soft">Memuat i-Care...</p>
                @endif
            </div>

            <div class="flex justify-end px-6 py-4 border-t border-hairline dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModalicare">Tutup</x-secondary-button>
            </div>

        </div>
    </x-modal>

    {{-- Cetak E-Resep PDF (headless: listen event cetak-eresep-ugd.open) --}}
    <livewire:pages::components.rekam-medis.u-g-d.cetak-eresep.cetak-eresep wire:key="cetak-eresep-ugd-emr" />
</div>
