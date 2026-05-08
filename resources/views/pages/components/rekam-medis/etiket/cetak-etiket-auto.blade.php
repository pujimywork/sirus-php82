<?php

/**
 * cetak-etiket-auto.blade.php
 *
 * Sibling component dari cetak-etiket.blade.php (yang download PDF).
 * Komponen ini khusus untuk AUTO-PRINT silent via local print agent
 * (sirus-print-agent.exe @ http://localhost:9999).
 *
 * Flow:
 *   Trigger event 'cetak-etiket-auto.print' dengan regNo
 *   → load data pasien
 *   → generate PDF (template sama dengan cetak-etiket-print.blade.php)
 *   → encode base64
 *   → dispatch 'cetak-etiket-auto.send-to-agent' ke browser
 *   → JS listener fetch ke http://localhost:9999/cetak-pdf
 *   → agent decode + simpan temp + print silent ke printer "etiket"
 *
 * Komponen ini invisible (rendered as <div> kosong + <script>).
 * Mount sekali per halaman: <livewire:pages::components.rekam-medis.etiket.cetak-etiket-auto wire:key="..." />
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use MasterPasienTrait;

    public ?string $regNo = null;

    #[On('cetak-etiket-auto.print')]
    public function print(string $regNo): void
    {
        $this->regNo = $regNo;

        $pasienData = $this->findDataMasterPasien($regNo);
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return;
        }

        $dataPasien = $pasienData['pasien'];

        // Hitung umur realtime
        if (!empty($dataPasien['tglLahir'])) {
            $dataPasien['thn'] = Carbon::createFromFormat('d/m/Y', $dataPasien['tglLahir'])
                ->diff(Carbon::now(env('APP_TIMEZONE')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        $pdf = Pdf::loadView('pages.components.rekam-medis.etiket.cetak-etiket-print', [
            'data' => $dataPasien,
        ])->setPaper([0, 0, 170.08, 113.39]); // 6cm x 4cm dalam points

        // Base64-encode PDF supaya dikirim langsung lewat HTTP body ke local agent.
        // Tidak butuh share/UNC — agent decode di sisi PC user, simpan ke temp,
        // lalu print silent via SumatraPDF.
        $base64 = base64_encode($pdf->output());
        $filename = 'etiket-' . ($dataPasien['regNo'] ?? $regNo) . '.pdf';

        $this->dispatch(
            'cetak-etiket-auto.send-to-agent',
            pdfBase64: $base64,
            printerKey: 'etiket',
            filename: $filename,
        );
    }
};

?>

<div>
    {{-- JS listener: terima event dari Livewire → fetch ke local print agent --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('cetak-etiket-auto.send-to-agent', async ({ pdfBase64, printerKey, filename }) => {
                try {
                    const res = await fetch('http://localhost:9999/cetak-pdf', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ pdfBase64, printerKey, filename })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        Livewire.dispatch('toast', { type: 'success', message: data.msg });
                    } else {
                        Livewire.dispatch('toast', { type: 'error', message: data.msg });
                    }
                } catch (e) {
                    Livewire.dispatch('toast', {
                        type: 'error',
                        message: 'Print agent tidak aktif di PC ini. Pastikan service SirusPrintAgent jalan (cek http://localhost:9999/health).'
                    });
                }
            });
        });
    </script>
</div>
