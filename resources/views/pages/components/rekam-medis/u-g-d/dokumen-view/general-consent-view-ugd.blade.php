<?php
// Viewer read-only "General Consent" — display Rekam Medis UGD (objek tunggal).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $consent = [];
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.general-consent.cetak-general-consent-print';

    public function mount(?int $rjNo = null, array $consent = []): void
    {
        $this->rjNo = $rjNo;
        $this->consent = $consent ?? [];
    }

    public function lihat(?string $id = null): void
    {
        $data = $this->buatData();
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-general-consent-ugd-{$this->rjNo}");
    }

    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $consent = $dataUGD['generalConsentPasienUGD'] ?? null;
        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return null;
        }

        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');
        return array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'consent' => $consent,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPetugasPath' => $this->dvTtdPath($consent['petugasPemeriksaCode'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-border-form title="General Consent">
        @if (filled($consent))
            <x-rm.doc-list-row wire:key="gc-ugd-{{ $rjNo }}" id="gc" title="General Consent"
                :date="data_get($consent, 'signatureDate')" :sub="'Petugas: ' . (data_get($consent, 'petugasPemeriksa') ?: '-')" />
        @else
            <x-rm.doc-empty />
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-general-consent-ugd-{{ $rjNo }}" title="General Consent"
        :subtitle="filled($consent) ? (data_get($consent, 'signatureDate') ?: null) : null"
        :showCetak="filled($consent)" :previewHtml="$previewHtml" />
</div>
