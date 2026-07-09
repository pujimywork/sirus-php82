<?php
// Viewer read-only "Permintaan Pelayanan Kerohaniawan" — display Rekam Medis RI.
// Pembeda entri = signatureDate (bukan createdAt).

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

    private string $printView = 'permintaan-kerohanian-ri.cetak-permintaan-kerohanian-ri-print';
    private string $filePrefix = 'permintaan-kerohanian-ri';
    private string $ttdKey = 'ttdPetugasPath';
    private ?string $ttdCodeField = 'petugasCode';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'signatureDate';
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('signatureDate', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data permintaan kerohaniawan tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-permintaan-kerohanian-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('signatureDate', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
    }
};
?>

<div>
    <x-border-form title="Permintaan Pelayanan Kerohaniawan">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signatureDate')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'signatureDate')" :title="data_get($entri, 'pemohonNama') ?: 'Permintaan Kerohaniawan'"
                :date="data_get($entri, 'signatureDate')"
                :sub="filled(data_get($entri, 'agama')) ? ('Agama: ' . data_get($entri, 'agama')) : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-permintaan-kerohanian-ri-{{ $riHdrNo }}" title="Permintaan Pelayanan Kerohaniawan"
        :subtitle="$selected ? ((data_get($selected, 'pemohonNama') ?: '-') . ' · ' . (data_get($selected, 'agama') ?: '-')) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
