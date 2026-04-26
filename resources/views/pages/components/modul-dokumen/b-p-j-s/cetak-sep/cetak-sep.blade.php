<?php
// resources/views/pages/components/modul-dokumen/b-p-j-s/cetak-sep/cetak-sep.blade.php

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
    #[On('cetak-sep-rj.open')]
    public function openRJ(?string $rjNo = null): mixed
    {
        if (empty($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi RJ tidak tersedia.');
            return null;
        }
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ) || empty($dataRJ['sep']['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Data SEP Rawat Jalan tidak ditemukan.');
            return null;
        }
        return $this->generatePdf($dataRJ, 'rj');
    }

    /* ── UGD ── */
    #[On('cetak-sep-ugd.open')]
    public function openUGD(?string $rjNo = null): mixed
    {
        if (empty($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi UGD tidak tersedia.');
            return null;
        }
        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD) || empty($dataUGD['sep']['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Data SEP UGD tidak ditemukan.');
            return null;
        }
        return $this->generatePdf($dataUGD, 'ugd');
    }

    /* ── RI ── */
    #[On('cetak-sep-ri.open')]
    public function openRI(?string $riHdrNo = null): mixed
    {
        if (empty($riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'No. Transaksi RI tidak tersedia.');
            return null;
        }
        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI) || empty($dataRI['sep']['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Data SEP Rawat Inap tidak ditemukan.');
            return null;
        }
        return $this->generatePdf($dataRI, 'ri');
    }

    /* ── Generate PDF ── */
    private function generatePdf(array $dataTxn, string $jenis): mixed
    {
        $sep = $dataTxn['sep'] ?? [];
        $reqSep = $sep['reqSep']['request']['t_sep'] ?? [];
        $resSep = $sep['resSep'] ?? [];

        // Ambil data pasien
        $regNo = $dataTxn['regNo'] ?? '';
        $pasienData = !empty($regNo) ? $this->findDataMasterPasien($regNo) : [];
        $pasien = $pasienData['pasien'] ?? [];

        // Identitas RS
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        // Resolve nama DPJP: resSep.dpjp.nmDPJP (BPJS) → lookup kode DPJP di reqSep ke rsmst_doctors
        // (kasus RI: dpjpLayan dikirim kosong, kode DPJP riil ada di skdp.kodeDPJP;
        //  drDesc di rihdrs = dokter penerima/umum, bukan DPJP spesialis) → dataTxn.drDesc.
        $dokterDpjp = $resSep['dpjp']['nmDPJP'] ?? null;
        $kodeDpjpReq = $reqSep['dpjpLayan'] ?? '';
        if (empty($kodeDpjpReq)) {
            $kodeDpjpReq = $reqSep['skdp']['kodeDPJP'] ?? '';
        }
        if (empty($dokterDpjp) && !empty($kodeDpjpReq)) {
            $dokterDpjp = DB::table('rsmst_doctors')
                ->where('kd_dr_bpjs', $kodeDpjpReq)
                ->value('dr_name');
        }
        if (empty($dokterDpjp)) {
            $dokterDpjp = $dataTxn['drDesc'] ?? '-';
        }

        $data = [
            'sep' => $sep,
            'reqSep' => $reqSep,
            'resSep' => $resSep,
            'dataTxn' => $dataTxn,
            'pasien' => $pasien,
            'jenis' => $jenis,
            'identitasRs' => $identitasRs,
            'namaRs' => $identitasRs->int_name ?? 'RSI MADINAH',
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d-m-Y H:i:s'),
            'dokterDpjp' => $dokterDpjp,
        ];

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.b-p-j-s.cetak-sep.cetak-sep-print', ['data' => $data])->setPaper('A5', 'landscape');

        $noSep = $sep['noSep'] ?? 'unknown';
        $filename = 'SEP-' . str_replace('/', '-', $noSep) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>
<div></div>
