<?php
// resources/views/pages/transaksi/ri/emr-ri/diagnosa/rm-diagnosa-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool    $isFormLocked  = false;
    public ?string $riHdrNo       = null;
    public array   $dataDaftarRi  = [];

    public ?string $diagnosaId   = null;
    public ?string $procedureId  = null;

    /* ── renderVersions ── */
    public array  $renderVersions = [];
    protected array $renderAreas = ['modal-diagnosis-ri'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-diagnosis-ri']);
    }

    /* ===============================
     | OPEN — dipanggil dari erm-ri
     =============================== */
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

        /* Pastikan key selalu ada agar blade tidak error */
        $this->dataDaftarRi['diagnosis']          ??= [];
        $this->dataDaftarRi['procedure']          ??= [];
        $this->dataDaftarRi['diagnosisFreeText']  ??= '';
        $this->dataDaftarRi['procedureFreeText']  ??= '';

        $this->incrementVersion('modal-diagnosis-ri');

        /* Lock jika pasien sudah pulang */
        $riStatus = DB::scalar(
            "select ri_status from rstxn_rihdrs where rihdr_no = :riHdrNo",
            ['riHdrNo' => $riHdrNo]
        );
        $this->isFormLocked = ($riStatus !== 'I');
    }

    /* ===============================
     | SYNC JSON — helper internal
     | Dipanggil di dalam withDiagnosisLock(), tidak bungkus lock sendiri.
     =============================== */
    private function syncDiagnosaJson(): void
    {
        $data = $this->findDataRI($this->riHdrNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
        }

        /* Patch hanya key milik komponen ini */
        $data['diagnosis']         = $this->dataDaftarRi['diagnosis']         ?? [];
        $data['procedure']         = $this->dataDaftarRi['procedure']         ?? [];
        $data['diagnosisFreeText'] = $this->dataDaftarRi['diagnosisFreeText'] ?? '';
        $data['procedureFreeText'] = $this->dataDaftarRi['procedureFreeText'] ?? '';

        $this->updateJsonRI((int) $this->riHdrNo, $data);

        /* Sinkronkan state komponen dari fresh data */
        $this->dataDaftarRi = $data;
    }

    /* ===============================
     | SAVE — standalone (tombol simpan manual)
     =============================== */
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
            $this->withDiagnosisLock(function () {
                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosis berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, gagal memperoleh lock. Coba lagi.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HANDLE LOV DIAGNOSA
     =============================== */
    #[On('lov.selected.riFormDiagnosaRm')]
    public function riFormDiagnosaRm(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah diagnosa.');
            return;
        }

        $diagnosaId   = $payload['diag_id']   ?? ($payload['icdx']         ?? null);
        $diagnosaDesc = $payload['diag_desc']  ?? ($payload['description']  ?? '');
        $icdx         = $payload['icdx']       ?? $diagnosaId;

        if (!$diagnosaId) {
            $this->dispatch('toast', type: 'error', message: 'Data diagnosa tidak valid.');
            return;
        }

        $this->insertDiagnosaICD10($diagnosaId, $diagnosaDesc, $icdx);
        $this->diagnosaId = null;
    }

    /* ===============================
     | INSERT DIAGNOSA ICD-10
     =============================== */
    private function insertDiagnosaICD10(string $diagnosaId, string $diagnosaDesc, string $icdx): void
    {
        try {
            $this->withDiagnosisLock(function () use ($diagnosaId, $diagnosaDesc, $icdx) {

                /* Cek duplikat di tabel relasional */
                $dup = DB::table('rstxn_ridtls')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->where('diag_id', $diagnosaId)
                    ->exists();

                if ($dup) {
                    throw new \RuntimeException("Diagnosis {$diagnosaId} sudah tercatat untuk pasien ini.");
                }

                /* Nomor detail berikutnya */
                $nextDtl = DB::table('rstxn_ridtls')->max('ridtl_dtl');
                $nextDtl = is_null($nextDtl) ? 1 : $nextDtl + 1;

                /* Insert ke tabel relasional */
                DB::table('rstxn_ridtls')->insert([
                    'rihdr_no'  => $this->riHdrNo,
                    'ridtl_dtl' => $nextDtl,
                    'diag_id'   => $diagnosaId,
                ]);

                /* Primary / Secondary */
                $sudahAdaPrimary = collect($this->dataDaftarRi['diagnosis'] ?? [])
                    ->contains(fn($d) => ($d['kategoriDiagnosa'] ?? '') === 'Primary');

                $kategori = (!$sudahAdaPrimary && empty($this->dataDaftarRi['diagnosis'])) ? 'Primary' : 'Secondary';

                /* Tambah ke array lokal */
                $this->dataDaftarRi['diagnosis'][] = [
                    'diagId'           => $diagnosaId,
                    'diagDesc'         => $diagnosaDesc,
                    'icdX'             => $icdx,
                    'ketdiagnosa'      => 'Keterangan Diagnosa',
                    'kategoriDiagnosa' => $kategori,
                    'riDtlDtl'         => $nextDtl,
                    'riHdrNo'          => $this->riHdrNo,
                ];

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosa berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE DIAGNOSA ICD-10
     =============================== */
    public function removeDiagnosaICD10(int $riDtlDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus diagnosa.');
            return;
        }

        try {
            $this->withDiagnosisLock(function () use ($riDtlDtl) {

                /* Cari item di array lokal */
                $idx = collect($this->dataDaftarRi['diagnosis'] ?? [])
                    ->search(fn($d) => (int)($d['riDtlDtl'] ?? 0) === $riDtlDtl);

                if ($idx === false) {
                    throw new \RuntimeException('Diagnosa tidak ditemukan di data.');
                }

                $deletedWasPrimary = ($this->dataDaftarRi['diagnosis'][$idx]['kategoriDiagnosa'] ?? '') === 'Primary';

                /* Hapus dari tabel relasional */
                DB::table('rstxn_ridtls')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->where('ridtl_dtl', $riDtlDtl)
                    ->delete();

                /* Hapus dari array lokal */
                array_splice($this->dataDaftarRi['diagnosis'], $idx, 1);
                $this->dataDaftarRi['diagnosis'] = array_values($this->dataDaftarRi['diagnosis']);

                /* Promosikan Primary baru jika yang dihapus adalah Primary */
                if ($deletedWasPrimary && count($this->dataDaftarRi['diagnosis']) > 0) {
                    foreach ($this->dataDaftarRi['diagnosis'] as &$d) {
                        $d['kategoriDiagnosa'] = 'Secondary';
                    }
                    unset($d);
                    $this->dataDaftarRi['diagnosis'][0]['kategoriDiagnosa'] = 'Primary';
                }

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Diagnosa berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus diagnosa: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HANDLE LOV PROSEDUR
     =============================== */
    #[On('lov.selected.riFormProsedurRm')]
    public function riFormProsedurRm(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah prosedur.');
            return;
        }

        $procedureId   = $payload['proc_id']   ?? null;
        $procedureDesc = $payload['proc_desc']  ?? '';

        if (!$procedureId) {
            $this->dispatch('toast', type: 'error', message: 'Data prosedur tidak valid.');
            return;
        }

        $this->insertProcedureICD9($procedureId, $procedureDesc);
        $this->procedureId = null;
    }

    /* ===============================
     | INSERT PROSEDUR ICD-9-CM
     | Prosedur RI hanya di JSON (tidak ada tabel relasional tersendiri)
     =============================== */
    private function insertProcedureICD9(string $procedureId, string $procedureDesc): void
    {
        try {
            $this->withDiagnosisLock(function () use ($procedureId, $procedureDesc) {

                /* Cek duplikat */
                $dup = collect($this->dataDaftarRi['procedure'] ?? [])
                    ->contains(fn($p) => ($p['procedureId'] ?? '') === $procedureId);

                if ($dup) {
                    throw new \RuntimeException("Prosedur {$procedureId} sudah tercatat untuk pasien ini.");
                }

                $this->dataDaftarRi['procedure'][] = [
                    'procedureId'   => $procedureId,
                    'procedureDesc' => $procedureDesc,
                    'ketProcedure'  => 'Keterangan Procedure',
                    'riHdrNo'       => $this->riHdrNo,
                ];

                $this->syncDiagnosaJson();
            });

            $this->afterSave('Prosedur berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
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
            $this->withDiagnosisLock(function () use ($procedureId) {

                $exists = collect($this->dataDaftarRi['procedure'] ?? [])
                    ->contains('procedureId', $procedureId);

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
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
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
        $this->dispatch('close-modal', name: 'rm-diagnosis-ri-actions');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-diagnosis-ri');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->diagnosaId   = null;
        $this->procedureId  = null;
    }

    /* ── Lock helper (Cache-based, sesuai pola RI) ── */
    private function withDiagnosisLock(callable $fn): void
    {
        $key = "ri:{$this->riHdrNo}:json";
        Cache::lock($key, 10)->block(5, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo); // row-level lock Oracle
                $fn();
            }, 5);
        });
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
            <livewire:lov.diagnosa.lov-diagnosa
                label="Cari Diagnosis"
                target="riFormDiagnosaRm"
                :initialDiagnosaId="$diagnosaId ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-diagnosa-{{ $this->renderKey('modal-diagnosis-ri') }}" />

            {{-- Free Text Diagnosa --}}
            <div>
                <x-input-label for="ri_diagnosis_freetext" value="Free Text Diagnosis" />
                <x-textarea
                    id="ri_diagnosis_freetext"
                    wire:key="ri-diagnosis-freetext-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    wire:model.live="dataDaftarRi.diagnosisFreeText"
                    :error="$errors->has('dataDaftarRi.diagnosisFreeText')"
                    placeholder="Masukkan diagnosa free text..."
                    :disabled="$isFormLocked"
                    rows="2"
                    class="w-full mt-1" />
            </div>

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
                                <tr
                                    wire:key="ri-diagnosa-row-{{ $diagnosa['riDtlDtl'] ?? $index }}-{{ $this->renderKey('modal-diagnosis-ri') }}"
                                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700"
                                >
                                    <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                        <span class="font-mono text-brand">{{ $diagnosa['icdX'] ?? '' }}</span>
                                        {{ $diagnosa['diagDesc'] ?? '' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-badge variant="{{ ($diagnosa['kategoriDiagnosa'] ?? 'Secondary') === 'Primary' ? 'success' : 'warning' }}">
                                            {{ $diagnosa['kategoriDiagnosa'] ?? 'Secondary' }}
                                        </x-badge>
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button
                                                variant="danger"
                                                wire:click="removeDiagnosaICD10({{ $diagnosa['riDtlDtl'] }})"
                                                wire:confirm="Yakin ingin menghapus diagnosa {{ $diagnosa['icdX'] ?? '' }}?"
                                                tooltip="Hapus">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5
                                                             4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                <p
                    wire:key="ri-diagnosa-empty-{{ $this->renderKey('modal-diagnosis-ri') }}"
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
            <livewire:lov.procedure.lov-procedure
                label="Cari Prosedur"
                target="riFormProsedurRm"
                :initialProcedureId="$procedureId ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-procedure-ri-{{ $this->renderKey('modal-diagnosis-ri') }}" />

            {{-- Free Text Procedure --}}
            <div>
                <x-input-label for="ri_procedure_freetext" value="Free Text Procedure" />
                <x-textarea
                    id="ri_procedure_freetext"
                    wire:key="ri-procedure-freetext-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    wire:model.live="dataDaftarRi.procedureFreeText"
                    :error="$errors->has('dataDaftarRi.procedureFreeText')"
                    placeholder="Masukkan procedure free text..."
                    :disabled="$isFormLocked"
                    rows="2"
                    class="w-full mt-1" />
            </div>

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
                                <tr
                                    wire:key="ri-procedure-row-{{ $procedure['procedureId'] }}-{{ $this->renderKey('modal-diagnosis-ri') }}"
                                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700"
                                >
                                    <td class="px-3 py-2 font-medium text-gray-800 dark:text-white">
                                        <span class="font-mono text-brand">{{ $procedure['procedureId'] ?? '' }}</span>
                                        {{ $procedure['procedureDesc'] ?? '' }}
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button
                                                variant="danger"
                                                wire:click="removeProcedureICD9Cm('{{ $procedure['procedureId'] }}')"
                                                wire:confirm="Yakin ingin menghapus procedure {{ $procedure['procedureId'] }}?"
                                                tooltip="Hapus">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5
                                                             4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                <p
                    wire:key="ri-procedure-empty-{{ $this->renderKey('modal-diagnosis-ri') }}"
                    class="text-xs text-center text-gray-400 py-4">
                    Belum ada procedure.
                </p>
            @endif

        </div>
    </x-border-form>

</div>
