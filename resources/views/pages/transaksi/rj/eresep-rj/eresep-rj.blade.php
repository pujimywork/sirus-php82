<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public string $activeTab = 'NonRacikan'; // tab aktif, default Non Racikan

    // renderVersions
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
     | OPEN ERESEP RJ
     =============================== */
    #[On('emr-rj.eresep.open')]
    public function openEresep(int $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

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

        // Initialize struktur data resep jika belum ada
        $this->dataDaftarPoliRJ['eresep'] ??= [];
        $this->dataDaftarPoliRJ['eresepRacikan'] ??= [];

        // Buka modal
        $this->dispatch('open-modal', name: 'emr-rj.eresep-rj');

        // 🔥 INCREMENT: Refresh komponen anak jika perlu
        $this->incrementVersion('modal');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'emr-rj.eresep-rj');
    }

    /* ===============================
     | SAVE ALL ERESEP → TERAPI
     | Dipanggil dari tombol Simpan di footer modal.
     | Membangun teks terapi dari eresep + eresepRacikan,
     | lalu menyimpannya ke key perencanaan.terapi.
     =============================== */
    public function saveAllEreseptoTerapi(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tujuan belum ditentukan.');
            return;
        }

        // 2. Guard: pasien sudah pulang
        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        // 3. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () {
                // 4. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 5. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // 6. Guard: data DB kosong — jangan overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // ── BUILD TEKS TERAPI ─────────────────────────────────────────
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

                // 7. Merge ke perencanaan yang sudah ada — tidak overwrite seluruh perencanaan
                $data['perencanaan']['terapi']['terapi'] = $eresepText . PHP_EOL . $eresepRacikanText;

                // 8. Auto-isi waktu pemeriksaan jika belum diisi
                if (empty($data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'])) {
                    $data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
                }

                // 9. Persist + sync properti lokal
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->dispatch('toast', type: 'success', message: 'Eresep berhasil disimpan.');
            $this->dispatch('emr-rj.rekam-medis.open', $this->rjNo);
            $this->closeModal();
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
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
        $this->reset(['rjNo', 'dataDaftarPoliRJ', 'activeTab']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="emr-rj.eresep-rj" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
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
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    {{-- Tombol close --}}
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
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

                                    {{-- Non Racikan Tab --}}
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

                                    {{-- Racikan Tab --}}
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
                                    wire:key="{{ $this->renderKey('modal', ['non-racikan', $rjNo ?? 'new']) }}"
                                    :rjNo="$rjNo" />
                            </div>

                            {{-- Konten Tab Racikan --}}
                            <div class="w-full mt-4 rounded-lg bg-gray-50" x-show="activeTab === 'Racikan'"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100">
                                <livewire:pages::transaksi.rj.eresep-rj.eresep-rj-racikan
                                    wire:key="{{ $this->renderKey('modal', ['racikan', $rjNo ?? 'new']) }}"
                                    :rjNo="$rjNo" />
                            </div>
                        </div>
                    </div>

                    {{-- REKAM MEDIS --}}
                    <div>
                        <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" :rjNo="$rjNo ?? 0"
                            wire:key="eresep-rj-rekam-medis-display-rj-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
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
