<?php
// resources/views/pages/transaksi/ri/emr-ri/inform-consent/rm-inform-consent-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public array $dataDaftarRi = [];

    /* TTD dari canvas */
    public $signature;
    public $signatureSaksi;

    public array $agreementOptions = [['agreementId' => '1', 'agreementDesc' => 'Setuju'], ['agreementId' => '0', 'agreementDesc' => 'Tidak Setuju']];

    public array $informConsentPasienRI = [
        'tindakan' => '',
        'tujuan' => '',
        'resiko' => '',
        'alternatif' => '',
        'dokter' => '',
        'signature' => '',
        'signatureDate' => '',
        'wali' => '',
        'signatureSaksi' => '',
        'signatureSaksiDate' => '',
        'saksi' => '',
        'agreement' => '1',
        'petugasPemeriksa' => '',
        'petugasPemeriksaDate' => '',
        'petugasPemeriksaCode' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-inform-consent-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-inform-consent-ri']);
    }

    #[On('open-rm-inform-consent-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }
        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        $this->dataDaftarRi['informConsentPasienRI'] ??= [];

        $this->incrementVersion('modal-inform-consent-ri');

        $riStatus = DB::scalar('select ri_status from rstxn_rihdrs where rihdr_no=:r', ['r' => $riHdrNo]);
        $this->isFormLocked = $riStatus !== 'I';
    }

    public function submit(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan pasien/wali belum diisi.');
            return;
        }
        if (empty($this->signatureSaksi)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan saksi belum diisi.');
            return;
        }

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->informConsentPasienRI['signature'] = (string) $this->signature;
        $this->informConsentPasienRI['signatureDate'] = $now;
        $this->informConsentPasienRI['signatureSaksi'] = (string) $this->signatureSaksi;
        $this->informConsentPasienRI['signatureSaksiDate'] = $now;

        try {
            $this->validate(
                [
                    'informConsentPasienRI.tindakan' => 'required',
                    'informConsentPasienRI.tujuan' => 'required',
                    'informConsentPasienRI.resiko' => 'required',
                    'informConsentPasienRI.alternatif' => 'required',
                    'informConsentPasienRI.dokter' => 'required',
                    'informConsentPasienRI.signature' => 'required',
                    'informConsentPasienRI.signatureDate' => 'required|date_format:d/m/Y H:i:s',
                    'informConsentPasienRI.wali' => 'required',
                    'informConsentPasienRI.signatureSaksi' => 'required',
                    'informConsentPasienRI.signatureSaksiDate' => 'required|date_format:d/m/Y H:i:s',
                    'informConsentPasienRI.saksi' => 'required',
                    'informConsentPasienRI.agreement' => 'required|in:0,1',
                    'informConsentPasienRI.petugasPemeriksa' => 'required',
                    'informConsentPasienRI.petugasPemeriksaDate' => 'required|date_format:d/m/Y H:i:s',
                    'informConsentPasienRI.petugasPemeriksaCode' => 'required',
                ],
                [
                    'required' => ':attribute wajib diisi.',
                    'in' => ':attribute tidak valid.',
                    'date_format' => ':attribute format harus dd/mm/yyyy hh:mm:ss.',
                ],
                [
                    'informConsentPasienRI.tindakan' => 'Tindakan',
                    'informConsentPasienRI.tujuan' => 'Tujuan',
                    'informConsentPasienRI.resiko' => 'Risiko',
                    'informConsentPasienRI.alternatif' => 'Alternatif',
                    'informConsentPasienRI.dokter' => 'Dokter',
                    'informConsentPasienRI.signature' => 'TTD pasien/wali',
                    'informConsentPasienRI.wali' => 'Nama wali',
                    'informConsentPasienRI.signatureSaksi' => 'TTD saksi',
                    'informConsentPasienRI.saksi' => 'Nama saksi',
                    'informConsentPasienRI.agreement' => 'Persetujuan',
                    'informConsentPasienRI.petugasPemeriksa' => 'Petugas pemeriksa',
                    'informConsentPasienRI.petugasPemeriksaDate' => 'Waktu TTD petugas',
                    'informConsentPasienRI.petugasPemeriksaCode' => 'Kode petugas',
                ],
            );
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first());
            return;
        }

        $this->store();
    }

    private function store(): void
    {
        try {
            Cache::lock("ri:{$this->riHdrNo}", 5)->block(3, function () {
                DB::transaction(function () {
                    $this->lockRIRow($this->riHdrNo); // row-level lock Oracle
                    $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                    $fresh['informConsentPasienRI'] ??= [];

                    $exists = collect($fresh['informConsentPasienRI'])->firstWhere('signatureDate', $this->informConsentPasienRI['signatureDate']);
                    if (!$exists) {
                        $fresh['informConsentPasienRI'][] = $this->informConsentPasienRI;
                    }

                    $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                    $this->dataDaftarRi = $fresh;
                });
            });

            $this->informConsentPasienRI = $this->defaultConsent();
            $this->signature = null;
            $this->signatureSaksi = null;
            $this->resetValidation();
            $this->afterSave('Inform Consent berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan Inform Consent.');
        }
    }

    public function setPetugasPemeriksa(): void
    {
        if (!empty($this->informConsentPasienRI['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Signature petugas sudah ada.');
            return;
        }
        $this->informConsentPasienRI['petugasPemeriksa'] = auth()->user()->myuser_name;
        $this->informConsentPasienRI['petugasPemeriksaCode'] = auth()->user()->myuser_code;
        $this->informConsentPasienRI['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function cetakInformConsent(string $signatureDate)
    {
        if (empty($this->dataDaftarRi)) {
            return;
        }
        $list = $this->dataDaftarRi['informConsentPasienRI'] ?? [];
        $consent = collect($list)->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $dataPasien = $this->findDataMasterPasien($this->regNo ?? '');
            $pdf = Pdf::loadView('livewire.cetak.cetak-inform-consent-r-i-print', [
                'identitasRs' => $identitasRs,
                'dataPasien' => $dataPasien,
                'dataRi' => $this->dataDaftarRi,
                'consent' => $consent,
            ])->output();
            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Inform Consent.');
            return response()->streamDownload(fn() => print $pdf, 'inform-consent-ri-' . $this->riHdrNo . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    private function defaultConsent(): array
    {
        return [
            'tindakan' => '',
            'tujuan' => '',
            'resiko' => '',
            'alternatif' => '',
            'dokter' => '',
            'signature' => '',
            'signatureDate' => '',
            'wali' => '',
            'signatureSaksi' => '',
            'signatureSaksiDate' => '',
            'saksi' => '',
            'agreement' => '1',
            'petugasPemeriksa' => '',
            'petugasPemeriksaDate' => '',
            'petugasPemeriksaCode' => '',
        ];
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-inform-consent-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->informConsentPasienRI = $this->defaultConsent();
        $this->signature = null;
        $this->signatureSaksi = null;
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-inform-consent-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- FORM ENTRY --}}
    @if (!$isFormLocked)
        <x-border-form title="Entry Inform Consent" align="start" bgcolor="bg-gray-50">
            <div class="mt-3 space-y-3">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Tindakan *" />
                        <x-textarea wire:model="informConsentPasienRI.tindakan" class="w-full mt-1" rows="3"
                            :error="$errors->has('informConsentPasienRI.tindakan')" placeholder="Nama tindakan medis..." />
                        <x-input-error :messages="$errors->get('informConsentPasienRI.tindakan')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tujuan *" />
                        <x-textarea wire:model="informConsentPasienRI.tujuan" class="w-full mt-1" rows="3"
                            :error="$errors->has('informConsentPasienRI.tujuan')" placeholder="Tujuan tindakan..." />
                        <x-input-error :messages="$errors->get('informConsentPasienRI.tujuan')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Risiko *" />
                        <x-textarea wire:model="informConsentPasienRI.resiko" class="w-full mt-1" rows="3"
                            :error="$errors->has('informConsentPasienRI.resiko')" placeholder="Risiko tindakan..." />
                    </div>
                    <div>
                        <x-input-label value="Alternatif *" />
                        <x-textarea wire:model="informConsentPasienRI.alternatif" class="w-full mt-1" rows="3"
                            :error="$errors->has('informConsentPasienRI.alternatif')" placeholder="Alternatif lain..." />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Dokter *" />
                        <x-text-input wire:model="informConsentPasienRI.dokter" class="w-full mt-1" :error="$errors->has('informConsentPasienRI.dokter')"
                            placeholder="Nama dokter..." />
                    </div>
                    <div>
                        <x-input-label value="Persetujuan *" />
                        <div class="mt-2 flex gap-4">
                            @foreach ($agreementOptions as $opt)
                                <x-radio-button :label="$opt['agreementDesc']" :value="$opt['agreementId']" name="informConsentAgreement"
                                    wire:model.live="informConsentPasienRI.agreement" :disabled="$isFormLocked" />
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Pasien/Wali --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Nama Wali *" />
                        <x-text-input wire:model="informConsentPasienRI.wali" class="w-full mt-1" :error="$errors->has('informConsentPasienRI.wali')"
                            placeholder="Nama pasien atau wali..." />
                    </div>
                    <div>
                        <x-input-label value="Nama Saksi *" />
                        <x-text-input wire:model="informConsentPasienRI.saksi" class="w-full mt-1" :error="$errors->has('informConsentPasienRI.saksi')"
                            placeholder="Nama saksi..." />
                    </div>
                </div>

                {{-- Tanda tangan --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="TTD Pasien / Wali *" />
                        <x-signature.signature-pad wire:model="signature" class="mt-1" />
                        <x-input-error :messages="$errors->get('informConsentPasienRI.signature')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="TTD Saksi *" />
                        <x-signature.signature-pad wire:model="signatureSaksi" class="mt-1" />
                        <x-input-error :messages="$errors->get('informConsentPasienRI.signatureSaksi')" class="mt-1" />
                    </div>
                </div>

                {{-- TTD Petugas --}}
                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <x-input-label value="Petugas Pemeriksa *" />
                        <x-text-input value="{{ $informConsentPasienRI['petugasPemeriksa'] ?? '-' }}"
                            class="w-full mt-1" readonly />
                        <x-input-error :messages="$errors->get('informConsentPasienRI.petugasPemeriksa')" class="mt-1" />
                    </div>
                    <div class="flex-1">
                        <x-input-label value="Waktu TTD" />
                        <x-text-input value="{{ $informConsentPasienRI['petugasPemeriksaDate'] ?? '-' }}"
                            class="w-full mt-1 font-mono" readonly />
                    </div>
                    <div class="pb-0.5">
                        <x-primary-button wire:click="setPetugasPemeriksa" type="button">TTD Saya</x-primary-button>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-primary-button wire:click="submit" type="button">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Inform Consent
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    {{-- LIST --}}
    <x-border-form title="Riwayat Inform Consent" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @forelse ($dataDaftarRi['informConsentPasienRI'] ?? [] as $idx => $consent)
                <div wire:key="ic-{{ $idx }}-{{ $this->renderKey('modal-inform-consent-ri') }}"
                    class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 overflow-hidden">
                    <div
                        class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-700/60 border-b border-gray-100 dark:border-gray-700">
                        <div class="text-xs space-x-2">
                            <span
                                class="font-semibold text-gray-700 dark:text-gray-200">{{ $consent['tindakan'] ?? '-' }}</span>
                            <span class="font-mono text-gray-400">{{ $consent['signatureDate'] ?? '-' }}</span>
                            <x-badge variant="{{ ($consent['agreement'] ?? '1') === '1' ? 'success' : 'danger' }}">
                                {{ ($consent['agreement'] ?? '1') === '1' ? 'Setuju' : 'Tidak Setuju' }}
                            </x-badge>
                        </div>
                        <x-primary-button wire:click="cetakInformConsent('{{ $consent['signatureDate'] }}')"
                            type="button" class="text-xs">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Cetak
                        </x-primary-button>
                    </div>
                    <div class="px-4 py-3 grid grid-cols-2 gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <div><span class="font-semibold">Wali:</span> {{ $consent['wali'] ?? '-' }}</div>
                        <div><span class="font-semibold">Saksi:</span> {{ $consent['saksi'] ?? '-' }}</div>
                        <div><span class="font-semibold">Dokter:</span> {{ $consent['dokter'] ?? '-' }}</div>
                        <div><span class="font-semibold">Petugas:</span> {{ $consent['petugasPemeriksa'] ?? '-' }}</div>
                    </div>
                </div>
            @empty
                <p class="text-xs text-center text-gray-400 py-6">Belum ada Inform Consent.</p>
            @endforelse
        </div>
    </x-border-form>

</div>
