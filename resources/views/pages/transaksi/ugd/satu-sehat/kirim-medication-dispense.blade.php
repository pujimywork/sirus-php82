<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-medication-dispense.blade.php
// Step 6: Kirim Obat Diserahkan (MedicationDispense). MVP dari eresep; WAJIB Resep dikirim dulu.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\MedicationDispenseTrait;

new class extends Component {
    use EmrUGDTrait, MedicationDispenseTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public bool $hasResep = false;
    public int $count = 0;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('ugd-satu-sehat.refresh')]
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
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $ss = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->hasResep     = !empty($ss['medicationRequestIds']);
        $this->count        = count($ss['medicationDispenseIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-medication-dispense-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $ss = $dataUGD['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $mrIds = $ss['medicationRequestIds'] ?? [];
            if (empty($mrIds)) { $this->dispatch('toast', type: 'error', message: 'Kirim Resep (MedicationRequest) terlebih dahulu.'); return; }
            if (!empty($ss['medicationDispenseIds'])) { $this->dispatch('toast', type: 'info', message: 'Obat diserahkan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($performerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $patientName = $dataUGD['regName'] ?? '';
            $nowIso      = Carbon::now()->toIso8601String();

            $resepList = $dataUGD['eresep'] ?? ($dataUGD['resepObat'] ?? []);
            if (empty($resepList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data resep obat.'); return; }

            $ss['medicationDispenseIds'] = [];
            $mrIdx = 0;
            foreach ($resepList as $idx => $obat) {
                $kfaCode = $obat['kfaCode'] ?? ($obat['product_id_satusehat'] ?? '');
                $kfaDisplay = $obat['kfaDisplay'] ?? ($obat['product_name_satusehat'] ?? ($obat['namaObat'] ?? ''));
                if (empty($kfaCode)) continue;

                $mrId = $mrIds[$mrIdx] ?? null;
                $mrIdx++;
                if (empty($mrId)) continue;

                $itemId = "{$rjNo}-" . ($idx + 1);
                $qty    = (int) ($obat['qty'] ?? ($obat['jumlah'] ?? ($obat['jml'] ?? 1)));

                $res = $this->createMedicationDispense([
                    'orgId' => $orgId, 'registrationId' => $kfaCode, 'prescriptionItemId' => $itemId,
                    'medContainedId' => "meddisp-{$itemId}",
                    'medicationCode' => $kfaCode, 'medicationDisplay' => $kfaDisplay,
                    'medicationFormCode' => $obat['formCode'] ?? 'BS066', 'medicationFormDisplay' => $obat['formDisplay'] ?? 'Tablet',
                    'medicationTypeCode' => ($obat['isCompound'] ?? false) ? 'SD' : 'NC',
                    'medicationTypeDisplay' => ($obat['isCompound'] ?? false) ? 'Compound' : 'Non-compound',
                    'patientId' => $patientId, 'patientName' => $patientName, 'encounterId' => $ss['encounterId'],
                    'status' => 'completed', 'category' => 'inpatient',
                    'whenPrepared' => $nowIso, 'whenHandedOver' => $nowIso,
                    'performer' => [['actor' => ['reference' => "Practitioner/{$performerId}"]]],
                    'dosageInstruction' => $obat['dosageInstruction'] ?? [],
                    'authorizingPrescription' => ['reference' => "MedicationRequest/{$mrId}"],
                    'quantity' => ['value' => $qty, 'unit' => 'unit', 'system' => 'http://terminology.kemkes.go.id/CodeSystem/kfa-satuan', 'code' => 'unit'],
                    'daysSupply' => ['value' => 1, 'unit' => 'Hari', 'system' => 'http://unitsofmeasure.org', 'code' => 'd'],
                    'receiver' => ['reference' => "Patient/{$patientId}", 'display' => $patientName],
                ]);
                if (!empty($res['id'])) $ss['medicationDispenseIds'][] = $res['id'];
            }

            if (empty($ss['medicationDispenseIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada obat ber-KFA yang bisa diserahkan.'); return; }

            $this->saveResult($rjNo, $ss);
            $count = count($ss['medicationDispenseIds']);
            $this->dispatch('toast', type: 'success', message: "Obat diserahkan berhasil dikirim ({$count} item).");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Obat diserahkan gagal: ' . $e->getMessage());
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
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['satusehat'] = $ss;
            $this->updateJsonUGD((int) $rjNo, $data);
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

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">6</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Medication Dispense</div>
            <div class="text-xs text-muted dark:text-gray-400">Obat diserahkan (butuh resep dikirim dulu).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $count }} terkirim
                </div>
            @elseif (!$hasResep)
                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">Kirim Resep dulu</div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter || !$hasResep"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
