<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/general-consent/rm-general-consent-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-general-consent'];

    // ── Form fields — top-level untuk wire:model ──
    public string $wali = '';
    public string $agreement = '1'; // 1=Setuju, 0=Tidak Setuju
    public string $signature = ''; // base64 dari canvas/signpad

    public array $agreementOptions = [['value' => '1', 'label' => 'Setuju'], ['value' => '0', 'label' => 'Tidak Setuju']];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-general-consent']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultGeneralConsent();
        $current = $this->dataDaftarUGD['generalConsentPasienUGD'] ?? [];
        $this->dataDaftarUGD['generalConsentPasienUGD'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-general-consent')]
    public function openGeneralConsent(int $rjNo): void
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

        // Inisialisasi default jika belum ada
        $this->dataDaftarUGD['generalConsentPasienUGD'] ??= $this->getDefaultGeneralConsent();

        // Populate form fields dari data tersimpan
        $consent = $this->dataDaftarUGD['generalConsentPasienUGD'];
        $this->wali = $consent['wali'] ?? '';
        $this->agreement = $consent['agreement'] ?? '1';
        $this->signature = $consent['signature'] ?? '';

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-general-consent');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'signature' => 'required|string',
            'wali' => 'required|string|max:200',
            'agreement' => 'required|in:0,1',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'signature' => 'Tanda tangan pasien/wali',
            'wali' => 'Nama wali',
            'agreement' => 'Persetujuan',
        ];
    }

    /* ===============================
     | UPDATED HOOKS — sync top-level → nested
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        if (in_array($name, ['wali', 'agreement'])) {
            $this->dataDaftarUGD['generalConsentPasienUGD'][$name] = $value;
        }
    }

    /* ===============================
     | SET SIGNATURE — dipanggil dari JS Alpine setelah signpad selesai
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->signature = $dataUrl;
        $this->dataDaftarUGD['generalConsentPasienUGD']['signature'] = $dataUrl;
        $this->dataDaftarUGD['generalConsentPasienUGD']['signatureDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | CLEAR SIGNATURE
     =============================== */
    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->signature = '';
        $this->dataDaftarUGD['generalConsentPasienUGD']['signature'] = '';
        $this->dataDaftarUGD['generalConsentPasienUGD']['signatureDate'] = '';
        $this->incrementVersion('modal-general-consent');
    }

    /* ===============================
     | SET PETUGAS PEMERIKSA
     =============================== */
    public function setPetugasPemeriksa(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->dataDaftarUGD['generalConsentPasienUGD']['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan petugas pemeriksa sudah ada.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $data['generalConsentPasienUGD'] ??= $this->getDefaultGeneralConsent();
                $data['generalConsentPasienUGD']['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
                $data['generalConsentPasienUGD']['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
                $data['generalConsentPasienUGD']['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->incrementVersion('modal-general-consent');
            $this->dispatch('toast', type: 'success', message: 'Tanda tangan petugas pemeriksa berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-general-consent')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan pasien/wali belum diisi.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                $data['generalConsentPasienUGD'] = array_replace($data['generalConsentPasienUGD'] ?? $this->getDefaultGeneralConsent(), $this->dataDaftarUGD['generalConsentPasienUGD'] ?? []);

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->incrementVersion('modal-general-consent');
            $this->dispatch('toast', type: 'success', message: 'General Consent berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-general-consent-ugd.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultGeneralConsent(): array
    {
        return [
            'signature' => '',
            'signatureDate' => '',
            'wali' => '',
            'agreement' => '1',
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
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
        $this->signature = '';
        $this->wali = '';
        $this->agreement = '1';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-general-consent', [$rjNo ?? 'new']) }}">

        @if ($isFormLocked)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mb-4 text-sm font-medium text-amber-700
                bg-amber-50 border border-amber-200 rounded-xl
                dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                EMR terkunci — data tidak dapat diubah.
            </div>
        @endif

        @if (isset($dataDaftarUGD['generalConsentPasienUGD']))

            @php $consent = $dataDaftarUGD['generalConsentPasienUGD']; @endphp

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                {{-- ══ KOLOM KIRI — Identitas & Persetujuan ══ --}}
                <div
                    class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                    <h3
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                        Data Persetujuan
                    </h3>

                    {{-- Wali --}}
                    <div>
                        <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                        <x-text-input wire:model.live="wali" placeholder="Nama lengkap pasien atau wali..."
                            :error="$errors->has('wali')" :disabled="$isFormLocked" class="w-full" />
                        <x-input-error :messages="$errors->get('wali')" class="mt-1" />
                    </div>

                    {{-- Agreement --}}
                    <div>
                        <x-input-label value="Persetujuan *" class="mb-1" />
                        <x-select-input wire:model.live="agreement" :error="$errors->has('agreement')" :disabled="$isFormLocked" class="w-full">
                            @foreach ($agreementOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('agreement')" class="mt-1" />
                    </div>

                    {{-- Petugas Pemeriksa --}}
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                        <x-input-label value="Tanda Tangan Petugas Pemeriksa" class="mb-2" />

                        @if (empty($consent['petugasPemeriksa']))
                            @if (!$isFormLocked)
                                <x-primary-button wire:click.prevent="setPetugasPemeriksa" wire:loading.attr="disabled"
                                    wire:target="setPetugasPemeriksa" class="gap-2">
                                    <span wire:loading.remove wire:target="setPetugasPemeriksa">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                        </svg>
                                        TTD sebagai Petugas Pemeriksa
                                    </span>
                                    <span wire:loading wire:target="setPetugasPemeriksa">
                                        <x-loading class="w-4 h-4" /> Menyimpan...
                                    </span>
                                </x-primary-button>
                            @else
                                <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                            @endif
                        @else
                            <div
                                class="p-3 text-center bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                <div class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $consent['petugasPemeriksa'] }}
                                </div>
                                @if (!empty($consent['petugasPemeriksaCode']))
                                    <div class="text-xs text-gray-500 mt-0.5">Kode:
                                        {{ $consent['petugasPemeriksaCode'] }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-500">{{ $consent['petugasPemeriksaDate'] ?? '-' }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ══ KOLOM KANAN — Tanda Tangan Canvas ══ --}}
                <div
                    class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                    <h3
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                        Tanda Tangan Pasien / Wali
                    </h3>

                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />

                    @if (!empty($consent['signature']))
                        <x-signature.signature-result :signature="$consent['signature']" :date="$consent['signatureDate'] ?? ''" :disabled="$isFormLocked"
                            wireMethod="clearSignature" />
                    @elseif (!$isFormLocked)
                        <x-signature.signature-pad wireMethod="setSignature" />
                    @else
                        <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                    @endif
                </div>
            </div>

            {{-- ══ FOOTER ACTIONS ══ --}}
            <div class="flex items-center justify-end gap-3 mt-4">
                @if (!$isFormLocked)
                    <x-secondary-button wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                        class="gap-2">
                        <span wire:loading.remove wire:target="cetak">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                            </svg>
                            Cetak
                        </span>
                        <span wire:loading wire:target="cetak"><x-loading class="w-4 h-4" /></span>
                    </x-secondary-button>

                    <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled" wire:target="save"
                        class="gap-2 min-w-[120px] justify-center">
                        <span wire:loading.remove wire:target="save">Simpan General Consent</span>
                        <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                    </x-primary-button>
                @else
                    <x-secondary-button wire:click="cetak" class="gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                        </svg>
                        Cetak
                    </x-secondary-button>
                @endif
            </div>
        @else
            {{-- Belum dimuat --}}
            <div class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm font-medium">Data UGD belum dimuat</p>
            </div>
        @endif

    </div>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.general-consent.cetak-general-consent
        wire:key="cetak-general-consent-ugd-{{ $rjNo ?? 'init' }}" />
</div>
