<?php
// Viewer read-only "Penandaan Lokasi Operasi (Site Marking)" — display Rekam Medis RI.

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

    private string $printView = 'site-marking-ri.cetak-site-marking-ri-print';
    private string $filePrefix = 'site-marking-ri';
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
            $this->dispatch('toast', type: 'error', message: 'Data site marking tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-site-marking-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
    }
};
?>

<div>
    <x-border-form title="Penandaan Lokasi Operasi (Site Marking)">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" :title="data_get($entri, 'rencanaTindakan') ?: 'Site Marking'"
                :date="data_get($entri, 'tanggal')"
                :sub="'Lokasi: ' . trim((data_get($entri, 'regionAnatomi') ?: '-') . (filled(data_get($entri, 'sisi')) ? ' / ' . data_get($entri, 'sisi') : ''))" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-site-marking-ri-{{ $riHdrNo }}" title="Penandaan Lokasi Operasi (Site Marking)"
        :subtitle="$selected ? (data_get($selected, 'tanggal') ?: null) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
