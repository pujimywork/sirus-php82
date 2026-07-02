<?php
// Viewer read-only "Pemulihan Pasca-Anestesi" — display Rekam Medis RI.
// Print butuh opsi Aldrete & Bromage (konstanta EMR) → dikirim via $extra.

use Livewire\Component;
use Illuminate\Support\Str;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pasca-anestesi-ri.cetak-pasca-anestesi-ri-print';
    private string $filePrefix = 'pasca-anestesi-ri';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

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
            $this->dispatch('toast', type: 'error', message: 'Data pasca-anestesi tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->opsiSkor());
        $this->dispatch('open-modal', name: "view-pasca-anestesi-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->opsiSkor());
    }

    /** Opsi Aldrete & Bromage (cerminan konstanta komponen EMR pasca-anestesi). */
    private function opsiSkor(): array
    {
        return [
            'aldreteItems' => [
                'kesadaran' => ['label' => 'Kesadaran', 'opsi' => ['2' => 'Sadar penuh', '1' => 'Bangun bila dipanggil', '0' => 'Tidak ada respon']],
                'pernafasan' => ['label' => 'Pernafasan', 'opsi' => ['2' => 'Nafas dalam & batuk bebas', '1' => 'Dangkal / sesak', '0' => 'Apnea / nafas dibantu']],
                'sirkulasi' => ['label' => 'Sirkulasi (TD)', 'opsi' => ['2' => '±20% nilai pra-op', '1' => '±20–50% nilai pra-op', '0' => '>50% nilai pra-op']],
                'aktivitas' => ['label' => 'Aktivitas / Pergerakan', 'opsi' => ['2' => 'Gerak 4 ekstremitas', '1' => 'Gerak 2 ekstremitas', '0' => 'Tidak dapat bergerak']],
                'warnaKulit' => ['label' => 'Warna Kulit / SpO2', 'opsi' => ['2' => 'Merah muda / SpO2 >92% udara kamar', '1' => 'Pucat / perlu O2', '0' => 'Sianosis']],
            ],
            'bromageOptions' => [
                '3' => 'Gerakan penuh tungkai',
                '2' => 'Mampu memfleksikan lutut',
                '1' => 'Tidak mampu memfleksikan pergelangan kaki',
                '0' => 'Tidak mampu menggerakkan tungkai',
            ],
        ];
    }
};
?>

<div>
    <x-border-form title="Pemulihan Pasca-Anestesi (Aldrete/Bromage)">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Pemulihan Pasca-Anestesi"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="'Jenis anestesi: ' . (data_get($entri, 'jenisAnestesi') ?: '-')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-pasca-anestesi-ri-{{ $riHdrNo }}" title="Pemulihan Pasca-Anestesi"
        :subtitle="$selected ? trim((string) data_get($selected, 'jamMasuk') . ' – ' . data_get($selected, 'jamKeluar'), ' –') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
