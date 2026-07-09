<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/general-consent-ri/rm-general-consent-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\GeneralConsentClause;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-general-consent-ri'];

    // ── Form fields top-level untuk wire:model ──
    public string $wali = '';
    public string $waliHubungan = ''; // HPK 4.2
    public string $agreement = '1';
    public string $signature = '';

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

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-general-consent-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                // Terkunci bila EMR terkunci, dinonaktifkan, ATAU sudah di-TTD petugas (final).
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled
                    || !empty($data['generalConsentPasienRI']['petugasPemeriksa'] ?? '');
            }
        }
    }

    public function rendering(): void
    {
        $default = $this->defaultConsent();
        $current = $this->dataDaftarRi['generalConsentPasienRI'] ?? [];
        $this->dataDaftarRi['generalConsentPasienRI'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        $this->dataDaftarRi['generalConsentPasienRI'] ??= $this->defaultConsent();

        $consent = $this->dataDaftarRi['generalConsentPasienRI'];
        // Default Nama Pasien/Wali = nama pasien & hubungan = Pasien Sendiri bila belum diisi (pola penundaan)
        $this->wali = $consent['wali'] ?: ($this->dataDaftarRi['regName'] ?? '');
        $this->waliHubungan = $consent['waliHubungan'] ?: 'pasien';
        $this->agreement = $consent['agreement'] ?? '1';
        $this->signature = $consent['signature'] ?? '';

        $loaded = $consent['pihakInfoMedis'] ?? [];
        $this->pihakInfoMedis = !empty($loaded) ? $loaded : [['nama' => '', 'hubungan' => '', 'noHp' => '']];

        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled
            || !empty($consent['petugasPemeriksa'] ?? '');
        $this->incrementVersion('modal-general-consent-ri');

        $this->dispatch('open-modal', name: "rm-general-consent-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-general-consent-ri-{$this->riHdrNo}");
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
            // Pihak yang Diberi Akses Info Medis: wajib minimal 1 entri ber-Nama
            'pihakInfoMedis' => [
                function ($attribute, $value, $fail) {
                    if (!collect($value)->contains(fn($p) => filled(trim($p['nama'] ?? '')))) {
                        $fail('Minimal 1 pihak yang diberi akses informasi medis wajib diisi (isi Nama).');
                    }
                },
            ],
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

    public function updated(string $name, mixed $value): void
    {
        $map = [
            'wali' => 'wali',
            'waliHubungan' => 'waliHubungan',
            'agreement' => 'agreement',
        ];
        if (isset($map[$name])) {
            $this->dataDaftarRi['generalConsentPasienRI'][$map[$name]] = $value;
        }

        if (str_starts_with($name, 'pihakInfoMedis.')) {
            $this->dataDaftarRi['generalConsentPasienRI']['pihakInfoMedis'] = $this->pihakInfoMedis;
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
        $this->dataDaftarRi['generalConsentPasienRI']['pihakInfoMedis'] = $this->pihakInfoMedis;
    }

    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->dataDaftarRi['generalConsentPasienRI']['signature'] = $dataUrl;
        $this->dataDaftarRi['generalConsentPasienRI']['signatureDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->dataDaftarRi['generalConsentPasienRI']['signature'] = '';
        $this->dataDaftarRi['generalConsentPasienRI']['signatureDate'] = '';
        $this->incrementVersion('modal-general-consent-ri');
    }

    public function setPetugasPemeriksa(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksa'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan petugas pemberi penjelasan sudah ada.');
            return;
        }

        // TTD petugas = finalize: wajib lengkap. Draft (tombol Simpan) boleh belum lengkap.
        $this->validateWithToast();

        $cleanPihak = collect($this->pihakInfoMedis)
            ->filter(fn($pihak) => !empty(trim($pihak['nama'] ?? '')) || !empty(trim($pihak['hubungan'] ?? '')) || !empty(trim($pihak['noHp'] ?? '')))
            ->values()
            ->toArray();
        $this->dataDaftarRi['generalConsentPasienRI']['pihakInfoMedis'] = $cleanPihak;
        // Sync field prop top-level (pre-fill wali/hubungan/agreement tak ter-updated bila user tak mengedit)
        $this->dataDaftarRi['generalConsentPasienRI']['wali'] = $this->wali;
        $this->dataDaftarRi['generalConsentPasienRI']['waliHubungan'] = $this->waliHubungan;
        $this->dataDaftarRi['generalConsentPasienRI']['agreement'] = $this->agreement;
        $this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksaCode'] = auth()->user()->myuser_code ?? '';
        $this->dataDaftarRi['generalConsentPasienRI']['petugasPemeriksaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                // Simpan seluruh isian form (in-memory) sekaligus stempel TTD petugas → kunci.
                $fresh['generalConsentPasienRI'] = array_replace($fresh['generalConsentPasienRI'] ?? $this->defaultConsent(), (array) ($this->dataDaftarRi['generalConsentPasienRI'] ?? []));

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'TTD Petugas + kunci General Consent — TTD pasien ' . ($fresh['generalConsentPasienRI']['signatureDate'] ?? '-'), 'MR');
            });

            $this->isFormLocked = true;
            $this->incrementVersion('modal-general-consent-ri');
            $this->dispatch('toast', type: 'success', message: 'General Consent tervalidasi, tersimpan, dan terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        // Draft: boleh simpan sebagian (nyicil). Validasi lengkap + kunci dilakukan saat TTD Petugas.

        $cleanPihak = collect($this->pihakInfoMedis)
            ->filter(fn($pihak) => !empty(trim($pihak['nama'] ?? '')) || !empty(trim($pihak['hubungan'] ?? '')) || !empty(trim($pihak['noHp'] ?? '')))
            ->values()
            ->toArray();
        $this->dataDaftarRi['generalConsentPasienRI']['pihakInfoMedis'] = $cleanPihak;
        // Sync field prop top-level (pre-fill wali/hubungan/agreement tak ter-updated bila user tak mengedit)
        $this->dataDaftarRi['generalConsentPasienRI']['wali'] = $this->wali;
        $this->dataDaftarRi['generalConsentPasienRI']['waliHubungan'] = $this->waliHubungan;
        $this->dataDaftarRi['generalConsentPasienRI']['agreement'] = $this->agreement;

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $isBaru = empty($fresh['generalConsentPasienRI']['signatureDate']);
                $fresh['generalConsentPasienRI'] = array_replace($fresh['generalConsentPasienRI'] ?? $this->defaultConsent(), (array) ($this->dataDaftarRi['generalConsentPasienRI'] ?? []));

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, ($isBaru ? 'Buat' : 'Update') . ' General Consent — TTD ' . ($fresh['generalConsentPasienRI']['signatureDate'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-general-consent-ri');
            $this->dispatch('toast', type: 'success', message: 'General Consent berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function cetak()
    {
        $consent = $this->dataDaftarRi['generalConsentPasienRI'] ?? null;
        if (!$consent || !is_array($consent) || empty($consent['signature'])) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            // Hitung umur
            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD Petugas
            $ttdPetugasPath = null;
            $petugasCode = $consent['petugasPemeriksaCode'] ?? null;
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'consent' => $consent,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.general-consent.cetak-general-consent-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak General Consent.');
            return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    private function defaultConsent(): array
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
            // Versi klausul yang berlaku saat record dibuat (stempel; utk cetak ulang sesuai redaksi saat TTD)
            'clauseVersion' => GeneralConsentClause::CURRENT,
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->signature = '';
        $this->wali = '';
        $this->waliHubungan = '';
        $this->agreement = '1';
        $this->pihakInfoMedis = [['nama' => '', 'hubungan' => '', 'noHp' => '']];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php
        $gc = $dataDaftarRi['generalConsentPasienRI'] ?? [];
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

                <p class="text-sm text-muted dark:text-gray-400">
                    Persetujuan umum pasien terhadap pelayanan rawat inap, hak & tanggung jawab, serta perlindungan data.
                </p>

                @if ($gcSigned)
                    <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3 text-muted dark:text-gray-300">
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
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
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
    <x-modal name="rm-general-consent-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-general-consent-ri', [$riHdrNo ?? 'new']) }}">

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
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Persetujuan umum pasien rawat inap — tampilan ini dapat diputar ke arah pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="info">Rawat Inap</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
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
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="gc-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    {{-- Isi Persetujuan (partial reusable) — entry pihak akses dirender via slot,
                         langsung di bawah paragraf izin akses info medis --}}
                    <x-consent.general-consent-ri mode="screen"
                        :consent="['wali' => $wali, 'waliHubungan' => $waliHubungan, 'agreement' => $agreement, 'pihakInfoMedis' => $pihakInfoMedis]"
                        :version="$dataDaftarRi['generalConsentPasienRI']['clauseVersion'] ?? null">

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
                                <div wire:key="pihak-info-ri-{{ $index }}"
                                    class="grid grid-cols-12 gap-2 items-start px-2 py-1.5">
                                    <span class="col-span-1 pt-2 text-sm text-center text-muted">
                                        {{ $index + 1 }}
                                    </span>
                                    {{-- Enter-chain ala e-resep ($refs antar field; baris baru via getElementById
                                         karena elemennya belum dirender saat Enter ditekan) --}}
                                    <x-text-input id="pihak-nama-ri-{{ $index }}"
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
                                        x-on:keydown.enter.prevent="$el.blur(); $wire.addPihakInfo().then(() => setTimeout(() => document.getElementById('pihak-nama-ri-{{ $index + 1 }}')?.focus(), 100))"
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

                        <x-input-error :messages="$errors->get('pihakInfoMedis')" class="mt-1" />

                        @if (!$isFormLocked)
                            <div class="flex justify-end">
                                <x-primary-button type="button" wire:click="addPihakInfo"
                                    class="text-sm py-1 px-2">
                                    + Tambah
                                </x-primary-button>
                            </div>
                        @endif
                    </x-consent.general-consent-ri>

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

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

                        @if (isset($dataDaftarRi['generalConsentPasienRI']))
                            @php $consent = $dataDaftarRi['generalConsentPasienRI']; @endphp

                            {{-- ══ DATA PERSETUJUAN ══ --}}
                            <section class="space-y-4">
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
                                        class="flex items-start gap-3 px-4 py-3 text-sm border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
                                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold">Pasien MENYETUJUI General Consent</p>
                                            <p class="mt-0.5">
                                                Persetujuan umum atas pelayanan rawat inap, hak &amp; tanggung jawab, serta
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
                                            <p class="py-8 text-sm italic text-center text-muted-soft">Belum
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
                                <p class="text-sm font-medium">Data RI belum dimuat</p>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    @if ($riHdrNo)
                        <x-secondary-button wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                            class="gap-2">
                            <span wire:loading.remove wire:target="cetak" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </span>
                            <span wire:loading wire:target="cetak" class="flex items-center gap-1">
                                <x-loading /> Mencetak...
                            </span>
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

        </div>
    </x-modal>
</div>
