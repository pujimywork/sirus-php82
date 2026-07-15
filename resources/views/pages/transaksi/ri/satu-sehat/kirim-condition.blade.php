<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-condition.blade.php
// Step 3 (RI): Kirim Diagnosa ICD-10.
//
// Sumber diagnosa RI = rstxn_ridtls (diag_id) JOIN rsmst_mstdiags BY diag_id.
// Lookup by diag_id (PK unik) → AMAN dari jebakan 288 icdx kembar (lihat skill diagnosa-flow).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRITrait, ConditionTrait;

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
        $this->count = count($ss['conditionIds'] ?? []);
    }

    /**
     * Ambil diagnosa RI dari rstxn_ridtls join rsmst_mstdiags by diag_id.
     * @return array<int, array{code:string, display:string}>
     */
    private function diagnosaRI(string $riHdrNo): array
    {
        return DB::table('rstxn_ridtls as d')
            ->join('rsmst_mstdiags as m', 'd.diag_id', '=', 'm.diag_id')
            ->where('d.rihdr_no', $riHdrNo)
            ->whereRaw('LENGTH(TRIM(m.icdx)) > 0')
            ->orderBy('d.ridtl_dtl')
            ->get([DB::raw('m.icdx as code'), DB::raw('m.diag_desc as display')])
            ->map(fn($r) => ['code' => (string) $r->code, 'display' => (string) ($r->display ?? '')])
            ->all();
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-condition-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['conditionIds'])) { $this->dispatch('toast', type: 'info', message: 'Diagnosa sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $diagnosaList = $this->diagnosaRI($riHdrNo);
            if (empty($diagnosaList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data diagnosa untuk dikirim.'); return; }

            $recordedDate = $this->parseDate($dataRI['entryDate'] ?? '');

            $ss['conditionIds'] = [];
            $count = 0;
            foreach ($diagnosaList as $diag) {
                $code = $diag['code'];
                $display = $diag['display'];
                if (empty($code)) continue;

                $res = $this->createFinalDiagnosis([
                    'patientId' => $patientId, 'encounterId' => $ss['encounterId'],
                    'icd10_code' => $code, 'icd10_display' => $display,
                    'diagnosis_text' => "{$code} - {$display}",
                    'recordedDate' => $recordedDate->toIso8601String(),
                ]);
                if (!empty($res['id'])) { $ss['conditionIds'][] = $res['id']; $count++; }
            }

            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: "Diagnosa berhasil dikirim ({$count} item).");
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">3</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Condition</div>
            <div class="text-xs text-muted dark:text-gray-400">Diagnosa pasien (ICD-10).</div>
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
