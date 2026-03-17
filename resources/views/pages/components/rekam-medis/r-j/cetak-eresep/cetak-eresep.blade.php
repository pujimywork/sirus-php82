<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?string $rjNo = null;

    #[On('cetak-eresep-rj.open')]
    public function open(string $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        // Hitung umur
        if (!empty($pasienData['pasien']['tglLahir'])) {
            try {
                $pasienData['pasien']['thn'] = Carbon::createFromFormat('d/m/Y', $pasienData['pasien']['tglLahir'])
                    ->diff(Carbon::now(env('APP_TIMEZONE')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Exception $e) {
                $pasienData['pasien']['thn'] = '-';
            }
        }

        $klaim = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $dataRJ['klaimId'] ?? '')
            ->select('klaim_status', 'klaim_desc')
            ->first();

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataRJ['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $pdf = Pdf::loadView('pages.components.rekam-medis.r-j.cetak-eresep.cetak-eresep-print', [
            'dataDaftarPoliRJ' => $dataRJ,
            'dataPasien' => $pasienData,
            'klaim' => $klaim,
            'dokter' => $dokter,
        ])->setPaper('A4');

        $filename = 'eresep-' . ($dataRJ['regNo'] ?? $rjNo) . '-' . ($dataRJ['rjNo'] ?? '') . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>

<div></div>
