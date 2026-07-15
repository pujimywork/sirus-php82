<?php
// resources/views/pages/components/modul-dokumen/u-g-d/surat-kematian/cetak-surat-kematian.blade.php

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

    #[On('cetak-surat-kematian-ugd.open')]
    public function open(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        // ── 1. Data UGD ──
        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        $form = $dataUGD['suratKematianUGD'] ?? [];
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Surat kematian belum tersimpan.');
            return null;
        }

        // ── 2. Data Pasien ──
        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }

        // ── 3. Identitas RS ──
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();

        // ── 4. TTD Dokter yang Menerangkan ──
        $ttdDokterPath = null;
        $dokterCode = $form['dokterPenerangCode'] ?? null;
        if ($dokterCode) {
            $ttdPath = DB::table('users')->where('myuser_code', $dokterCode)->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdDokterPath = public_path('storage/' . $ttdPath);
            }
        }

        $data = array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'form' => $form,
            'identitasRs' => $identitasRs,
            'ttdDokterPath' => $ttdDokterPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.surat-kematian.cetak-surat-kematian-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'surat-kematian-ugd-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
