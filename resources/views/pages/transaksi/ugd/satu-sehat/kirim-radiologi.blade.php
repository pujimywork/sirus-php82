<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-radiologi.blade.php
// Step 8: Kirim Penunjang Radiologi UGD — ServiceRequest + DiagnosticReport (kode generik).
// BEDA RJ: order dari rstxn_ugdrads. ImagingStudy DILEWATI (master tanpa kode, no DICOM).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;

new class extends Component {
    use EmrUGDTrait, ServiceRequestTrait, DiagnosticReportTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
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
        $this->count = count($ss['radDiagnosticReportIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-radiologi-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $ss = $dataUGD['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['radDiagnosticReportIds'])) { $this->dispatch('toast', type: 'info', message: 'Radiologi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $encounterId = $ss['encounterId'];
            $drDesc      = $dataUGD['drDesc'] ?? '';
            $when        = $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String();

            $orders = DB::table('rstxn_ugdrads as a')
                ->leftJoin('rsmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
                ->where('a.rj_no', $rjNo)
                ->select('a.rad_dtl', 'a.rad_id', 'm.rad_desc')
                ->get();
            if ($orders->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi UGD untuk dikirim.'); return; }

            $ss['radServiceRequestIds']   = $ss['radServiceRequestIds']   ?? [];
            $ss['radDiagnosticReportIds'] = $ss['radDiagnosticReportIds'] ?? [];

            foreach ($orders as $ord) {
                $dtl  = trim((string) ($ord->rad_dtl ?? ''));
                $desc = trim((string) ($ord->rad_desc ?? 'Pemeriksaan Radiologi'));
                $key  = "{$rjNo}-{$dtl}";

                $sr = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => "ugd-rad-{$key}"],
                    'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                    'category' => ['system' => 'http://snomed.info/sct', 'code' => '363679005', 'display' => 'Imaging'],
                    'code' => ['system' => 'http://loinc.org', 'code' => '18748-4', 'display' => $desc],
                    'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                    'occurrenceDateTime' => $when, 'authoredOn' => $when,
                    'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                ]);
                $srId = $sr['id'] ?? null;
                if (empty($srId)) { continue; }
                $ss['radServiceRequestIds'][] = $srId;

                $dr = $this->createDiagnosticReport([
                    'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}", 'use' => 'official', 'value' => "ugd-rad-{$key}"]],
                    'status' => 'final', 'categoryCode' => 'RAD', 'categoryDisplay' => 'Radiology',
                    'codeSystem' => 'http://loinc.org', 'code' => '18748-4', 'display' => $desc,
                    'patientId' => $patientId, 'encounterId' => $encounterId,
                    'effectiveDate' => $when, 'issued' => $when,
                    'performer' => ["Practitioner/{$practitionerId}"], 'basedOn' => [$srId],
                ]);
                if (!empty($dr['id'])) { $ss['radDiagnosticReportIds'][] = $dr['id']; }
            }

            if (empty($ss['radServiceRequestIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi yang bisa dikirim.'); return; }

            $this->saveResult($rjNo, $ss);
            $srCount = count($ss['radServiceRequestIds']);
            $drCount = count($ss['radDiagnosticReportIds']);
            $this->dispatch('toast', type: 'success', message: "Radiologi terkirim: {$srCount} order, {$drCount} laporan (ImagingStudy dilewati — no DICOM).");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Radiologi gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">8</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Penunjang Radiologi</div>
            <div class="text-xs text-muted dark:text-gray-400">ServiceRequest + DiagnosticReport (ImagingStudy dilewati — no DICOM).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $count }} laporan terkirim
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
