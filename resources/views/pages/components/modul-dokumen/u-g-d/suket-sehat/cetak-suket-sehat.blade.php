<?php
// resources/views/pages/components/modul-dokumen/u-g-d/suket-sehat/cetak-suket-sehat-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?int $rjNo = null;

    /* ═══════════════════════════════════════
     | OPEN & LANGSUNG CETAK
    ═══════════════════════════════════════ */
    #[On('cetak-suket-sehat-ugd.open')]
    public function open(int $rjNo): mixed
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

        $pasien = $pasienData['pasien'];
        $suketSehat = $dataUGD['suket']['suketSehat'] ?? [];

        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(env('APP_TIMEZONE')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataUGD['drId'] ?? '')
            ->select('dr_name', 'dr_str')
            ->first();

        $data = array_merge($pasien, [
            'keteranganSehat' => $suketSehat['suketSehat'] ?? null,
            'namaDokter' => $dokter->dr_name ?? null,
            'strDokter' => $dokter->dr_str ?? null,
            'tglCetak' => Carbon::now()->translatedFormat('d F Y'),
        ]);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.suket-sehat.cetak-suket-sehat-ugd-print', [
            'data' => $data,
        ])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'suket-sehat-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
