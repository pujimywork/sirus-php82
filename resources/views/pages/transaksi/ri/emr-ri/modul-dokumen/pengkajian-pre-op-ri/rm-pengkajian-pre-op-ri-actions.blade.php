<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-pre-op-ri/rm-pengkajian-pre-op-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengkajian-pre-op-ri'];

    // ── Form entri baru (Pengkajian Pre Operasi keperawatan — RM 49) ──
    public array $newForm = [
        'diagnosaPreOp' => '',
        'rencanaOperasi' => '',
        'dokterOperator' => '',
        'tanggalOperasi' => '',
        'urgensi' => '',
        // TTV / keadaan pra bedah
        'tb' => '',
        'bb' => '',
        'nadi' => '',
        'suhu' => '',
        'rr' => '',
        'hb' => '',
        'golDarah' => '',
        // Persiapan pasien
        'preMedikasi' => '',
        'cairan' => '',
        'obat' => '',
        'puasaMulaiJam' => '',
        'premedikasiJam' => '',
        'sudahDicukur' => false,
        'persiapanDarah' => false,
        'gigiPalsuDilepas' => false,
        'pengosonganKandungKemih' => false,
        'clysma' => false,
        'riwayatPenyakit' => false,
        'riwayatPenyakitKet' => '',
        'lainLain' => '',
        // Persiapan administrasi (sertakan ke OK)
        'adaRekamMedis' => false,
        'adaSuratIjin' => false,
        'adaLab' => false,
        'adaRadiologi' => false,
        'radiologiJenis' => '',
        'adaDiagnostik' => false,
        'diagnostikJenis' => '',
        // Serah terima
        'perawatOk' => '',
        // TTD perawat ruangan (auto)
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $preOpList = [];

    public array $urgensiOptions = ['Elektif', 'Cito'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-pre-op-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->preOpList = $data['pengkajianPreOpRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi['pengkajianPreOpRI']) || !is_array($this->dataDaftarRi['pengkajianPreOpRI'])) {
            $this->dataDaftarRi['pengkajianPreOpRI'] = [];
        }
        $this->preOpList = $this->dataDaftarRi['pengkajianPreOpRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pengkajian-pre-op-ri');

        $this->dispatch('open-modal', name: "rm-pengkajian-pre-op-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pengkajian-pre-op-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.diagnosaPreOp' => 'required|string|max:500',
            'newForm.rencanaOperasi' => 'required|string|max:500',
            'newForm.dokterOperator' => 'required|string|max:200',
            'newForm.tanggalOperasi' => 'nullable|string|max:30',
            'newForm.urgensi' => 'required|string',
            'newForm.riwayatPenyakitKet' => 'nullable|string|max:300',
            'newForm.radiologiJenis' => 'nullable|string|max:200',
            'newForm.diagnostikJenis' => 'nullable|string|max:200',
            'newForm.lainLain' => 'nullable|string|max:1000',
            'newForm.perawatOk' => 'nullable|string|max:200',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.diagnosaPreOp' => 'Diagnosa pre operasi',
            'newForm.rencanaOperasi' => 'Rencana operasi',
            'newForm.dokterOperator' => 'Dokter operator',
            'newForm.tanggalOperasi' => 'Tanggal operasi',
            'newForm.urgensi' => 'Urgensi operasi',
            'newForm.perawatOk' => 'Nama perawat OK',
        ];
    }

    /* ===============================
     | TTD PERAWAT RUANGAN (auto user login)
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan sudah ada.');
            return;
        }
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan berhasil ditambahkan.');
    }

    public function clearTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | SIMPAN ENTRI BARU
     =============================== */
    public function addEntry(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan perawat ruangan belum diisi.');
            return;
        }

        $this->validateWithToast();

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $entry = $this->newForm;
        $entry['createdAt'] = $now;

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['pengkajianPreOpRI']) || !is_array($fresh['pengkajianPreOpRI'])) {
                    $fresh['pengkajianPreOpRI'] = [];
                }

                $fresh['pengkajianPreOpRI'][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->preOpList = $fresh['pengkajianPreOpRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Pengkajian Pre Operasi — ' . ($entry['rencanaOperasi'] ?? '-') . ' — ' . ($entry['createdAt'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pre operasi berhasil disimpan.');

            $this->resetNewForm();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (inline stream PDF)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->preOpList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-pre-op-ri.cetak-pengkajian-pre-op-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak pengkajian pre operasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-pre-op-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $createdAt): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($createdAt) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['pengkajianPreOpRI'])) {
                    throw new \RuntimeException('Data pengkajian tidak ditemukan.');
                }

                $fresh['pengkajianPreOpRI'] = collect($fresh['pengkajianPreOpRI'])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->preOpList = $fresh['pengkajianPreOpRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Pre Operasi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pre operasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'diagnosaPreOp' => '',
            'rencanaOperasi' => '',
            'dokterOperator' => '',
            'tanggalOperasi' => '',
            'urgensi' => '',
            'tb' => '',
            'bb' => '',
            'nadi' => '',
            'suhu' => '',
            'rr' => '',
            'hb' => '',
            'golDarah' => '',
            'preMedikasi' => '',
            'cairan' => '',
            'obat' => '',
            'puasaMulaiJam' => '',
            'premedikasiJam' => '',
            'sudahDicukur' => false,
            'persiapanDarah' => false,
            'gigiPalsuDilepas' => false,
            'pengosonganKandungKemih' => false,
            'clysma' => false,
            'riwayatPenyakit' => false,
            'riwayatPenyakitKet' => '',
            'lainLain' => '',
            'adaRekamMedis' => false,
            'adaSuratIjin' => false,
            'adaLab' => false,
            'adaRadiologi' => false,
            'radiologiJenis' => '',
            'adaDiagnostik' => false,
            'diagnostikJenis' => '',
            'perawatOk' => '',
            'ttd' => '',
            'ttdCode' => '',
            'ttdDate' => '',
        ];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $poCount = count($preOpList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Pre Operasi</h3>
                    @if ($poCount > 0)
                        <x-badge variant="success">{{ $poCount }} pengkajian</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Persiapan pasien & serah-terima ruangan → OK (RM 49): keadaan pra bedah, persiapan pasien
                    (puasa/cukur/premedikasi), kelengkapan administrasi yang disertakan ke kamar operasi.
                </p>
                @if ($poCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($preOpList, 0, 3) as $po)
                            <li>
                                <span class="font-medium">{{ \Illuminate\Support\Str::limit($po['rencanaOperasi'] ?? '-', 55) ?: '-' }}</span>
                                @if (!empty($po['createdAt']))
                                    <span class="text-sm text-muted-soft">— {{ $po['createdAt'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($poCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $poCount - 3 }} lainnya…</li>
                        @endif
                    </ul>
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
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-pengkajian-pre-op-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pengkajian-pre-op-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10">
                                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Pengkajian Pre Operasi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    RM 49 — persiapan pasien & serah-terima ruangan → kamar operasi
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($preOpList) > 0)
                                <x-badge variant="info">{{ count($preOpList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

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
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="po-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

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

                        {{-- ══ DATA OPERASI ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Diagnosa Pre Operasi *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.diagnosaPreOp" :error="$errors->has('newForm.diagnosaPreOp')" rows="2" :disabled="$isFormLocked"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.diagnosaPreOp')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Rencana Operasi *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.rencanaOperasi" :error="$errors->has('newForm.rencanaOperasi')" rows="2" :disabled="$isFormLocked"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.rencanaOperasi')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Dokter Operator *" class="mb-1" />
                                <x-text-input wire:model.live="newForm.dokterOperator" :error="$errors->has('newForm.dokterOperator')" :disabled="$isFormLocked"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.dokterOperator')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal / Jam Operasi" class="mb-1" />
                                <x-text-input wire:model.live="newForm.tanggalOperasi" :error="$errors->has('newForm.tanggalOperasi')" placeholder="dd/mm/yyyy HH:mm"
                                    :disabled="$isFormLocked" class="w-full" />
                            </div>
                            <div>
                                <x-input-label value="Urgensi Operasi *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($urgensiOptions as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="urgensiPreOp"
                                            wire:model.live="newForm.urgensi" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.urgensi')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ KEADAAN PRA BEDAH (TTV) ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Keadaan Pra Bedah</h3>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
                                <div>
                                    <x-input-label value="TB (cm)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.tb" :error="$errors->has('newForm.tb')" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="BB (kg)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.bb" :error="$errors->has('newForm.bb')" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu (°C)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="RR" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Hb" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.hb" :error="$errors->has('newForm.hb')" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Gol. Darah" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.golDarah" :error="$errors->has('newForm.golDarah')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ PERSIAPAN PASIEN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Persiapan Pasien</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Pre Medikasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.preMedikasi" :error="$errors->has('newForm.preMedikasi')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Cairan" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.cairan" :error="$errors->has('newForm.cairan')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Obat" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.obat" :error="$errors->has('newForm.obat')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Puasa Mulai Jam" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.puasaMulaiJam" :error="$errors->has('newForm.puasaMulaiJam')" placeholder="HH:mm"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Premedikasi Jam" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.premedikasiJam" :error="$errors->has('newForm.premedikasiJam')" placeholder="HH:mm"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <x-toggle wire:model.live="newForm.sudahDicukur" :trueValue="true" :falseValue="false"
                                    label="Sudah dicukur / dibersihkan daerah operasi" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.persiapanDarah" :trueValue="true" :falseValue="false"
                                    label="Persiapan darah" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.gigiPalsuDilepas" :trueValue="true" :falseValue="false"
                                    label="Gigi palsu / kontak lensa / perhiasan dilepas" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.pengosonganKandungKemih" :trueValue="true"
                                    :falseValue="false" label="Pengosongan kandung kemih" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.clysma" :trueValue="true" :falseValue="false"
                                    label="Clysma / glyserin" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.riwayatPenyakit" :trueValue="true" :falseValue="false"
                                    label="Ada riwayat penyakit" :disabled="$isFormLocked" />
                            </div>
                            @if ($newForm['riwayatPenyakit'])
                                <div>
                                    <x-input-label value="Keterangan Riwayat Penyakit" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.riwayatPenyakitKet" :error="$errors->has('newForm.riwayatPenyakitKet')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                            @endif
                            <div>
                                <x-input-label value="Lain-lain" class="mb-1" />
                                <x-textarea wire:model.live="newForm.lainLain" :error="$errors->has('newForm.lainLain')" rows="2" :disabled="$isFormLocked"
                                    class="w-full" />
                            </div>
                        </section>

                        {{-- ══ PERSIAPAN ADMINISTRASI (KE OK) ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Persiapan Administrasi
                                (sertakan bersama pasien ke OK)</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <x-toggle wire:model.live="newForm.adaRekamMedis" :trueValue="true" :falseValue="false"
                                    label="Rekam Medis" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.adaSuratIjin" :trueValue="true" :falseValue="false"
                                    label="Surat Ijin Tindakan Operasi" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.adaLab" :trueValue="true" :falseValue="false"
                                    label="Hasil Pemeriksaan Laboratorium" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.adaRadiologi" :trueValue="true" :falseValue="false"
                                    label="Hasil Pemeriksaan Radiologi" :disabled="$isFormLocked" />
                            </div>
                            @if ($newForm['adaRadiologi'])
                                <div>
                                    <x-input-label value="Jenis Radiologi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.radiologiJenis" :error="$errors->has('newForm.radiologiJenis')"
                                        placeholder="cth: Thorak Foto / CT-Scan / MRI" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                            @endif
                            <x-toggle wire:model.live="newForm.adaDiagnostik" :trueValue="true" :falseValue="false"
                                label="Hasil Pemeriksaan Diagnostik" :disabled="$isFormLocked" />
                            @if ($newForm['adaDiagnostik'])
                                <div>
                                    <x-input-label value="Jenis Diagnostik" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.diagnostikJenis" :error="$errors->has('newForm.diagnostikJenis')"
                                        placeholder="cth: USG / Colonoscopi / Gastroscopi" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                            @endif
                        </section>

                        {{-- ══ SERAH TERIMA + TTD ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="max-w-md">
                                <x-input-label value="Perjanjian dengan Perawat OK (nama/kru)" class="mb-1" />
                                <x-text-input wire:model.live="newForm.perawatOk" :error="$errors->has('newForm.perawatOk')" :disabled="$isFormLocked"
                                    class="w-full" />
                            </div>

                            <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                                :code="$newForm['ttdCode'] ?? ''" :locked="$isFormLocked" sign="setTtd" clear="clearTtd"
                                title="Tanda Tangan Perawat Ruangan" label="" signLabel="TTD sebagai Perawat Ruangan" clearLabel="Hapus TTD" />
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @if (count($preOpList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Pengkajian Tersimpan
                                </h3>
                                <table
                                    class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tanggal</th>
                                            <th class="px-4 py-2 border-b">Rencana Operasi</th>
                                            <th class="px-4 py-2 border-b">Perawat</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($preOpList as $po)
                                            <tr
                                                class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $po['createdAt'] ?? '-' }}</td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">
                                                    {{ $po['rencanaOperasi'] ? Str::limit($po['rencanaOperasi'], 45) : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $po['ttd'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $po['createdAt'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="cetak('{{ $po['createdAt'] }}')"
                                                        class="text-sm py-1 px-2">
                                                        <span wire:loading.remove
                                                            wire:target="cetak('{{ $po['createdAt'] }}')"
                                                            class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading
                                                            wire:target="cetak('{{ $po['createdAt'] }}')"
                                                            class="flex items-center gap-1"><x-loading />
                                                            Mencetak...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button"
                                                            wire:click.prevent="hapus('{{ $po['createdAt'] }}')"
                                                            wire:confirm="Yakin hapus pengkajian ini?"
                                                            wire:loading.attr="disabled"
                                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                                            title="Hapus">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-outline-button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
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

                    @if ($riHdrNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addEntry" wire:loading.attr="disabled"
                            wire:target="addEntry" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addEntry">Simpan Pengkajian</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
