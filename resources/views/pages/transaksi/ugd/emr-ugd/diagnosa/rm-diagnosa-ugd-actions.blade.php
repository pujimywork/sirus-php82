<?php

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

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

        // Init keys jika belum ada
        $this->dataDaftarUGD['diagnosis'] ??= [];
        $this->dataDaftarUGD['procedure'] ??= [];
        $this->dataDaftarUGD['diagnosisFreeText'] ??= '';
        $this->dataDaftarUGD['procedureFreeText'] ??= '';

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-diagnosis-ugd');
        $this->dispatch('open-modal', name: 'rm-diagnosis-ugd-actions');
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-diagnosis-ugd-actions');
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
                $lastInserted = DB::table('rstxn_ugddtls')->select(DB::raw('nvl(max(ugddtl_dtl)+1,1) as ugddtl_dtl_max'))->first();

                DB::table('rstxn_ugddtls')->insert([
                    'ugddtl_dtl' => $lastInserted->ugddtl_dtl_max,
                    'rj_no' => $this->rjNo,
                    'diag_id' => $diagnosaId,
                ]);

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['rj_diagnosa' => 'D']);

                $kategoriDiagnosa = collect($this->dataDaftarUGD['diagnosis'] ?? [])->count() ? 'Secondary' : 'Primary';

                $this->dataDaftarUGD['diagnosis'][] = [
                    'diagId' => $diagnosaId,
                    'diagDesc' => $diagnosaDesc,
                    'icdX' => $icdx,
                    'ketdiagnosa' => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategoriDiagnosa,
                    'ugdDtlDtl' => $lastInserted->ugddtl_dtl_max,
                    'rjNo' => $this->rjNo,
                ];

                $this->save();
            });

            $this->afterSave('Diagnosa berhasil ditambahkan.');
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
                DB::table('rstxn_ugddtls')->where('ugddtl_dtl', $ugdDtlDtl)->delete();

                $this->dataDaftarUGD['diagnosis'] = collect($this->dataDaftarUGD['diagnosis'] ?? [])
                    ->where('ugdDtlDtl', '!=', $ugdDtlDtl)
                    ->values()
                    ->toArray();

                $this->save();
            });

            $this->afterSave('Diagnosa berhasil dihapus.');
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
                $this->dataDaftarUGD['procedure'][] = [
                    'procedureId' => $procedureId,
                    'procedureDesc' => $procedureDesc,
                    'ketProcedure' => 'Keterangan Procedure',
                    'rjNo' => $this->rjNo,
                ];

                $this->save();
            });

            $this->afterSave('Prosedur berhasil ditambahkan.');
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
                $exists = collect($this->dataDaftarUGD['procedure'] ?? [])->contains('procedureId', $procedureId);
                if (!$exists) {
                    throw new \Exception("Procedure {$procedureId} tidak ditemukan.");
                }

                $this->dataDaftarUGD['procedure'] = collect($this->dataDaftarUGD['procedure'] ?? [])
                    ->where('procedureId', '!=', $procedureId)
                    ->values()
                    ->toArray();

                $this->save();
            });

            $this->afterSave('Procedure berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus procedure: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE
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
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                $data['diagnosis'] = $this->dataDaftarUGD['diagnosis'] ?? [];
                $data['procedure'] = $this->dataDaftarUGD['procedure'] ?? [];
                $data['diagnosisFreeText'] = $this->dataDaftarUGD['diagnosisFreeText'] ?? '';
                $data['procedureFreeText'] = $this->dataDaftarUGD['procedureFreeText'] ?? '';

                $this->updateJsonUGD($this->rjNo, $data);
            });
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

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
    }

    public function mount(): void
    {
        $this->registerAreas(['modal-diagnosis-ugd']);
    }
};
?>

<div>
    <x-modal name="rm-diagnosis-ugd-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Diagnosis & Procedure UGD
                        </h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            ICD-10 Diagnosis dan ICD-9-CM Procedure pasien UGD
                        </p>
                        <div class="flex gap-2 mt-3">
                            <x-badge variant="danger">UGD / IGD</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only — EMR Terkunci</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">

                @if (!empty($dataDaftarUGD))

                    {{-- Display Pasien --}}
                    <div class="mb-4">
                        <livewire:pages::transaksi.ugd.emr-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="display-pasien-ugd-diagnosa-{{ $rjNo }}" />
                    </div>

                    <div class="space-y-4" wire:key="{{ $this->renderKey('modal-diagnosis-ugd', [$rjNo ?? 'new']) }}">

                        {{-- DIAGNOSIS ICD-10 --}}
                        <x-border-form :title="__('Diagnosis (ICD-10)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                            <div class="mt-4 space-y-4">

                                {{-- LOV Diagnosa --}}
                                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosis" target="ugdFormDiagnosaRm"
                                    :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked"
                                    wire:key="lov-diagnosa-ugd-{{ $this->renderKey('modal-diagnosis-ugd') }}" />

                                {{-- Free Text Diagnosa --}}
                                <div>
                                    <x-input-label value="Free Text Diagnosis" />
                                    <x-textarea wire:model.live="dataDaftarUGD.diagnosisFreeText" :error="$errors->has('dataDaftarUGD.diagnosisFreeText')"
                                        placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked" rows="2"
                                        class="w-full mt-1" />
                                </div>

                                {{-- List Diagnosa --}}
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
                                                    <tr wire:key="diagnosa-ugd-{{ $diagnosa['ugdDtlDtl'] ?? $index }}-{{ $this->renderKey('modal-diagnosis-ugd') }}"
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

                                {{-- LOV Prosedur --}}
                                <livewire:lov.procedure.lov-procedure label="Cari Prosedur" target="ugdFormProsedurRm"
                                    :initialProcedureId="$procedureId ?? null" :disabled="$isFormLocked"
                                    wire:key="lov-procedure-ugd-{{ $this->renderKey('modal-diagnosis-ugd') }}" />

                                {{-- Free Text Procedure --}}
                                <div>
                                    <x-input-label value="Free Text Procedure" />
                                    <x-textarea wire:model.live="dataDaftarUGD.procedureFreeText" :error="$errors->has('dataDaftarUGD.procedureFreeText')"
                                        placeholder="Masukkan procedure free text..." :disabled="$isFormLocked" rows="2"
                                        class="w-full mt-1" />
                                </div>

                                {{-- List Procedure --}}
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
                                                    <tr wire:key="procedure-ugd-{{ $procedure['procedureId'] }}-{{ $this->renderKey('modal-diagnosis-ugd') }}"
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
                @else
                    <div class="flex flex-col items-center justify-center py-24 text-gray-300 dark:text-gray-600">
                        <p class="text-sm font-medium">Data UGD belum dimuat</p>
                    </div>
                @endif

            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
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
            </div>

        </div>
    </x-modal>
</div>
