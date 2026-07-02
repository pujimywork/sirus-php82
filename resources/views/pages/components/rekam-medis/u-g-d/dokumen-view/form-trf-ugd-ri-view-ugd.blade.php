<?php
// Viewer read-only "Form Transfer UGD → RI" — display Rekam Medis UGD (objek tunggal).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $trf = [];
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.form-trf-ugd-ri.cetak-form-trf-ugd-ri-print';

    public function mount(?int $rjNo = null, array $trf = []): void
    {
        $this->rjNo = $rjNo;
        $this->trf = $trf ?? [];
    }

    public function lihat(?string $id = null): void
    {
        $data = $this->buatData();
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-form-trf-ugd-ri-{$this->rjNo}");
    }

    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'form-trf-ugd-ri-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');
        $dokter = DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->select('dr_name')->first();

        return array_merge($pasien, [
            'trfUgd' => $dataUGD['trfUgd'] ?? [],
            'dataUGD' => $dataUGD,
            'identitasRs' => $this->dvIdentitasRs(),
            'namaDokter' => $dokter->dr_name ?? null,
            'strDokter' => $dokter->dr_str ?? null,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    @php $t = $trf ?? []; @endphp
    <x-border-form title="Form Transfer UGD &rarr; RI">
        @if (filled($t))
            <x-rm.doc-list-row wire:key="trf-ugd-{{ $rjNo }}" id="trf" title="Transfer UGD &rarr; RI"
                :date="data_get($t, 'tglPindah') ?: data_get($t, 'petugasPengirimDate')"
                :sub="filled(data_get($t, 'alasanPindah')) ? 'Alasan: ' . data_get($t, 'alasanPindah') : null" />
        @else
            <x-rm.doc-empty />
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-form-trf-ugd-ri-{{ $rjNo }}" title="Form Transfer UGD &rarr; RI"
        :showCetak="filled($t)" :previewHtml="$previewHtml" />
</div>
