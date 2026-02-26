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

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-diagnosis-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT - DIAGNOSIS
     =============================== */
    #[On('open-rm-diagnosis-rj')]
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
                $this->store();
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
                $this->store();
            });

            $this->afterSave('Diagnosa berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PROCEDURE METHODS (Manual Entry)
     =============================== */
    public function addProcedure(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah procedure.');
            return;
        }

        $this->validate(
            [
                'collectingMyProcedureICD9Cm.procedureId' => 'required',
                'collectingMyProcedureICD9Cm.procedureDesc' => 'required',
            ],
            [
                'collectingMyProcedureICD9Cm.procedureId.required' => 'Kode Procedure wajib diisi',
                'collectingMyProcedureICD9Cm.procedureDesc.required' => 'Deskripsi Procedure wajib diisi',
            ],
        );

        $this->dataDaftarPoliRJ['procedure'][] = [
            'procedureId' => $this->collectingMyProcedureICD9Cm['procedureId'],
            'procedureDesc' => $this->collectingMyProcedureICD9Cm['procedureDesc'],
            'ketProcedure' => $this->collectingMyProcedureICD9Cm['ketProcedure'] ?? 'Keterangan Procedure',
            'rjNo' => $this->rjNo,
        ];

        $this->reset('collectingMyProcedureICD9Cm');
        $this->store();
        $this->afterSave('Procedure berhasil ditambahkan.');
    }

    public function removeProcedureICD9Cm($index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus procedure.');
            return;
        }

        $procedureCollection = collect($this->dataDaftarPoliRJ['procedure'] ?? [])
            ->forget($index)
            ->values()
            ->toArray();

        $this->dataDaftarPoliRJ['procedure'] = $procedureCollection;
        $this->store();
        $this->afterSave('Procedure berhasil dihapus.');
    }

    public function updateddataDaftarPoliRJdiagnosisFreeText(): void
    {
        $this->store();
    }

    public function updateddataDaftarPoliRJprocedureFreeText(): void
    {
        $this->store();
    }

    /* ===============================
     | STORE DATA
     =============================== */
    public function store(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->updateJsonRJ($this->rjNo, $this->dataDaftarPoliRJ);
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

        // Emit event untuk sinkronisasi dengan komponen lain
        $this->dispatch('syncronizeAssessmentPerawatRJFindData');

        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->diagnosaId = null;
        $this->collectingMyProcedureICD9Cm = [];
    }

    public function mount()
    {
        $this->registerAreas(['modal-diagnosis-rj']);
    }
};

?>

<div>
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-diagnosis-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- DIAGNOSIS SECTION --}}
                <div class="w-full">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Diagnosa (ICD-10)</h3>

                    {{-- LOV DIAGNOSA --}}
                    <div class="mb-4" wire:ignore>
                        <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa" target="rjFormDiagnosaRm"
                            :initialDiagnosaId="$diagnosaId ?? null" :disabled="$isFormLocked"
                            wire:key="lov-diagnosa-{{ $this->getRenderVersion('modal-diagnosis-rj') }}" />
                    </div>

                    {{-- FREE TEXT DIAGNOSA --}}
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Free Text
                            Diagnosa</label>
                        <textarea wire:model.live="dataDaftarPoliRJ.diagnosisFreeText"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                            rows="2" placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked"></textarea>
                    </div>

                    {{-- LIST DIAGNOSA --}}
                    @if (!empty($dataDaftarPoliRJ['diagnosis']))
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Kode</th>
                                        <th scope="col" class="px-6 py-3">Deskripsi</th>
                                        <th scope="col" class="px-6 py-3">Kategori</th>
                                        <th scope="col" class="px-6 py-3">Keterangan</th>
                                        <th scope="col" class="px-6 py-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dataDaftarPoliRJ['diagnosis'] as $index => $diagnosa)
                                        <tr
                                            class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td
                                                class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $diagnosa['diagId'] ?? ($diagnosa['icdX'] ?? '') }}
                                            </td>
                                            <td class="px-6 py-4">{{ $diagnosa['diagDesc'] ?? '' }}</td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="px-2 py-1 text-xs font-medium rounded-full {{ ($diagnosa['kategoriDiagnosa'] ?? 'Secondary') == 'Primary' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                    {{ $diagnosa['kategoriDiagnosa'] ?? 'Secondary' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text"
                                                    wire:model.live="dataDaftarPoliRJ.diagnosis.{{ $index }}.ketdiagnosa"
                                                    class="block w-full p-1 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:border-gray-600"
                                                    placeholder="Keterangan" :disabled="$isFormLocked" />
                                            </td>
                                            <td class="px-6 py-4">
                                                @if (!$isFormLocked)
                                                    <button type="button"
                                                        wire:click="removeDiagnosaICD10({{ $diagnosa['rjDtlDtl'] ?? $index }})"
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
                        <div
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

                    {{-- FORM TAMBAH PROCEDURE --}}
                    @if (!$isFormLocked)
                        <div class="grid grid-cols-12 gap-4 mb-4">
                            <div class="col-span-3">
                                <input type="text" wire:model="collectingMyProcedureICD9Cm.procedureId"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600"
                                    placeholder="Kode Procedure" />
                            </div>
                            <div class="col-span-6">
                                <input type="text" wire:model="collectingMyProcedureICD9Cm.procedureDesc"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600"
                                    placeholder="Deskripsi Procedure" />
                            </div>
                            <div class="col-span-2">
                                <input type="text" wire:model="collectingMyProcedureICD9Cm.ketProcedure"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600"
                                    placeholder="Keterangan" />
                            </div>
                            <div class="col-span-1">
                                <button type="button" wire:click="addProcedure"
                                    class="text-white bg-primary hover:bg-primary-dark focus:ring-4 focus:outline-none focus:ring-primary-light font-medium rounded-lg text-sm p-2.5 text-center inline-flex items-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- FREE TEXT PROCEDURE --}}
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Free Text
                            Procedure</label>
                        <textarea wire:model.live="dataDaftarPoliRJ.procedureFreeText"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                            rows="2" placeholder="Masukkan procedure free text..." :disabled="$isFormLocked"></textarea>
                    </div>

                    {{-- LIST PROCEDURE --}}
                    @if (!empty($dataDaftarPoliRJ['procedure']))
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Kode</th>
                                        <th scope="col" class="px-6 py-3">Deskripsi</th>
                                        <th scope="col" class="px-6 py-3">Keterangan</th>
                                        <th scope="col" class="px-6 py-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dataDaftarPoliRJ['procedure'] as $index => $procedure)
                                        <tr
                                            class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td
                                                class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $procedure['procedureId'] ?? '' }}
                                            </td>
                                            <td class="px-6 py-4">{{ $procedure['procedureDesc'] ?? '' }}</td>
                                            <td class="px-6 py-4">
                                                <input type="text"
                                                    wire:model.live="dataDaftarPoliRJ.procedure.{{ $index }}.ketProcedure"
                                                    class="block w-full p-1 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:border-gray-600"
                                                    placeholder="Keterangan" :disabled="$isFormLocked" />
                                            </td>
                                            <td class="px-6 py-4">
                                                @if (!$isFormLocked)
                                                    <button type="button"
                                                        wire:click="removeProcedureICD9Cm({{ $index }})"
                                                        wire:confirm="Yakin ingin menghapus procedure ini?"
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
                        <div
                            class="p-4 text-sm text-center text-gray-500 rounded-lg bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            Belum ada procedure
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
