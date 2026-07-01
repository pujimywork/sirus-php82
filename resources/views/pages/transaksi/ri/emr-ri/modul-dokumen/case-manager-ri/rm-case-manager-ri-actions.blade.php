<?php
// resources/views/pages/transaksi/ri/emr-ri/case-manager/rm-case-manager-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $formA = [
        'formA_id' => '',
        'tipeForm' => 'FormA',
        'tanggal' => '',
        'indentifikasiKasus' => '',
        'assessment' => '',
        'perencanaan' => '',
        'tandaTanganPetugas' => ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'],
    ];

    public array $formB = [
        'formB_id' => '',
        'tipeForm' => 'FormB',
        'formA_id' => '',
        'tanggal' => '',
        'pelaksanaanMonitoring' => '',
        'advokasiKolaborasi' => '',
        'terminasi' => '',
        'tandaTanganPetugas' => ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'],
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-case-manager-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-case-manager-ri']);
    }

    #[On('open-rm-case-manager-ri')]
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
        $this->dataDaftarRi['formMPP'] ??= ['formA' => [], 'formB' => []];

        $this->setPetugasData();

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-case-manager-ri');
    }

    private function setPetugasData(): void
    {
        $code = auth()->user()->myuser_code ?? '';
        $name = auth()->user()->myuser_name ?? '';
        $this->formA['tandaTanganPetugas'] = ['petugasCode' => $code, 'petugasName' => $name, 'jabatan' => 'MPP'];
        $this->formB['tandaTanganPetugas'] = ['petugasCode' => $code, 'petugasName' => $name, 'jabatan' => 'MPP'];
    }

    public function setTanggalFormA(): void
    {
        $this->formA['tanggal'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    public function setTanggalFormB(): void
    {
        $this->formB['tanggal'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    public function simpanFormA(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->validateWithToast(
            [
                'formA.tanggal' => 'required|date_format:d/m/Y H:i:s',
                'formA.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
                'formA.tandaTanganPetugas.petugasName' => 'required|string|max:150',
            ],
            [
                'formA.tanggal.required' => 'Tanggal wajib diisi.',
                'formA.tandaTanganPetugas.petugasCode.required' => 'Kode petugas wajib diisi.',
                'formA.tandaTanganPetugas.petugasName.required' => 'Nama petugas wajib diisi.',
            ],
        );

        $this->formA['formA_id'] = (string) Str::uuid();
        $entry = array_merge($this->formA, ['created_at' => now()->format('Y-m-d H:i:s')]);

        try {
            DB::transaction(function () use ($entry) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['formMPP']['formA'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Form A (Skrining MPP) — entri ' . ($entry['tanggal'] ?? '-'), 'MR');
            });

            $this->resetFormA();
            $this->dispatch('close-modal', name: 'case-manager-form-a-' . ($this->riHdrNo ?? 'new'));
            $this->dispatch('cm-form-a-saved');
            $this->afterSave('Form A (Skrining MPP) berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function simpanFormB(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->validateWithToast(
            [
                'formB.formA_id' => 'required|string',
                'formB.tanggal' => 'required|date_format:d/m/Y H:i:s',
                'formB.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
                'formB.tandaTanganPetugas.petugasName' => 'required|string|max:150',
            ],
            [
                'formB.formA_id.required' => 'Referensi Form A wajib dipilih.',
                'formB.tanggal.required' => 'Tanggal wajib diisi.',
            ],
        );

        $this->formB['formB_id'] = (string) Str::uuid();
        $entry = array_merge($this->formB, ['created_at' => now()->format('Y-m-d H:i:s')]);

        try {
            DB::transaction(function () use ($entry) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['formMPP']['formB'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Form B (Pelaksanaan MPP) — entri ' . ($entry['tanggal'] ?? '-'), 'MR');
            });

            $this->resetFormB();
            $this->dispatch('close-modal', name: 'case-manager-form-b-' . ($this->riHdrNo ?? 'new'));
            $this->dispatch('cm-form-b-saved');
            $this->afterSave('Form B (Pelaksanaan MPP) berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function hapusForm(string $tipe, string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($tipe, $id) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh['formMPP'][$tipe] ?? [];
                $deletedRow = collect($list)->firstWhere($tipe . '_id', $id);
                $fresh['formMPP'][$tipe] = array_values(array_filter($list, fn($e) => ($e[$tipe . '_id'] ?? null) !== $id));
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $formLabel = $tipe === 'formA' ? 'Form A (Skrining MPP)' : 'Form B (Pelaksanaan MPP)';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus ' . $formLabel . ' — entri ' . ($deletedRow['tanggal'] ?? '-'), 'MR');
            });

            $this->afterSave("Data {$tipe} berhasil dihapus.");
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function tambahFormA(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->resetFormA();
        $this->formA['tanggal'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'case-manager-form-a-' . ($this->riHdrNo ?? 'new'));
    }

    public function tambahFormB(string $formA_id): void
    {
        $this->formB['formA_id'] = $formA_id;
        $this->formB['tanggal'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'case-manager-form-b-' . ($this->riHdrNo ?? 'new'));
    }

    public function cetakFormA(string $id)
    {
        $formA = collect($this->dataDaftarRi['formMPP']['formA'] ?? [])->firstWhere('formA_id', $id);
        if (!$formA) {
            $this->dispatch('toast', type: 'error', message: 'Data Form A tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-a-print', [
                'identitasRs' => $identitasRs,
                'dataPasien' => $dataPasien,
                'dataDaftarRi' => $this->dataDaftarRi,
                'dataFormA' => $formA,
            ])->output();

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Form A.');
            return response()->streamDownload(fn() => print $pdf, 'form-a-' . $id . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    public function cetakFormB(string $id)
    {
        $formB = collect($this->dataDaftarRi['formMPP']['formB'] ?? [])->firstWhere('formB_id', $id);
        if (!$formB) {
            $this->dispatch('toast', type: 'error', message: 'Data Form B tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-b-print', [
                'identitasRs' => $identitasRs,
                'dataPasien' => $dataPasien,
                'dataDaftarRi' => $this->dataDaftarRi,
                'dataFormB' => $formB,
            ])->output();

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Form B.');
            return response()->streamDownload(fn() => print $pdf, 'form-b-' . $id . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    private function resetFormA(): void
    {
        $c = auth()->user()->myuser_code ?? '';
        $n = auth()->user()->myuser_name ?? '';
        $this->formA = [
            'formA_id' => '',
            'tipeForm' => 'FormA',
            'tanggal' => '',
            'indentifikasiKasus' => '',
            'assessment' => '',
            'perencanaan' => '',
            'tandaTanganPetugas' => ['petugasCode' => $c, 'petugasName' => $n, 'jabatan' => 'MPP'],
        ];
    }

    private function resetFormB(): void
    {
        $c = auth()->user()->myuser_code ?? '';
        $n = auth()->user()->myuser_name ?? '';
        $this->formB = [
            'formB_id' => '',
            'tipeForm' => 'FormB',
            'formA_id' => '',
            'tanggal' => '',
            'pelaksanaanMonitoring' => '',
            'advokasiKolaborasi' => '',
            'terminasi' => '',
            'tandaTanganPetugas' => ['petugasCode' => $c, 'petugasName' => $n, 'jabatan' => 'MPP'],
        ];
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'case-manager-form-a-' . ($this->riHdrNo ?? 'new'));
        $this->dispatch('close-modal', name: 'case-manager-form-b-' . ($this->riHdrNo ?? 'new'));
    }

    #[On('cm-save-form-a')]
    public function cmSaveFormA(): void
    {
        $this->simpanFormA();
    }

    #[On('cm-save-form-b')]
    public function cmSaveFormB(): void
    {
        $this->simpanFormB();
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-case-manager-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-case-manager-ri', [$riHdrNo ?? 'new']) }}">

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

    {{-- FORM A: SKRINING AWAL MPP (modal) --}}
    @if (!$isFormLocked)
        <div class="flex justify-end">
            <x-primary-button wire:click="tambahFormA" type="button">+ Tambah Form A (Skrining MPP)</x-primary-button>
        </div>
    @endif

    <x-modal name="case-manager-form-a-{{ $riHdrNo ?? 'new' }}" size="full" height="full" focusable>
        <x-dirty-modal-content name="case-manager-form-a-{{ $riHdrNo ?? 'new' }}" event="cm-form-a-saved" label="Form A"
            wireKey="cm-form-a-{{ $riHdrNo ?? 'new' }}" :saveEvents="['cm-save-form-a']"
            wrapperClass="flex flex-col min-h-0">
        {{-- HEADER --}}
        <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Form A — Skrining Awal MPP</h2>
                <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>
        </div>
        {{-- CONTENT --}}
        <div class="flex-1 px-6 py-6 overflow-y-auto space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formA.tanggal" class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formA.tanggal')" />
                    <x-input-error :messages="$errors->get('formA.tanggal')" class="mt-1" />
                </div>
                <x-now-button wire:click="setTanggalFormA" />
            </div>
            @foreach ([['key' => 'indentifikasiKasus', 'label' => 'Identifikasi Kasus'], ['key' => 'assessment', 'label' => 'Assessment'], ['key' => 'perencanaan', 'label' => 'Perencanaan']] as $field)
                <div>
                    <x-input-label value="{{ $field['label'] }}" />
                    <x-textarea wire:model="formA.{{ $field['key'] }}" class="w-full mt-1" rows="3"
                        placeholder="{{ $field['label'] }}..." />
                </div>
            @endforeach
        </div>
        {{-- FOOTER (sticky) --}}
        <div class="sticky bottom-0 z-10 px-6 py-4 bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                <x-primary-button wire:click="simpanFormA" type="button" wire:loading.attr="disabled" wire:target="simpanFormA">
                    <span wire:loading.remove wire:target="simpanFormA">+ Simpan Form A</span>
                    <span wire:loading wire:target="simpanFormA" class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>
        </x-dirty-modal-content>
    </x-modal>

    {{-- LIST FORM A --}}
    <x-border-form title="Daftar Form MPP" align="start" bgcolor="bg-surface-soft">
        <div class="mt-3 space-y-3">
            @forelse (array_reverse($dataDaftarRi['formMPP']['formA'] ?? [], true) as $index => $entriFormA)
                <div wire:key="fa-{{ $entriFormA['formA_id'] ?? $index }}"
                    class="border border-hairline dark:border-gray-700 rounded-lg bg-canvas dark:bg-gray-800 overflow-hidden">
                    <div
                        class="flex items-center justify-between px-4 py-2.5 bg-surface-soft dark:bg-gray-700/60 border-b border-hairline-soft dark:border-gray-700">
                        <div class="text-sm space-x-2">
                            <span class="font-bold text-brand">Form A</span>
                            <span
                                class="font-semibold text-body dark:text-gray-200">{{ $entriFormA['tandaTanganPetugas']['petugasName'] ?? '-' }}</span>
                            <span class="font-mono text-muted-soft">{{ $entriFormA['tanggal'] ?? '-' }}</span>
                        </div>
                        <div class="flex gap-1.5">
                            @if (!$isFormLocked)
                                <x-info-button wire:click="tambahFormB('{{ $entriFormA['formA_id'] }}')" type="button">
                                    + Form B
                                </x-info-button>
                            @endif
                            <x-primary-button wire:click="cetakFormA('{{ $entriFormA['formA_id'] }}')" type="button"
                                wire:loading.attr="disabled"
                                wire:target="cetakFormA('{{ $entriFormA['formA_id'] }}')">
                                <span wire:loading.remove wire:target="cetakFormA('{{ $entriFormA['formA_id'] }}')"
                                    class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak
                                </span>
                                <span wire:loading wire:target="cetakFormA('{{ $entriFormA['formA_id'] }}')"
                                    class="flex items-center gap-1">
                                    <x-loading /> Mencetak...
                                </span>
                            </x-primary-button>
                            @if (!$isFormLocked)
                                <x-outline-button type="button" wire:click="hapusForm('formA','{{ $entriFormA['formA_id'] }}')"
                                    wire:confirm="Hapus Form A ini?" wire:loading.attr="disabled"
                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                    title="Hapus">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </x-outline-button>
                            @endif
                        </div>
                    </div>
                    <div class="px-4 py-3 text-sm space-y-1 text-body dark:text-gray-300">
                        @if (!empty($entriFormA['indentifikasiKasus']))
                            <p><span class="font-semibold">Identifikasi Kasus:</span> {{ $entriFormA['indentifikasiKasus'] }}
                            </p>
                        @endif
                        @if (!empty($entriFormA['assessment']))
                            <p><span class="font-semibold">Assessment:</span> {{ $entriFormA['assessment'] }}</p>
                        @endif
                        @if (!empty($entriFormA['perencanaan']))
                            <p><span class="font-semibold">Perencanaan:</span> {{ $entriFormA['perencanaan'] }}</p>
                        @endif

                        {{-- Form B milik Form A ini --}}
                        @php
                            $formBList = collect($dataDaftarRi['formMPP']['formB'] ?? [])->where(
                                'formA_id',
                                $entriFormA['formA_id'],
                            );
                        @endphp
                        @if ($formBList->count() > 0)
                            <div class="mt-2 ml-3 space-y-1.5 border-l-2 border-brand/20 pl-3">
                                <p class="text-xs font-semibold text-muted uppercase tracking-wide">Form B —
                                    Pelaksanaan MPP</p>
                                @foreach ($formBList as $entriFormB)
                                    <div class="flex items-center justify-between">
                                        <div class="space-x-2">
                                            <span class="font-mono text-muted-soft">{{ $entriFormB['tanggal'] ?? '-' }}</span>
                                            @if (!empty($entriFormB['pelaksanaanMonitoring']))
                                                <span
                                                    class="text-muted dark:text-gray-300">{{ Str::limit($entriFormB['pelaksanaanMonitoring'], 60) }}</span>
                                            @endif
                                        </div>
                                        <div class="flex gap-1">
                                            <x-primary-button wire:click="cetakFormB('{{ $entriFormB['formB_id'] }}')"
                                                type="button" wire:loading.attr="disabled"
                                                wire:target="cetakFormB('{{ $entriFormB['formB_id'] }}')">
                                                <span wire:loading.remove
                                                    wire:target="cetakFormB('{{ $entriFormB['formB_id'] }}')"
                                                    class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                    Cetak
                                                </span>
                                                <span wire:loading wire:target="cetakFormB('{{ $entriFormB['formB_id'] }}')"
                                                    class="flex items-center gap-1">
                                                    <x-loading /> Mencetak...
                                                </span>
                                            </x-primary-button>
                                            @if (!$isFormLocked)
                                                <x-outline-button type="button"
                                                    wire:click="hapusForm('formB','{{ $entriFormB['formB_id'] }}')"
                                                    wire:confirm="Hapus Form B ini?" wire:loading.attr="disabled"
                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
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
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-xs text-center text-muted-soft py-6">Belum ada data MPP.</p>
            @endforelse
        </div>
    </x-border-form>

    {{-- FORM B: PELAKSANAAN (modal) --}}
    <x-modal name="case-manager-form-b-{{ $riHdrNo ?? 'new' }}" size="full" height="full" focusable>
        <x-dirty-modal-content name="case-manager-form-b-{{ $riHdrNo ?? 'new' }}" event="cm-form-b-saved" label="Form B"
            wireKey="cm-form-b-{{ $riHdrNo ?? 'new' }}" :saveEvents="['cm-save-form-b']"
            wrapperClass="flex flex-col min-h-0">
        {{-- HEADER --}}
        <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Form B — Pelaksanaan, Monitoring, Advokasi, Terminasi</h2>
                <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>
        </div>
        {{-- CONTENT --}}
        <div class="flex-1 px-6 py-6 overflow-y-auto space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formB.tanggal" class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formB.tanggal')" />
                </div>
                <x-now-button wire:click="setTanggalFormB" />
            </div>
            <div class="bg-brand/5 rounded px-3 py-2 text-xs">
                <span class="text-muted">Referensi Form A:</span>
                <span class="ml-1 font-mono text-brand">{{ $formB['formA_id'] }}</span>
            </div>
            @foreach ([['key' => 'pelaksanaanMonitoring', 'label' => 'Pelaksanaan & Monitoring'], ['key' => 'advokasiKolaborasi', 'label' => 'Advokasi / Kolaborasi'], ['key' => 'terminasi', 'label' => 'Terminasi']] as $field)
                <div>
                    <x-input-label value="{{ $field['label'] }}" />
                    <x-textarea wire:model="formB.{{ $field['key'] }}" class="w-full mt-1" rows="3"
                        placeholder="{{ $field['label'] }}..." />
                </div>
            @endforeach
        </div>
        {{-- FOOTER (sticky) --}}
        <div class="sticky bottom-0 z-10 px-6 py-4 bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                <x-primary-button wire:click="simpanFormB" type="button" wire:loading.attr="disabled" wire:target="simpanFormB">
                    <span wire:loading.remove wire:target="simpanFormB">+ Simpan Form B</span>
                    <span wire:loading wire:target="simpanFormB" class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>
        </x-dirty-modal-content>
    </x-modal>

</div>
