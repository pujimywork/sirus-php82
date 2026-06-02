<?php

/**
 * scan-wajah-frista.blade.php
 *
 * Komponen invisible untuk SCAN WAJAH peserta BPJS via local agent
 * (sirus-frista-agent.exe @ http://localhost:9998).
 *
 * Flow:
 *   Trigger event 'scan-wajah-frista.buka' dengan regNo
 *   → resolve No. Kartu BPJS pasien (MasterPasienTrait)
 *   → dispatch 'scan-wajah-frista.send-to-agent' ke browser
 *   → JS listener fetch ke http://localhost:9998/buka-frista
 *   → agent buka FRISTA + auto-login + ketik No. Kartu peserta
 *
 * Komponen ini invisible (rendered as <div> kosong + <script>).
 * Mount sekali per halaman: <livewire:pages::components.rekam-medis.frista.scan-wajah-frista wire:key="..." />
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use MasterPasienTrait, VclaimTrait;

    #[On('scan-wajah-frista.buka')]
    public function buka(string $regNo): void
    {
        $pasienData = $this->findDataMasterPasien($regNo);
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return;
        }

        $idbpjs = trim($pasienData['pasien']['identitas']['idbpjs'] ?? '');
        if ($idbpjs === '') {
            $this->dispatch('toast', type: 'warning', message: 'No. Kartu BPJS pasien kosong.');
            return;
        }

        // Validasi peserta ke BPJS (VCLAIM) — server-side PHP. Sekaligus ambil
        // No. Kartu resmi dari BPJS supaya yang diketik ke FRISTA pasti valid.
        $tgl = Carbon::now()->format('Y-m-d');
        $response = $this->peserta_nomorkartu($idbpjs, $tgl)->getOriginalContent();

        if (($response['metadata']['code'] ?? null) != 200) {
            $this->dispatch('toast', type: 'error',
                message: $response['metadata']['message'] ?? 'Gagal memvalidasi peserta BPJS.');
            return;
        }

        $peserta = $response['response']['peserta'] ?? [];
        $noKartu = $peserta['noKartu'] ?? $idbpjs;

        $this->dispatch('scan-wajah-frista.send-to-agent', bpjsId: $noKartu);
    }
};

?>

<div>
    {{-- JS listener: terima event dari Livewire → fetch ke local frista agent --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('scan-wajah-frista.send-to-agent', async ({ bpjsId }) => {
                try {
                    const res = await fetch('http://localhost:9998/buka-frista', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ bpjsId })
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
                        message: 'Frista agent tidak aktif di PC ini. Pastikan SirusFristaAgent jalan (cek http://localhost:9998/health).'
                    });
                }
            });
        });
    </script>
</div>
