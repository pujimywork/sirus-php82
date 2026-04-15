<?php
// resources/views/pages/transaksi/ugd/eresep-ugd/eresep-ugd.blade.php

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
    public string $activeTab = 'NonRacikan';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('emr-ugd.eresep.open')]
    public function openEresep(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;

        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dataDaftarUGD['eresep'] ??= [];
        $this->dataDaftarUGD['eresepRacikan'] ??= [];

        $this->dispatch('open-modal', name: 'emr-ugd.eresep-ugd');
        $this->incrementVersion('modal');
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-ugd.eresep-ugd');
    }

    /* ===============================
     | SAVE ALL ERESEP TO TERAPI
     =============================== */
    public function saveAllEreseptoTerapi(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tujuan belum ditentukan.');
            return;
        }

        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Bangun teks terapi dari eresep
                $eresepText = collect($data['eresep'] ?? [])
                    ->map(function ($item) {
                        $catatan = $item['catatanKhusus'] ? " ({$item['catatanKhusus']})" : '';
                        return "R/ {$item['productName']} | No. {$item['qty']} | S {$item['signaX']}dd{$item['signaHari']}{$catatan}";
                    })
                    ->implode(PHP_EOL);

                $eresepRacikanText = collect($data['eresepRacikan'] ?? [])
                    ->filter(fn($item) => isset($item['jenisKeterangan']))
                    ->map(function ($item) {
                        $jmlRacikan = $item['qty'] ? "Jml Racikan {$item['qty']} | {$item['catatan']} | S {$item['catatanKhusus']}" . PHP_EOL : '';
                        return "{$item['noRacikan']}/ {$item['productName']} - " . ($item['dosis'] ?? '') . PHP_EOL . $jmlRacikan;
                    })
                    ->implode('');

                $data['perencanaan']['terapi']['terapi'] = $eresepText . PHP_EOL . $eresepRacikanText;

                if (empty($data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'])) {
                    $data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
                }

                // 4. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 5. Notify + dispatch — di luar transaksi
            $this->dispatch('toast', type: 'success', message: 'Eresep berhasil disimpan.');
            $this->dispatch('emr-ugd.rekam-medis.open', $this->rjNo);
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan eresep: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD', 'activeTab']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="emr-ugd.eresep-ugd" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">E-Resep UGD</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Penulisan resep obat racikan
                                    dan non racikan</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="danger">UGD / IGD</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
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

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="grid max-w-full grid-cols-3 gap-4 mx-auto">
                    <div
                        class="col-span-2 p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Display Pasien UGD --}}
                        <div>
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="eresep-ugd-display-pasien-ugd-{{ $rjNo }}" />
                        </div>

                        {{-- Tab Navigasi --}}
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

                            <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'NonRacikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">
                                <livewire:pages::transaksi.ugd.eresep-ugd.eresep-ugd-non-racikan
                                    wire:key="{{ $this->renderKey('modal', ['non-racikan', $rjNo ?? 'new']) }}"
                                    :rjNo="$rjNo" />
                            </div>

                            <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'Racikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">
                                <livewire:pages::transaksi.ugd.eresep-ugd.eresep-ugd-racikan
                                    wire:key="{{ $this->renderKey('modal', ['racikan', $rjNo ?? 'new']) }}"
                                    :rjNo="$rjNo" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                            :regNo="$dataDaftarUGD['regNo'] ?? ''" :rjNo="$rjNo ?? 0"
                            wire:key="eresep-ugd-rekam-medis-display-ugd-{{ $dataDaftarUGD['regNo'] ?? 'new' }}" />
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                    @if (!$isFormLocked)
                        <x-primary-button wire:click="saveAllEreseptoTerapi" class="min-w-[120px]"
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
</div>
