<?php
// resources/views/pages/transaksi/ri/emr-ri/general-consent/rm-general-consent-ri-actions.blade.php

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

    public $signature;

    public array $agreementOptions = [['agreementId' => '1', 'agreementDesc' => 'Setuju'], ['agreementId' => '0', 'agreementDesc' => 'Tidak Setuju']];

    /* Nilai default — digunakan untuk init & init di dataDaftarRi */
    private function defaultConsent(): array
    {
        return [
            'signature' => '',
            'signatureDate' => '',
            'wali' => '',
            'agreement' => '1',
            'petugasPemeriksa' => '',
            'petugasPemeriksaDate' => '',
            'petugasPemeriksaCode' => '',
        ];
    }

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-general-consent-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-general-consent-ri']);
    }

    #[On('open-rm-general-consent-ri')]
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
        $this->dataDaftarRi['generalConsentPasienRI'] ??= $this->defaultConsent();

        $this->incrementVersion('modal-general-consent-ri');
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

        $this->dataDaftarRi['generalConsentPasienRI']['signature'] = (string) $this->signature;
        $this->dataDaftarRi['generalConsentPasienRI']['signatureDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->validate(
                [
                    'dataDaftarRi.generalConsentPasienRI.signature' => 'required',
                    'dataDaftarRi.generalConsentPasienRI.signatureDate' => 'required|date_format:d/m/Y H:i:s',
                    'dataDaftarRi.generalConsentPasienRI.wali' => 'required',
                    'dataDaftarRi.generalConsentPasienRI.agreement' => 'required|in:0,1',
                    'dataDaftarRi.generalConsentPasienRI.petugasPemeriksa' => 'required',
                    'dataDaftarRi.generalConsentPasienRI.petugasPemeriksaDate' => 'required|date_format:d/m/Y H:i:s',
                    'dataDaftarRi.generalConsentPasienRI.petugasPemeriksaCode' => 'required',
                ],
                [
                    'required' => ':attribute wajib diisi.',
                    'in' => ':attribute tidak valid.',
                    'date_format' => ':attribute format harus dd/mm/yyyy hh:mm:ss.',
                ],
                [
                    'dataDaftarRi.generalConsentPasienRI.signature' => 'TTD pasien/wali',
                    'dataDaftarRi.generalConsentPasienRI.wali' => 'Nama wali',
                    'dataDaftarRi.generalConsentPasienRI.agreement' => 'Persetujuan',
                    'dataDaftarRi.generalConsentPasienRI.petugasPemeriksa' => 'Petugas pemeriksa',
                    'dataDaftarRi.generalConsentPasienRI.petugasPemeriksaDate' => 'Waktu TTD petugas',
                    'dataDaftarRi.generalConsentPasienRI.petugasPemeriksaCode' => 'Kode petugas',
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
                    $fresh['generalConsentPasienRI'] = array_replace($fresh['generalConsentPasienRI'] ?? $this->defaultConsent(), (array) ($this->dataDaftarRi['generalConsentPasienRI'] ?? []));
                    $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                    $this->dataDaftarRi = $fresh;
                });
            });
            $this->signature = null;
            $this->afterSave('General Consent berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan General Consent.');
        }
    }

    public function setPetugasPemeriksa(): void
    {
        if (!empty($this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Signature petugas sudah ada.');
            return;
        }
        $this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksa'] = auth()->user()->myuser_name;
        $this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksaCode'] = auth()->user()->myuser_code;
        $this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function cetakGeneralConsent()
    {
        $consent = $this->dataDaftarRi['generalConsentPasienRI'] ?? null;
        if (!$consent || !is_array($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return;
        }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $dataPasien = $this->findDataMasterPasien($this->regNo ?? '');
            $pdf = Pdf::loadView('livewire.cetak.cetak-general-consent-r-i-print', [
                'identitasRs' => $identitasRs,
                'dataPasien' => $dataPasien,
                'dataRi' => $this->dataDaftarRi,
                'consent' => $consent,
            ])->output();
            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak General Consent.');
            return response()->streamDownload(fn() => print $pdf, 'general-consent-ri-' . $this->riHdrNo . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-general-consent-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->signature = null;
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-general-consent-ri', [$riHdrNo ?? 'new']) }}">

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

    <x-border-form title="General Consent Rawat Inap" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-4">

            {{-- Persetujuan --}}
            <div>
                <x-input-label value="Persetujuan *" />
                <div class="mt-2 flex gap-4">
                    @foreach ($agreementOptions as $opt)
                        <x-radio-button :label="$opt['agreementDesc']" :value="$opt['agreementId']" name="generalConsentAgreement"
                            wire:model.live="dataDaftarRi.generalConsentPasienRI.agreement" :disabled="$isFormLocked" />
                    @endforeach
                </div>
            </div>

            {{-- Nama wali --}}
            <div>
                <x-input-label value="Nama Pasien / Wali *" />
                <x-text-input wire:model.live="dataDaftarRi.generalConsentPasienRI.wali" class="w-full mt-1"
                    :disabled="$isFormLocked" :error="$errors->has('dataDaftarRi.generalConsentPasienRI.wali')" placeholder="Nama pasien atau wali yang menandatangani..." />
                <x-input-error :messages="$errors->get('dataDaftarRi.generalConsentPasienRI.wali')" class="mt-1" />
            </div>

            {{-- TTD --}}
            @if (!$isFormLocked)
                <div>
                    <x-input-label value="Tanda Tangan Pasien / Wali *" />
                    <x-signature.signature-pad wire:model="signature" class="mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarRi.generalConsentPasienRI.signature')" class="mt-1" />
                </div>
            @elseif (!empty($dataDaftarRi['generalConsentPasienRI']['signature']))
                <div>
                    <x-input-label value="Tanda Tangan Pasien / Wali" />
                    <img src="{{ $dataDaftarRi['generalConsentPasienRI']['signature'] }}"
                        class="mt-1 max-h-24 border border-gray-200 rounded" alt="TTD" />
                    <p class="text-xs text-gray-400 mt-0.5 font-mono">
                        {{ $dataDaftarRi['generalConsentPasienRI']['signatureDate'] ?? '' }}</p>
                </div>
            @endif

            {{-- TTD Petugas --}}
            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <x-input-label value="Petugas Pemeriksa *" />
                    <x-text-input value="{{ $dataDaftarRi['generalConsentPasienRI']['petugasPemeriksa'] ?? '-' }}"
                        class="w-full mt-1" readonly />
                    <x-input-error :messages="$errors->get('dataDaftarRi.generalConsentPasienRI.petugasPemeriksa')" class="mt-1" />
                </div>
                <div class="flex-1">
                    <x-input-label value="Waktu TTD Petugas" />
                    <x-text-input value="{{ $dataDaftarRi['generalConsentPasienRI']['petugasPemeriksaDate'] ?? '-' }}"
                        class="w-full mt-1 font-mono" readonly />
                </div>
                @if (!$isFormLocked)
                    <div class="pb-0.5">
                        <x-primary-button wire:click="setPetugasPemeriksa" type="button">TTD Saya</x-primary-button>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex justify-between pt-2">
                <x-primary-button wire:click="cetakGeneralConsent" type="button">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak General Consent
                </x-primary-button>
                @if (!$isFormLocked)
                    <x-primary-button wire:click="submit" type="button">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan General Consent
                    </x-primary-button>
                @endif
            </div>
        </div>
    </x-border-form>

</div>
