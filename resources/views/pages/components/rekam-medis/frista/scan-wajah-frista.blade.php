<?php

/**
 * scan-wajah-frista.blade.php
 *
 * Komponen invisible untuk SCAN WAJAH peserta BPJS via local agent
 * (sirus-frista-agent.exe @ http://127.0.0.1:9998).
 *
 * Flow:
 *   Trigger event 'scan-wajah-frista.buka' dengan regNo
 *   → resolve No. Kartu BPJS pasien (MasterPasienTrait)
 *   → dispatch 'scan-wajah-frista.send-to-agent' ke browser
 *   → JS listener fetch ke http://127.0.0.1:9998/buka-frista
 *   → agent buka FRISTA + auto-login + ketik No. Kartu peserta
 *
 * Komponen ini invisible (rendered as <div> kosong + <script>).
 * Mount sekali per halaman: <livewire:pages::components.rekam-medis.frista.scan-wajah-frista wire:key="..." />
 */

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use MasterPasienTrait;

    #[On('scan-wajah-frista.buka')]
    public function buka(string $regNo): void
    {
        // Bungkus seluruh alur: tangkap \Throwable (Error/TypeError/QueryException),
        // bukan hanya \Exception, supaya kegagalan apa pun (DB down, BPJS, dll.)
        // SELALU jadi toast — tidak pernah 500 senyap "tidak terjadi apa-apa".
        try {
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

            // No. Kartu BPJS langsung dari database (tanpa validasi VCLAIM).
            $this->dispatch('scan-wajah-frista.send-to-agent', bpjsId: $idbpjs);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error',
                message: 'Gagal memproses Scan Wajah: ' . $e->getMessage());
        }
    }
};

?>

<div>
    {{-- JS listener: terima event dari Livewire → fetch ke local frista agent --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('scan-wajah-frista.send-to-agent', async ({ bpjsId }) => {
                try {
                    const res = await fetch('http://127.0.0.1:9998/buka-frista', {
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
                        message: 'Frista agent tidak aktif di PC ini. Pastikan SirusFristaAgent jalan (cek http://127.0.0.1:9998/health).'
                    });
                }
            });
        });
    </script>
</div>
