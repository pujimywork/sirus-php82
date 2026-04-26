<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    // Radio properties — sync terpisah seperti klaimId
    public string $pernafasan = '';
    public string $kesadaran = '';
    public string $nyeriDada = '';
    public string $nyeriDadaTingkat = '';
    public string $prioritasPelayanan = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-screening-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-screening-ugd']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultScreening();
        $current = $this->dataDaftarUGD['screening'] ?? [];
        $this->dataDaftarUGD['screening'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-screening-ugd')]
    public function openScreening(int $rjNo): void
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

        if (!isset($this->dataDaftarUGD['screening']) || !is_array($this->dataDaftarUGD['screening'])) {
            $this->dataDaftarUGD['screening'] = $this->getDefaultScreening();
        }

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);

        // Sync radio properties dari data
        $sc = $this->dataDaftarUGD['screening'];
        $this->pernafasan = $sc['pernafasan'] ?? '';
        $this->kesadaran = $sc['kesadaran'] ?? '';
        $this->nyeriDada = $sc['nyeriDada'] ?? '';
        $this->nyeriDadaTingkat = $sc['nyeriDadaTingkat'] ?? '';
        $this->prioritasPelayanan = $sc['prioritasPelayanan'] ?? '';

        $this->incrementVersion('modal-screening-ugd');
        $this->dispatch('open-modal', name: 'rm-screening-ugd-actions');
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-screening-ugd-actions');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarUGD.screening.keluhanUtama' => 'required',
            'dataDaftarUGD.screening.pernafasan' => 'required',
            'dataDaftarUGD.screening.kesadaran' => 'required',
            'dataDaftarUGD.screening.nyeriDada' => 'required',
            'dataDaftarUGD.screening.prioritasPelayanan' => 'required',
        ];
    }

    protected function messages(): array
    {
        return ['required' => ':attribute wajib diisi.'];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarUGD.screening.keluhanUtama' => 'Keluhan Utama',
            'dataDaftarUGD.screening.pernafasan' => 'Pernafasan',
            'dataDaftarUGD.screening.kesadaran' => 'Kesadaran',
            'dataDaftarUGD.screening.nyeriDada' => 'Nyeri Dada',
            'dataDaftarUGD.screening.prioritasPelayanan' => 'Prioritas Pelayanan',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Patch hanya key screening
                $data['screening'] = $this->dataDaftarUGD['screening'] ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 5. Notify + increment — di luar transaksi
            $this->incrementVersion('modal-screening-ugd');
            $this->dispatch('toast', type: 'success', message: 'Screening berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | ACTIONS
     =============================== */
    public function setPetugasPelayanan(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (
            !auth()
                ->user()
                ->hasAnyRole(['Perawat', 'Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menandatangani screening.');
            return;
        }

        $this->dataDaftarUGD['screening']['petugasPelayanan'] = auth()->user()->myuser_name;
        $this->dataDaftarUGD['screening']['tanggalPelayanan'] = now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        match ($name) {
            'pernafasan' => ($this->dataDaftarUGD['screening']['pernafasan'] = $value),
            'kesadaran' => ($this->dataDaftarUGD['screening']['kesadaran'] = $value),
            'nyeriDada' => ($this->dataDaftarUGD['screening']['nyeriDada'] = $value),
            'nyeriDadaTingkat' => ($this->dataDaftarUGD['screening']['nyeriDadaTingkat'] = $value),
            'prioritasPelayanan' => ($this->dataDaftarUGD['screening']['prioritasPelayanan'] = $value),
            default => null,
        };
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultScreening(): array
    {
        return [
            'keluhanUtama' => '',
            'pernafasan' => '',
            'pernafasanOptions' => [['pernafasan' => 'Nafas Normal'], ['pernafasan' => 'Tampak Sesak']],
            'kesadaran' => '',
            'kesadaranOptions' => [['kesadaran' => 'Sadar Penuh'], ['kesadaran' => 'Tampak Mengantuk'], ['kesadaran' => 'Gelisah'], ['kesadaran' => 'Bicara Tidak Jelas']],
            'nyeriDada' => '',
            'nyeriDadaOptions' => [['nyeriDada' => 'Tidak Ada'], ['nyeriDada' => 'Ada']],
            'nyeriDadaTingkat' => '',
            'nyeriDadaTingkatOptions' => [['nyeriDadaTingkat' => 'Ringan'], ['nyeriDadaTingkat' => 'Sedang'], ['nyeriDadaTingkat' => 'Berat']],
            'prioritasPelayanan' => '',
            'prioritasPelayananOptions' => [['prioritasPelayanan' => 'Preventif'], ['prioritasPelayanan' => 'Paliatif'], ['prioritasPelayanan' => 'Kuratif'], ['prioritasPelayanan' => 'Rehabilitatif']],
            'tanggalPelayanan' => '',
            'petugasPelayanan' => '',
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->pernafasan = '';
        $this->kesadaran = '';
        $this->nyeriDada = '';
        $this->nyeriDadaTingkat = '';
        $this->prioritasPelayanan = '';
    }
};
?>

<div>
    <x-modal name="rm-screening-ugd-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-screening-ugd', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Screening UGD</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Triase awal pasien Unit Gawat
                                    Darurat</p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">

                @if (isset($dataDaftarUGD['screening']))

                    {{-- Display Pasien --}}
                    <div class="mb-4">
                        <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="display-pasien-ugd-screening-{{ $rjNo }}" />
                    </div>

                    <x-border-form :title="__('Screening Awal')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                        <div class="mt-4">
                            <div class="grid grid-cols-2 gap-6">

                                {{-- KOLOM KIRI --}}
                                <div class="space-y-4">

                                    {{-- Keluhan Utama --}}
                                    <div>
                                        <x-input-label value="Keluhan Utama" :required="true" />
                                        <x-textarea wire:model.live="dataDaftarUGD.screening.keluhanUtama"
                                            placeholder="Keluhan utama pasien..." :disabled="$isFormLocked" rows="3"
                                            class="w-full mt-1" />
                                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.keluhanUtama')" class="mt-1" />
                                    </div>

                                    {{-- Nyeri Dada --}}
                                    <div>
                                        <x-input-label value="Nyeri Dada" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarUGD['screening']['nyeriDadaOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['nyeriDada']" :value="$opt['nyeriDada']" name="nyeriDada"
                                                    wire:model.live="nyeriDada" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        @if ($nyeriDada === 'Ada')
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                @foreach ($dataDaftarUGD['screening']['nyeriDadaTingkatOptions'] ?? [] as $opt)
                                                    <x-radio-button :label="$opt['nyeriDadaTingkat']" :value="$opt['nyeriDadaTingkat']"
                                                        name="nyeriDadaTingkat" wire:model.live="nyeriDadaTingkat"
                                                        :disabled="$isFormLocked" />
                                                @endforeach
                                            </div>
                                        @endif
                                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.nyeriDada')" class="mt-1" />
                                    </div>

                                    {{-- Prioritas Pelayanan --}}
                                    <div>
                                        <x-input-label value="Prioritas Pelayanan" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarUGD['screening']['prioritasPelayananOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['prioritasPelayanan']" :value="$opt['prioritasPelayanan']"
                                                    name="prioritasPelayanan" wire:model.live="prioritasPelayanan"
                                                    :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.prioritasPelayanan')" class="mt-1" />
                                    </div>

                                    {{-- TTD Petugas --}}
                                    @if (!empty($dataDaftarUGD['screening']['petugasPelayanan']))
                                        <div
                                            class="px-3 py-2 text-xs border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                                            <span class="font-medium text-green-700 dark:text-green-300">Petugas:</span>
                                            {{ $dataDaftarUGD['screening']['petugasPelayanan'] }}
                                            <span
                                                class="ml-2 text-green-600">{{ $dataDaftarUGD['screening']['tanggalPelayanan'] ?? '' }}</span>
                                        </div>
                                    @endif

                                </div>

                                {{-- KOLOM KANAN --}}
                                <div class="space-y-4">

                                    {{-- Pernafasan --}}
                                    <div>
                                        <x-input-label value="Pernafasan" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarUGD['screening']['pernafasanOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['pernafasan']" :value="$opt['pernafasan']" name="pernafasan"
                                                    wire:model.live="pernafasan" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.pernafasan')" class="mt-1" />
                                    </div>

                                    {{-- Kesadaran --}}
                                    <div>
                                        <x-input-label value="Kesadaran" :required="true" />
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach ($dataDaftarUGD['screening']['kesadaranOptions'] ?? [] as $opt)
                                                <x-radio-button :label="$opt['kesadaran']" :value="$opt['kesadaran']" name="kesadaran"
                                                    wire:model.live="kesadaran" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('dataDaftarUGD.screening.kesadaran')" class="mt-1" />
                                    </div>

                                    {{-- Scoring Triage --}}
                                    <div class="pt-2">
                                        <x-input-label value="Indikator Triage" />
                                        @php
                                            $triageScore = match (true) {
                                                $kesadaran === 'Bicara Tidak Jelas' ||
                                                    $pernafasan === 'Tampak Sesak' ||
                                                    ($nyeriDada === 'Ada' && $nyeriDadaTingkat === 'Berat')
                                                    => 'P1',
                                                $kesadaran === 'Gelisah' ||
                                                    ($nyeriDada === 'Ada' && $nyeriDadaTingkat === 'Sedang')
                                                    => 'P2',
                                                $kesadaran === 'Tampak Mengantuk' ||
                                                    ($nyeriDada === 'Ada' && $nyeriDadaTingkat === 'Ringan')
                                                    => 'P3',
                                                $kesadaran === 'Sadar Penuh' &&
                                                    $pernafasan === 'Nafas Normal' &&
                                                    $nyeriDada === 'Tidak Ada'
                                                    => 'P3',
                                                default => null,
                                            };
                                        @endphp
                                        <div class="grid grid-cols-4 gap-2 mt-2">
                                            @foreach ([
        'P1' => ['bg' => 'bg-red-500', 'ring' => 'ring-red-400', 'label' => 'Kritis'],
        'P2' => ['bg' => 'bg-yellow-400', 'ring' => 'ring-yellow-300', 'label' => 'Urgent'],
        'P3' => ['bg' => 'bg-green-500', 'ring' => 'ring-green-400', 'label' => 'Minor'],
        'P0' => ['bg' => 'bg-gray-700', 'ring' => 'ring-gray-500', 'label' => 'Meninggal'],
    ] as $p => $info)
                                                <div
                                                    class="flex flex-col items-center justify-center p-2 rounded-lg text-white text-xs font-bold transition-all
                                                    {{ $info['bg'] }}
                                                    {{ $triageScore === $p ? 'opacity-100 ring-2 ring-offset-2 ' . $info['ring'] . ' scale-105 shadow-md' : 'opacity-30' }}">
                                                    <span class="text-base">{{ $p }}</span>
                                                    <span
                                                        class="font-normal text-[10px] opacity-90">{{ $info['label'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if ($triageScore)
                                            <div
                                                class="mt-3 px-3 py-2 rounded-lg text-sm font-medium border
                                                {{ match ($triageScore) {
                                                    'P1' => 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
                                                    'P2'
                                                        => 'bg-yellow-50 border-yellow-200 text-yellow-700 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-yellow-300',
                                                    'P3'
                                                        => 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300',
                                                    default => 'bg-gray-50 border-gray-200 text-gray-700',
                                                } }}">
                                                Saran Triase: <strong>{{ $triageScore }}</strong>
                                                &mdash;
                                                {{ match ($triageScore) {
                                                    'P1' => 'Penanganan segera, kondisi mengancam jiwa',
                                                    'P2' => 'Penanganan cepat, kondisi gawat tidak darurat',
                                                    'P3' => 'Penanganan dapat ditunda, kondisi minor',
                                                    default => '',
                                                } }}
                                            </div>
                                        @endif
                                    </div>

                                </div>
                            </div>
                        </div>
                    </x-border-form>
                @else
                    <div class="flex flex-col items-center justify-center py-24 text-gray-300 dark:text-gray-600">
                        <p class="text-sm font-medium">Data UGD belum dimuat</p>
                    </div>
                @endif

            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    @hasanyrole('Perawat|Dokter|Admin')
                        @if (!$isFormLocked)
                            <x-secondary-button type="button" wire:click="setPetugasPelayanan" class="gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                TTD-E Petugas
                            </x-secondary-button>
                        @endif
                    @endhasanyrole

                    <div class="flex gap-3 ml-auto">
                        <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save()" wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan Screening
                                </span>
                                <span wire:loading><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
