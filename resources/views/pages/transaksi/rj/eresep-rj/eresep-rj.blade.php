<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public string $activeTab = 'NonRacikan'; // tab aktif, default Non Racikan

    // Untuk render versioning (opsional, untuk memaksa refresh komponen anak)
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /**
     * Membuka modal E-Resep
     */
    #[On('emr-rj.eresep.open')]
    public function openEresep(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        // Ambil data kunjungan RJ
        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;

        // Cek status lock kunjungan
        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        // Pastikan struktur data resep ada di dalam array (untuk jaga-jaga)
        if (!isset($this->dataDaftarPoliRJ['eresep'])) {
            $this->dataDaftarPoliRJ['eresep'] = [];
        }
        if (!isset($this->dataDaftarPoliRJ['eresepRacikan'])) {
            $this->dataDaftarPoliRJ['eresepRacikan'] = [];
        }

        // Buka modal
        $this->dispatch('open-modal', name: 'emr-rj.eresep-rj');

        // Increment version untuk memaksa refresh komponen anak jika perlu
        $this->incrementVersion('modal');
    }

    /**
     * Menutup modal dan reset form
     */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-rj.eresep-rj');
    }

    /**
     * Reset semua state
     */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ', 'activeTab']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    /**
     * Event listener untuk menyimpan semua data resep (dipanggil dari tombol Simpan di footer)
     */
    #[On('save-eresepp')]
    public function saveAll(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan.');
            return;
        }

        // Dispatch event ke kedua komponen anak agar mereka menyimpan datanya masing-masing
        // (komponen anak sudah memiliki listener 'storeAssessmentDokterRJ')
        $this->dispatch('storeAssessmentDokterRJ')->to('emr-r-j.eresep-r-j.eresep-r-j');
        $this->dispatch('storeAssessmentDokterRJ')->to('emr-r-j.eresep-r-j.eresep-r-j-racikan');

        // Opsional: tampilkan notifikasi sukses (bisa juga dari masing-masing komponen)
        $this->dispatch('toast', type: 'success', message: 'Eresep berhasil disimpan.');
    }

    public function mount()
    {
        $this->registerAreas(['modal']);
    }
};
?>

<div>
    <x-modal name="emr-rj.eresep-rj" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern (opsional) --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            {{-- Icon / Logo --}}
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            {{-- Title & subtitle --}}
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    E-Resep
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Penulisan resep obat racikan dan non racikan
                                </p>
                            </div>
                        </div>

                        {{-- Info status --}}
                        <div class="flex flex-wrap gap-4 mt-3">
                            @if ($isFormLocked)
                                <x-badge variant="danger">
                                    Read Only
                                </x-badge>
                            @endif
                        </div>
                    </div>

                    {{-- Tombol close --}}
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="grid max-w-full grid-cols-3 gap-4 mx-auto">
                    <div
                        class="col-span-2 p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Data Pasien --}}
                        <div>
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="eresep-rj-display-pasien-rj-{{ $rjNo }}" />
                        </div>


                        {{-- Tab Navigasi Racikan / Non Racikan --}}
                        <div x-data="{ activeTab: @entangle('activeTab') }" class="w-full">
                            <div class="px-2 mb-0 overflow-auto border-b border-gray-200">
                                <ul
                                    class="flex flex-row flex-wrap justify-center -mb-px text-sm font-medium text-gray-500 text-start">
                                    <li class="mx-1 mr-0 rounded-t-lg"
                                        :class="activeTab === 'NonRacikan' ? 'text-primary border-primary bg-gray-100' :
                                            'border border-gray-200'">
                                        <label
                                            class="inline-block p-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            x-on:click="activeTab = 'NonRacikan'"
                                            wire:click="$set('activeTab', 'NonRacikan')">
                                            Non Racikan
                                        </label>
                                    </li>
                                    <li class="mx-1 mr-0 rounded-t-lg"
                                        :class="activeTab === 'Racikan' ? 'text-primary border-primary bg-gray-100' :
                                            'border border-gray-200'">
                                        <label
                                            class="inline-block p-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            x-on:click="activeTab = 'Racikan'"
                                            wire:click="$set('activeTab', 'Racikan')">
                                            Racikan
                                        </label>
                                    </li>
                                </ul>
                            </div>

                            {{-- Konten Tab Non Racikan --}}
                            <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'NonRacikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">

                                <livewire:pages::transaksi.rj.eresep-rj.eresep-rj-non-racikan
                                    :wire:key="'eresep-non-racikan-rj-' . ($rjNo ?? 'new')" :rjNo="$rjNo" />
                            </div>

                            {{-- Konten Tab Racikan --}}
                            <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'Racikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">
                                <livewire:pages::transaksi.rj.eresep-rj.eresep-rj-racikan
                                    :wire:key="'eresep-racikan-rj-' . ($rjNo ?? 'new')" :rjNo="$rjNo" />
                            </div>
                        </div>
                    </div>

                    <div>
                        {{-- REKAM MEDIS --}}
                        <livewire:pages::components.rekam-medis.rekam-medis.rekam-medis-display.rekam-medis-display
                            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''"
                            wire:key="emr-rj.eresep-rj-rekam-medis-display-rj-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>

                    @if (!$isFormLocked)
                        <x-primary-button wire:click="saveAll" class="min-w-[120px]" wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
</div>
