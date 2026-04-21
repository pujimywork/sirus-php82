<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-final-klaim.blade.php
// Step 6: Final Klaim + Kirim + Cetak + Status + Re-edit — UGD
// Kriteria 20-24

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, iDrgTrait;

    #[On('idrg-klaim-ugd.final')]
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

            // coder = emp_id user aktif (pola kasir). User harus login & punya emp_id.
            $coderNik = $coderNik ?: (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }

            $res = $this->finalClaim($nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Final Klaim'));
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

    #[On('idrg-klaim-ugd.reedit')]
    public function reedit(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->reeditClaim($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Edit Ulang Klaim'));
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

    #[On('idrg-klaim-ugd.send')]
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
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Kirim Klaim ke Data Center'));
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

    #[On('idrg-klaim-ugd.print')]
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
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cetak Klaim'));
                return;
            }

            // Response: response.data adalah base64 PDF
            $pdfBase64 = $res['response']['data'] ?? ($res['response'] ?? null);
            if (empty($pdfBase64) || !is_string($pdfBase64)) {
                $this->dispatch('toast', type: 'error', message: 'Response cetak tidak berisi PDF.');
                return;
            }
            // Dispatch ke parent untuk unduh / tampilkan PDF
            $this->dispatch('idrg-klaim-ugd.pdf-ready', ['nomorSep' => $nomorSep, 'base64' => $pdfBase64]);
            $this->dispatch('toast', type: 'success', message: 'PDF klaim siap.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Cetak klaim gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-ugd.get-data')]
    public function getData(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getClaimData($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Ambil Data Klaim'));
                return;
            }

            $idrg['claimSnapshot'] = $res['response']['data'] ?? ($res['response'] ?? []);
            $idrg['claimSnapshotAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('idrg-klaim-ugd.data-loaded', $idrg['claimSnapshot']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get claim data gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-klaim-ugd.get-status')]
    public function getStatus(string $rjNo): void
    {
        try {
            [, , $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            $res = $this->getClaimStatus($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cek Status Klaim'));
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
        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) throw new \RuntimeException('Data UGD tidak ditemukan.');
        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        return [$dataUGD, $pasien, $dataUGD['idrg'] ?? []];
    }

    private function saveResult(string $rjNo, array $idrg): void
    {
        DB::transaction(function () use ($rjNo, $idrg) {
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonUGD($rjNo, $data);
        });
    }
};
?>
<div></div>
