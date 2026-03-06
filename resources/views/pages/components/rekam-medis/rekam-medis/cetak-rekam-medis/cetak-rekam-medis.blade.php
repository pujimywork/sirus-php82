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

    public ?int $rjNo = null;

    /* ===============================
     | OPEN & LANGSUNG CETAK
     =============================== */
    #[On('cetak-rekam-medis.open')]
    public function open(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        // Ambil data JSON RJ dari DB
        $dataRJ = $this->findDataRJ($rjNo);

        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return null;
        }

        // Ambil data pasien via regNo
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');

        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        // Hitung umur realtime
        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(env('APP_TIMEZONE')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        // Ambil data dokter langsung dari DB berdasarkan drId
        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataRJ['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $data = array_merge($pasien, [
            'dataDaftarTxn' => $dataRJ,
            'namaDokter' => $dokter->dr_name ?? null,
            'tglCetak' => Carbon::now()->translatedFormat('d F Y'),
        ]);

        $pdf = Pdf::loadView('pages.components.rekam-medis.rekam-medis.cetak-rekam-medis.cetak-rekam-medis-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'rekam-medis-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
