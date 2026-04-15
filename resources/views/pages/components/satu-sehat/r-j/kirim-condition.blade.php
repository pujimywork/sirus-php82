<?php
// resources/views/pages/components/satu-sehat/r-j/kirim-condition.blade.php
// Step 2: Kirim Diagnosa ICD-10

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRJTrait, ConditionTrait;

    #[On('ss-condition-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $ss = $dataRJ['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['conditionIds'])) { $this->dispatch('toast', type: 'info', message: 'Diagnosa sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $rjDate = $this->parseDate($dataRJ['rjDate'] ?? '');
            $diagnosaList = $dataRJ['diagnpinaList'] ?? [];
            if (empty($diagnosaList) && !empty($dataRJ['diagnosaPinaUtama']['kodeIcdx'])) {
                $diagnosaList = [$dataRJ['diagnosaPinaUtama']];
            }
            if (empty($diagnosaList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data diagnosa untuk dikirim.'); return; }

            $ss['conditionIds'] = [];
            $count = 0;
            foreach ($diagnosaList as $diag) {
                $code = $diag['kodeIcdx'] ?? ($diag['icdx'] ?? '');
                $display = $diag['descIcdx'] ?? ($diag['icdxDesc'] ?? '');
                if (empty($code)) continue;

                $res = $this->createFinalDiagnosis([
                    'patientId' => $patientId, 'encounterId' => $ss['encounterId'],
                    'icd10_code' => $code, 'icd10_display' => $display,
                    'diagnosis_text' => "{$code} - {$display}",
                    'recordedDate' => $rjDate->toIso8601String(),
                ]);
                if (!empty($res['id'])) { $ss['conditionIds'][] = $res['id']; $count++; }
            }

            $this->saveResult($rjNo, $ss);
            $this->dispatch('toast', type: 'success', message: "Diagnosa berhasil dikirim ({$count} item).");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosa gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        $json = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('meta_data_pasien_json');
        if (empty($json)) return '';
        $data = json_decode($json, true);
        return $data['pasien']['satusehatId'] ?? '';
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
