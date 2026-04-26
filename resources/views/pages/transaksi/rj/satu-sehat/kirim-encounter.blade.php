<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-encounter.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, EncounterTrait;

    public ?string $rjNo = null;
    public ?string $encounterId = null;
    public bool $encounterInProgress = false;
    public bool $encounterFinished = false;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('rj-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $ss = $data['satusehat'] ?? [];
        $this->encounterId = $ss['encounterId'] ?? null;
        $this->encounterInProgress = !empty($ss['encounterInProgress']);
        $this->encounterFinished = !empty($ss['encounterFinished']);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->finish($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-encounter-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRJ, $pasien, $ss] = $this->loadData($rjNo);

            // Ambil 3 IHS (patient/dokter/poli) dari DB — pola sama rujukan-kompetensi.
            // Patient UUID registration (SATUSEHAT) di-handle di master-pasien,
            // BUKAN di sini. Kalau kosong, arahkan user ke master-pasien dulu.
            $regNo = $dataRJ['regNo'] ?? '';
            $patientId = $regNo ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';

            $drId = $dataRJ['drId'] ?? '';
            $practitionerId = $drId ? (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '') : '';

            $poliId = $dataRJ['poliId'] ?? '';
            $locationId = $poliId ? (string) (DB::table('rsmst_polis')->where('poli_id', $poliId)->value('poli_uuid') ?? '') : '';

            if (empty($patientId)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Patient IHS Number kosong. Daftarkan pasien ke SATUSEHAT dulu via Master Pasien (tombol "Update patientUuid").');
                return;
            }
            if (empty($practitionerId)) {
                $this->dispatch('toast', type: 'error', message: 'Dokter IHS Number kosong.');
                return;
            }
            if (empty($locationId)) {
                $this->dispatch('toast', type: 'error', message: 'Poli IHS Number kosong.');
                return;
            }

            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');

            if (empty($ss['encounterId'])) {
                $res = $this->createNewEncounter([
                    'encounterId' => 'RJ-' . $rjNo,
                    'patientId' => $patientId,
                    'patientName' => $pasien['regName'] ?? '',
                    'practitionerId' => $practitionerId,
                    'practitionerName' => $dataRJ['drDesc'] ?? '',
                    'locationId' => $locationId,
                    'class_code' => 'AMB',
                    'startDate' => $rjDate->toIso8601String(),
                ]);
                $ss['encounterId'] = $res['id'] ?? null;
            }

            if (!empty($ss['encounterId']) && empty($ss['encounterInProgress'])) {
                $this->startRoomEncounter($ss['encounterId'], [
                    'startDate' => $rjDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $ss['encounterInProgress'] = true;
            }

            $this->saveResult($rjNo, $dataRJ, $ss);
            $this->dispatch('toast', type: 'success', message: 'Encounter berhasil dikirim: ' . ($ss['encounterId'] ?? '-'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Encounter gagal: ' . $e->getMessage());
        }
    }

    #[On('ss-encounter-rj.finish')]
    public function finish(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRJ, , $ss] = $this->loadData($rjNo);

            if (empty($ss['encounterId'])) {
                $this->dispatch('toast', type: 'error', message: 'Encounter belum dibuat.');
                return;
            }
            if (!empty($ss['encounterFinished'])) {
                $this->dispatch('toast', type: 'info', message: 'Encounter sudah finished.');
                return;
            }

            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $existing = $this->getEncounter($ss['encounterId']);
            $existing['status'] = 'finished';
            $existing['statusHistory'][] = ['status' => 'finished', 'period' => ['start' => $rjDate->toIso8601String(), 'end' => now()->toIso8601String()]];
            $existing['period']['end'] = now()->toIso8601String();
            $this->makeRequest('put', "Encounter/{$ss['encounterId']}", $existing);
            $ss['encounterFinished'] = true;

            $this->saveResult($rjNo, $dataRJ, $ss);
            $this->dispatch('toast', type: 'success', message: 'Encounter finished.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            throw new \RuntimeException('Data RJ tidak ditemukan.');
        }
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        return [$dataRJ, $pasien, $dataRJ['satusehat'] ?? []];
    }

    private function saveResult(string $rjNo, array $dataRJ, array $ss): void
    {
        DB::transaction(function () use ($rjNo, $ss) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $ss;
            $this->updateJsonRJ($rjNo, $data);
        });
    }

    private function getIHS(string $table, string $col, string $val): string
    {
        if (empty($val)) {
            return '';
        }
        $uuidCol = match ($table) {
            'rsmst_doctors' => 'dr_uuid',
            'rsmst_polis' => 'poli_uuid',
            'rsmst_pasiens' => 'patient_uuid',
            default => 'dr_uuid',
        };
        return (string) (DB::table($table)->where($col, $val)->value($uuidCol) ?? '');
    }

    private function parseDate(string $str): Carbon
    {
        if (empty($str)) {
            return Carbon::now();
        }
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $str);
        } catch (\Throwable) {
            try {
                return Carbon::parse($str);
            } catch (\Throwable) {
                return Carbon::now();
            }
        }
    }
};
?>

<div class="space-y-3">
    {{-- Step 1: Encounter --}}
    <div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($encounterId) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">1</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Encounter</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Kunjungan pasien ke RS.</div>
                @if (!empty($encounterId))
                    <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                        ID: {{ $encounterId }}
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
            class="!bg-teal-600 hover:!bg-teal-700 {{ !empty($encounterId) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">
                {{ !empty($encounterId) ? 'Terkirim' : 'Kirim' }}
            </span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    {{-- Selesaikan Encounter (visible setelah encounter ada) --}}
    @if (!empty($encounterId))
        <div class="flex items-center justify-between p-4 bg-white border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ $encounterFinished ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-100">Selesaikan Encounter</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Update status encounter menjadi finished.</div>
                </div>
            </div>
            <x-primary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled"
                class="{{ $encounterFinished ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
                <span wire:loading.remove wire:target="finishForCurrent">
                    {{ $encounterFinished ? 'Selesai' : 'Finish' }}
                </span>
                <span wire:loading wire:target="finishForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    @endif
</div>
