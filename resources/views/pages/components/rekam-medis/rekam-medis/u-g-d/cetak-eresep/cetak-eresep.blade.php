<?php
// resources/views/pages/components/rekam-medis/u-g-d/cetak-eresep/cetak-eresep-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?string $rjNo = null;

    #[On('cetak-eresep-ugd.open')]
    public function open(string $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

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
            ->where('klaim_id', $dataUGD['klaimId'] ?? '')
            ->select('klaim_status', 'klaim_desc')
            ->first();

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataUGD['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $pdf = Pdf::loadView('pages.components.rekam-medis.u-g-d.cetak-eresep.cetak-eresep-ugd-print', [
            'dataDaftarUGD' => $dataUGD,
            'dataPasien' => $pasienData,
            'klaim' => $klaim,
            'dokter' => $dokter,
        ])->setPaper('A4');

        $filename = 'eresep-ugd-' . ($dataUGD['regNo'] ?? $rjNo) . '-' . ($dataUGD['rjNo'] ?? '') . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>
<div></div>
