<?php

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
    protected array $renderAreas = ['modal-suket-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-suket-ugd']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultSuket();
        $current = $this->dataDaftarUGD['suket'] ?? [];
        $this->dataDaftarUGD['suket'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-suket-ugd')]
    public function openSuket($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;

        $this->dataDaftarUGD['suket'] ??= $this->getDefaultSuket();

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-suket-ugd');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari' => 'nullable|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari.integer' => ':attribute harus berupa angka.',
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari.min' => ':attribute minimal 1 hari.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari' => 'Jumlah Hari Istirahat',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-suket-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu — cegah race condition update JSON bersamaan
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Patch hanya key suket
                $data['suket'] = $this->dataDaftarUGD['suket'] ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 4. Notify + increment — di luar transaksi
            $this->afterSave('Surat Keterangan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetakSuketSehat(): void
    {
        $this->dispatch('cetak-suket-sehat.open', rjNo: $this->rjNo);
    }

    public function cetakSuketSakit(): void
    {
        $this->dispatch('cetak-suket-sakit.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultSuket(): array
    {
        try {
            $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarUGD['rjDate'] ?? '');
        } catch (\Throwable) {
            $rjDate = Carbon::now(config('app.timezone'));
        }

        $hariIni = $rjDate->format('d/m/Y');
        $besok = $rjDate->copy()->addDay()->format('d/m/Y');

        return [
            'suketSehatTab' => 'Suket Sehat',
            'suketSehat' => ['suketSehat' => ''],

            'suketIstirahatTab' => 'Suket Istirahat',
            'suketIstirahat' => [
                'mulaiIstirahat' => $hariIni,
                'mulaiIstirahatOptions' => [['mulaiIstirahat' => $hariIni . ' (Hari Ini)'], ['mulaiIstirahat' => $besok . ' (Besok)']],
                'suketIstirahatHari' => '2',
                'suketIstirahat' => '',
            ],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-suket-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-suket-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if ($isFormLocked)
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        EMR terkunci — data tidak dapat diubah.
                    </div>
                @endif

                @if (isset($dataDaftarUGD['suket']))
                    <div class="w-full">
                        <div x-data="{ activeTab: '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-wrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                            {{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}
                                        </label>
                                    </li>

                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                            {{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}
                                        </label>
                                    </li>

                                </ul>
                            </div>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                @if (isset($dataDaftarUGD['suket']['suketSehatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.modul-dokumen.suket.tab.suket-sehat-tab')
                                    </div>
                                @endif

                                @if (isset($dataDaftarUGD['suket']['suketIstirahatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.modul-dokumen.suket.tab.suket-istirahat-tab')
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>

                    {{-- ══ TOMBOL SIMPAN & CETAK ══ --}}
                    <div class="flex justify-end gap-3 px-4 pb-2">
                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled" wire:target="save"
                                class="gap-2 min-w-[140px] justify-center">
                                <span wire:loading.remove wire:target="save">
                                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan Suket
                                </span>
                                <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>

                @endif

            </div>
        </div>
    </div>

    {{-- Cetak components --}}
    <livewire:pages::components.modul-dokumen.u-g-d.suket-sakit.cetak-suket-sakit wire:key="cetak-suket-sakit-ugd" />
    <livewire:pages::components.modul-dokumen.u-g-d.suket-sehat.cetak-suket-sehat wire:key="cetak-suket-sehat-ugd" />
</div>
