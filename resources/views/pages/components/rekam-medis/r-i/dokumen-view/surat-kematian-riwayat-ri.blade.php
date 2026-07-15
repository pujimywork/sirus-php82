<?php
// Viewer "Surat Keterangan Kematian" RI untuk daftar riwayat kunjungan (rekam-medis-display).
// Headless: hanya modal, tanpa kartu — riHdrNo datang dari baris yang diklik.
//
// ⚠️  Nama event ber-suffix "-riwayat-ri", beda dari viewer kartu di RM display RI
//     (surat-kematian-view-ri) supaya dua instance tak menangkap event yang sama.

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public string $nomorSurat = '';
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.surat-kematian-ri.cetak-surat-kematian-ri-print';

    #[On('surat-kematian-riwayat-ri.lihat')]
    public function lihatRiwayat(string $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->previewHtml = '';

        $data = $this->buatData();
        if (!$data) {
            return;
        }

        $this->nomorSurat = (string) ($data['form']['nomorSurat'] ?? '');
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: 'view-surat-kematian-riwayat-ri');
    }

    #[On('surat-kematian-riwayat-ri.cetak')]
    public function cetakRiwayat(string $riHdrNo): mixed
    {
        $this->riHdrNo = $riHdrNo;
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

        return response()->streamDownload(fn() => print $pdf->output(), 'surat-kematian-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataRI = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $form = $dataRI['suratKematianRI'] ?? null;
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Surat Keterangan Kematian belum tersedia untuk kunjungan ini.');
            return null;
        }

        // Nomor & tanggal ikut Perencanaan (sumber yang dikirim ke BPJS).
        $tindak = $dataRI['perencanaan']['tindakLanjut'] ?? [];
        $form['nomorSurat'] = $tindak['noSuratMeninggal'] ?? '';
        $form['tanggalMeninggal'] = $tindak['tglMeninggal'] ?? '';

        $pasien = $this->dvPasien($dataRI['regNo'] ?? '');
        return array_merge($pasien, [
            'dataRI' => $dataRI,
            'form' => $form,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdDokterPath' => $this->dvTtdPath($form['dokterPenerangCode'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-rm.dokumen-view-modal name="view-surat-kematian-riwayat-ri" title="Surat Keterangan Kematian"
        :subtitle="$nomorSurat ? 'No. ' . $nomorSurat : null" :showCetak="filled($previewHtml)"
        :previewHtml="$previewHtml" />
</div>
