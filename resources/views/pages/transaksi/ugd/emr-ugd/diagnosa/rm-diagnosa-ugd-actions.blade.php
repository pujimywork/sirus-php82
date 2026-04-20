<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public ?string $diagnosaId = null;
    public ?string $procedureId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-diagnosis-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-diagnosis-ugd']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-diagnosa-ugd')]
    public function openDiagnosis(int $rjNo): void
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

        $this->dataDaftarUGD['diagnosis'] ??= [];
        $this->dataDaftarUGD['procedure'] ??= [];
        $this->dataDaftarUGD['diagnosisFreeText'] ??= '';
        $this->dataDaftarUGD['procedureFreeText'] ??= '';

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-diagnosis-ugd');
    }

    /* ===============================
     | SAVE JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockUGDRow()-nya.
     | Patch hanya key diagnosis + procedure + freeText.
     =============================== */
    private function syncDiagnosisJson(): void
    {
        $data = $this->findDataUGD($this->rjNo);

        if (empty($data)) {
            throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
        }

        $data['diagnosis'] = $this->dataDaftarUGD['diagnosis'] ?? [];
        $data['procedure'] = $this->dataDaftarUGD['procedure'] ?? [];
        $data['diagnosisFreeText'] = $this->dataDaftarUGD['diagnosisFreeText'] ?? '';
        $data['procedureFreeText'] = $this->dataDaftarUGD['procedureFreeText'] ?? '';

        $this->updateJsonUGD($this->rjNo, $data);
        $this->dataDaftarUGD = $data;
    }

    /* ===============================
     | SAVE (event + explicit call dari footer)
     =============================== */
    #[On('save-rm-diagnosa-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (empty($this->dataDaftarUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);
                $this->syncDiagnosisJson();
            });

            $this->incrementVersion('modal-diagnosis-ugd');
            $this->dispatch('toast', type: 'success', message: 'Diagnosa berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV DIAGNOSA SELECTED
     =============================== */
    #[On('lov.selected.ugdFormDiagnosaRm')]
    public function ugdFormDiagnosaRm(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menambah diagnosa.');
            return;
        }

        $diagnosaId = $payload['diag_id'] ?? ($payload['icdx'] ?? null);
        $diagnosaDesc = $payload['diag_desc'] ?? ($payload['description'] ?? '');
        $icdx = $payload['icdx'] ?? $diagnosaId;

        if (!$diagnosaId) {
            $this->dispatch('toast', type: 'error', message: 'Data diagnosa tidak valid.');
            return;
        }

        $this->insertDiagnosaICD10($diagnosaId, $diagnosaDesc, $icdx);
        $this->diagnosaId = null;
    }

    private function insertDiagnosaICD10(string $diagnosaId, string $diagnosaDesc, string $icdx): void
    {
        try {
            DB::transaction(function () use ($diagnosaId, $diagnosaDesc, $icdx) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Insert ke tabel transaksi
                $lastInserted = DB::table('rstxn_ugddtls')->select(DB::raw('nvl(max(rjdtl_dtl)+1,1) as rjdtl_dtl_max'))->first();

                DB::table('rstxn_ugddtls')->insert([
                    'rjdtl_dtl' => $lastInserted->rjdtl_dtl_max,
                    'rj_no' => $this->rjNo,
                    'diag_id' => $diagnosaId,
                ]);

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['rj_diagnosa' => 'D']);

                // 3. Tambah ke array lokal
                $kategoriDiagnosa = collect($this->dataDaftarUGD['diagnosis'] ?? [])->count() ? 'Secondary' : 'Primary';

                $this->dataDaftarUGD['diagnosis'][] = [
                    'diagId' => $diagnosaId,
                    'diagDesc' => $diagnosaDesc,
                    'icdX' => $icdx,
                    'ketdiagnosa' => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategoriDiagnosa,
                    'ugdDtlDtl' => $lastInserted->rjdtl_dtl_max,
                    'rjNo' => $this->rjNo,
                ];

                // 4. Sync JSON — row sudah di-lock
                $this->syncDiagnosisJson();
            });

            // 5. Notify — di luar transaksi
            $this->afterSave('Diagnosa berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah diagnosa: ' . $e->getMessage());
        }
    }

    public function removeDiagnosaICD10(int $ugdDtlDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus diagnosa.');
            return;
        }

        try {
            DB::transaction(function () use ($ugdDtlDtl) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Hapus dari tabel
                DB::table('rstxn_ugddtls')->where('rjdtl_dtl', $ugdDtlDtl)->delete();

                // 3. Hapus dari array lokal
                $this->dataDaftarUGD['diagnosis'] = collect($this->dataDaftarUGD['diagnosis'] ?? [])
                    ->where('ugdDtlDtl', '!=', $ugdDtlDtl)
                    ->values()
                    ->toArray();

                // 4. Sync JSON
                $this->syncDiagnosisJson();
            });

            $this->afterSave('Diagnosa berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV PROSEDUR SELECTED
     =============================== */
    #[On('lov.selected.ugdFormProsedurRm')]
    public function ugdFormProsedurRm(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menambah prosedur.');
            return;
        }

        $procedureId = $payload['proc_id'] ?? null;
        $procedureDesc = $payload['proc_desc'] ?? '';

        if (!$procedureId) {
            $this->dispatch('toast', type: 'error', message: 'Data prosedur tidak valid.');
            return;
        }

        $this->insertProcedureICD9($procedureId, $procedureDesc);
        $this->procedureId = null;
    }

    protected function insertProcedureICD9(string $procedureId, string $procedureDesc): void
    {
        try {
            DB::transaction(function () use ($procedureId, $procedureDesc) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Tambah ke array lokal
                $this->dataDaftarUGD['procedure'][] = [
                    'procedureId' => $procedureId,
                    'procedureDesc' => $procedureDesc,
                    'ketProcedure' => 'Keterangan Procedure',
                    'rjNo' => $this->rjNo,
                ];

                // 3. Sync JSON
                $this->syncDiagnosisJson();
            });

            $this->afterSave('Prosedur berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah prosedur: ' . $e->getMessage());
        }
    }

    public function removeProcedureICD9Cm(string $procedureId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus prosedur.');
            return;
        }

        try {
            DB::transaction(function () use ($procedureId) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Cek keberadaan
                $exists = collect($this->dataDaftarUGD['procedure'] ?? [])->contains('procedureId', $procedureId);
                if (!$exists) {
                    throw new \RuntimeException("Procedure {$procedureId} tidak ditemukan.");
                }

                // 3. Hapus dari array lokal
                $this->dataDaftarUGD['procedure'] = collect($this->dataDaftarUGD['procedure'] ?? [])
                    ->where('procedureId', '!=', $procedureId)
                    ->values()
                    ->toArray();

                // 4. Sync JSON
                $this->syncDiagnosisJson();
            });

            $this->afterSave('Procedure berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus procedure: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-diagnosis-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->diagnosaId = null;
        $this->procedureId = null;
        $this->dataDaftarUGD = [];
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-diagnosis-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarUGD['diagnosis']))
                    <div class="space-y-4">

                        {{-- DIAGNOSIS ICD-10 --}}
                        <x-border-form :title="__('Diagnosis (ICD-10)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                            <div class="mt-4 space-y-4">

                                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosis" target="ugdFormDiagnosaRm"
                                    :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked"
                                    wire:key="lov-diagnosa-ugd-{{ $this->renderKey('modal-diagnosis-ugd') }}" />

                                <div>
                                    <x-input-label value="Free Text Diagnosis" />
                                    <x-textarea wire:model.live="dataDaftarUGD.diagnosisFreeText"
                                        placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked" rows="2"
                                        class="w-full mt-1" />
                                </div>

                                @if (!empty($dataDaftarUGD['diagnosis']))
                                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-3 py-2 font-medium">Diagnosis</th>
                                                    <th class="px-3 py-2 font-medium">Kategori</th>
                                                    @if (!$isFormLocked)
                                                        <th class="px-3 py-2"></th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach ($dataDaftarUGD['diagnosis'] as $index => $diagnosa)
                                                    <tr wire:key="diagnosa-ugd-{{ $diagnosa['ugdDtlDtl'] ?? $index }}"
                                                        class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700">
                                                        <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                                            {{ $diagnosa['diagId'] ?? ($diagnosa['icdX'] ?? '') }}
                                                            {{ $diagnosa['diagDesc'] ?? '' }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <x-badge :variant="($diagnosa['kategoriDiagnosa'] ?? 'Secondary') === 'Primary' ? 'success' : 'warning'">
                                                                {{ $diagnosa['kategoriDiagnosa'] ?? 'Secondary' }}
                                                            </x-badge>
                                                        </td>
                                                        @if (!$isFormLocked)
                                                            <td class="px-3 py-2">
                                                                <x-icon-button variant="danger"
                                                                    wire:click="removeDiagnosaICD10({{ $diagnosa['ugdDtlDtl'] }})"
                                                                    wire:confirm="Yakin ingin menghapus diagnosa ini?"
                                                                    tooltip="Hapus">
                                                                    <svg class="w-3.5 h-3.5" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-icon-button>
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-xs text-center text-gray-400 py-4">Belum ada diagnosa.</p>
                                @endif
                            </div>
                        </x-border-form>

                        {{-- PROCEDURE ICD-9-CM --}}
                        <x-border-form :title="__('Procedure (ICD-9-CM)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                            <div class="mt-4 space-y-4">

                                <livewire:lov.procedure.lov-procedure label="Cari Prosedur" target="ugdFormProsedurRm"
                                    :initialProcedureId="$procedureId ?? null" :disabled="$isFormLocked"
                                    wire:key="lov-procedure-ugd-{{ $this->renderKey('modal-diagnosis-ugd') }}" />

                                <div>
                                    <x-input-label value="Free Text Procedure" />
                                    <x-textarea wire:model.live="dataDaftarUGD.procedureFreeText"
                                        placeholder="Masukkan procedure free text..." :disabled="$isFormLocked" rows="2"
                                        class="w-full mt-1" />
                                </div>

                                @if (!empty($dataDaftarUGD['procedure']))
                                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-3 py-2 font-medium">Procedure</th>
                                                    @if (!$isFormLocked)
                                                        <th class="px-3 py-2"></th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach ($dataDaftarUGD['procedure'] as $index => $procedure)
                                                    <tr wire:key="procedure-ugd-{{ $procedure['procedureId'] }}"
                                                        class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700">
                                                        <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                                            {{ $procedure['procedureId'] ?? '' }}
                                                            {{ $procedure['procedureDesc'] ?? '' }}
                                                        </td>
                                                        @if (!$isFormLocked)
                                                            <td class="px-3 py-2">
                                                                <x-icon-button variant="danger"
                                                                    wire:click="removeProcedureICD9Cm('{{ $procedure['procedureId'] }}')"
                                                                    wire:confirm="Yakin ingin menghapus procedure {{ $procedure['procedureId'] }}?"
                                                                    tooltip="Hapus">
                                                                    <svg class="w-3.5 h-3.5" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-icon-button>
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-xs text-center text-gray-400 py-4">Belum ada procedure.</p>
                                @endif
                            </div>
                        </x-border-form>

                    </div>

                    {{-- FOOTER ACTIONS --}}
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="save()" wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                    </svg>
                                    Simpan
                                </span>
                                <span wire:loading><x-loading /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-24 text-gray-300 dark:text-gray-600">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="text-sm font-medium">Data UGD belum dimuat</p>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
