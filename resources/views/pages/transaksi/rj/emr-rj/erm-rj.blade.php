<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\iCareTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait, iCareTrait;

    // i-Care
    public string $icareUrlResponse = '';

    /* =======================
     | Open/Close i-Care
     * ======================= */
    public function openModalicare(): void
    {
        $this->dispatch('open-modal', name: 'icare-modal');
    }

    public function closeModalicare(): void
    {
        $this->icareUrlResponse = '';
        $this->dispatch('close-modal', name: 'icare-modal');
    }

    /* =======================
     | i-Care BPJS
     * ======================= */
    public function myiCare(string $noSep): void
    {
        if (!$noSep) {
            $this->dispatch('toast', type: 'error', message: 'Belum Terbit SEP.');
            return;
        }

        // Ambil nokartu BPJS dari master pasien
        $regNo = $this->dataDaftarPoliRJ['regNo'] ?? '';
        $dataMasterPasien = $this->findDataMasterPasien($regNo);
        $nokartuBpjs = $dataMasterPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (!$nokartuBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS tidak ditemukan.');
            return;
        }

        $drId = $this->dataDaftarPoliRJ['drId'] ?? '';

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
    // i-Care

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-emr-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT
     =============================== */
    #[On('emr-rj.rekam-medis.open')]
    public function openRekamMedisPerawat(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        // Ambil data kunjungan RJ
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Ambil data rekam medis perawat jika sudah ada
        // $this->dataDaftarPoliRJ = $this->findRekamMedisPerawat($rjNo);

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dispatch('open-modal', name: 'rm-perawat-actions');
        $this->dispatch('open-rm-anamnesa-rj', $rjNo);
        $this->dispatch('open-rm-pemeriksaan-rj', $rjNo);
        $this->dispatch('open-rm-penilaian-rj', $rjNo);
        $this->dispatch('open-rm-diagnosa-rj', $rjNo);
        $this->dispatch('open-rm-perencanaan-rj', $rjNo);
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perawat-actions');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount()
    {
        $this->registerAreas(['modal-emr-rj']);
    }

    public function save()
    {
        $this->dispatch('save-rm-anamnesa-rj');
        $this->dispatch('save-rm-pemeriksaan-rj');
        $this->dispatch('save-rm-diagnosa-rj');
        $this->dispatch('save-rm-perencanaan-rj');
    }

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-rj.administrasi.open', rjNo: $rjNo);
    }
};

?>

<div>
    <x-modal name="rm-perawat-actions" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA — dirty tracking pure-Alpine (no server dispatch) --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-emr-rj', [$rjNo ?? 'new']) }}"
            x-data="{
                dirty: false,
                showUnsavedWarning: false,
                tryClose() {
                    if (this.dirty) {
                        this.showUnsavedWarning = true;
                    } else {
                        $wire.closeModal();
                    }
                },
                forceClose() {
                    this.dirty = false;
                    this.showUnsavedWarning = false;
                    $wire.closeModal();
                },
            }"
            x-on:input="dirty = true"
            x-on:change="dirty = true"
            x-init="
                window.addEventListener('refresh-after-rj.saved', () => { dirty = false });
                window.addEventListener('open-modal', (e) => {
                    if (e.detail && e.detail.name === 'rm-perawat-actions') {
                        dirty = false;
                        showUnsavedWarning = false;
                    }
                });
            ">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            {{-- Icon --}}
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            {{-- Title & subtitle --}}
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Rekam Medis Perawat
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Pengkajian awal dan asuhan keperawatan
                                </p>
                            </div>
                        </div>

                        {{-- Info kunjungan --}}
                        <div class="flex flex-wrap gap-4 mt-3">
                            {{-- <x-badge variant="info">
                                No. RJ: {{ $rjNo ?? '-' }}
                            </x-badge> --}}

                            @if ($isFormLocked)
                                <x-badge variant="danger">
                                    Read Only
                                </x-badge>
                            @endif

                            @role(['Dokter', 'Admin'])
                                @if (!empty($dataDaftarPoliRJ['sep']['noSep']))
                                    <x-secondary-button type="button"
                                        wire:click="myiCare('{{ $dataDaftarPoliRJ['sep']['noSep'] }}')"
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

                            {{-- Administrasi --}}
                            @hasanyrole('Admin|Perawat|Casemix')
                                <x-outline-button type="button" wire:click="openAdministrasiPasien('{{ $rjNo }}')"
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

                    {{-- Close button --}}
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">


                        {{-- Data Pasien --}}
                        <div>
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="emr-rj-display-pasien-rj-{{ $rjNo }}" />
                        </div>

                        {{-- Row 1: S | O --}}
                        <div class="grid grid-cols-2 gap-2">
                            {{-- ANAMNESA — S: Subjective --}}
                            <div>
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-sm font-bold dark:bg-blue-900/40 dark:text-blue-300">S</span>
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Subjective — Anamnesa</span>
                                </div>
                                <livewire:pages::transaksi.rj.emr-rj.anamnesa.rm-anamnesa-rj-actions :rjNo="$rjNo"
                                    wire:key="anamnesa-rj-{{ $rjNo }}" />
                            </div>

                            {{-- PEMERIKSAAN — O: Objective --}}
                            <div>
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 text-sm font-bold dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Objective — Pemeriksaan</span>
                                </div>
                                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.rm-pemeriksaan-rj-actions :rjNo="$rjNo"
                                    wire:key="pemeriksaan-rj-{{ $rjNo }}" />
                            </div>
                        </div>

                        {{-- Kelompok APN (kiri, 2/3) + R (kanan, 1/3) --}}
                        <div class="grid grid-cols-3 gap-2">
                            {{-- KELOMPOK APN — A | P di atas, N (span 2) di bawah --}}
                            <div class="col-span-2 grid grid-cols-2 gap-2">
                                {{-- DIAGNOSA — A: Assessment --}}
                                <div>
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-sm font-bold dark:bg-amber-900/40 dark:text-amber-300">A</span>
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Assessment — Diagnosa</span>
                                    </div>
                                    <x-border-form :align="__('start')" :bgcolor="__('bg-white')">
                                        <livewire:pages::transaksi.rj.emr-rj.diagnosa.rm-diagnosa-rj-actions :rjNo="$rjNo"
                                            wire:key="diagnosa-rj-{{ $rjNo }}" />
                                    </x-border-form>
                                </div>

                                {{-- PERENCANAAN — P: Plan --}}
                                <div>
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-rose-100 text-rose-700 text-sm font-bold dark:bg-rose-900/40 dark:text-rose-300">P</span>
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Plan — Perencanaan</span>
                                    </div>
                                    <livewire:pages::transaksi.rj.emr-rj.perencanaan.rm-perencanaan-rj-actions :rjNo="$rjNo"
                                        wire:key="perencanaan-rj-{{ $rjNo }}" />
                                </div>

                                {{-- N: Penilaian — di bawah AP, span 2 --}}
                                <div class="col-span-2 -mt-1">
                                    <div class="mb-1 flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-sm font-bold dark:bg-purple-900/40 dark:text-purple-300">N</span>
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Penilaian — Nyeri / Risiko Jatuh / Dekubitus / Gizi</span>
                                    </div>
                                    <livewire:pages::transaksi.rj.emr-rj.penilaian.rm-penilaian-rj-actions :rjNo="$rjNo"
                                        wire:key="penilaian-rj-{{ $rjNo }}" />
                                </div>
                            </div>

                            {{-- R: Rekam Medis — sebelah kanan kelompok APN --}}
                            <div>
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-600 text-sm font-bold dark:bg-gray-700 dark:text-gray-300">R</span>
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rekam Medis</span>
                                </div>
                                <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                    :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" :rjNoRefCopyTo="$rjNo ?? 0"
                                    wire:key="emr-rj.eresep-rj-rekam-medis-display-rj-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- KONFIRMASI TUTUP TANPA SIMPAN --}}
            <div x-cloak x-show="showUnsavedWarning" class="fixed inset-0 z-[99]" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70"
                    x-on:click="showUnsavedWarning = false"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="w-full max-w-md overflow-hidden bg-white border border-gray-200 shadow-2xl rounded-2xl dark:bg-gray-800 dark:border-gray-700"
                        x-on:click.stop>
                        <div class="flex items-start gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 shrink-0">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Data belum disimpan</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Ada perubahan di form EMR yang belum disimpan. Yakin tutup tanpa simpan?
                                </p>
                            </div>
                            <x-icon-button color="gray" type="button" x-on:click="showUnsavedWarning = false"
                                class="shrink-0" aria-label="Tutup">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </x-icon-button>
                        </div>
                        <div class="flex items-center justify-end gap-2 px-5 py-4 bg-gray-50/70 dark:bg-gray-900/20">
                            <x-secondary-button type="button" x-on:click="showUnsavedWarning = false">
                                Lanjut Edit
                            </x-secondary-button>
                            <x-danger-button type="button" x-on:click="forceClose()">
                                Tutup Tanpa Simpan
                            </x-danger-button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="tryClose()">
                        Tutup
                    </x-secondary-button>

                    @if (!$isFormLocked)
                        <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan
                            </span>
                            <span wire:loading>
                                <x-loading />
                                Menyimpan...
                            </span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>

    {{-- Modal i-Care --}}
    <x-modal name="icare-modal" size="full" height="full" focusable padding="p-0">
        <div class="flex flex-col h-full">

            {{-- Header --}}
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

            {{-- Content --}}
            <div class="flex-1 min-h-0">
                @if ($icareUrlResponse)
                    <iframe src="{{ $icareUrlResponse }}" class="w-full h-full border-0"></iframe>
                @else
                    <p class="py-10 text-sm text-center text-gray-400">Memuat i-Care...</p>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex justify-end px-6 py-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModalicare">
                    Tutup
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
