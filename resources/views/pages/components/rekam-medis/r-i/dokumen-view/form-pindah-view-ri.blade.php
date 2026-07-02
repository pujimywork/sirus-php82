<?php
// Viewer read-only "Form Pindah Antar Ruang" — display Rekam Medis RI (pembeda = tglPindah).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.form-pindah-antar-ruang-ri.cetak-form-pindah-antar-ruang-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'tglPindah';
    }

    public function lihat(string $tglPindah): void
    {
        $this->selected = collect($this->list)->firstWhere('tglPindah', $tglPindah) ?: null;
        $data = $this->buatData($tglPindah);
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-form-pindah-ri-{$this->riHdrNo}");
    }

    public function cetak(string $tglPindah): mixed
    {
        $data = $this->buatData($tglPindah);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'form-pindah-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    private function buatData(string $tglPindah): ?array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $pindah = collect($dataRi['formPindahAntarRuangRI'] ?? [])->firstWhere('tglPindah', $tglPindah);
        if (empty($pindah)) {
            $this->dispatch('toast', type: 'error', message: 'Catatan pindah tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');
        return array_merge($pasien, [
            'pindah' => $pindah,
            'dataRI' => $dataRi,
            'identitasRs' => $this->dvIdentitasRs(),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-border-form title="Form Pindah Antar Ruang">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'tglPindah')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'tglPindah')"
                :title="(data_get($entri, 'dariRoomDesc') ?: '-') . ' → ' . (data_get($entri, 'keRoomDesc') ?: '-')"
                :date="data_get($entri, 'tglPindah')"
                :sub="filled(data_get($entri, 'alasanPindah')) ? 'Alasan: ' . data_get($entri, 'alasanPindah') : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-form-pindah-ri-{{ $riHdrNo }}" title="Form Pindah Antar Ruang"
        :subtitle="$selected ? trim((string) (data_get($selected, 'dariRoomDesc') ?: '-') . ' → ' . (data_get($selected, 'keRoomDesc') ?: '-')) : null"
        :cetakId="data_get($selected, 'tglPindah')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
