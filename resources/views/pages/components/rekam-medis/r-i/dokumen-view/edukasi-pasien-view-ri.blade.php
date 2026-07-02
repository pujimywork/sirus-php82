<?php
// Viewer read-only "Edukasi Pasien" — display Rekam Medis RI (pembeda = index array).

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
    public ?int $selectedIndex = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.edukasi-pasien.cetak-edukasi-pasien-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
    }

    public function lihat(string $id): void
    {
        $data = $this->buatData((int) $id);
        if (!$data) {
            return;
        }
        $this->selectedIndex = (int) $id;
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-edukasi-pasien-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        $data = $this->buatData((int) $id);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-pasien-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    /* Navigasi Prev/Next berbasis index (override trait yg berbasis field). */
    private function navIndeksList(): array
    {
        $ids = [];
        foreach ($this->list as $indeks => $edu) {
            if (filled(data_get($edu, 'tglEdukasi')) || filled(data_get($edu, 'sasaranEdukasi'))) {
                $ids[] = (string) $indeks;
            }
        }
        return $ids;
    }

    public function navTotal(): int
    {
        return count($this->navIndeksList());
    }

    public function navPos(): int
    {
        $pos = array_search((string) ($this->selectedIndex ?? ''), $this->navIndeksList(), true);
        return $pos === false ? 0 : $pos + 1;
    }

    public function prevRecord(): void
    {
        $ids = $this->navIndeksList();
        $pos = array_search((string) ($this->selectedIndex ?? ''), $ids, true);
        if ($pos !== false && $pos > 0) {
            $this->lihat($ids[$pos - 1]);
        }
    }

    public function nextRecord(): void
    {
        $ids = $this->navIndeksList();
        $pos = array_search((string) ($this->selectedIndex ?? ''), $ids, true);
        if ($pos !== false && $pos < count($ids) - 1) {
            $this->lihat($ids[$pos + 1]);
        }
    }

    private function buatData(int $index): ?array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $entry = ($dataRi['edukasiPasien'] ?? [])[$index] ?? null;
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');
        return array_merge($pasien, [
            'dataRi' => $dataRi,
            'entry' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPetugasPath' => $this->dvTtdPath($entry['petugasEdukasiCode'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-border-form title="Edukasi Pasien">
        @php $ada = false; @endphp
        @foreach ($list as $indeks => $edu)
            @if (filled(data_get($edu, 'tglEdukasi')) || filled(data_get($edu, 'sasaranEdukasi')))
                @php
                    $ada = true;
                    $materi = (string) data_get($edu, 'edukasi.materiTopikEdukasi', '');
                    $kategori = collect(data_get($edu, 'edukasi.kategoriEdukasi', []))->flatten()->filter(fn($item) => is_scalar($item))->implode(', ');
                @endphp
                <x-rm.doc-list-row :id="$indeks" :title="$materi ?: ($kategori ?: 'Edukasi')" :date="data_get($edu, 'tglEdukasi')"
                    :sub="'Sasaran: ' . (data_get($edu, 'sasaranEdukasi') ?: '-') . ' · Petugas: ' . (data_get($edu, 'petugasEdukasi') ?: '-')" />
            @endif
        @endforeach
        @unless ($ada)
            <x-rm.doc-empty />
        @endunless
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-edukasi-pasien-ri-{{ $riHdrNo }}" title="Edukasi Pasien"
        :cetakId="$selectedIndex !== null ? (string) $selectedIndex : null" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
