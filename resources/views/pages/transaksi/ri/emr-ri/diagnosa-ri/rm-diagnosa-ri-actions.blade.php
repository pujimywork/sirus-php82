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

    private function syncDiagnosaJson(): bool
    {
        $data = $this->findDataRI($this->riHdrNo) ?? [];
        if (empty($data)) {
            throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
        }

        $isBaru = empty($data['diagnosis']) && empty($data['procedure']) && empty($data['diagnosisFreeText']) && empty($data['procedureFreeText']);

        $data['diagnosis'] = $this->dataDaftarRi['diagnosis'] ?? [];
        $data['procedure'] = $this->dataDaftarRi['procedure'] ?? [];
        $data['diagnosisFreeText'] = $this->dataDaftarRi['diagnosisFreeText'] ?? '';
        $data['procedureFreeText'] = $this->dataDaftarRi['procedureFreeText'] ?? '';

        $this->updateJsonRI((int) $this->riHdrNo, $data);
        $this->dataDaftarRi = $data;

        return $isBaru;
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
                $isBaru = $this->syncDiagnosaJson();
                $this->appendAdminLogRI((int) $this->riHdrNo, ($isBaru ? 'Buat' : 'Update') . ' Diagnosis & Prosedur — ' . count($this->dataDaftarRi['diagnosis'] ?? []) . ' diagnosa, ' . count($this->dataDaftarRi['procedure'] ?? []) . ' prosedur', 'MR');
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
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Diagnosa ICD-10 — ' . $icdx . ' ' . $diagnosaDesc, 'MR');
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
                $diagRow = $this->dataDaftarRi['diagnosis'][$idx] ?? [];

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
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Diagnosa ICD-10 — ' . ($diagRow['icdX'] ?? $diagRow['diagId'] ?? '-') . ' ' . ($diagRow['diagDesc'] ?? ''), 'MR');
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
                $current = $rows[$targetIndex]['kategoriDiagnosa'] ?? 'Secondary';
                $this->dispatch('reset-select-kategori-emr', id: (int) $riDtlDtl, value: $current);
                return;
            }
            foreach ($rows as $i => $r) {
                if ($i !== $targetIndex && ($r['kategoriDiagnosa'] ?? '') === 'Primary') {
                    $rows[$i]['kategoriDiagnosa'] = 'Secondary';
                }
            }
        }

        $rows[$targetIndex]['kategoriDiagnosa'] = $kategori;
        // Sort Primary di atas, Secondary di bawah (stable)
        usort($rows, fn($a, $b) => (($a['kategoriDiagnosa'] ?? '') === 'Primary' ? 0 : 1) - (($b['kategoriDiagnosa'] ?? '') === 'Primary' ? 0 : 1));
        $this->dataDaftarRi['diagnosis'] = array_values($rows);

        try {
            DB::transaction(function () use ($riDtlDtl, $kategori) {
                $this->lockRIRow($this->riHdrNo);
                $this->syncDiagnosaJson();
                $target = collect($this->dataDaftarRi['diagnosis'] ?? [])->firstWhere('riDtlDtl', (int) $riDtlDtl);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Ubah Kategori Diagnosa ICD-10 — ' . ($target['icdX'] ?? $target['diagId'] ?? '-') . ' menjadi ' . $kategori, 'MR');
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
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Prosedur ICD-9-CM — ' . $procedureId . ' ' . $procedureDesc, 'MR');
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

                $procRow = collect($this->dataDaftarRi['procedure'] ?? [])->firstWhere('procedureId', $procedureId) ?? [];

                $this->dataDaftarRi['procedure'] = collect($this->dataDaftarRi['procedure'] ?? [])
                    ->reject(fn($p) => ($p['procedureId'] ?? '') === $procedureId)
                    ->values()
                    ->toArray();

                $this->syncDiagnosaJson();
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Prosedur ICD-9-CM — ' . $procedureId . ' ' . ($procRow['procedureDesc'] ?? ''), 'MR');
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
        $this->dispatch('refresh-after-ri.saved', tab: 'diagnosa');
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

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-diagnosis-ri', [$riHdrNo ?? 'new']) }}"
    x-data="{
        sectionDirty: false,
        openedAt: 0,
        tab: 'diagnosa',
        markDirty() {
            if (!this.sectionDirty && Date.now() - this.openedAt > 300) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: this.tab });
            }
        },
    }"
    x-init="
        openedAt = Date.now();
        window.addEventListener('refresh-after-ri.saved', (e) => {
        // hanya bereaksi pada save tab sendiri — kalau tidak, save tab lain ikut
        // menghapus penanda dirty tab ini padahal isinya belum tersimpan
        const savedTab = e.detail?.tab;
        if (savedTab && savedTab !== 'diagnosa') return;
            sectionDirty = false;
            openedAt = Date.now();
            $dispatch('section-clean', { tab: tab });
        });
    "
    x-on:input="markDirty()"
    x-on:change="markDirty()">

    <div class="grid grid-cols-2 gap-4">

    {{-- ============================================================
    | DIAGNOSIS ICD-10
    ============================================================= --}}
    <x-border-form :title="__('Diagnosis (ICD-10)')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="mt-4 space-y-4">

            {{-- Free Text Diagnosa --}}
            <x-border-form bgcolor="bg-canvas">
                <x-input-label for="ri_diagnosis_freetext" value="Free Text Diagnosis" />
                <x-textarea id="ri_diagnosis_freetext"
                    wire:key="ri-diagnosis-freetext-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    wire:model.live="dataDaftarRi.diagnosisFreeText" :error="$errors->has('dataDaftarRi.diagnosisFreeText')"
                    placeholder="Masukkan diagnosa free text..." :disabled="$isFormLocked" rows="2" class="w-full mt-1" />
            </x-border-form>

            {{-- LOV Diagnosa --}}
            <x-border-form bgcolor="bg-canvas">
                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosis" target="riFormDiagnosaRm" :initialDiagnosaId="$diagnosaId ?? null"
                    :disabled="$isFormLocked" wire:key="lov-diagnosa-{{ $this->renderKey('modal-diagnosis-ri') }}" />
            </x-border-form>

            {{-- List Diagnosa --}}
            @if (!empty($dataDaftarRi['diagnosis']))
                <div class="overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                    <table class="w-full text-sm text-left text-muted dark:text-gray-300">
                        <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400 text-xs">
                            <tr>
                                <th class="px-3 py-2 font-medium">Diagnosis</th>
                                <th class="px-3 py-2 font-medium">Kategori</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 font-medium w-14 text-center">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                            @foreach ($dataDaftarRi['diagnosis'] as $index => $diagnosa)
                                <tr wire:key="ri-diagnosa-row-{{ $diagnosa['riDtlDtl'] ?? $index }}-{{ $this->renderKey('modal-diagnosis-ri') }}"
                                    class="bg-canvas hover:bg-surface-soft dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <td class="px-3 py-3 text-ink dark:text-white">
                                        <div class="font-mono font-semibold text-brand text-sm">{{ $diagnosa['icdX'] ?? '' }}</div>
                                        <div class="font-semibold text-base leading-snug">{{ $diagnosa['diagDesc'] ?? '' }}</div>
                                    </td>
                                    <td class="px-3 py-3">
                                        @php $curKat = $diagnosa['kategoriDiagnosa'] ?? 'Secondary'; @endphp
                                        <x-select-input x-data
                                            @reset-select-kategori-emr.window="if ($event.detail.id === {{ (int) ($diagnosa['riDtlDtl'] ?? 0) }}) $el.value = $event.detail.value"
                                            wire:change="setKategoriDiagnosa({{ (int) ($diagnosa['riDtlDtl'] ?? 0) }}, $event.target.value)"
                                            :disabled="$isFormLocked" class="w-32 text-sm">
                                            <option value="Primary" @selected($curKat === 'Primary')>Primary</option>
                                            <option value="Secondary" @selected($curKat === 'Secondary')>Secondary</option>
                                        </x-select-input>
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-3 text-center">
                                            <x-outline-button type="button"
                                                wire:click.prevent="removeDiagnosaICD10({{ $diagnosa['riDtlDtl'] }})"
                                                wire:confirm="Yakin ingin menghapus diagnosa {{ $diagnosa['icdX'] ?? '' }}?"
                                                wire:loading.attr="disabled"
                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                title="Hapus diagnosa">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-outline-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p wire:key="ri-diagnosa-empty-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    class="text-xs text-center text-muted-soft py-4">
                    Belum ada diagnosa.
                </p>
            @endif

        </div>
    </x-border-form>

    {{-- ============================================================
    | PROCEDURE ICD-9-CM
    ============================================================= --}}
    <x-border-form :title="__('Procedure (ICD-9-CM)')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="mt-4 space-y-4">

            {{-- Free Text Procedure --}}
            <x-border-form bgcolor="bg-canvas">
                <x-input-label for="ri_procedure_freetext" value="Free Text Procedure" />
                <x-textarea id="ri_procedure_freetext"
                    wire:key="ri-procedure-freetext-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    wire:model.live="dataDaftarRi.procedureFreeText" :error="$errors->has('dataDaftarRi.procedureFreeText')"
                    placeholder="Masukkan procedure free text..." :disabled="$isFormLocked" rows="2"
                    class="w-full mt-1" />
            </x-border-form>

            {{-- LOV Prosedur --}}
            <x-border-form bgcolor="bg-canvas">
                <livewire:lov.procedure.lov-procedure label="Cari Prosedur" target="riFormProsedurRm" :initialProcedureId="$procedureId ?? null"
                    :disabled="$isFormLocked" wire:key="lov-procedure-ri-{{ $this->renderKey('modal-diagnosis-ri') }}" />
            </x-border-form>

            {{-- List Procedure --}}
            @if (!empty($dataDaftarRi['procedure']))
                <div class="overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                    <table class="w-full text-sm text-left text-muted dark:text-gray-300">
                        <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400 text-xs">
                            <tr>
                                <th class="px-3 py-2 font-medium">Procedure</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2 font-medium w-14 text-center">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                            @foreach ($dataDaftarRi['procedure'] as $index => $procedure)
                                <tr wire:key="ri-procedure-row-{{ $procedure['procedureId'] }}-{{ $this->renderKey('modal-diagnosis-ri') }}"
                                    class="bg-canvas hover:bg-surface-soft dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <td class="px-3 py-3 text-ink dark:text-white">
                                        <div class="font-mono font-semibold text-brand text-sm">{{ $procedure['procedureId'] ?? '' }}</div>
                                        <div class="font-semibold text-base leading-snug">{{ $procedure['procedureDesc'] ?? '' }}</div>
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-3 text-center">
                                            <x-outline-button type="button"
                                                wire:click.prevent="removeProcedureICD9Cm('{{ $procedure['procedureId'] }}')"
                                                wire:confirm="Yakin ingin menghapus procedure {{ $procedure['procedureId'] }}?"
                                                wire:loading.attr="disabled"
                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                title="Hapus procedure">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-outline-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p wire:key="ri-procedure-empty-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    class="text-xs text-center text-muted-soft py-4">
                    Belum ada procedure.
                </p>
            @endif

        </div>
    </x-border-form>

    </div>{{-- end grid 2-col --}}

</div>
