<?php
// resources/views/pages/transaksi/rj/idrg/kirim-claim.blade.php
// Step 1: Buat claim baru + set data klaim (iDRG)

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, iDrgTrait;

    #[On('idrg-claim-rj.generate-number')]
    public function generateNumber(string $rjNo): void
    {
        try {
            $res = $this->generateClaimNumber()->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Generate Nomor Klaim'));
                return;
            }
            $claimNumber = $res['response']['claim_number'] ?? null;
            if (empty($claimNumber)) { $this->dispatch('toast', type: 'error', message: 'claim_number kosong.'); return; }

            [$dataRJ, , $idrg] = $this->loadData($rjNo);
            $idrg['claimNumber'] = $claimNumber;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Claim number: {$claimNumber}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Generate gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-claim-rj.new')]
    public function new(string $rjNo): void
    {
        try {
            [$dataRJ, $pasien, $idrg] = $this->loadData($rjNo);

            $nomorSep = $idrg['claimNumber'] ?? ($dataRJ['sep']['noSep'] ?? '');
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Nomor SEP / claim_number kosong. Pasang SEP VClaim dulu atau generate_claim_number.'); return; }

            $nomorKartu = data_get($pasien, 'identitas.idbpjs')
                ?: data_get($dataRJ, 'sep.resSep.peserta.noKartu')
                ?: data_get($dataRJ, 'sep.reqSep.t_sep.noKartu')
                ?: '';
            $nomorRm = $dataRJ['regNo'] ?? '';
            $namaPasien = $pasien['regName'] ?? ($dataRJ['regName'] ?? '');
            $tglLahir = $this->parseBirth($pasien['regBirth'] ?? '');
            $gender = ($pasien['regSex'] ?? 'L') === 'P' ? 2 : 1;

            $res = $this->newClaim($nomorKartu, $nomorSep, $nomorRm, $namaPasien, $tglLahir, $gender)
                ->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Buat Klaim Baru'));
                return;
            }

            $idrg['nomorSep'] = $nomorSep;
            $idrg['patientId'] = $res['response']['patient_id'] ?? null;
            $idrg['admissionId'] = $res['response']['admission_id'] ?? null;
            $idrg['hospitalAdmissionId'] = $res['response']['hospital_admission_id'] ?? null;
            $idrg['createdAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);

            $this->dispatch('toast', type: 'success', message: "Claim dibuat untuk SEP {$nomorSep}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'new_claim gagal: ' . $e->getMessage());
        }
    }

    // setData → moved to SFC kirim-set-data.blade.php (form-style editor)

    #[On('idrg-claim-rj.delete')]
    public function delete(string $rjNo, ?string $coderNik = null): void
    {
        try {
            [, $pasien, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            // coder = emp_id user aktif (pola kasir). User harus login & punya emp_id.
            $coderNik = $coderNik ?: (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }

            $res = $this->deleteClaim($nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Hapus Klaim'));
                return;
            }

            $this->saveResult($rjNo, []);
            $this->dispatch('toast', type: 'success', message: "Klaim {$nomorSep} dihapus.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'delete_claim gagal: ' . $e->getMessage());
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

        // Notify parent component (daftar-rj-actions) to reload state & re-render modal.
        $this->dispatch('idrg-state-updated', rjNo: (string) $rjNo);
    }

    private function parseBirth(string $str): string
    {
        if (empty($str)) return Carbon::now()->format('Y-m-d H:i:s');
        try { return Carbon::createFromFormat('d/m/Y', $str)->format('Y-m-d H:i:s'); } catch (\Throwable) {
            try { return Carbon::parse($str)->format('Y-m-d H:i:s'); } catch (\Throwable) { return Carbon::now()->format('Y-m-d H:i:s'); }
        }
    }
};
?>
<div></div>
