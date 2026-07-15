<?php
// Viewer read-only "Surat Keterangan Kematian" — display Rekam Medis UGD (objek tunggal).
// Hanya dirender bila screening menyimpulkan P0 (gate ada di cetak-rekam-medis-open).

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $surat = [];
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.surat-kematian.cetak-surat-kematian-print';

    public function mount(?int $rjNo = null, array $surat = []): void
    {
        $this->rjNo = $rjNo;
        $this->surat = $surat ?? [];
    }

    public function lihat(?string $id = null): void
    {
        $data = $this->buatData();
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-surat-kematian-ugd-{$this->rjNo}");
    }

    public function cetak(?string $id = null): mixed
    {
        $data = $this->buatData();
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'surat-kematian-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }

    private function buatData(): ?array
    {
        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $form = $dataUGD['suratKematianUGD'] ?? null;
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Surat Keterangan Kematian belum tersedia.');
            return null;
        }

        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');
        return array_merge($pasien, [
            'dataUGD' => $dataUGD,
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
            <x-rm.doc-list-row wire:key="sk-ugd-{{ $rjNo }}" id="sk"
                :title="'Surat Keterangan Kematian' . (filled(data_get($surat, 'nomorSurat')) ? ' · No. ' . data_get($surat, 'nomorSurat') : '')"
                :date="data_get($surat, 'tanggalMeninggal')"
                :sub="data_get($surat, 'isFinal')
                    ? 'Dokter: ' . (data_get($surat, 'dokterPenerang') ?: '-')
                    : 'Draft — belum ditandatangani dokter'" />
        @else
            {{-- Pasien P0 tapi surat belum dibuat. Rekam Medis read-only, tapi jangan diam:
                 sebut di mana mengisinya, supaya pembaca tak bingung "P0 kok belum diisi". --}}
            <div
                class="flex items-start gap-2.5 px-3 py-2.5 text-base border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                    <strong>Belum dibuat.</strong> Pasien sudah P0 (Meninggal), tetapi Surat Keterangan Kematian
                    belum diterbitkan. Buat lewat <strong>Pelayanan UGD › Modul Dokumen › Surat Kematian</strong>.
                </span>
            </div>
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-surat-kematian-ugd-{{ $rjNo }}" title="Surat Keterangan Kematian"
        :subtitle="filled($surat) ? (data_get($surat, 'nomorSurat') ?: null) : null"
        :showCetak="filled($surat)" :previewHtml="$previewHtml" />
</div>
