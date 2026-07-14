<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-medication-dispense.blade.php
// Kirim Obat Diserahkan (MedicationDispense) — RI.
//
// Sumber = eresepHdr[].eresep[] (obat non-racikan), KFA via immst_products.
// obatList() IDENTIK dgn kirim-medication-request (filter KFA + urutan sama) →
// tiap dispense sejajar 1:1 dengan medicationRequestIds & mereferensikannya.
// WAJIB: MedicationRequest dikirim lebih dulu (butuh mrIds). Racikan belum ditangani.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\MedicationDispenseTrait;

new class extends Component {
    use EmrRITrait, MedicationDispenseTrait;

    public ?string $riHdrNo = null;
    public bool $hasRequest = false;
    public int $count = 0;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $ss = $data['satusehat'] ?? [];
        $this->hasRequest = !empty($ss['encounterId']) && !empty($ss['medicationRequestIds']);
        $this->count = count($ss['medicationDispenseIds'] ?? []);
    }

    /**
     * Daftar obat non-racikan ber-KFA — HARUS identik urutannya dengan
     * kirim-medication-request agar sejajar dengan medicationRequestIds.
     * @return array<int, array{code:string, display:string, qty:int}>
     */
    private function obatList(array $dataRI): array
    {
        $items = [];
        foreach ($dataRI['eresepHdr'] ?? [] as $hdr) {
            foreach ($hdr['eresep'] ?? [] as $obat) {
                $pid = trim((string) ($obat['productId'] ?? ''));
                if ($pid === '') continue;
                $items[] = ['productId' => $pid, 'productName' => (string) ($obat['productName'] ?? ''), 'qty' => (int) ($obat['qty'] ?? 1)];
            }
        }
        if (empty($items)) return [];

        $pids = array_values(array_unique(array_column($items, 'productId')));
        $kfaMap = DB::table('immst_products')
            ->whereIn('product_id', $pids)
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $out = [];
        foreach ($items as $it) {
            $master = $kfaMap->get($it['productId']);
            $kfaCode = (string) ($master->product_id_satusehat ?? '');
            if ($kfaCode === '') continue;
            $out[] = [
                'code'    => $kfaCode,
                'display' => (string) ($master->product_name_satusehat ?? '') ?: $it['productName'],
                'qty'     => $it['qty'],
            ];
        }
        return $out;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-medication-dispense-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $mrIds = $ss['medicationRequestIds'] ?? [];
            if (empty($mrIds)) { $this->dispatch('toast', type: 'error', message: 'Kirim Resep (MedicationRequest) terlebih dahulu.'); return; }
            if (!empty($ss['medicationDispenseIds'])) { $this->dispatch('toast', type: 'info', message: 'Obat diserahkan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $performerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($performerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $patientName = $dataRI['regName'] ?? '';
            $nowIso      = Carbon::now()->toIso8601String();

            $obatList = $this->obatList($dataRI);
            if (empty($obatList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada obat ber-KFA untuk diserahkan.'); return; }

            $ss['medicationDispenseIds'] = [];
            foreach ($obatList as $idx => $obat) {
                $mrId = $mrIds[$idx] ?? null;
                if (empty($mrId)) continue; // tak sejajar dgn MedicationRequest → skip

                $no = $idx + 1;
                $itemId = "{$riHdrNo}-{$no}";

                $res = $this->createMedicationDispense([
                    'orgId' => $orgId, 'registrationId' => $obat['code'], 'prescriptionItemId' => $itemId,
                    'medContainedId' => "meddisp-{$itemId}",
                    'medicationCode' => $obat['code'], 'medicationDisplay' => $obat['display'],
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => 'NC', 'medicationTypeDisplay' => 'Non-compound',
                    'patientId' => $patientId, 'patientName' => $patientName, 'encounterId' => $ss['encounterId'],
                    'status' => 'completed', 'category' => 'inpatient',
                    'whenPrepared' => $nowIso, 'whenHandedOver' => $nowIso,
                    'performer' => [['actor' => ['reference' => "Practitioner/{$performerId}"]]],
                    'dosageInstruction' => [],
                    'authorizingPrescription' => ['reference' => "MedicationRequest/{$mrId}"],
                    'quantity' => ['value' => max(1, $obat['qty']), 'unit' => 'unit', 'system' => 'http://terminology.kemkes.go.id/CodeSystem/kfa-satuan', 'code' => 'unit'],
                    'daysSupply' => ['value' => 1, 'unit' => 'Hari', 'system' => 'http://unitsofmeasure.org', 'code' => 'd'],
                    'receiver' => ['reference' => "Patient/{$patientId}", 'display' => $patientName],
                ]);
                if (!empty($res['id'])) $ss['medicationDispenseIds'][] = $res['id'];
            }

            if (empty($ss['medicationDispenseIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada obat yang bisa diserahkan.'); return; }

            $this->saveResult($riHdrNo, $ss);
            $count = count($ss['medicationDispenseIds']);
            $this->dispatch('toast', type: 'success', message: "Obat diserahkan berhasil dikirim ({$count} item).");
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Obat diserahkan gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $riHdrNo, array $ss): void
    {
        DB::transaction(function () use ($riHdrNo, $ss) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['satusehat'] = $ss;
            $this->updateJsonRI((int) $riHdrNo, $data);
        });
    }
};
?>

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-xs font-bold">℞→</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">MedicationDispense</div>
            <div class="text-xs text-muted dark:text-gray-400">Obat diserahkan (butuh MedicationRequest dulu).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $count }} terkirim
                </div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasRequest"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
