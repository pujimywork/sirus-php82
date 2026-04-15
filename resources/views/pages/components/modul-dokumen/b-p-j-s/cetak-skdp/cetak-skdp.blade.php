<?php
// resources/views/pages/components/modul-dokumen/b-p-j-s/cetak-skdp/cetak-skdp.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, EmrUGDTrait, EmrRITrait, MasterPasienTrait {
        EmrRJTrait::checkLabPending insteadof EmrUGDTrait;
    }

    /* ── RJ ── */
    #[On('cetak-skdp-rj.open')]
    public function openRJ(?string $rjNo = null): mixed
    {
        if (empty($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi RJ tidak tersedia.');
            return null;
        }
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ) || empty($dataRJ['kontrol']['tglKontrol'])) {
            $this->dispatch('toast', type: 'error', message: 'Data surat kontrol RJ belum tersedia.');
            return null;
        }
        return $this->generatePdf($dataRJ, 'rj');
    }

    /* ── UGD ── */
    #[On('cetak-skdp-ugd.open')]
    public function openUGD(?string $rjNo = null): mixed
    {
        if (empty($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi UGD tidak tersedia.');
            return null;
        }
        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD) || empty($dataUGD['kontrol']['tglKontrol'])) {
            $this->dispatch('toast', type: 'error', message: 'Data surat kontrol UGD belum tersedia.');
            return null;
        }
        return $this->generatePdf($dataUGD, 'ugd');
    }

    /* ── RI ── */
    #[On('cetak-skdp-ri.open')]
    public function openRI(?string $riHdrNo = null): mixed
    {
        if (empty($riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi RI tidak tersedia.');
            return null;
        }
        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI) || empty($dataRI['kontrol']['tglKontrol'])) {
            $this->dispatch('toast', type: 'error', message: 'Data surat kontrol RI belum tersedia.');
            return null;
        }
        return $this->generatePdf($dataRI, 'ri');
    }

    /* ── Generate PDF ── */
    private function generatePdf(array $dataTxn, string $jenis): mixed
    {
        $kontrol = $dataTxn['kontrol'] ?? [];
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

        // Format tgl kontrol
        if (!empty($kontrol['tglKontrol'])) {
            try {
                $kontrol['tglKontrolFormatted'] = Carbon::createFromFormat('d/m/Y', $kontrol['tglKontrol'])
                    ->translatedFormat('j F Y');
            } catch (\Throwable) {
                $kontrol['tglKontrolFormatted'] = $kontrol['tglKontrol'];
            }
        }

        // Diagnosa dari SEP
        $resSep = $sep['resSep'] ?? [];
        $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
        $diagnosa = $resSep['diagnosa'] ?? ($reqSep['diagAwal'] ?? '-');

        // Identitas RS
        $identitasRs = DB::table('rsmst_identitases')->select('int_name')->first();

        $data = [
            'kontrol' => $kontrol,
            'pasien' => $pasien,
            'dataTxn' => $dataTxn,
            'diagnosa' => $diagnosa,
            'jenis' => $jenis,
            'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y H:i:s'),
        ];

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.b-p-j-s.cetak-skdp.cetak-skdp-print', ['data' => $data])->setPaper('A5', 'landscape');

        $noSkdp = $kontrol['noSKDPBPJS'] ?? ($kontrol['noKontrolRS'] ?? 'unknown');
        $filename = 'SKDP-' . str_replace('/', '-', $noSkdp) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>
<div></div>
