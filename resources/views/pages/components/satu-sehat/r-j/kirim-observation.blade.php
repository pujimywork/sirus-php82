<?php
// resources/views/pages/components/satu-sehat/r-j/kirim-observation.blade.php
// Step 3: Kirim Tanda Vital

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrRJTrait, ObservationTrait;

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

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('ihs_number') ?? '');
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
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        $json = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('meta_data_pasien_json');
        if (empty($json)) return '';
        return json_decode($json, true)['pasien']['satusehatId'] ?? '';
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
<div></div>
