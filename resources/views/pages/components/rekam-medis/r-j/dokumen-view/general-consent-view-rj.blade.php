<?php
// Viewer read-only "General Consent" — display Rekam Medis RJ (objek tunggal).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $consent = [];
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-j.general-consent.cetak-general-consent-rj-print';

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
        $this->dispatch('open-modal', name: "view-general-consent-rj-{$this->rjNo}");
    }

    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-rj-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataRJ = $this->rjNo ? ($this->findDataRJ($this->rjNo) ?: []) : [];
        $consent = $dataRJ['generalConsentPasienRJ'] ?? null;
        if (empty($consent) || empty($consent['signature'])) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return null;
        }

        $pasien = $this->dvPasien($dataRJ['regNo'] ?? '');
        return array_merge($pasien, [
            'dataRJ' => $dataRJ,
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
            <x-rm.doc-list-row wire:key="gc-rj-{{ $rjNo }}" id="gc" title="General Consent"
                :date="data_get($consent, 'signatureDate')" :sub="'Petugas: ' . (data_get($consent, 'petugasPemeriksa') ?: '-')" />
        @else
            <x-rm.doc-empty />
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-general-consent-rj-{{ $rjNo }}" title="General Consent"
        :subtitle="filled($consent) ? (data_get($consent, 'signatureDate') ?: null) : null"
        :showCetak="filled($consent)" :previewHtml="$previewHtml" />
</div>
