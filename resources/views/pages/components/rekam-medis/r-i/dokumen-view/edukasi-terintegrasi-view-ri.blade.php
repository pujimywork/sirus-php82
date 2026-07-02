<?php
// Viewer read-only "Edukasi Terintegrasi" — display Rekam Medis RI.
// Lihat = preview HTML dokumen cetak (iframe) → isi persis tampilan Cetak PDF.

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

    private string $printView = 'pages.components.modul-dokumen.r-i.edukasi-terintegrasi.cetak-edukasi-terintegrasi-ri-print';

    public array $tujuanLabels = [
        'penyakit' => 'Pemahaman penyakit/diagnosis',
        'obat' => 'Penggunaan obat yang aman',
        'nutrisi' => 'Nutrisi & diet',
        'aktivitas' => 'Aktivitas & latihan',
        'perawatanRumah' => 'Perawatan di rumah',
        'pencegahan' => 'Pencegahan komplikasi',
        'lainnya' => 'Lainnya',
    ];

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'id';
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('id', $id) ?: null;
        $data = $this->buatData($id);
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-edukasi-terintegrasi-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        $data = $this->buatData($id);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-terintegrasi-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    private function buatData(string $id): ?array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $entri = collect($dataRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $id);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi terintegrasi tidak ditemukan.');
            return null;
        }

        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');
        $petugasCode = $entri['form']['pemberiInformasi']['petugasCode'] ?? ($entri['created_by']['code'] ?? null);
        return array_merge($pasien, [
            'dataRi' => $dataRi,
            'entry' => $entri,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPetugasPath' => $this->dvTtdPath($petugasCode),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    /** Judul entri = daftar tujuan edukasi (untuk baris list). */
    public function judulTujuan(array $entri): string
    {
        $kunciList = (array) data_get($entri, 'form.tujuan.opsi', []);
        $tujuan = collect($kunciList)
            ->filter(fn($kunci) => is_scalar($kunci))
            ->map(fn($kunci) => $this->tujuanLabels[$kunci] ?? $kunci)
            ->filter()
            ->implode(', ');
        $lainnya = trim((string) data_get($entri, 'form.tujuan.lainnya', ''));
        return trim($tujuan . ($lainnya ? ($tujuan ? ', ' : '') . $lainnya : ''));
    }
};
?>

<div>
    <x-border-form title="Edukasi Terintegrasi">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'id')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'id')" :title="$this->judulTujuan($entri) ?: 'Edukasi Terintegrasi'"
                :date="data_get($entri, 'tglEdukasi')"
                :sub="filled(data_get($entri, 'form.pemberiInformasi.petugasName')) ? 'Petugas: ' . data_get($entri, 'form.pemberiInformasi.petugasName') : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-edukasi-terintegrasi-ri-{{ $riHdrNo }}" title="Edukasi Terintegrasi"
        :subtitle="$selected ? (data_get($selected, 'tglEdukasi') ?: null) : null"
        :cetakId="data_get($selected, 'id')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
