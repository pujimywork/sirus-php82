<?php
// resources/views/pages/components/modul-dokumen/b-p-j-s/cetak-prb/cetak-prb.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    #[On('cetak-prb-rj.open')]
    public function openRJ(?string $rjNo = null): mixed
    {
        if (empty($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi RJ tidak tersedia.');
            return null;
        }
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ) || empty($dataRJ['prb'])) {
            $this->dispatch('toast', type: 'error', message: 'Data PRB belum tersedia.');
            return null;
        }
        return $this->generatePdf($dataRJ);
    }

    private function generatePdf(array $dataTxn): mixed
    {
        $prb = $dataTxn['prb'] ?? [];
        $sep = $dataTxn['sep'] ?? [];

        // Ambil data pasien
        $regNo = $dataTxn['regNo'] ?? '';
        $pasienData = !empty($regNo) ? $this->findDataMasterPasien($regNo) : [];
        $pasien = $pasienData['pasien'] ?? [];

        // Format tgl lahir
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['tglLahirFormatted'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->translatedFormat('j F Y');
            } catch (\Throwable) {
                $pasien['tglLahirFormatted'] = $pasien['tglLahir'];
            }
        }

        // Diagnosa dari SEP
        $resSep = $sep['resSep'] ?? [];
        $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
        $diagnosa = $resSep['diagnosa'] ?? ($reqSep['diagAwal'] ?? '-');

        // Identitas RS
        $identitasRs = DB::table('rsmst_identitases')->select('int_name')->first();

        // Map kode program ke nama
        $programMap = [
            '01' => 'Diabetes Mellitus', '02' => 'Hypertensi', '03' => 'Asthma',
            '04' => 'Penyakit Jantung', '05' => 'PPOK', '06' => 'Schizophrenia',
            '07' => 'Stroke', '08' => 'Epilepsi', '09' => 'Systemic Lupus Erythematosus',
        ];
        $kodePrb = trim($prb['programPRB'] ?? '');
        $programNama = $prb['programPRBNama'] ?? ($programMap[$kodePrb] ?? $kodePrb);

        $data = [
            'prb' => $prb,
            'pasien' => $pasien,
            'dataTxn' => $dataTxn,
            'diagnosa' => $diagnosa,
            'programNama' => $programNama,
            'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y h:i:s A'),
            'tglSRB' => Carbon::now(config('app.timezone'))->translatedFormat('j F Y'),
        ];

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.b-p-j-s.cetak-prb.cetak-prb-print', ['data' => $data])
            ->setPaper('A5', 'landscape');

        $noSrb = $prb['noSrb'] ?? 'draft';
        $filename = 'PRB-' . str_replace('/', '-', $noSrb) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>
<div></div>
