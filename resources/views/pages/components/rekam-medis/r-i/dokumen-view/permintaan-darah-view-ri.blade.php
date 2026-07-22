<?php
// Viewer read-only "Formulir Permintaan Darah" — display Rekam Medis RI.
// Pembeda entri = id (uuid). Payload cetak bespoke (entry + opsiLabel).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\PermintaanDarahOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.permintaan-darah-ri.cetak-permintaan-darah-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'id';
    }

    private function buildData(array $entry): array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');

        return array_merge($pasien, [
            'dataRi' => $dataRi,
            'entry' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdDokterPath' => $this->dvTtdPath(data_get($entry, 'form.ttd.dokterCode')),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            'opsiLabel' => PermintaanDarahOptions::labels(),
        ]);
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('id', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data permintaan darah tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData($this->selected));
        $this->dispatch('open-modal', name: "view-permintaan-darah-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        $entry = collect($this->list)->firstWhere('id', $id);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data permintaan darah tidak ditemukan.');
            return null;
        }
        $data = $this->buildData($entry);
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'permintaan-darah-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Formulir Permintaan Darah">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'id')))->values() as $entri)
            @php
                $jenis = collect(\App\Support\PermintaanDarahOptions::JENIS)
                    ->filter(fn($l, $k) => !empty(data_get($entri, "form.jenisDarah.$k.pilih")))
                    ->values()->implode(', ');
            @endphp
            <x-rm.doc-list-row :id="data_get($entri, 'id')" title="Permintaan Darah"
                :date="data_get($entri, 'form.tglPermintaan')"
                :sub="($jenis ?: '-') . (data_get($entri, 'finalized') || filled(data_get($entri, 'form.ttd.dokterNama')) ? ' · Terkunci' : ' · Draft')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-permintaan-darah-ri-{{ $riHdrNo }}" title="Formulir Permintaan Darah"
        :subtitle="$selected ? (data_get($selected, 'form.tglPermintaan') ?: '-') : null"
        :cetakId="data_get($selected, 'id')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
