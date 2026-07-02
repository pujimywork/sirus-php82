<?php
// Viewer read-only "General Consent" — display Rekam Medis RI (objek tunggal).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $consent = [];
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.general-consent.cetak-general-consent-ri-print';

    public function mount(?string $riHdrNo = null, array $consent = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->consent = $consent ?? [];
    }

    public function lihat(?string $id = null): void
    {
        $data = $this->buatData();
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-general-consent-ri-{$this->riHdrNo}");
    }

    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $consent = $dataRi['generalConsentPasienRI'] ?? null;
        if (!$consent || !is_array($consent) || empty($consent['signature'])) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return null;
        }

        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');
        return array_merge($pasien, [
            'dataRi' => $dataRi,
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
            <x-rm.doc-list-row wire:key="gc-ri-{{ $riHdrNo }}" id="gc" title="General Consent"
                :date="data_get($consent, 'signatureDate')" :sub="'Petugas: ' . (data_get($consent, 'petugasPemeriksa') ?: '-')" />
        @else
            <x-rm.doc-empty />
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-general-consent-ri-{{ $riHdrNo }}" title="General Consent"
        :subtitle="filled($consent) ? (data_get($consent, 'signatureDate') ?: null) : null"
        :showCetak="filled($consent)" :previewHtml="$previewHtml" />
</div>
