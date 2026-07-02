<?php
// Viewer read-only "Penundaan / Kelambatan Pelayanan" — display Rekam Medis RI.
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

    private string $printView = 'penundaan-pelayanan-ri.cetak-penundaan-pelayanan-ri-print';
    private string $filePrefix = 'penundaan-pelayanan-ri';
    private string $ttdKey = 'ttdPemberiPath';
    private ?string $ttdCodeField = 'pemberiInfoCode';

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
            $this->dispatch('toast', type: 'error', message: 'Data penundaan pelayanan tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-penundaan-pelayanan-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('signatureDate', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
    }
};
?>

<div>
    <x-border-form title="Penundaan / Kelambatan Pelayanan">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signatureDate')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'signatureDate')" :title="data_get($entri, 'jenis') ?: 'Penundaan Pelayanan'"
                :date="data_get($entri, 'tglPemberitahuan')"
                :sub="filled(data_get($entri, 'alasan')) ? \Illuminate\Support\Str::limit(data_get($entri, 'alasan'), 90) : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-penundaan-pelayanan-ri-{{ $riHdrNo }}" title="Penundaan / Kelambatan Pelayanan"
        :subtitle="$selected ? ((data_get($selected, 'jenis') ?: '-') . ' · ' . (data_get($selected, 'tglPemberitahuan') ?: '-')) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
