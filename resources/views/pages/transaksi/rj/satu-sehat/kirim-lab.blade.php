<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-lab.blade.php
// Step 9: Kirim Penunjang Lab — rantai ServiceRequest → Specimen → Observation(laboratory) → DiagnosticReport.
//
// Sumber (DB, bukan JSON): lbtxn_checkuphdrs(ref_no=rj_no) → lbtxn_checkupdtls(clabitem_id, lab_result)
//   → lbmst_clabitems(loinc_code, loinc_display, unit_desc). Satu paket checkup = satu SR + Specimen + DR;
//   tiap item ber-loinc_code + hasil = satu Observation. Item TANPA loinc_code di-skip (butuh diisi di Master Lab).
//
// ASUMSI MVP (perlu validasi sandbox): code panel SR/DR = LOINC generik 26436-6 (Laboratory studies);
//   Specimen = darah (SNOMED 119297000) metode venipuncture; nilai numerik → valueQuantity, selain itu valueString.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Http\Traits\SATUSEHAT\SpecimenTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait, SpecimenTrait, ObservationTrait, DiagnosticReportTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;   // jumlah DiagnosticReport terkirim

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
        $this->count = count($ss['labDiagnosticReportIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-lab-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $ss = $dataRJ['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['labDiagnosticReportIds'])) { $this->dispatch('toast', type: 'info', message: 'Lab sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $encounterId = $ss['encounterId'];
            $drDesc      = $dataRJ['drDesc'] ?? '';

            // Paket checkup lab internal RJ yang SUDAH ada hasilnya (checkup_status != 'P').
            $checkups = DB::table('lbtxn_checkuphdrs')
                ->where('ref_no', $rjNo)
                ->where('status_rjri', 'RJ')
                ->where('checkup_status', '<>', 'P')
                ->select('checkup_no', 'checkup_date')
                ->get();
            if ($checkups->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada hasil lab (paket selesai) untuk dikirim.'); return; }

            $ss['labServiceRequestIds']    = $ss['labServiceRequestIds']    ?? [];
            $ss['labSpecimenIds']          = $ss['labSpecimenIds']          ?? [];
            $ss['labObservationIds']       = $ss['labObservationIds']       ?? [];
            $ss['labDiagnosticReportIds']  = $ss['labDiagnosticReportIds']  ?? [];

            $skippedNoLoinc = 0;
            $totalObs = 0;

            foreach ($checkups as $chk) {
                $cno = trim((string) $chk->checkup_no);
                $when = $this->parseDate($chk->checkup_date ?? ($dataRJ['rjDate'] ?? ''))->toIso8601String();

                // Item hasil ber-loinc (kecuali group header).
                $items = DB::table('lbtxn_checkupdtls as b')
                    ->join('lbmst_clabitems as d', 'b.clabitem_id', '=', 'd.clabitem_id')
                    ->where('b.checkup_no', $chk->checkup_no)
                    ->whereRaw("nvl(d.hidden_status,'N') = 'N'")
                    ->whereRaw("nvl(d.is_group,'N') <> 'Y'")
                    ->select('d.clabitem_desc', 'd.loinc_code', 'd.loinc_display', 'd.unit_desc', 'b.lab_result')
                    ->get();

                $itemsWithLoinc = $items->filter(fn ($i) => trim((string) ($i->loinc_code ?? '')) !== '');
                $skippedNoLoinc += $items->count() - $itemsWithLoinc->count();
                if ($itemsWithLoinc->isEmpty()) { continue; }   // paket ini tak punya item ber-LOINC

                // 1) ServiceRequest (order) — code panel LOINC generik.
                $sr = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => "{$rjNo}-{$cno}"],
                    'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                    'category' => ['system' => 'http://snomed.info/sct', 'code' => '108252007', 'display' => 'Laboratory procedure'],
                    'code' => ['system' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies'],
                    'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                    'occurrenceDateTime' => $when, 'authoredOn' => $when,
                    'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                ]);
                $srId = $sr['id'] ?? null;
                if (empty($srId)) { continue; }
                $ss['labServiceRequestIds'][] = $srId;

                // 2) Specimen — darah, venipuncture.
                $sp = $this->postSpecimen([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/specimen/{$orgId}", 'value' => "{$rjNo}-{$cno}", 'assigner' => "Organization/{$orgId}"],
                    'status' => 'available', 'subject' => "Patient/{$patientId}",
                    'type' => ['system' => 'http://snomed.info/sct', 'code' => '119297000', 'display' => 'Blood specimen'],
                    'collection' => ['collectedDateTime' => $when, 'method' => ['system' => 'http://snomed.info/sct', 'code' => '129300006', 'display' => 'Puncture - action']],
                    'receivedTime' => $when, 'request' => ["ServiceRequest/{$srId}"],
                ]);
                $spId = $sp['id'] ?? null;
                if (!empty($spId)) { $ss['labSpecimenIds'][] = $spId; }

                // 3) Observation per item ber-loinc.
                $obsIdsThisPaket = [];
                foreach ($itemsWithLoinc as $it) {
                    $loinc = trim((string) $it->loinc_code);
                    $result = trim((string) ($it->lab_result ?? ''));
                    if ($result === '') { continue; }

                    $obsData = [
                        'patientId' => $patientId, 'encounterId' => $encounterId, 'performerId' => $practitionerId,
                        'effectiveDate' => $when,
                        'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
                        'code' => ['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $it->loinc_display ?: $it->clabitem_desc],
                    ];
                    if (is_numeric(str_replace(',', '.', $result))) {
                        $unit = trim((string) ($it->unit_desc ?? '')) ?: '1';
                        $obsData['valueQuantity'] = ['value' => (float) str_replace(',', '.', $result), 'unit' => $unit, 'system' => 'http://unitsofmeasure.org', 'code' => $unit];
                    } else {
                        $obsData['valueString'] = $result;
                    }

                    $obs = $this->createObservation($obsData);
                    if (!empty($obs['id'])) { $obsIdsThisPaket[] = $obs['id']; $ss['labObservationIds'][] = $obs['id']; $totalObs++; }
                }

                if (empty($obsIdsThisPaket)) { continue; }

                // 4) DiagnosticReport — merangkum paket.
                $dr = $this->createDiagnosticReport([
                    'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}", 'use' => 'official', 'value' => "{$rjNo}-{$cno}"]],
                    'status' => 'final', 'categoryCode' => 'LAB', 'categoryDisplay' => 'Laboratory',
                    'codeSystem' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies',
                    'patientId' => $patientId, 'encounterId' => $encounterId,
                    'effectiveDate' => $when, 'issued' => $when,
                    'performer' => ["Practitioner/{$practitionerId}"],
                    'specimen' => $spId ? ["Specimen/{$spId}"] : [],
                    'observationIds' => $obsIdsThisPaket, 'basedOn' => [$srId],
                ]);
                if (!empty($dr['id'])) { $ss['labDiagnosticReportIds'][] = $dr['id']; }
            }

            if (empty($ss['labDiagnosticReportIds'])) {
                $msg = $skippedNoLoinc > 0
                    ? "Gagal: {$skippedNoLoinc} item lab belum punya kode LOINC di Master Lab."
                    : 'Tidak ada hasil lab yang bisa dikirim.';
                $this->dispatch('toast', type: 'error', message: $msg);
                return;
            }

            $this->saveResult($rjNo, $ss);
            $drCount = count($ss['labDiagnosticReportIds']);
            $note = $skippedNoLoinc > 0 ? " ({$skippedNoLoinc} item tanpa LOINC dilewati)" : '';
            $this->dispatch('toast', type: 'success', message: "Lab terkirim: {$drCount} laporan, {$totalObs} observasi{$note}.");
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Lab gagal: ' . $e->getMessage());
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

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">9</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Penunjang Lab</div>
            <div class="text-xs text-muted dark:text-gray-400">ServiceRequest · Specimen · Observation · DiagnosticReport.</div>
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
