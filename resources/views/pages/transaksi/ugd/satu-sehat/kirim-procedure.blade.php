<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-procedure.blade.php
// Step 4: Kirim Tindakan (Procedure, ICD-9-CM).
// CATATAN: UGD belum jelas menyimpan tindakan ber-ICD9 di JSON (RJ: tindakanList).
//   Dibaca dari tindakanList/tindakan bila ada; kalau kosong → guard (bukan error fatal).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ProcedureTrait;

new class extends Component {
    use EmrUGDTrait, ProcedureTrait;

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
        $this->count = count($ss['procedureIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-procedure-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $ss = $dataUGD['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['procedureIds'])) { $this->dispatch('toast', type: 'info', message: 'Tindakan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $ugdDate = $this->parseDate($dataUGD['rjDate'] ?? '');

            $tindakanList = $dataUGD['tindakanList'] ?? ($dataUGD['tindakan'] ?? []);
            if (empty($tindakanList) || !is_array($tindakanList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tindakan ber-ICD9 di UGD.'); return; }

            $ss['procedureIds'] = [];
            foreach ($tindakanList as $t) {
                if (!is_array($t)) continue;
                $code = $t['kodeIcd9'] ?? ($t['icd9'] ?? '');
                $display = $t['descIcd9'] ?? ($t['icd9Desc'] ?? '');
                if (empty($code)) continue;

                $res = $this->createProcedure([
                    'patientId' => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId,
                    'code' => $code, 'display' => $display, 'codeSystem' => 'http://hl7.org/fhir/sid/icd-9-cm',
                    'performedDateTime' => $ugdDate->toIso8601String(),
                ]);
                if (!empty($res['id'])) $ss['procedureIds'][] = $res['id'];
            }

            if (empty($ss['procedureIds'])) { $this->dispatch('toast', type: 'error', message: 'Tindakan tanpa kode ICD-9 — tidak ada yang dikirim.'); return; }

            $this->saveResult($rjNo, $ss);
            $count = count($ss['procedureIds']);
            $this->dispatch('toast', type: 'success', message: "Tindakan berhasil dikirim ({$count} item).");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Tindakan gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">4</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Procedure</div>
            <div class="text-xs text-muted dark:text-gray-400">Tindakan medis (ICD-9-CM).</div>
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
