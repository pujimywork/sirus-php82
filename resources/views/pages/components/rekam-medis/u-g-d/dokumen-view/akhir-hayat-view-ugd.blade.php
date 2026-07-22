<?php
// Viewer read-only "Pengkajian Akhir Hayat" — display Rekam Medis UGD.
// Pembeda entri = id (uuid). Payload cetak bespoke (entry + opsiLabel + clause).

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\AkhirHayatClause;
use App\Support\AkhirHayatOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.akhir-hayat.cetak-akhir-hayat-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'id';
    }

    /** Payload cetak identik dgn aksi cetak() di komponen EMR Akhir Hayat UGD. */
    private function buildData(array $entry): array
    {
        $dataUgd = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $pasien = $this->dvPasien($dataUgd['regNo'] ?? '');
        $petugasCode = data_get($entry, 'form.ttd.petugasCode') ?: data_get($entry, 'created_by.code');

        return array_merge($pasien, [
            'dataRi' => $dataUgd,
            'entry' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPetugasPath' => $this->dvTtdPath($petugasCode),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            'clause' => AkhirHayatClause::get(data_get($entry, 'form.ttd.clauseVersion')),
            'opsiLabel' => AkhirHayatOptions::labels(),
        ]);
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('id', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian akhir hayat tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData($this->selected));
        $this->dispatch('open-modal', name: "view-akhir-hayat-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        $entry = collect($this->list)->firstWhere('id', $id);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian akhir hayat tidak ditemukan.');
            return null;
        }
        $data = $this->buildData($entry);
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'akhir-hayat-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Pengkajian Akhir Hayat">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'id')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'id')" title="Pengkajian Akhir Hayat"
                :date="data_get($entri, 'form.tglAsesmen')"
                :sub="(data_get($entri, 'form.jenisAsesmen') === 'ulang' ? 'Asesmen ulang' : 'Asesmen awal') . (data_get($entri, 'finalized') ? ' · Terkunci' : ' · Draft')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-akhir-hayat-ugd-{{ $rjNo }}" title="Pengkajian Akhir Hayat"
        :subtitle="$selected ? ((data_get($selected, 'form.jenisAsesmen') === 'ulang' ? 'Asesmen ulang' : 'Asesmen awal') . ' · ' . (data_get($selected, 'form.tglAsesmen') ?: '-')) : null"
        :cetakId="data_get($selected, 'id')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
