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
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-diagnosis-rj']);
    }

    /* ===============================
     | OPEN REKAM MEDIS - DIAGNOSIS
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

        // Initialize diagnosis & procedure jika belum ada
        $this->dataDaftarPoliRJ['diagnosis'] ??= [];
        $this->dataDaftarPoliRJ['procedure'] ??= [];
        $this->dataDaftarPoliRJ['diagnosisFreeText'] ??= '';
        $this->dataDaftarPoliRJ['procedureFreeText'] ??= '';

        // 🔥 INCREMENT: Refresh seluruh modal diagnosis
        $this->incrementVersion('modal-diagnosis-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Tidak membungkus transaction/lock sendiri untuk menghindari nested.
     =============================== */
    private function syncDiagnosaJson(): void
    {
        // Ambil data terkini dari DB (row sudah di-lock oleh caller)
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        // Set hanya key milik komponen ini — key lain tidak tersentuh
        $data['diagnosis'] = $this->dataDaftarPoliRJ['diagnosis'] ?? [];
        $data['procedure'] = $this->dataDaftarPoliRJ['procedure'] ?? [];
        $data['diagnosisFreeText'] = $this->dataDaftarPoliRJ['diagnosisFreeText'] ?? '';
        $data['procedureFreeText'] = $this->dataDaftarPoliRJ['procedureFreeText'] ?? '';

        $this->updateJsonRJ($this->rjNo, $data);
        $this->dataDaftarPoliRJ = $data;
    }

    /* ===============================
     | SAVE — standalone via #[On] event (tombol simpan manual)
     =============================== */
    #[On('save-rm-diagnosa-rj')]
    public function save(): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () {
                // 3. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 4. Sync JSON via helper
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosis berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() / syncDiagnosaJson() throws RuntimeException
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
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

        $diagnosaId = $payload['diag_id'] ?? ($payload['icdx'] ?? null);
        $diagnosaDesc = $payload['diag_desc'] ?? ($payload['description'] ?? '');
        $icdx = $payload['icdx'] ?? $diagnosaId;

        if (!$diagnosaId) {
            $this->dispatch('toast', type: 'error', message: 'Data diagnosa tidak valid.');
            return;
        }

        $this->insertDiagnosaICD10($diagnosaId, $diagnosaDesc, $icdx);

        // Reset LOV selection
        $this->diagnosaId = null;
    }

    /* ===============================
     | INSERT DIAGNOSA ICD-10
     =============================== */
    private function insertDiagnosaICD10(string $diagnosaId, string $diagnosaDesc, string $icdx): void
    {
        try {
            DB::transaction(function () use ($diagnosaId, $diagnosaDesc, $icdx) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Get next detail number
                $lastInserted = DB::table('rstxn_rjdtls')->select(DB::raw('nvl(max(rjdtl_dtl)+1,1) as rjdtl_dtl_max'))->first();

                // 3. Insert ke tabel transaksi
                DB::table('rstxn_rjdtls')->insert([
                    'rjdtl_dtl' => $lastInserted->rjdtl_dtl_max,
                    'rj_no' => $this->rjNo,
                    'diag_id' => $diagnosaId,
                ]);

                // 4. Update status diagnosa di header
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['rj_diagnosa' => 'D']);

                // 5. Tentukan kategori (Primary jika belum ada, Secondary jika sudah ada)
                $kategoriDiagnosa = collect($this->dataDaftarPoliRJ['diagnosis'] ?? [])->isEmpty() ? 'Primary' : 'Secondary';

                // 6. Tambah ke array lokal
                $this->dataDaftarPoliRJ['diagnosis'][] = [
                    'diagId' => $diagnosaId,
                    'diagDesc' => $diagnosaDesc,
                    'icdX' => $icdx,
                    'ketdiagnosa' => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategoriDiagnosa,
                    'rjDtlDtl' => $lastInserted->rjdtl_dtl_max,
                    'rjNo' => $this->rjNo,
                ];

                // 7. Sync JSON — row sudah di-lock, tidak perlu lock/transaction lagi
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosa berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE DIAGNOSA ICD-10
     =============================== */
    public function removeDiagnosaICD10($rjDtlDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus diagnosa.');
            return;
        }

        try {
            DB::transaction(function () use ($rjDtlDtl) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Hapus dari tabel transaksi
                DB::table('rstxn_rjdtls')->where('rjdtl_dtl', $rjDtlDtl)->delete();

                // 3. Hapus dari array lokal
                $this->dataDaftarPoliRJ['diagnosis'] = collect($this->dataDaftarPoliRJ['diagnosis'] ?? [])
                    ->where('rjDtlDtl', '!=', $rjDtlDtl)
                    ->values()
                    ->toArray();

                // 4. Sync JSON
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosa berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
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

        $procedureId = $payload['proc_id'] ?? null;
        $procedureDesc = $payload['proc_desc'] ?? '';

        if (!$procedureId) {
            $this->dispatch('toast', type: 'error', message: 'Data prosedur tidak valid.');
            return;
        }

        $this->insertProcedureICD9($procedureId, $procedureDesc);

        // Reset LOV selection
        $this->procedureId = null;
    }

    /* ===============================
     | INSERT PROSEDUR ICD-9-CM
     =============================== */
    protected function insertProcedureICD9(string $procedureId, string $procedureDesc): void
    {
        try {
            DB::transaction(function () use ($procedureId, $procedureDesc) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Tambah ke array lokal
                $this->dataDaftarPoliRJ['procedure'][] = [
                    'procedureId' => $procedureId,
                    'procedureDesc' => $procedureDesc,
                    'ketProcedure' => 'Keterangan Procedure',
                    'rjNo' => $this->rjNo,
                ];

                // 3. Sync JSON
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Prosedur berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah prosedur: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE PROSEDUR ICD-9-CM
     =============================== */
    public function removeProcedureICD9Cm(string $procedureId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus procedure.');
            return;
        }

        try {
            DB::transaction(function () use ($procedureId) {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Validasi keberadaan procedure
                $procedureExists = collect($this->dataDaftarPoliRJ['procedure'] ?? [])->contains('procedureId', $procedureId);

                if (!$procedureExists) {
                    throw new \RuntimeException("Procedure dengan ID {$procedureId} tidak ditemukan.");
                }

                // 3. Hapus dari array lokal
                $this->dataDaftarPoliRJ['procedure'] = collect($this->dataDaftarPoliRJ['procedure'] ?? [])
                    ->where('procedureId', '!=', $procedureId)
                    ->values()
                    ->toArray();

                // 4. Sync JSON
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Procedure berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus procedure: ' . $e->getMessage());
        }
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

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
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
};

?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-diagnosis-rj', [$rjNo ?? 'new']) }}">

    {{-- DIAGNOSIS ICD-10 --}}
    <x-border-form :title="__('Diagnosis (ICD-10)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- LOV Diagnosa --}}
            <div>
                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosis" target="rjFormDiagnosaRm" :initialDiagnosaId="$diagnosaId ?? null"
                    :disabled="$isFormLocked" wire:key="lov-diagnosa-{{ $this->renderKey('modal-diagnosis-rj') }}" />
            </div>

            {{-- Free Text Diagnosa --}}
            <div>
                <x-input-label for="diagnosis_freetext" value="Free Text Diagnosis" />
                <x-textarea id="diagnosis_freetext"
                    wire:key="diagnosis-freetext-{{ $this->renderKey('modal-diagnosis-rj') }}"
                    wire:model.live="dataDaftarPoliRJ.diagnosisFreeText" :error="$errors->has('dataDaftarPoliRJ.diagnosisFreeText')"
                    placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked" rows="2" class="w-full mt-1" />
            </div>

            {{-- List Diagnosa --}}
            @if (!empty($dataDaftarPoliRJ['diagnosis']))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Diagnosis</th>
                                <th class="px-3 py-2 font-medium">Kategori</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 font-medium"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($dataDaftarPoliRJ['diagnosis'] as $index => $diagnosa)
                                <tr wire:key="diagnosa-row-{{ $diagnosa['rjDtlDtl'] ?? $index }}-{{ $this->renderKey('modal-diagnosis-rj') }}"
                                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                        {{ $diagnosa['diagId'] ?? ($diagnosa['icdX'] ?? '') }}
                                        {{ $diagnosa['diagDesc'] ?? '' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-badge
                                            variant="{{ ($diagnosa['kategoriDiagnosa'] ?? 'Secondary') === 'Primary' ? 'success' : 'warning' }}">
                                            {{ $diagnosa['kategoriDiagnosa'] ?? 'Secondary' }}
                                        </x-badge>
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeDiagnosaICD10({{ $diagnosa['rjDtlDtl'] }})"
                                                wire:confirm="Yakin ingin menghapus diagnosa ini?" tooltip="Hapus">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
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
                <p wire:key="diagnosa-empty-{{ $this->renderKey('modal-diagnosis-rj') }}"
                    class="text-xs text-center text-gray-400 py-4">
                    Belum ada diagnosa.
                </p>
            @endif

        </div>
    </x-border-form>

    {{-- PROCEDURE ICD-9-CM --}}
    <x-border-form :title="__('Procedure (ICD-9-CM)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- LOV Prosedur --}}
            <div>
                <livewire:lov.procedure.lov-procedure label="Cari Prosedur" target="rjFormProsedurRm" :initialProcedureId="$procedureId ?? null"
                    :disabled="$isFormLocked" wire:key="lov-procedure-{{ $this->renderKey('modal-diagnosis-rj') }}" />
            </div>

            {{-- Free Text Procedure --}}
            <div>
                <x-input-label for="procedure_freetext" value="Free Text Procedure" />
                <x-textarea id="procedure_freetext"
                    wire:key="procedure-freetext-{{ $this->renderKey('modal-diagnosis-rj') }}"
                    wire:model.live="dataDaftarPoliRJ.procedureFreeText" :error="$errors->has('dataDaftarPoliRJ.procedureFreeText')"
                    placeholder="Masukkan procedure free text..." :disabled="$isFormLocked" rows="2"
                    class="w-full mt-1" />
            </div>

            {{-- List Procedure --}}
            @if (!empty($dataDaftarPoliRJ['procedure']))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Procedure</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 font-medium"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($dataDaftarPoliRJ['procedure'] as $index => $procedure)
                                <tr wire:key="procedure-row-{{ $procedure['procedureId'] }}-{{ $this->renderKey('modal-diagnosis-rj') }}"
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
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
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
                <p wire:key="procedure-empty-{{ $this->renderKey('modal-diagnosis-rj') }}"
                    class="text-xs text-center text-gray-400 py-4">
                    Belum ada procedure.
                </p>
            @endif

        </div>
    </x-border-form>

</div>
