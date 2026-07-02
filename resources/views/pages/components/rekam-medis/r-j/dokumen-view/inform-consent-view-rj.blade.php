<?php
// Viewer read-only "Inform Consent" (per tindakan) — display Rekam Medis RJ.

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-j.inform-consent.cetak-inform-consent-rj-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo;
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
        $this->dispatch('open-modal', name: "view-inform-consent-rj-{$this->rjNo}");
    }

    public function cetak(string $signatureDate): mixed
    {
        $data = $this->buatData($signatureDate);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'inform-consent-rj-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(string $signatureDate): ?array
    {
        $dataRJ = $this->rjNo ? ($this->findDataRJ($this->rjNo) ?: []) : [];
        $consent = collect($dataRJ['informConsentPasienRJ'] ?? [])->firstWhere('signatureDate', $signatureDate);
        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data Inform Consent tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataRJ['regNo'] ?? '');

        $ttdDokterTindakanPath = null;
        $dokterTindakanName = null;
        if (!empty($consent['petugasPemeriksaCode'])) {
            $userRow = DB::table('users')->where('myuser_code', $consent['petugasPemeriksaCode'])->first(['myuser_ttd_image', 'myuser_name']);
            if ($userRow) {
                $dokterTindakanName = $userRow->myuser_name ?? null;
                if (!empty($userRow->myuser_ttd_image) && file_exists(public_path('storage/' . $userRow->myuser_ttd_image))) {
                    $ttdDokterTindakanPath = public_path('storage/' . $userRow->myuser_ttd_image);
                }
            }
            if (empty($dokterTindakanName)) {
                $dokterTindakanName = DB::table('rsmst_doctors')->where('dr_id', $consent['petugasPemeriksaCode'])->value('dr_name');
            }
        }

        return array_merge($pasien, [
            'dataRJ' => $dataRJ,
            'consent' => $consent,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdDokterPath' => $this->dvTtdPath($consent['dokterCode'] ?? null),
            'ttdDokterTindakanPath' => $ttdDokterTindakanPath,
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
                    :date="data_get($entri, 'signatureDate')" :sub="'Dokter: ' . (data_get($entri, 'dokter') ?: '-')" />
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

    <x-rm.dokumen-view-modal name="view-inform-consent-rj-{{ $rjNo }}"
        :title="'Inform Consent' . ($selected && filled(data_get($selected, 'tindakan')) ? ' — ' . data_get($selected, 'tindakan') : '')"
        :subtitle="$selected ? (data_get($selected, 'signatureDate') ?: null) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
