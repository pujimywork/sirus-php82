<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-medication-request.blade.php
// Step 7 (RI): Kirim Resep Obat (MedicationRequest).
//
// Sumber = datadaftarri_json → eresepHdr[].eresep[] (obat non-racikan).
// JSON RI hanya simpan productId/productName → KFA (product_id_satusehat) DIAMBIL via
// join ke master immst_products. Item tanpa KFA di-SKIP (tak bisa dikirim ke SATUSEHAT).
// Racikan (eresepRacikan[]) BELUM ditangani di MVP ini (compound perlu contained ingredients).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\MedicationRequestTrait;

new class extends Component {
    use EmrRITrait, MedicationRequestTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $racikanSkipped = 0;

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
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->count = count($ss['medicationRequestIds'] ?? []);

        // hitung racikan (informasi ke user bahwa MVP belum kirim racikan)
        $racikan = 0;
        foreach ($data['eresepHdr'] ?? [] as $hdr) {
            $racikan += count($hdr['eresepRacikan'] ?? []);
        }
        $this->racikanSkipped = $racikan;
    }

    /**
     * Kumpulkan item obat non-racikan dari semua eresepHdr, sudah di-enrich KFA.
     * @return array<int, array{code:string, display:string}>
     */
    private function obatList(array $dataRI): array
    {
        $items = [];
        foreach ($dataRI['eresepHdr'] ?? [] as $hdr) {
            foreach ($hdr['eresep'] ?? [] as $obat) {
                $pid = trim((string) ($obat['productId'] ?? ''));
                if ($pid === '') continue;
                $items[] = ['productId' => $pid, 'productName' => (string) ($obat['productName'] ?? '')];
            }
        }
        if (empty($items)) return [];

        // Bulk lookup KFA dari master obat
        $pids = array_values(array_unique(array_column($items, 'productId')));
        $kfaMap = DB::table('immst_products')
            ->whereIn('product_id', $pids)
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $out = [];
        foreach ($items as $it) {
            $master = $kfaMap->get($it['productId']);
            $kfaCode = (string) ($master->product_id_satusehat ?? '');
            if ($kfaCode === '') continue; // tak ada KFA → skip
            $out[] = [
                'code'    => $kfaCode,
                'display' => (string) ($master->product_name_satusehat ?? '') ?: $it['productName'],
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

    #[On('ss-medication-request-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['medicationRequestIds'])) { $this->dispatch('toast', type: 'info', message: 'Resep obat sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            $authoredOn = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();
            $orgId = env('SATUSEHAT_ORGANIZATION_ID');
            $drDesc = $dataRI['drDesc'] ?? '';
            $patientName = $dataRI['regName'] ?? '';

            $obatList = $this->obatList($dataRI);
            if (empty($obatList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Tidak ada obat non-racikan ber-KFA untuk dikirim. Pastikan product_id_satusehat terisi di Master Obat.');
                return;
            }

            $ss['medicationRequestIds'] = [];
            foreach ($obatList as $idx => $obat) {
                $kfaCode = $obat['code'];
                $kfaDisplay = $obat['display'];
                $no = $idx + 1;
                $itemId = "{$riHdrNo}-{$no}";

                $res = $this->createMedicationRequest([
                    'registrationId' => $kfaCode, 'orgId' => $orgId, 'medContainedId' => "med-{$itemId}",
                    'medicationCode' => $kfaCode, 'medicationDisplay' => $kfaDisplay,
                    'medicationFormCode' => 'BS066', 'medicationFormDisplay' => 'Tablet',
                    'medicationTypeCode' => 'NC', 'medicationTypeDisplay' => 'Non-compound',
                    'prescriptionId' => $riHdrNo, 'patientId' => $patientId, 'patientName' => $patientName,
                    'encounterId' => $ss['encounterId'], 'requesterId' => $practitionerId, 'requesterName' => $drDesc,
                    'authoredOn' => $authoredOn, 'category' => 'inpatient',
                    'dosageInstruction' => [], 'dispenseRequest' => [], 'reasonReference' => [],
                ]);
                if (!empty($res['id'])) $ss['medicationRequestIds'][] = $res['id'];
            }

            $this->saveResult($riHdrNo, $ss);

            $msg = 'Resep obat berhasil dikirim (' . count($ss['medicationRequestIds']) . ' item).';
            if ($this->racikanSkipped > 0) {
                $msg .= " Catatan: {$this->racikanSkipped} obat racikan belum dikirim (belum didukung).";
            }
            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resep obat gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">7</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">MedicationRequest</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Resep obat (KFA). Non-racikan.
                @if ($racikanSkipped > 0)
                    <span class="text-amber-600 dark:text-amber-400">{{ $racikanSkipped }} racikan belum didukung.</span>
                @endif
            </div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
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
