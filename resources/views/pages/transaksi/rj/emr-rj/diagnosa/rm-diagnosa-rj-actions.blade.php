<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // Data untuk diagnosa terpilih dari LOV
    public ?string $diagnosaId = null;
    public ?string $procedureId = null;

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-diagnosis-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT - DIAGNOSIS
     =============================== */
    #[On('open-rm-diagnosa-rj')]
    public function openDiagnosis($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        // Ambil data kunjungan RJ
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;
        $this->diagnosaId = null;
        $this->procedureId = null;

        // Initialize diagnosis & procedure if not exists
        if (!isset($this->dataDaftarPoliRJ['diagnosis'])) {
            $this->dataDaftarPoliRJ['diagnosis'] = [];
        }

        if (!isset($this->dataDaftarPoliRJ['procedure'])) {
            $this->dataDaftarPoliRJ['procedure'] = [];
        }

        if (!isset($this->dataDaftarPoliRJ['diagnosisFreeText'])) {
            $this->dataDaftarPoliRJ['diagnosisFreeText'] = '';
        }

        if (!isset($this->dataDaftarPoliRJ['procedureFreeText'])) {
            $this->dataDaftarPoliRJ['procedureFreeText'] = '';
        }

        // 🔥 INCREMENT: Refresh seluruh modal diagnosis
        $this->incrementVersion('modal-diagnosis-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | HANDLE LOV DIAGNOSA SELECTED
     =============================== */
    #[On('lov.selected.rjFormDiagnosaRm')]
    public function rjFormDiagnosaRm(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah diagnosa.');
            return;
        }

        // Ambil data diagnosa dari payload
        $diagnosaId = $payload['diag_id'] ?? ($payload['icdx'] ?? null);
        $diagnosaDesc = $payload['diag_desc'] ?? ($payload['description'] ?? '');
        $icdx = $payload['icdx'] ?? $diagnosaId;

        if (!$diagnosaId) {
            $this->dispatch('toast', type: 'error', message: 'Data diagnosa tidak valid.');
            return;
        }

        // Insert diagnosa ke database
        $this->insertDiagnosaICD10($diagnosaId, $diagnosaDesc, $icdx);

        // Reset LOV selection
        $this->diagnosaId = null;
    }

    private function insertDiagnosaICD10(string $diagnosaId, string $diagnosaDesc, string $icdx): void
    {
        try {
            DB::transaction(function () use ($diagnosaId, $diagnosaDesc, $icdx) {
                // Get next detail number
                $lastInserted = DB::table('rstxn_rjdtls')->select(DB::raw('nvl(max(rjdtl_dtl)+1,1) as rjdtl_dtl_max'))->first();

                // Insert into transaction table
                DB::table('rstxn_rjdtls')->insert([
                    'rjdtl_dtl' => $lastInserted->rjdtl_dtl_max,
                    'rj_no' => $this->rjNo,
                    'diag_id' => $diagnosaId,
                ]);

                // Update diagnosis status in rstxn_rjhdrs
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['rj_diagnosa' => 'D']);

                // Determine diagnosis category (Primary/Secondary)
                $checkDiagnosaCount = collect($this->dataDaftarPoliRJ['diagnosis'] ?? [])->count();
                $kategoriDiagnosa = $checkDiagnosaCount ? 'Secondary' : 'Primary';

                // Add to local array
                $this->dataDaftarPoliRJ['diagnosis'][] = [
                    'diagId' => $diagnosaId,
                    'diagDesc' => $diagnosaDesc,
                    'icdX' => $icdx,
                    'ketdiagnosa' => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategoriDiagnosa,
                    'rjDtlDtl' => $lastInserted->rjdtl_dtl_max,
                    'rjNo' => $this->rjNo,
                ];

                // Save to JSON
                $this->save();
            });

            $this->afterSave('Diagnosa berhasil ditambahkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah diagnosa: ' . $e->getMessage());
        }
    }

    public function removeDiagnosaICD10($rjDtlDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus diagnosa.');
            return;
        }

        try {
            DB::transaction(function () use ($rjDtlDtl) {
                // Delete from transaction table
                DB::table('rstxn_rjdtls')->where('rjdtl_dtl', $rjDtlDtl)->delete();

                // Remove from local array
                $diagnosaCollection = collect($this->dataDaftarPoliRJ['diagnosis'] ?? [])
                    ->where('rjDtlDtl', '!=', $rjDtlDtl)
                    ->values()
                    ->toArray();

                $this->dataDaftarPoliRJ['diagnosis'] = $diagnosaCollection;

                // Save to JSON
                $this->save();
            });

            $this->afterSave('Diagnosa berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
    | HANDLE LOV PROSEDUR SELECTED
    =============================== */
    #[On('lov.selected.rjFormProsedurRm')]
    public function rjFormProsedurRm(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah prosedur.');
            return;
        }

        // Ambil data prosedur dari payload
        $procedureId = $payload['proc_id'] ?? null;
        $procedureDesc = $payload['proc_desc'] ?? '';

        if (!$procedureId) {
            $this->dispatch('toast', type: 'error', message: 'Data prosedur tidak valid.');
            return;
        }

        // Insert prosedur ke database
        $this->insertProcedureICD9($procedureId, $procedureDesc);

        // Reset LOV selection
        $this->procedureId = null;
    }

    protected function insertProcedureICD9(string $procedureId, string $procedureDesc): void
    {
        try {
            DB::transaction(function () use ($procedureId, $procedureDesc) {
                // Add to local array sesuai dengan model array yang diminta
                $this->dataDaftarPoliRJ['procedure'][] = [
                    'procedureId' => $procedureId,
                    'procedureDesc' => $procedureDesc,
                    'ketProcedure' => 'Keterangan Procedure',
                    'rjNo' => $this->rjNo,
                ];

                // Save to JSON
                $this->save();
            });

            $this->afterSave('Prosedur berhasil ditambahkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah prosedur: ' . $e->getMessage());
        }
    }

    /**
     * Hapus prosedur berdasarkan procedureId
     */
    public function removeProcedureICD9Cm(string $procedureId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus procedure.');
            return;
        }

        try {
            DB::transaction(function () use ($procedureId) {
                // Cek apakah procedure dengan ID tersebut ada
                $procedureExists = collect($this->dataDaftarPoliRJ['procedure'] ?? [])->contains('procedureId', $procedureId);

                if (!$procedureExists) {
                    throw new \Exception("Procedure dengan ID {$procedureId} tidak ditemukan.");
                }
                // Filter out procedure dengan procedureId yang sama
                $procedureCollection = collect($this->dataDaftarPoliRJ['procedure'] ?? [])
                    ->where('procedureId', '!=', $procedureId) // Seragam: where('field', '!=', $value)
                    ->values()
                    ->toArray();

                $this->dataDaftarPoliRJ['procedure'] = $procedureCollection;
                $this->save();
            });

            $this->afterSave('Procedure berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus procedure: ' . $e->getMessage());
        }
    }

    /* ===============================
     | save DATA
     =============================== */
    #[On('save-rm-diagnosa-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->updateJsonRJ($this->rjNo, $this->dataDaftarPoliRJ);
        $this->afterSave('Diagnosis berhasil disimpan.');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-diagnosis-actions');
    }

    private function afterSave(string $message): void
    {
        // 🔥 INCREMENT: Refresh seluruh modal diagnosis
        $this->incrementVersion('modal-diagnosis-rj');

        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->diagnosaId = null;
        $this->procedureId = null;
    }

    public function mount()
    {
        $this->registerAreas(['modal-diagnosis-rj']);
    }
};

?>

<div class="flex flex-col min-h-[calc(100vh-8rem)]"
    wire:key="{{ $this->renderKey('modal-diagnosis-rj', [$rjNo ?? 'new']) }}">

    {{-- BODY --}}
    <div class="w-full mx-auto">
        <div
            class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            {{-- DIAGNOSIS SECTION --}}
            <div class="w-full">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Diagnosis (ICD-10)</h3>

                {{-- LOV DIAGNOSA --}}
                <div class="mb-4">
                    <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosis" target="rjFormDiagnosaRm" :initialDiagnosaId="$diagnosaId ?? null"
                        :disabled="$isFormLocked" wire:key="lov-diagnosa-{{ $this->renderKey('modal-diagnosis-rj') }}" />
                </div>

                {{-- FREE TEXT DIAGNOSA --}}
                <div class="mb-4">
                    <x-input-label for="diagnosis_freetext" :value="__('Free Text Diagnosis')" class="mb-2" />
                    <x-textarea id="diagnosis_freetext"
                        wire:key="diagnosis-freetext-{{ $this->renderKey('modal-diagnosis-rj') }}"
                        wire:model.live="dataDaftarPoliRJ.diagnosisFreeText" :error="$errors->has('dataDaftarPoliRJ.diagnosisFreeText')" class="w-full mt-1"
                        rows="2" placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked" />
                </div>

                {{-- LIST DIAGNOSA --}}
                @if (!empty($dataDaftarPoliRJ['diagnosis']))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Diagnosis</th>
                                    <th scope="col" class="px-6 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dataDaftarPoliRJ['diagnosis'] as $index => $diagnosa)
                                    <tr wire:key="diagnosa-row-{{ $diagnosa['rjDtlDtl'] ?? $index }}-{{ $this->renderKey('modal-diagnosis-rj') }}"
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td
                                            class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <div>{{ $diagnosa['diagId'] ?? ($diagnosa['icdX'] ?? '') }}
                                                {{ $diagnosa['diagDesc'] ?? '' }}</div>
                                            <x-badge
                                                variant="{{ ($diagnosa['kategoriDiagnosa'] ?? 'Secondary') == 'Primary' ? 'success' : 'warning' }}">
                                                {{ $diagnosa['kategoriDiagnosa'] ?? 'Secondary' }}
                                            </x-badge>
                                        </td>


                                        </td>

                                        <td class="px-6 py-4">
                                            @if (!$isFormLocked)
                                                <button type="button"
                                                    wire:click="removeDiagnosaICD10({{ $diagnosa['rjDtlDtl'] }})"
                                                    wire:confirm="Yakin ingin menghapus diagnosa ini?"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div wire:key="diagnosa-empty-{{ $this->renderKey('modal-diagnosis-rj') }}"
                        class="p-4 text-sm text-center text-gray-500 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        Belum ada diagnosa
                    </div>
                @endif
            </div>

            {{-- DIVIDER --}}
            <hr class="my-6 border-gray-200 dark:border-gray-700">

            {{-- PROCEDURE SECTION --}}
            <div class="w-full">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Procedure (ICD-9-CM)</h3>

                {{-- LOV PROSEDUR --}}
                <div class="mb-4">
                    <livewire:lov.procedure.lov-procedure label="Cari Prosedur" target="rjFormProsedurRm"
                        :initialProcedureId="$procedureId ?? null" :disabled="$isFormLocked"
                        wire:key="lov-procedure-{{ $this->renderKey('modal-diagnosis-rj') }}" />
                </div>

                {{-- FREE TEXT PROCEDURE --}}
                <div class="mb-4">
                    <x-input-label for="procedure_freetext" :value="__('Free Text Procedure')" class="mb-2" />
                    <x-textarea id="procedure_freetext"
                        wire:key="procedure-freetext-{{ $this->renderKey('modal-diagnosis-rj') }}"
                        wire:model.live="dataDaftarPoliRJ.procedureFreeText" :error="$errors->has('dataDaftarPoliRJ.procedureFreeText')" class="w-full mt-1"
                        rows="2" placeholder="Masukkan procedure free text..." :disabled="$isFormLocked" />
                </div>

                {{-- LIST PROCEDURE --}}
                @if (!empty($dataDaftarPoliRJ['procedure']))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Procedure</th>
                                    <th scope="col" class="px-6 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dataDaftarPoliRJ['procedure'] as $index => $procedure)
                                    <tr wire:key="procedure-row-{{ $procedure['procedureId'] }}-{{ $this->renderKey('modal-diagnosis-rj') }}"
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td
                                            class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <div>{{ $procedure['procedureId'] ?? '' }}
                                                {{ $procedure['procedureDesc'] ?? '' }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if (!$isFormLocked)
                                                <button type="button"
                                                    wire:click="removeProcedureICD9Cm('{{ $procedure['procedureId'] }}')"
                                                    wire:confirm="Yakin ingin menghapus procedure {{ $procedure['procedureId'] }}?"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div wire:key="procedure-empty-{{ $this->renderKey('modal-diagnosis-rj') }}"
                        class="p-4 text-sm text-center text-gray-500 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        Belum ada procedure
                    </div>
                @endif
            </div>

            {{-- ACTION BUTTONS --}}
            @if (!$isFormLocked)
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                        class="text-white bg-primary hover:bg-primary-dark focus:ring-4 focus:outline-none focus:ring-primary-light font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                    <button type="button" wire:click="closeModal"
                        class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
                        Tutup
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
