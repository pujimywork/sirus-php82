<?php
// resources/views/pages/transaksi/rj/idrg/kirim-final-klaim.blade.php
// Step 6: Final Klaim + Kirim + Cetak + Status + Re-edit
// Kriteria 20-24

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, iDrgTrait;

    #[On('idrg-klaim-rj.final')]
    public function final(string $rjNo, ?string $coderNik = null): void
    {
        try {
            [, $pasien, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            // Kriteria 20: klaim final hanya setelah INACBG final
            if (empty($idrg['inacbgFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'INACBG harus final terlebih dahulu.');
                return;
            }

            $coderNik = $coderNik ?: ($pasien['identitas']['nik'] ?? '');
            if (empty($coderNik)) { $this->dispatch('toast', type: 'error', message: 'NIK coder tidak ditemukan di master pasien.'); return; }

            $res = $this->finalClaim($nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'claim_final gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['klaimFinal'] = true;
            $idrg['klaimFinalAt'] = now()->toIso8601String();
            $idrg['coderNik'] = $coderNik;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Klaim final.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final klaim gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-rj.reedit')]
    public function reedit(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->reeditClaim($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'reedit_claim gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['klaimFinal'] = false;
            $idrg['klaimFinalAt'] = null;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Klaim dibuka untuk edit ulang.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit klaim gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-rj.send')]
    public function send(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            // Kriteria 22: kirim hanya setelah klaim final
            if (empty($idrg['klaimFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'Klaim harus final terlebih dahulu.');
                return;
            }

            $res = $this->sendClaimIndividual($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'send_claim_individual gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['sendResult'] = $res['response']['data'][0] ?? [];
            $idrg['sentAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Klaim terkirim ke data center.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Kirim klaim gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-rj.print')]
    public function print(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }
            // Kriteria 23: cetak hanya setelah klaim final
            if (empty($idrg['klaimFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'Klaim harus final terlebih dahulu.');
                return;
            }

            $res = $this->printClaim($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'claim_print gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            // Response: response.data adalah base64 PDF
            $pdfBase64 = $res['response']['data'] ?? ($res['response'] ?? null);
            if (empty($pdfBase64) || !is_string($pdfBase64)) {
                $this->dispatch('toast', type: 'error', message: 'Response cetak tidak berisi PDF.');
                return;
            }
            // Dispatch ke parent untuk unduh / tampilkan PDF
            $this->dispatch('idrg-klaim-rj.pdf-ready', ['nomorSep' => $nomorSep, 'base64' => $pdfBase64]);
            $this->dispatch('toast', type: 'success', message: 'PDF klaim siap.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Cetak klaim gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-rj.get-data')]
    public function getData(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getClaimData($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'get_claim_data gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['claimSnapshot'] = $res['response']['data'] ?? ($res['response'] ?? []);
            $idrg['claimSnapshotAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('idrg-klaim-rj.data-loaded', $idrg['claimSnapshot']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get claim data gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-rj.get-status')]
    public function getStatus(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getClaimStatus($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'get_claim_status gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['claimStatus'] = $res['response'] ?? [];
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'info', message: 'Status: ' . ($idrg['claimStatus']['nmStatusSep'] ?? '-'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get claim status gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) throw new \RuntimeException('Data RJ tidak ditemukan.');
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        return [$dataRJ, $pasien, $dataRJ['idrg'] ?? []];
    }

    private function saveResult(string $rjNo, array $idrg): void
    {
        DB::transaction(function () use ($rjNo, $idrg) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($rjNo, $data);
        });
    }
};
?>
<div></div>
