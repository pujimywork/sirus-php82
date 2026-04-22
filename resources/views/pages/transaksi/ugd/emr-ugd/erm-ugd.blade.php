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
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-emr-ugd', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 12.75l7.5-7.5 7.5 7.5m-15 6l7.5-7.5 7.5 7.5" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Rekam Medis UGD</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Pengkajian awal dan asuhan keperawatan Unit Gawat Darurat
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="danger">UGD / IGD</x-badge>

                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif

                            {{-- Tombol Screening --}}
                            @role(['Perawat', 'Dokter', 'Admin'])
                                <x-secondary-button type="button"
                                    wire:click="$dispatch('open-rm-screening-ugd', { rjNo: {{ $rjNo }} })"
                                    class="gap-1 text-xs">
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                    </svg>
                                    Screening
                                </x-secondary-button>
                            @endrole

                            {{-- Tombol i-Care --}}
                            @role(['Dokter', 'Admin'])
                                @if (!empty($dataDaftarUGD['sep']['noSep']))
                                    <x-secondary-button type="button"
                                        wire:click="myiCare('{{ $dataDaftarUGD['sep']['noSep'] }}')"
                                        wire:loading.attr="disabled" wire:target="myiCare">
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
                                    </x-secondary-button>
                                @endif
                            @endrole

                            {{-- Tombol Administrasi --}}
                            @hasanyrole('Admin|Perawat|Casemix')
                                <x-outline-button type="button" wire:click="openAdministrasiPasien({{ $rjNo }})"
                                    wire:loading.attr="disabled" wire:target="openAdministrasiPasien">
                                    <span wire:loading.remove wire:target="openAdministrasiPasien"
                                        class="flex items-center gap-1">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z" />
                                        </svg>
                                        Administrasi
                                    </span>
                                    <span wire:loading wire:target="openAdministrasiPasien" class="flex items-center gap-1">
                                        <x-loading /> Memuat...
                                    </span>
                                </x-outline-button>
                            @endhasanyrole
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Display Pasien UGD --}}
                        <div>
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="emr-ugd-display-pasien-ugd-{{ $rjNo }}" />
                        </div>

                        {{-- Screening UGD (x-modal, dibuka via tombol / dispatch) --}}
                        <livewire:pages::transaksi.ugd.emr-ugd.screening.rm-screening-ugd-actions :rjNo="$rjNo"
                            wire:key="screening-ugd-{{ $rjNo }}" />

                        {{-- Grid 1: Anamnesa | Pemeriksaan | Penilaian --}}
                        <div class="grid grid-cols-3 gap-2">

                            <livewire:pages::transaksi.ugd.emr-ugd.anamnesa.rm-anamnesa-ugd-actions :rjNo="$rjNo"
                                wire:key="anamnesa-ugd-{{ $rjNo }}" />

                            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.rm-pemeriksaan-ugd-actions
                                :rjNo="$rjNo" wire:key="pemeriksaan-ugd-{{ $rjNo }}" />

                            <livewire:pages::transaksi.ugd.emr-ugd.penilaian.rm-penilaian-ugd-actions :rjNo="$rjNo"
                                wire:key="penilaian-ugd-{{ $rjNo }}" />

                        </div>

                        {{-- Grid 2: Diagnosa | Perencanaan | Rekam Medis Display --}}
                        <div class="grid grid-cols-3 gap-2">

                            <livewire:pages::transaksi.ugd.emr-ugd.diagnosa.rm-diagnosa-ugd-actions :rjNo="$rjNo"
                                wire:key="diagnosa-ugd-{{ $rjNo }}" />

                            <livewire:pages::transaksi.ugd.emr-ugd.perencanaan.rm-perencanaan-ugd-actions
                                :rjNo="$rjNo" wire:key="perencanaan-ugd-{{ $rjNo }}" />

                            <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                :regNo="$dataDaftarUGD['regNo'] ?? ''" :rjNoRefCopyTo="$rjNo ?? 0"
                                wire:key="emr-ugd.rekam-medis-display-ugd-{{ $dataDaftarUGD['regNo'] ?? 'new' }}" />

                        </div>

                        {{-- Observasi Lanjutan --}}
                        <livewire:pages::transaksi.ugd.emr-ugd.observasi.rm-observasi-ugd-actions :rjNo="$rjNo"
                            wire:key="observasi-ugd-{{ $rjNo }}" />

                        {{-- Obat dan Cairan --}}
                        <livewire:pages::transaksi.ugd.emr-ugd.obat-dan-cairan.rm-obat-dan-cairan-ugd-actions
                            :rjNo="$rjNo" wire:key="obat-dan-cairan-ugd-{{ $rjNo }}" />

                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

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
    </x-modal>

    {{-- Modal i-Care --}}
    <x-modal name="icare-modal-ugd" size="full" height="full" focusable padding="p-0">
        <div class="flex flex-col h-full">

            <div
                class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">i-Care BPJS</h3>
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
                    <p class="py-10 text-sm text-center text-gray-400">Memuat i-Care...</p>
                @endif
            </div>

            <div class="flex justify-end px-6 py-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModalicare">Tutup</x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
