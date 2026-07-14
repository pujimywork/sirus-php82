<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-observation.blade.php
// Step 5 (RI): Kirim Tanda Vital (Observation).
//
// Sumber = datadaftarri_json → observasi.observasiLanjutan.tandaVital[] (MULTI-ENTRI).
// Beda dari RJ (1x vital): RI punya banyak entri sepanjang rawat inap → 1 Observation
// per vital PER entri. Effective time = waktuPemeriksaan tiap entri.
//
// Key per entri (ejaan non-standar dari EMR): sistolik, distolik (diastolik),
// frekuensiNadi, frekuensiNafas, suhu, spo2.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrRITrait, ObservationTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;      // jumlah Observation terkirim
    public int $entryCount = 0; // jumlah entri waktu vital tersedia

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
        $this->count = count($ss['observationIds'] ?? []);
        $this->entryCount = count($this->tandaVitalEntries($data));
    }

    /** @return array<int, array> daftar entri tandaVital */
    private function tandaVitalEntries(array $data): array
    {
        return $data['observasi']['observasiLanjutan']['tandaVital'] ?? [];
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-observation-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['observationIds'])) { $this->dispatch('toast', type: 'info', message: 'Tanda vital sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');

            $entries = $this->tandaVitalEntries($dataRI);
            if (empty($entries)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tanda vital (Observasi Lanjutan).'); return; }

            $ss['observationIds'] = [];
            foreach ($entries as $e) {
                $when = trim((string) ($e['waktuPemeriksaan'] ?? ''));
                $isoDate = ($when !== '' ? $this->parseDate($when) : $this->parseDate($dataRI['entryDate'] ?? ''))->toIso8601String();
                $base = ['patientId' => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate];

                // Tekanan Darah (panel)
                $sistole = $e['sistolik'] ?? null;
                $diastole = $e['distolik'] ?? null;
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

                // Vital tunggal
                $singles = [
                    ['val' => $e['frekuensiNadi'] ?? null,  'loinc' => '8867-4', 'display' => 'Heart rate',        'unit' => 'beats/minute',   'ucum' => '/min'],
                    ['val' => $e['suhu'] ?? null,           'loinc' => '8310-5', 'display' => 'Body temperature',  'unit' => 'C',              'ucum' => 'Cel'],
                    ['val' => $e['frekuensiNafas'] ?? null, 'loinc' => '9279-1', 'display' => 'Respiratory rate',  'unit' => 'breaths/minute', 'ucum' => '/min'],
                    ['val' => $e['spo2'] ?? null,           'loinc' => '59408-5','display' => 'Oxygen saturation in Arterial blood by Pulse oximetry', 'unit' => '%', 'ucum' => '%'],
                ];
                foreach ($singles as $v) {
                    if (empty($v['val'])) continue;
                    $res = $this->createObservation(array_merge($base, [
                        'code' => ['system' => 'http://loinc.org', 'code' => $v['loinc'], 'display' => $v['display']],
                        'valueQuantity' => ['value' => (float) $v['val'], 'unit' => $v['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $v['ucum']],
                    ]));
                    if (!empty($res['id'])) $ss['observationIds'][] = $res['id'];
                }
            }

            if (empty($ss['observationIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai vital valid untuk dikirim.'); return; }

            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'Tanda vital berhasil dikirim (' . count($ss['observationIds']) . ' observation dari ' . count($entries) . ' waktu).');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">5</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Observation</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Tanda vital (TD, nadi, suhu, RR, SpO₂).
                @if ($entryCount > 0)
                    <span class="text-muted-soft">{{ $entryCount }} waktu ukur.</span>
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
