<?php
// resources/views/pages/transaksi/ri/emr-ri/diagnosa/rm-diagnosa-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public ?string $diagnosaId = null;
    public ?string $procedureId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-diagnosis-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-diagnosis-ri']);
    }

    #[On('open-rm-diagnosa-ri')]
    public function openDiagnosis(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $dataDaftarRi = $this->findDataRI($riHdrNo);
        if (!$dataDaftarRi) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $dataDaftarRi;
        $this->dataDaftarRi['diagnosis'] ??= [];
        $this->dataDaftarRi['procedure'] ??= [];
        $this->dataDaftarRi['diagnosisFreeText'] ??= '';
        $this->dataDaftarRi['procedureFreeText'] ??= '';

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-diagnosis-ri');
    }

    private function syncDiagnosaJson(): void
    {
        $data = $this->findDataRI($this->riHdrNo) ?? [];
        if (empty($data)) {
            throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
        }

        $data['diagnosis'] = $this->dataDaftarRi['diagnosis'] ?? [];
        $data['procedure'] = $this->dataDaftarRi['procedure'] ?? [];
        $data['diagnosisFreeText'] = $this->dataDaftarRi['diagnosisFreeText'] ?? '';
        $data['procedureFreeText'] = $this->dataDaftarRi['procedureFreeText'] ?? '';

        $this->updateJsonRI((int) $this->riHdrNo, $data);
        $this->dataDaftarRi = $data;
    }

    #[On('save-rm-diagnosa-ri')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        if (empty($this->dataDaftarRi)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosis berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    #[On('lov.selected.riFormDiagnosaRm')]
    public function riFormDiagnosaRm(string $target, array $payload): void
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
        $this->diagnosaId = null;
    }

    private function insertDiagnosaICD10(string $diagnosaId, string $diagnosaDesc, string $icdx): void
    {
        try {
            DB::transaction(function () use ($diagnosaId, $diagnosaDesc, $icdx) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $dup = DB::table('rstxn_ridtls')->where('rihdr_no', $this->riHdrNo)->where('diag_id', $diagnosaId)->exists();

                if ($dup) {
                    throw new \RuntimeException("Diagnosis {$diagnosaId} sudah tercatat untuk pasien ini.");
                }

                $nextDtl = DB::table('rstxn_ridtls')->max('ridtl_dtl');
                $nextDtl = is_null($nextDtl) ? 1 : $nextDtl + 1;

                DB::table('rstxn_ridtls')->insert([
                    'rihdr_no' => $this->riHdrNo,
                    'ridtl_dtl' => $nextDtl,
                    'diag_id' => $diagnosaId,
                ]);

                $sudahAdaPrimary = collect($this->dataDaftarRi['diagnosis'] ?? [])->contains(fn($d) => ($d['kategoriDiagnosa'] ?? '') === 'Primary');
                // Auto-Primary hanya kalau (belum ada Primary) AND (accpdx='Y'). User bisa ubah manual via dropdown.
                $accpdx = DB::table('rsmst_mstdiags')->where('diag_id', $diagnosaId)->value('accpdx');
                $kategori = (!$sudahAdaPrimary && $accpdx === 'Y') ? 'Primary' : 'Secondary';

                $this->dataDaftarRi['diagnosis'][] = [
                    'diagId' => $diagnosaId,
                    'diagDesc' => $diagnosaDesc,
                    'icdX' => $icdx,
                    'ketdiagnosa' => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategori,
                    'riDtlDtl' => $nextDtl,
                    'riHdrNo' => $this->riHdrNo,
                ];

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosis berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah diagnosa: ' . $e->getMessage());
        }
    }

    public function removeDiagnosaICD10(int $riDtlDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus diagnosa.');
            return;
        }

        try {
            DB::transaction(function () use ($riDtlDtl) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $idx = collect($this->dataDaftarRi['diagnosis'] ?? [])->search(fn($d) => (int) ($d['riDtlDtl'] ?? 0) === $riDtlDtl);

                if ($idx === false) {
                    throw new \RuntimeException('Diagnosis tidak ditemukan di data.');
                }

                $deletedWasPrimary = ($this->dataDaftarRi['diagnosis'][$idx]['kategoriDiagnosa'] ?? '') === 'Primary';

                DB::table('rstxn_ridtls')->where('rihdr_no', $this->riHdrNo)->where('ridtl_dtl', $riDtlDtl)->delete();

                array_splice($this->dataDaftarRi['diagnosis'], $idx, 1);
                $this->dataDaftarRi['diagnosis'] = array_values($this->dataDaftarRi['diagnosis']);

                if ($deletedWasPrimary && count($this->dataDaftarRi['diagnosis']) > 0) {
                    foreach ($this->dataDaftarRi['diagnosis'] as &$d) {
                        $d['kategoriDiagnosa'] = 'Secondary';
                    }
                    unset($d);
                    $this->dataDaftarRi['diagnosis'][0]['kategoriDiagnosa'] = 'Primary';
                }

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosis berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UBAH KATEGORI Primary/Secondary
     | - Validasi accpdx='Y' kalau di-promote ke Primary
     | - Single-Primary invariant: auto-demote Primary lain
     =============================== */
    public function setKategoriDiagnosa($riDtlDtl, string $kategori): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat mengubah kategori.');
            return;
        }
        $kategori = in_array($kategori, ['Primary', 'Secondary'], true) ? $kategori : 'Secondary';

        $rows = $this->dataDaftarRi['diagnosis'] ?? [];
        $targetIndex = null;
        foreach ($rows as $i => $r) {
            if ((int) ($r['riDtlDtl'] ?? 0) === (int) $riDtlDtl) {
                $targetIndex = $i;
                break;
            }
        }
        if ($targetIndex === null) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa tidak ditemukan.');
            return;
        }

        if ($kategori === 'Primary') {
            $diagId = $rows[$targetIndex]['diagId'] ?? '';
            $accpdx = DB::table('rsmst_mstdiags')->where('diag_id', $diagId)->value('accpdx');
            if ($accpdx !== 'Y') {
                $code = $rows[$targetIndex]['icdX'] ?? $diagId;
                $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak boleh sebagai diagnosa primer (accpdx='N').");
                return;
            }
            foreach ($rows as $i => $r) {
                if ($i !== $targetIndex && ($r['kategoriDiagnosa'] ?? '') === 'Primary') {
                    $rows[$i]['kategoriDiagnosa'] = 'Secondary';
                }
            }
        }

        $rows[$targetIndex]['kategoriDiagnosa'] = $kategori;
        $this->dataDaftarRi['diagnosis'] = array_values($rows);

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $this->syncDiagnosaJson();
            });
            $this->afterSave('Kategori diagnosa diperbarui.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal update kategori: ' . $e->getMessage());
        }
    }

    #[On('lov.selected.riFormProsedurRm')]
    public function riFormProsedurRm(string $target, array $payload): void
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
        $this->procedureId = null;
    }

    private function insertProcedureICD9(string $procedureId, string $procedureDesc): void
    {
        try {
            DB::transaction(function () use ($procedureId, $procedureDesc) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $dup = collect($this->dataDaftarRi['procedure'] ?? [])->contains(fn($p) => ($p['procedureId'] ?? '') === $procedureId);

                if ($dup) {
                    throw new \RuntimeException("Prosedur {$procedureId} sudah tercatat untuk pasien ini.");
                }

                $this->dataDaftarRi['procedure'][] = [
                    'procedureId' => $procedureId,
                    'procedureDesc' => $procedureDesc,
                    'ketProcedure' => 'Keterangan Procedure',
                    'riHdrNo' => $this->riHdrNo,
                ];

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Prosedur berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah prosedur: ' . $e->getMessage());
        }
    }

    public function removeProcedureICD9Cm(string $procedureId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus procedure.');
            return;
        }

        try {
            DB::transaction(function () use ($procedureId) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $exists = collect($this->dataDaftarRi['procedure'] ?? [])->contains('procedureId', $procedureId);

                if (!$exists) {
                    throw new \RuntimeException("Procedure {$procedureId} tidak ditemukan.");
                }

                $this->dataDaftarRi['procedure'] = collect($this->dataDaftarRi['procedure'] ?? [])
                    ->reject(fn($p) => ($p['procedureId'] ?? '') === $procedureId)
                    ->values()
                    ->toArray();

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Procedure berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus procedure: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-diagnosis-ri-actions');
    }

    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-diagnosis-ri');
        $this->dispatch('refresh-after-ri.saved');
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-diagnosis-ri', [$riHdrNo ?? 'new']) }}">

    {{-- ============================================================
    | DIAGNOSIS ICD-10
    ============================================================= --}}
    <x-border-form :title="__('Diagnosis (ICD-10)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- LOV Diagnosa --}}
            <x-border-form bgcolor="bg-white">
                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosis" target="riFormDiagnosaRm" :initialDiagnosaId="$diagnosaId ?? null"
                    :disabled="$isFormLocked" wire:key="lov-diagnosa-{{ $this->renderKey('modal-diagnosis-ri') }}" />
            </x-border-form>

            {{-- Free Text Diagnosa --}}
            <x-border-form bgcolor="bg-white">
                <x-input-label for="ri_diagnosis_freetext" value="Free Text Diagnosis" />
                <x-textarea id="ri_diagnosis_freetext"
                    wire:key="ri-diagnosis-freetext-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    wire:model.live="dataDaftarRi.diagnosisFreeText" :error="$errors->has('dataDaftarRi.diagnosisFreeText')"
                    placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked" rows="2" class="w-full mt-1" />
            </x-border-form>

            {{-- List Diagnosa --}}
            @if (!empty($dataDaftarRi['diagnosis']))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Diagnosis</th>
                                <th class="px-3 py-2 font-medium">Kategori</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 font-medium w-10"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($dataDaftarRi['diagnosis'] as $index => $diagnosa)
                                <tr wire:key="ri-diagnosa-row-{{ $diagnosa['riDtlDtl'] ?? $index }}-{{ $this->renderKey('modal-diagnosis-ri') }}"
                                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                        <span class="font-mono text-brand">{{ $diagnosa['icdX'] ?? '' }}</span>
                                        {{ $diagnosa['diagDesc'] ?? '' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        @php $curKat = $diagnosa['kategoriDiagnosa'] ?? 'Secondary'; @endphp
                                        <x-select-input
                                            wire:change="setKategoriDiagnosa({{ (int) ($diagnosa['riDtlDtl'] ?? 0) }}, $event.target.value)"
                                            :disabled="$isFormLocked" class="w-32">
                                            <option value="Primary" @selected($curKat === 'Primary')>Primary</option>
                                            <option value="Secondary" @selected($curKat === 'Secondary')>Secondary</option>
                                        </x-select-input>
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeDiagnosaICD10({{ $diagnosa['riDtlDtl'] }})"
                                                wire:confirm="Yakin ingin menghapus diagnosa {{ $diagnosa['icdX'] ?? '' }}?"
                                                tooltip="Hapus">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5
                                                             4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
                <p wire:key="ri-diagnosa-empty-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    class="text-xs text-center text-gray-400 py-4">
                    Belum ada diagnosa.
                </p>
            @endif

        </div>
    </x-border-form>

    {{-- ============================================================
    | PROCEDURE ICD-9-CM
    ============================================================= --}}
    <x-border-form :title="__('Procedure (ICD-9-CM)')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- LOV Prosedur --}}
            <x-border-form bgcolor="bg-white">
                <livewire:lov.procedure.lov-procedure label="Cari Prosedur" target="riFormProsedurRm" :initialProcedureId="$procedureId ?? null"
                    :disabled="$isFormLocked" wire:key="lov-procedure-ri-{{ $this->renderKey('modal-diagnosis-ri') }}" />
            </x-border-form>

            {{-- Free Text Procedure --}}
            <x-border-form bgcolor="bg-white">
                <x-input-label for="ri_procedure_freetext" value="Free Text Procedure" />
                <x-textarea id="ri_procedure_freetext"
                    wire:key="ri-procedure-freetext-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    wire:model.live="dataDaftarRi.procedureFreeText" :error="$errors->has('dataDaftarRi.procedureFreeText')"
                    placeholder="Masukkan procedure free text..." :disabled="$isFormLocked" rows="2"
                    class="w-full mt-1" />
            </x-border-form>

            {{-- List Procedure --}}
            @if (!empty($dataDaftarRi['procedure']))
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Procedure</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 font-medium w-10"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($dataDaftarRi['procedure'] as $index => $procedure)
                                <tr wire:key="ri-procedure-row-{{ $procedure['procedureId'] }}-{{ $this->renderKey('modal-diagnosis-ri') }}"
                                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                        <span class="font-mono text-brand">{{ $procedure['procedureId'] ?? '' }}</span>
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
                                                        stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5
                                                             4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
                <p wire:key="ri-procedure-empty-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    class="text-xs text-center text-gray-400 py-4">
                    Belum ada procedure.
                </p>
            @endif

        </div>
    </x-border-form>

</div>
