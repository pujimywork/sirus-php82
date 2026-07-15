<?php
// Viewer "Surat Keterangan Kematian" untuk daftar riwayat kunjungan (rekam-medis-display).
// Headless: hanya modal, tanpa kartu — rjNo datang dari baris yang diklik.
//
// ⚠️  Nama event SENGAJA beda dari cetak-surat-kematian.blade.php ('cetak-surat-kematian-ugd.open').
//     Di halaman Pelayanan UGD kedua komponen hidup bersamaan (⚡pelayanan-ugd me-mount
//     erm-ugd → rekam-medis-display DAN modul-dokumen-ugd → rm-surat-kematian-actions),
//     jadi event yang sama akan ditangkap dua instance → PDF ter-download dobel.

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public string $nomorSurat = '';
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.surat-kematian.cetak-surat-kematian-print';

    #[On('surat-kematian-riwayat.lihat')]
    public function lihatRiwayat(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->previewHtml = '';

        $data = $this->buatData();
        if (!$data) {
            return;
        }

        $this->nomorSurat = (string) ($data['form']['nomorSurat'] ?? '');
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: 'view-surat-kematian-riwayat');
    }

    #[On('surat-kematian-riwayat.cetak')]
    public function cetakRiwayat(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;
        return $this->cetak();
    }

    /** Dipanggil tombol "Cetak PDF" di footer modal (x-rm.dokumen-view-modal). */
    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }

        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'surat-kematian-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $form = $dataUGD['suratKematianUGD'] ?? null;
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Surat Keterangan Kematian belum tersedia untuk kunjungan ini.');
            return null;
        }

        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');
        return array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'form' => $form,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdDokterPath' => $this->dvTtdPath($form['dokterPenerangCode'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-rm.dokumen-view-modal name="view-surat-kematian-riwayat" title="Surat Keterangan Kematian"
        :subtitle="$nomorSurat ? 'No. ' . $nomorSurat : null" :showCetak="filled($previewHtml)"
        :previewHtml="$previewHtml" />
</div>
