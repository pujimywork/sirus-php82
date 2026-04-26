<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-observation.blade.php
// Step 3: Kirim Tanda Vital

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrRJTrait, ObservationTrait;

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
        $this->count = count($ss['observationIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-observation-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $ss = $dataRJ['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['observationIds'])) { $this->dispatch('toast', type: 'info', message: 'Tanda vital sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $isoDate = $rjDate->toIso8601String();

            $pf = $dataRJ['pemeriksaanFisik'] ?? ($dataRJ['tandaVital'] ?? []);
            if (empty($pf)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tanda vital.'); return; }

            $ss['observationIds'] = [];
            $base = ['patientId' => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate];

            // TD
            $sistole = $pf['sistole'] ?? null; $diastole = $pf['diastole'] ?? null;
            if (!empty($sistole) && !empty($diastole)) {
                $res = $this->createObservation(array_merge($base, [
                    'code' => ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional'],
                    'components' => [
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]], 'valueQuantity' => ['value' => (float) $sistole, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                        ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]], 'valueQuantity' => ['value' => (float) $diastole, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                    ],
                ]));
                if (!empty($res['id'])) $ss['observationIds'][] = $res['id'];
            }

            // Nadi, Suhu, RR
            $singles = [
                ['val' => $pf['nadi'] ?? null, 'loinc' => '8867-4', 'display' => 'Heart rate', 'unit' => 'beats/minute', 'ucum' => '/min'],
                ['val' => $pf['suhu'] ?? null, 'loinc' => '8310-5', 'display' => 'Body temperature', 'unit' => 'C', 'ucum' => 'Cel'],
                ['val' => $pf['rr'] ?? ($pf['respirasi'] ?? null), 'loinc' => '9279-1', 'display' => 'Respiratory rate', 'unit' => 'breaths/minute', 'ucum' => '/min'],
            ];
            foreach ($singles as $v) {
                if (empty($v['val'])) continue;
                $res = $this->createObservation(array_merge($base, [
                    'code' => ['system' => 'http://loinc.org', 'code' => $v['loinc'], 'display' => $v['display']],
                    'valueQuantity' => ['value' => (float) $v['val'], 'unit' => $v['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $v['ucum']],
                ]));
                if (!empty($res['id'])) $ss['observationIds'][] = $res['id'];
            }

            $this->saveResult($rjNo, $ss);
            $count = count($ss['observationIds']);
            $this->dispatch('toast', type: 'success', message: "Tanda vital berhasil dikirim ({$count} item).");
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">3</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Observation</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Tanda vital, hasil lab.</div>
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
