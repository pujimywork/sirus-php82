<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/general-consent/rm-general-consent-rj-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-general-consent-rj'];

    // ── Form fields — top-level untuk wire:model ──
    public string $wali = '';
    public string $waliHubungan = ''; // Hubungan wali dengan pasien — HPK 4.2
    public string $agreement = '1'; // 1=Setuju, 0=Tidak Setuju
    public string $signature = ''; // base64 dari canvas/signpad

    // HPK 1 EP-c — Pihak yg diberi akses info medis (max 5 baris).
    public array $pihakInfoMedis = [
        ['nama' => '', 'hubungan' => '', 'noHp' => ''],
    ];

    public array $agreementOptions = [['value' => '1', 'label' => 'Setuju'], ['value' => '0', 'label' => 'Tidak Setuju']];

    public array $waliHubunganOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-general-consent-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultGeneralConsent();
        $current = $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [];
        $this->dataDaftarPoliRJ['generalConsentPasienRJ'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ??= $this->getDefaultGeneralConsent();

        $consent = $this->dataDaftarPoliRJ['generalConsentPasienRJ'];
        // Default Nama Pasien/Wali = nama pasien & hubungan = Pasien Sendiri bila belum diisi (pola penundaan)
        $this->wali = $consent['wali'] ?: ($this->dataDaftarPoliRJ['regName'] ?? '');
        $this->waliHubungan = $consent['waliHubungan'] ?: 'pasien';
        $this->agreement = $consent['agreement'] ?? '1';
        $this->signature = $consent['signature'] ?? '';

        $loaded = $consent['pihakInfoMedis'] ?? [];
        $this->pihakInfoMedis = !empty($loaded) ? $loaded : [['nama' => '', 'hubungan' => '', 'noHp' => '']];

        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-general-consent-rj');

        $this->dispatch('open-modal', name: "rm-general-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-general-consent-rj-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'signature' => 'required|string',
            'wali' => 'required|string|max:200',
            'waliHubungan' => 'required|string|max:50',
            'agreement' => 'required|in:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'agreement.in' => 'Persetujuan Pelayanan harus "Setuju" agar General Consent dapat diproses.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'signature' => 'Tanda tangan pasien/wali',
            'wali' => 'Nama wali',
            'waliHubungan' => 'Hubungan wali',
            'agreement' => 'Persetujuan',
        ];
    }

    /* ===============================
     | UPDATED HOOKS — sync top-level → nested
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        $map = [
            'wali' => 'wali',
            'waliHubungan' => 'waliHubungan',
            'agreement' => 'agreement',
        ];
        if (isset($map[$name])) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ'][$map[$name]] = $value;
        }

        // Sync pihakInfoMedis (nested wire:model live)
        if (str_starts_with($name, 'pihakInfoMedis.')) {
            $this->dataDaftarPoliRJ['generalConsentPasienRJ']['pihakInfoMedis'] = $this->pihakInfoMedis;
        }

        if ($name === 'agreement') {
            $this->validateOnly('agreement');
        }
    }

    public function addPihakInfo(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (count($this->pihakInfoMedis) >= 5) {
            $this->dispatch('toast', type: 'warning', message: 'Maksimal 5 pihak.');
            return;
        }
        $this->pihakInfoMedis[] = ['nama' => '', 'hubungan' => '', 'noHp' => ''];
    }

    public function removePihakInfo(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (count($this->pihakInfoMedis) <= 1) {
            $this->pihakInfoMedis = [['nama' => '', 'hubungan' => '', 'noHp' => '']];
        } else {
            unset($this->pihakInfoMedis[$index]);
            $this->pihakInfoMedis = array_values($this->pihakInfoMedis);
        }
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['pihakInfoMedis'] = $this->pihakInfoMedis;
    }

    /* ===============================
     | SET SIGNATURE
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->signature = $dataUrl;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signature'] = $dataUrl;
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signatureDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
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
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signature'] = '';
        $this->dataDaftarPoliRJ['generalConsentPasienRJ']['signatureDate'] = '';
        $this->incrementVersion('modal-general-consent-rj');
    }

    /* ===============================
     | SET PETUGAS PEMBERI PENJELASAN
     =============================== */
    public function setPetugasPemeriksa(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->dataDaftarPoliRJ['generalConsentPasienRJ']['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan petugas pemberi penjelasan sudah ada.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan.');
                }

                $data['generalConsentPasienRJ'] ??= $this->getDefaultGeneralConsent();
                $data['generalConsentPasienRJ']['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
                $data['generalConsentPasienRJ']['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
                $data['generalConsentPasienRJ']['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->appendAdminLogRJ((int) $this->rjNo, 'TTD Petugas Pemberi Penjelasan General Consent — TTD pasien ' . ($data['generalConsentPasienRJ']['signatureDate'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'Tanda tangan petugas pemberi penjelasan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-general-consent-rj')]
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
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status baru/lama sebelum overwrite — pakai signatureDate
                // (key generalConsentPasienRJ bisa pre-init, tapi signatureDate baru terisi saat sudah disimpan/ditandatangani)
                $isBaru = empty($data['generalConsentPasienRJ']['signatureDate'] ?? '');

                $data['generalConsentPasienRJ'] = array_replace($data['generalConsentPasienRJ'] ?? $this->getDefaultGeneralConsent(), $this->dataDaftarPoliRJ['generalConsentPasienRJ'] ?? []);

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' General Consent — TTD ' . ($data['generalConsentPasienRJ']['signatureDate'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-general-consent-rj');
            $this->dispatch('toast', type: 'success', message: 'General Consent berhasil disimpan.');
            $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
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
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-general-consent-rj.open', rjNo: $this->rjNo);
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
            'waliHubungan' => '',
            'agreement' => '1',
            'pihakInfoMedis' => [],
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
        $this->dataDaftarPoliRJ = [];
        $this->signature = '';
        $this->wali = '';
        $this->waliHubungan = '';
        $this->agreement = '1';
        $this->pihakInfoMedis = [['nama' => '', 'hubungan' => '', 'noHp' => '']];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php
        $gc = $dataDaftarPoliRJ['generalConsentPasienRJ'] ?? [];
        $gcSigned = !empty($gc['signature']);
    @endphp

    <div
        class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        General Consent
                    </h3>
                    @if ($gcSigned)
                        <x-badge variant="success">Sudah ditandatangani</x-badge>
                    @else
                        <x-badge variant="warning">Belum ditandatangani</x-badge>
                    @endif
                </div>

                <p class="text-base text-muted dark:text-gray-400">
                    Persetujuan umum pasien terhadap pelayanan rawat jalan, hak & tanggung jawab, serta perlindungan data.
                </p>

                @if ($gcSigned)
                    <dl class="grid grid-cols-1 gap-2 text-base sm:grid-cols-3 text-muted dark:text-gray-300">
                        <div>
                            <dt class="text-sm uppercase text-muted-soft">Wali</dt>
                            <dd class="font-medium">{{ $gc['wali'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm uppercase text-muted-soft">Persetujuan</dt>
                            <dd class="font-medium">
                                {{ ($gc['agreement'] ?? '1') === '1' ? 'Setuju' : 'Tidak Setuju' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm uppercase text-muted-soft">Tanggal TTD</dt>
                            <dd class="font-medium">{{ $gc['signatureDate'] ?? '-' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka General Consent
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-general-consent-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        {{-- dirty-guard: peringatan bila isian General Consent belum disimpan saat modal ditutup --}}
        <x-dirty-modal-content
            name="rm-general-consent-rj-{{ $rjNo ?? 'init' }}"
            event="refresh-modul-dokumen-rj-data"
            label="General Consent"
            wireKey="dirty-general-consent-rj-{{ $rjNo ?? 'init' }}"
            wrapperClass="flex flex-col min-h-0"
            :saveEvents="['save-rm-general-consent-rj']">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">
                                    General Consent
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    Persetujuan umum pasien rawat jalan — tampilan ini dapat diputar ke arah pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="success">Rawat Jalan</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="gc-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        @if (isset($dataDaftarPoliRJ['generalConsentPasienRJ']))

                            @php $consent = $dataDaftarPoliRJ['generalConsentPasienRJ']; @endphp

                            {{-- ══ ISI PERSETUJUAN ══ --}}
                            <section>
                                {{-- Isi Persetujuan — entry pihak akses dirender via slot,
                                     langsung di bawah paragraf izin akses info medis --}}
                                <x-consent.general-consent-body context="rj" :showReleaseInfo="true"
                                    :pihakInfoList="$pihakInfoMedis">

                                    {{-- Tabel entry bergaris tipis — selaras tabel di cetakan (No/Nama/Hubungan/No. HP) --}}
                                    <div class="overflow-hidden border border-hairline rounded-lg dark:border-gray-700">
                                        <div class="grid grid-cols-12 gap-2 px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted bg-surface-soft border-b border-hairline dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                                            <span class="col-span-1 text-center">#</span>
                                            <span class="col-span-4">Nama</span>
                                            <span class="col-span-4">Hubungan</span>
                                            <span class="col-span-2">No. HP</span>
                                            <span class="col-span-1"></span>
                                        </div>

                                        <div class="divide-y divide-hairline-soft dark:divide-gray-800">
                                        @foreach ($pihakInfoMedis as $index => $pihak)
                                            <div wire:key="pihak-info-rj-{{ $index }}"
                                                class="grid grid-cols-12 gap-2 items-start px-2 py-1.5">
                                                <span class="col-span-1 pt-2 text-sm text-center text-muted">
                                                    {{ $index + 1 }}
                                                </span>
                                                {{-- Enter-chain ala e-resep ($refs antar field; baris baru via getElementById
                                                     karena elemennya belum dirender saat Enter ditekan) --}}
                                                <x-text-input id="pihak-nama-rj-{{ $index }}"
                                                    wire:model.live.debounce.500ms="pihakInfoMedis.{{ $index }}.nama"
                                                    placeholder="Nama" :disabled="$isFormLocked"
                                                    x-on:keydown.enter.prevent="$refs.pihakHub{{ $index }}.focus()"
                                                    class="col-span-4 text-sm" />
                                                <x-text-input x-ref="pihakHub{{ $index }}"
                                                    wire:model.live.debounce.500ms="pihakInfoMedis.{{ $index }}.hubungan"
                                                    placeholder="Hubungan (cth: anak, istri)" :disabled="$isFormLocked"
                                                    x-on:keydown.enter.prevent="$refs.pihakHp{{ $index }}.focus()"
                                                    class="col-span-4 text-sm" />
                                                <x-text-input x-ref="pihakHp{{ $index }}"
                                                    wire:model.live.debounce.500ms="pihakInfoMedis.{{ $index }}.noHp"
                                                    placeholder="No. HP" :disabled="$isFormLocked"
                                                    x-on:keydown.enter.prevent="$el.blur(); $wire.addPihakInfo().then(() => setTimeout(() => document.getElementById('pihak-nama-rj-{{ $index + 1 }}')?.focus(), 100))"
                                                    class="col-span-2 text-sm" />
                                                @if (!$isFormLocked)
                                                    <x-outline-button type="button" wire:click="removePihakInfo({{ $index }})"
                                                        wire:confirm="Hapus item ini?" wire:loading.attr="disabled"
                                                        class="col-span-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                        title="Hapus">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </x-outline-button>
                                                @endif
                                            </div>
                                        @endforeach
                                        </div>
                                    </div>

                                    @if (!$isFormLocked)
                                        <div class="flex justify-end">
                                            <x-primary-button type="button" wire:click="addPihakInfo"
                                                class="text-sm py-1 px-2">
                                                + Tambah
                                            </x-primary-button>
                                        </div>
                                    @endif
                                </x-consent.general-consent-body>
                            </section>

                            {{-- ══ DATA PERSETUJUAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                    Data Persetujuan
                                </h3>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Persetujuan Pelayanan *" class="mb-1" />
                                        <x-select-input wire:model.live="agreement" :error="$errors->has('agreement')"
                                            :disabled="$isFormLocked" class="w-full">
                                            @foreach ($agreementOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('agreement')" class="mt-1" />
                                    </div>

                                </div>

                                @if (($agreement ?? '1') === '1')
                                    <div
                                        class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
                                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold">Pasien MENYETUJUI General Consent</p>
                                            <p class="mt-0.5">
                                                Persetujuan umum atas pelayanan rawat jalan, hak &amp; tanggung jawab, serta
                                                perlindungan data. Tindakan medis spesifik tetap memerlukan
                                                <strong>Inform Consent</strong> tersendiri.
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </section>

                            {{-- ══ TANDA TANGAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                    Tanda Tangan
                                </h3>

                                <x-input-error :messages="$errors->get('signature')" />

                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Pasien / Wali --}}
                                    <div class="flex flex-col">
                                        <div
                                            class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                            Pasien / Wali
                                        </div>
                                        @if (!empty($consent['signature']))
                                            <x-signature.signature-result :signature="$consent['signature']"
                                                :date="$consent['signatureDate'] ?? ''" :disabled="$isFormLocked"
                                                wireMethod="clearSignature" />
                                        @elseif (!$isFormLocked)
                                            <x-signature.signature-pad wireMethod="setSignature" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif

                                        <div class="mt-3">
                                            <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                                            <x-text-input wire:model.live="wali"
                                                placeholder="Nama lengkap pasien atau wali..." :error="$errors->has('wali')"
                                                :disabled="$isFormLocked" class="w-full" />
                                            <x-input-error :messages="$errors->get('wali')" class="mt-1" />
                                        </div>

                                        <div class="mt-2">
                                            <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                            <x-select-input wire:model.live="waliHubungan"
                                                :error="$errors->has('waliHubungan')" :disabled="$isFormLocked"
                                                class="w-full">
                                                <option value="">— Pilih hubungan —</option>
                                                @foreach ($waliHubunganOptions as $opt)
                                                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('waliHubungan')" class="mt-1" />
                                        </div>
                                    </div>

                                    {{-- Petugas Pemberi Penjelasan --}}
                                    <div class="flex flex-col">
                                        <div
                                            class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                            Petugas Pemberi Penjelasan
                                        </div>
                                        @if (empty($consent['petugasPemeriksa']) && !empty($consent['signature']))
                                            <div class="mb-2 text-center">
                                                <x-badge variant="warning">Menunggu TTD Petugas</x-badge>
                                            </div>
                                        @endif
                                        <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                            :ttd="$consent['petugasPemeriksa'] ?? ''"
                                            :date="$consent['petugasPemeriksaDate'] ?? ''"
                                            :code="$consent['petugasPemeriksaCode'] ?? ''" :locked="$isFormLocked"
                                            sign="setPetugasPemeriksa" label="" signLabel="TTD sebagai Petugas" />
                                    </div>
                                </div>
                            </section>

                        @else
                            <div
                                class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-base font-medium">Data RJ belum dimuat</p>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button x-on:click="tryClose()">
                        Tutup
                    </x-secondary-button>

                    @if ($rjNo)
                        <x-secondary-button wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                            class="gap-2">
                            <span wire:loading.remove wire:target="cetak" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </span>
                            <span wire:loading wire:target="cetak" class="flex items-center gap-1"><x-loading /> Mencetak...</span>
                        </x-secondary-button>

                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                                wire:target="save" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="save">Simpan General Consent</span>
                                <span wire:loading wire:target="save"><x-loading class="w-4 h-4" />
                                    Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    @endif
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.r-j.general-consent.cetak-general-consent-rj
        wire:key="cetak-general-consent-rj-{{ $rjNo ?? 'init' }}" />
</div>
