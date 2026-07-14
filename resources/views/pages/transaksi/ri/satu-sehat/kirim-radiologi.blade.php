<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-radiologi.blade.php
// Kirim Penunjang Radiologi RI — ServiceRequest + DiagnosticReport.
// Sumber order: rstxn_riradiologs (rihdr_no) ⋈ rsmst_radiologis (rad_desc, loinc_code).
// LOINC: pakai rsmst_radiologis.loinc_code bila terisi, else generik 18748-4.
// ImagingStudy DILEWATI (modul upload-based, no DICOM).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;

new class extends Component {
    use EmrRITrait, ServiceRequestTrait, DiagnosticReportTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
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
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->count = count($ss['radDiagnosticReportIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-radiologi-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['radDiagnosticReportIds'])) { $this->dispatch('toast', type: 'info', message: 'Radiologi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $encounterId = $ss['encounterId'];
            $drDesc      = $dataRI['drDesc'] ?? '';
            $when        = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $orders = DB::table('rstxn_riradiologs as a')
                ->leftJoin('rsmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
                ->where('a.rihdr_no', $riHdrNo)
                ->select('a.rirad_no', 'a.rad_id', 'm.rad_desc', 'm.loinc_code', 'm.loinc_display')
                ->get();
            if ($orders->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi RI untuk dikirim.'); return; }

            $ss['radServiceRequestIds']   = $ss['radServiceRequestIds']   ?? [];
            $ss['radDiagnosticReportIds'] = $ss['radDiagnosticReportIds'] ?? [];

            foreach ($orders as $ord) {
                $dtl  = trim((string) ($ord->rirad_no ?? ''));
                $desc = trim((string) ($ord->rad_desc ?? 'Pemeriksaan Radiologi'));
                $key  = "{$riHdrNo}-{$dtl}";

                // LOINC spesifik bila master terisi, else generik 18748-4.
                $loincCode    = trim((string) ($ord->loinc_code ?? ''));
                $loincDisplay = trim((string) ($ord->loinc_display ?? ''));
                if ($loincCode === '') {
                    $loincCode = '18748-4';
                    $loincDisplay = $desc;
                }

                $sr = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => "ri-rad-{$key}"],
                    'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                    'category' => ['system' => 'http://snomed.info/sct', 'code' => '363679005', 'display' => 'Imaging'],
                    'code' => ['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay ?: $desc],
                    'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                    'occurrenceDateTime' => $when, 'authoredOn' => $when,
                    'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                ]);
                $srId = $sr['id'] ?? null;
                if (empty($srId)) { continue; }
                $ss['radServiceRequestIds'][] = $srId;

                $dr = $this->createDiagnosticReport([
                    'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}", 'use' => 'official', 'value' => "ri-rad-{$key}"]],
                    'status' => 'final', 'categoryCode' => 'RAD', 'categoryDisplay' => 'Radiology',
                    'codeSystem' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay ?: $desc,
                    'patientId' => $patientId, 'encounterId' => $encounterId,
                    'effectiveDate' => $when, 'issued' => $when,
                    'performer' => ["Practitioner/{$practitionerId}"], 'basedOn' => [$srId],
                ]);
                if (!empty($dr['id'])) { $ss['radDiagnosticReportIds'][] = $dr['id']; }
            }

            if (empty($ss['radServiceRequestIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi yang bisa dikirim.'); return; }

            $this->saveResult($riHdrNo, $ss);
            $srCount = count($ss['radServiceRequestIds']);
            $drCount = count($ss['radDiagnosticReportIds']);
            $this->dispatch('toast', type: 'success', message: "Radiologi terkirim: {$srCount} order, {$drCount} laporan (ImagingStudy dilewati — no DICOM).");
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Radiologi gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">10</span>
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
