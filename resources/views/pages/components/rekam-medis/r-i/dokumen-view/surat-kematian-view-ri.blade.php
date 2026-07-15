<?php
// Viewer read-only "Surat Keterangan Kematian" — display Rekam Medis RI (objek tunggal).
// Hanya dirender bila status pulang Perencanaan = Meninggal (gate ada di cetak-rekam-medis-open RI).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $surat = [];
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.surat-kematian-ri.cetak-surat-kematian-ri-print';

    public function mount(?string $riHdrNo = null, array $surat = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->surat = $surat ?? [];
    }

    public function lihat(?string $id = null): void
    {
        $data = $this->buatData();
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-surat-kematian-ri-{$this->riHdrNo}");
    }

    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'surat-kematian-ri-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataRI = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $form = $dataRI['suratKematianRI'] ?? null;
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Surat Keterangan Kematian belum tersedia.');
            return null;
        }

        // Nomor & tanggal ikut Perencanaan (sumber yang dikirim ke BPJS), bukan salinan
        // di record surat — konsisten dengan rm-surat-kematian-ri-actions.
        $tindak = $dataRI['perencanaan']['tindakLanjut'] ?? [];
        $form['nomorSurat'] = $tindak['noSuratMeninggal'] ?? '';
        $form['tanggalMeninggal'] = $tindak['tglMeninggal'] ?? '';

        $pasien = $this->dvPasien($dataRI['regNo'] ?? '');
        return array_merge($pasien, [
            'dataRI' => $dataRI,
            'form' => $form,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdDokterPath' => $this->dvTtdPath($form['dokterPenerangCode'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }
};
?>

<div>
    <x-border-form title="Surat Keterangan Kematian">
        @if (filled($surat))
            <x-rm.doc-list-row wire:key="sk-ri-{{ $riHdrNo }}" id="sk" title="Surat Keterangan Kematian"
                :date="data_get($surat, 'createdAt')"
                :sub="data_get($surat, 'isFinal')
                    ? 'Dokter: ' . (data_get($surat, 'dokterPenerang') ?: '-')
                    : 'Draft — belum ditandatangani dokter'" />
        @else
            {{-- Status pulang Meninggal tapi surat belum dibuat. RM read-only — sebut di mana
                 mengisinya, jangan cuma menulis "belum diisi". --}}
            <div
                class="flex items-start gap-2.5 px-3 py-2.5 text-base border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                    <strong>Belum dibuat.</strong> Status pulang pasien Meninggal, tetapi Surat Keterangan Kematian
                    belum diterbitkan. Buat lewat <strong>EMR Rawat Inap › Modul Dokumen › Surat Kematian</strong>.
                </span>
            </div>
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-surat-kematian-ri-{{ $riHdrNo }}" title="Surat Keterangan Kematian"
        :subtitle="filled($surat) ? (data_get($surat, 'dokterPenerangDate') ?: null) : null"
        :showCetak="filled($surat)" :previewHtml="$previewHtml" />
</div>
