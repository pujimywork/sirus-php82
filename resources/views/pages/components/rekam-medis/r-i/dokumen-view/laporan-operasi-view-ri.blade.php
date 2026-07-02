<?php
// Viewer read-only "Laporan Operasi (BAP)" — display Rekam Medis RI.
// Lihat = preview HTML dokumen cetak (iframe); Cetak = PDF. Keduanya payload sama.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'laporan-operasi-ri.cetak-laporan-operasi-ri-print';
    private string $filePrefix = 'laporan-operasi-ri';
    private string $ttdKey = 'ttdOperatorPath';
    private ?string $ttdCodeField = 'operatorTtdCode';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data laporan operasi tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-laporan-operasi-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
    }
};
?>

<div>
    <x-border-form title="Laporan Operasi (BAP)">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" :title="data_get($entri, 'jenisTindakan') ?: 'Laporan Operasi'"
                :date="data_get($entri, 'tanggalOperasi')" :sub="'Operator: ' . (data_get($entri, 'namaOperator') ?: '-')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-laporan-operasi-ri-{{ $riHdrNo }}" title="Laporan Operasi (BAP)"
        :subtitle="$selected ? ((data_get($selected, 'tanggalOperasi') ?: '-') . ' · ' . (data_get($selected, 'namaOperator') ?: '-')) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
