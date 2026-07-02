<?php
// Viewer read-only "Form Penjaminan & Orientasi Kamar" — display Rekam Medis UGD.
// Pembeda entri = signaturePembuatDate.

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.form-penjaminan.cetak-form-penjaminan-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo;
        $this->list = array_values($entries);
        $this->navField = 'signaturePembuatDate';
    }

    public function lihat(string $signaturePembuatDate): void
    {
        $this->selected = collect($this->list)->firstWhere('signaturePembuatDate', $signaturePembuatDate) ?: null;
        $data = $this->buatData($signaturePembuatDate);
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-form-penjaminan-ugd-{$this->rjNo}");
    }

    public function cetak(string $signaturePembuatDate): mixed
    {
        $data = $this->buatData($signaturePembuatDate);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'form-penjaminan-biaya-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(string $signaturePembuatDate): ?array
    {
        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $form = collect($dataUGD['formPenjaminanOrientasiKamar'] ?? [])->firstWhere('signaturePembuatDate', $signaturePembuatDate);
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Data Form Pernyataan tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');
        return array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'form' => $form,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPetugasPath' => $this->dvTtdPath($form['kodePetugas'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-border-form title="Form Penjaminan & Orientasi Kamar">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signaturePembuatDate')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'signaturePembuatDate')"
                :title="(data_get($entri, 'jenisPenjamin') ?: 'Penjaminan') . (filled(data_get($entri, 'kelasKamar')) ? ' · Kelas ' . data_get($entri, 'kelasKamar') : '')"
                :date="data_get($entri, 'signaturePembuatDate')"
                :sub="'Hubungan: ' . (data_get($entri, 'hubunganDenganPasien') ?: '-')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-form-penjaminan-ugd-{{ $rjNo }}" title="Form Penjaminan & Orientasi Kamar"
        :subtitle="$selected ? trim((string) (data_get($selected, 'jenisPenjamin') ?: '-') . ' · ' . (data_get($selected, 'signaturePembuatDate') ?: '')) : null"
        :cetakId="data_get($selected, 'signaturePembuatDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
