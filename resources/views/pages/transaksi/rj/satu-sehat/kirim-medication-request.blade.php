<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-medication-request.blade.php
// Step 7: Kirim Resep Obat (MedicationRequest)

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\MedicationRequestTrait;

new class extends Component {
    use EmrRJTrait, MedicationRequestTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

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
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->count = count($ss['medicationRequestIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-medication-request-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $ss = $dataRJ['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['medicationRequestIds'])) { $this->dispatch('toast', type: 'info', message: 'Resep obat sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $orgId = env('SATUSEHAT_ORGANIZATION_ID');
            $drDesc = $dataRJ['drDesc'] ?? '';
            $patientName = $dataRJ['regName'] ?? '';

            $resepList = $dataRJ['eresep'] ?? ($dataRJ['resepObat'] ?? []);
            if (empty($resepList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data resep obat.'); return; }

            $ss['medicationRequestIds'] = [];
            foreach ($resepList as $idx => $obat) {
                $kfaCode = $obat['kfaCode'] ?? ($obat['product_id_satusehat'] ?? '');
                $kfaDisplay = $obat['kfaDisplay'] ?? ($obat['product_name_satusehat'] ?? ($obat['namaObat'] ?? ''));
                if (empty($kfaCode)) continue;

                $itemId = "{$rjNo}-" . ($idx + 1);
                $res = $this->createMedicationRequest([
                    'registrationId' => $kfaCode, 'orgId' => $orgId, 'medContainedId' => "med-{$itemId}",
                    'medicationCode' => $kfaCode, 'medicationDisplay' => $kfaDisplay,
                    'medicationFormCode' => $obat['formCode'] ?? 'BS066', 'medicationFormDisplay' => $obat['formDisplay'] ?? 'Tablet',
                    'medicationTypeCode' => ($obat['isCompound'] ?? false) ? 'SD' : 'NC',
                    'medicationTypeDisplay' => ($obat['isCompound'] ?? false) ? 'Compound' : 'Non-compound',
                    'prescriptionId' => $rjNo, 'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $ss['encounterId'], 'requesterId' => $practitionerId, 'requesterName' => $drDesc,
                    'authoredOn' => $rjDate->toIso8601String(), 'category' => 'outpatient',
                    'dosageInstruction' => $obat['dosageInstruction'] ?? [], 'dispenseRequest' => $obat['dispenseRequest'] ?? [],
                    'reasonReference' => [],
                ]);
                if (!empty($res['id'])) $ss['medicationRequestIds'][] = $res['id'];
            }

            $this->saveResult($rjNo, $ss);
            $count = count($ss['medicationRequestIds']);
            $this->dispatch('toast', type: 'success', message: "Resep obat berhasil dikirim ({$count} item).");
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resep obat gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $rjNo, array $ss): void
    {
        DB::transaction(function () use ($rjNo, $ss) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $ss;
            $this->updateJsonRJ($rjNo, $data);
        });
    }

    private function parseDate(string $str): Carbon
    {
        if (empty($str)) return Carbon::now();
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $str); } catch (\Throwable) {
            try { return Carbon::parse($str); } catch (\Throwable) { return Carbon::now(); }
        }
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">5</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Medication Request</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Resep obat.</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                    {{ $count }} terkirim
                </div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
