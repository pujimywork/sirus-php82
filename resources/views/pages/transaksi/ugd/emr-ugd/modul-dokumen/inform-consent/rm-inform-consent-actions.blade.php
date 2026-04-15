<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/inform-consent/rm-inform-consent-ugd-actions.blade.php

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
    protected array $renderAreas = ['modal-inform-consent'];

    public array $newConsent = [
        'tindakan' => '',
        'tujuan' => '',
        'resiko' => '',
        'alternatif' => '',
        'dokter' => '',
        'wali' => '',
        'saksi' => '',
        'agreement' => '1',
        'dokterCode' => '',
        'dokterDate' => '',
        'petugasPemeriksa' => '',
        'petugasPemeriksaCode' => '',
        'petugasPemeriksaDate' => '',
    ];

    public string $signature = '';
    public string $signatureSaksi = '';

    public array $agreementOptions = [['value' => '1', 'label' => 'Setuju'], ['value' => '0', 'label' => 'Tidak Setuju']];

    public array $consentList = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-inform-consent']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-inform-consent')]
    public function openInformConsent(int $rjNo): void
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
        if (!isset($this->dataDaftarUGD['informConsentPasienUGD']) || !is_array($this->dataDaftarUGD['informConsentPasienUGD'])) {
            $this->dataDaftarUGD['informConsentPasienUGD'] = [];
        }
        $this->consentList = $this->dataDaftarUGD['informConsentPasienUGD'];
        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-inform-consent');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newConsent.tindakan' => 'required|string|max:500',
            'newConsent.tujuan' => 'nullable|string',
            'newConsent.resiko' => 'nullable|string',
            'newConsent.alternatif' => 'nullable|string',
            'newConsent.dokter' => 'nullable|string',
            'newConsent.wali' => 'required|string|max:200',
            'newConsent.saksi' => 'nullable|string|max:200',
            'newConsent.agreement' => 'required|in:0,1',
            'signature' => 'required|string',
            'signatureSaksi' => 'nullable|string',
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
            'newConsent.tindakan' => 'Nama tindakan',
            'newConsent.tujuan' => 'Tujuan tindakan',
            'newConsent.resiko' => 'Risiko tindakan',
            'newConsent.alternatif' => 'Alternatif tindakan',
            'newConsent.dokter' => 'Dokter penjelas',
            'newConsent.wali' => 'Nama pasien/wali',
            'newConsent.saksi' => 'Nama saksi',
            'newConsent.agreement' => 'Persetujuan',
            'signature' => 'Tanda tangan pasien/wali',
            'signatureSaksi' => 'Tanda tangan saksi',
        ];
    }

    /* ===============================
     | SET SIGNATURES
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-inform-consent');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-inform-consent');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-inform-consent');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-inform-consent');
    }

    /* ===============================
     | SET DOKTER PENJELAS
     =============================== */
    public function setDokterPenjelas(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->newConsent['dokter'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan dokter sudah ada.');
            return;
        }

        $this->newConsent['dokter'] = auth()->user()->myuser_name ?? '';
        $this->newConsent['dokterCode'] = auth()->user()->myuser_code ?? '';
        $this->newConsent['dokterDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan dokter berhasil ditambahkan.');
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

        if (!empty($this->newConsent['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan petugas pemeriksa sudah ada.');
            return;
        }

        $this->newConsent['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->newConsent['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
        $this->newConsent['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan petugas pemeriksa berhasil ditambahkan.');
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-inform-consent')]
    public function addConsent(): void
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

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $consentEntry = [
            'tindakan' => $this->newConsent['tindakan'],
            'tujuan' => $this->newConsent['tujuan'],
            'resiko' => $this->newConsent['resiko'],
            'alternatif' => $this->newConsent['alternatif'],
            'dokter' => $this->newConsent['dokter'] ?? '',
            'dokterCode' => $this->newConsent['dokterCode'] ?? '',
            'dokterDate' => $this->newConsent['dokterDate'] ?? '',
            'signature' => $this->signature,
            'signatureDate' => $now,
            'wali' => $this->newConsent['wali'],
            'signatureSaksi' => $this->signatureSaksi,
            'signatureSaksiDate' => $this->signatureSaksi ? $now : '',
            'saksi' => $this->newConsent['saksi'],
            'agreement' => $this->newConsent['agreement'],
            'petugasPemeriksa' => $this->newConsent['petugasPemeriksa'] ?? '',
            'petugasPemeriksaCode' => $this->newConsent['petugasPemeriksaCode'] ?? '',
            'petugasPemeriksaDate' => $this->newConsent['petugasPemeriksaDate'] ?? '',
        ];

        try {
            DB::transaction(function () use ($consentEntry) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                if (!isset($data['informConsentPasienUGD']) || !is_array($data['informConsentPasienUGD'])) {
                    $data['informConsentPasienUGD'] = [];
                }

                $data['informConsentPasienUGD'][] = $consentEntry;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'];
            });

            $this->incrementVersion('modal-inform-consent');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent berhasil disimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);

            $this->resetNewConsent();
            $this->signature = '';
            $this->signatureSaksi = '';
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $consent = collect($this->consentList)->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-inform-consent.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS  ← tambahan baru
     =============================== */
    public function hapus(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                if (!isset($data['informConsentPasienUGD'])) {
                    throw new \RuntimeException('Data consent tidak ditemukan.');
                }

                $data['informConsentPasienUGD'] = collect($data['informConsentPasienUGD'])->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)->values()->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'];
            });

            $this->incrementVersion('modal-inform-consent');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewConsent(): void
    {
        $this->newConsent = [
            'tindakan' => '',
            'tujuan' => '',
            'resiko' => '',
            'alternatif' => '',
            'dokter' => '',
            'wali' => '',
            'saksi' => '',
            'agreement' => '1',
            'dokterCode' => '',
            'dokterDate' => '',
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->consentList = [];
        $this->resetNewConsent();
        $this->signature = '';
        $this->signatureSaksi = '';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-inform-consent', [$rjNo ?? 'new']) }}">

        @if ($isFormLocked)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mb-4 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                EMR terkunci — data tidak dapat diubah.
            </div>
        @endif

        {{-- FORM CONSENT BARU --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

            {{-- KOLOM KIRI --}}
            <div
                class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                <h3
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                    Informasi Tindakan Baru
                </h3>

                <div>
                    <x-input-label value="Nama Tindakan / Prosedur *" class="mb-1" />
                    <x-text-input wire:model.live="newConsent.tindakan"
                        placeholder="Contoh: Pemasangan Infus, Hecting, Nebulizer..." :disabled="$isFormLocked"
                        class="w-full" />
                    <x-input-error :messages="$errors->get('newConsent.tindakan')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Tujuan Tindakan" class="mb-1" />
                    <x-textarea wire:model.live="newConsent.tujuan" rows="3"
                        placeholder="Uraian singkat mengenai tujuan tindakan..." :disabled="$isFormLocked" />
                </div>

                <div>
                    <x-input-label value="Risiko Tindakan" class="mb-1" />
                    <x-textarea wire:model.live="newConsent.resiko" rows="2"
                        placeholder="Kemungkinan risiko / efek samping..." :disabled="$isFormLocked" />
                </div>

                <div>
                    <x-input-label value="Alternatif Tindakan" class="mb-1" />
                    <x-textarea wire:model.live="newConsent.alternatif" rows="2"
                        placeholder="Alternatif lain yang dapat dilakukan..." :disabled="$isFormLocked" />
                </div>

                <div>
                    <x-input-label value="Persetujuan *" class="mb-1" />
                    <x-select-input wire:model.live="newConsent.agreement" :disabled="$isFormLocked" class="w-full">
                        @foreach ($agreementOptions as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('newConsent.agreement')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                    <x-text-input wire:model.live="newConsent.wali" placeholder="Nama lengkap pasien atau wali..."
                        :disabled="$isFormLocked" class="w-full" />
                    <x-input-error :messages="$errors->get('newConsent.wali')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Saksi" class="mb-1" />
                    <x-text-input wire:model.live="newConsent.saksi" placeholder="Nama saksi..." :disabled="$isFormLocked"
                        class="w-full" />
                    <x-input-error :messages="$errors->get('newConsent.saksi')" class="mt-1" />
                </div>

                {{-- Dokter Penjelas --}}
                <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                    <x-input-label value="Dokter / Petugas yang Menjelaskan" class="mb-2" />
                    @if (empty($newConsent['dokter']))
                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="setDokterPenjelas" wire:loading.attr="disabled"
                                wire:target="setDokterPenjelas" class="gap-2">
                                <span wire:loading.remove wire:target="setDokterPenjelas">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                    </svg>
                                    TTD sebagai Dokter / Petugas Penjelas
                                </span>
                                <span wire:loading wire:target="setDokterPenjelas">
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
                                {{ $newConsent['dokter'] }}</div>
                            @if (!empty($newConsent['dokterCode']))
                                <div class="text-xs text-gray-500 mt-0.5">Kode: {{ $newConsent['dokterCode'] }}
                                </div>
                            @endif
                            <div class="mt-1 text-xs text-gray-500">{{ $newConsent['dokterDate'] ?? '-' }}</div>
                        </div>
                    @endif
                </div>

                {{-- Petugas Pemeriksa --}}
                <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                    <x-input-label value="Tanda Tangan Petugas Pemeriksa" class="mb-2" />
                    @if (empty($newConsent['petugasPemeriksa']))
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
                                {{ $newConsent['petugasPemeriksa'] }}</div>
                            @if (!empty($newConsent['petugasPemeriksaCode']))
                                <div class="text-xs text-gray-500 mt-0.5">Kode:
                                    {{ $newConsent['petugasPemeriksaCode'] }}</div>
                            @endif
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $newConsent['petugasPemeriksaDate'] ?? '-' }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- KOLOM KANAN: Tanda Tangan --}}
            <div class="space-y-4">

                {{-- TTD Pasien / Wali --}}
                <div
                    class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                    <h3
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                        Tanda Tangan Pasien / Wali
                    </h3>
                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />

                    @if (!empty($signature))
                        <x-signature.signature-result :signature="$signature" :date="$signatureDate ?? ''" :disabled="$isFormLocked"
                            wireMethod="clearSignature" />
                    @elseif (!$isFormLocked)
                        <x-signature.signature-pad wireMethod="setSignature" />
                    @else
                        <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                    @endif
                </div>

                {{-- TTD Saksi --}}
                <div
                    class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                    <h3
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                        Tanda Tangan Saksi
                    </h3>
                    <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />

                    @if (!empty($signatureSaksi))
                        <x-signature.signature-result :signature="$signatureSaksi" :date="$signatureSaksiDate ?? ''" :disabled="$isFormLocked"
                            wireMethod="clearSignatureSaksi" />
                    @elseif (!$isFormLocked)
                        <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                    @else
                        <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                    @endif
                </div>

            </div>
        </div>

        {{-- FOOTER ACTIONS --}}
        <div class="flex items-center justify-end gap-3 mt-4">
            @if (!$isFormLocked)
                <x-secondary-button wire:click="cetak('')" wire:loading.attr="disabled" wire:target="cetak"
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

                <x-primary-button wire:click.prevent="addConsent" wire:loading.attr="disabled"
                    wire:target="addConsent" class="gap-2 min-w-[120px] justify-center">
                    <span wire:loading.remove wire:target="addConsent">Simpan Inform Consent</span>
                    <span wire:loading wire:target="addConsent"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                </x-primary-button>
            @else
                <x-secondary-button wire:click="cetak('')" class="gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                    </svg>
                    Cetak
                </x-secondary-button>
            @endif
        </div>

        {{-- DAFTAR CONSENT TERSIMPAN --}}
        @if (count($consentList) > 0)
            <div class="mt-6 overflow-x-auto">
                <h3
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800 mb-3">
                    Daftar Inform Consent Tersimpan
                </h3>
                <table class="min-w-full text-sm border border-gray-200 rounded-lg dark:border-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-4 py-2 border-b">Tindakan</th>
                            <th class="px-4 py-2 border-b">Tanggal TTD Pasien</th>
                            <th class="px-4 py-2 border-b">Dokter Penjelas</th>
                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($consentList as $consent)
                            <tr
                                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">
                                    {{ Str::limit($consent['tindakan'], 50) }}
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                    {{ $consent['signatureDate'] ?? '-' }}
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                    {{ $consent['dokter'] ?? '-' }}
                                </td>
                                <td class="px-4 py-2 text-center space-x-2">
                                    <x-secondary-button wire:click="cetak('{{ $consent['signatureDate'] }}')"
                                        class="text-xs py-1 px-2">
                                        <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                                        </svg>
                                        Cetak
                                    </x-secondary-button>
                                    @if (!$isFormLocked)
                                        <x-confirm-button variant="danger" :action="'hapus(\'' . $consent['signatureDate'] . '\')'"
                                            title="Hapus Inform Consent"
                                            message="Yakin hapus Inform Consent ini? Dokumen yang sudah ditandatangani akan dihapus."
                                            confirmText="Ya, hapus" cancelText="Batal"
                                            class="text-xs py-1 px-2">
                                            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Hapus
                                        </x-confirm-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.inform-consent.cetak-inform-consent
        wire:key="cetak-inform-consent-{{ $rjNo ?? 'init' }}" />
</div>
