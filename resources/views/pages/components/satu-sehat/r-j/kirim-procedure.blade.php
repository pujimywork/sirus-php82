<?php
// resources/views/pages/components/satu-sehat/r-j/kirim-procedure.blade.php
// Step 4: Kirim Tindakan ICD-9

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ProcedureTrait;

new class extends Component {
    use EmrRJTrait, ProcedureTrait;

    #[On('ss-procedure-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $ss = $dataRJ['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['procedureIds'])) { $this->dispatch('toast', type: 'info', message: 'Tindakan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');

            $tindakanList = $dataRJ['tindakanList'] ?? ($dataRJ['tindakan'] ?? []);
            if (empty($tindakanList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tindakan.'); return; }

            $ss['procedureIds'] = [];
            foreach ($tindakanList as $t) {
                $code = $t['kodeIcd9'] ?? ($t['icd9'] ?? '');
                $display = $t['descIcd9'] ?? ($t['icd9Desc'] ?? '');
                if (empty($code)) continue;

                $res = $this->createProcedure([
                    'patientId' => $patientId, 'encounterId' => $ss['encounterId'], 'performerId' => $practitionerId,
                    'code' => $code, 'display' => $display, 'codeSystem' => 'http://hl7.org/fhir/sid/icd-9-cm',
                    'performedDateTime' => $rjDate->toIso8601String(),
                ]);
                if (!empty($res['id'])) $ss['procedureIds'][] = $res['id'];
            }

            $this->saveResult($rjNo, $ss);
            $count = count($ss['procedureIds']);
            $this->dispatch('toast', type: 'success', message: "Tindakan berhasil dikirim ({$count} item).");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Tindakan gagal: ' . $e->getMessage());
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
