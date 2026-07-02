<?php
// Viewer read-only "Inform Consent" (per tindakan) — display Rekam Medis RI.

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.inform-consent.cetak-inform-consent-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'signatureDate';
    }

    public function lihat(string $signatureDate): void
    {
        $this->selected = collect($this->list)->firstWhere('signatureDate', $signatureDate) ?: null;
        $data = $this->buatData($signatureDate);
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-inform-consent-ri-{$this->riHdrNo}");
    }

    public function cetak(string $signatureDate): mixed
    {
        $data = $this->buatData($signatureDate);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'inform-consent-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    private function buatData(string $signatureDate): ?array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $consent = collect($dataRi['informConsentPasienRI'] ?? [])->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');

        $dokterTindakanName = null;
        if (!empty($consent['petugasPemeriksaCode'])) {
            $userRow = DB::table('users')->where('myuser_code', $consent['petugasPemeriksaCode'])->first(['myuser_name']);
            $dokterTindakanName = $userRow->myuser_name ?? null;
            if (empty($dokterTindakanName)) {
                $dokterTindakanName = DB::table('rsmst_doctors')->where('dr_id', $consent['petugasPemeriksaCode'])->value('dr_name');
            }
        }

        return array_merge($pasien, [
            'dataRi' => $dataRi,
            'consent' => $consent,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdDokterPath' => $this->dvTtdPath($consent['dokterCode'] ?? null),
            'dokterTindakanName' => $dokterTindakanName ?? ($consent['petugasPemeriksa'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-border-form title="Inform Consent">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signatureDate')) || filled(data_get($entri, 'tindakan')))->values() as $entri)
            @if (filled(data_get($entri, 'signatureDate')))
                <x-rm.doc-list-row :id="data_get($entri, 'signatureDate')" :title="data_get($entri, 'tindakan') ?: '(Tanpa nama tindakan)'"
                    :date="data_get($entri, 'signatureDate')"
                    :sub="'Dokter: ' . (data_get($entri, 'dokter') ?: '-') . (filled(data_get($entri, 'diagnosa')) ? ' · Diagnosa: ' . data_get($entri, 'diagnosa') : '')" />
            @else
                <div class="flex items-center gap-2 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800 text-base text-muted">
                    {{ data_get($entri, 'tindakan') ?: '(Tanpa nama tindakan)' }}
                    <x-badge variant="gray">Belum TTD</x-badge>
                </div>
            @endif
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-inform-consent-ri-{{ $riHdrNo }}"
        :title="'Inform Consent' . ($selected && filled(data_get($selected, 'tindakan')) ? ' — ' . data_get($selected, 'tindakan') : '')"
        :subtitle="$selected ? (data_get($selected, 'signatureDate') ?: null) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
