<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-claim.blade.php
// Step 1: Buat claim baru + set data klaim (iDRG) — UGD

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, iDrgTrait;

    #[On('idrg-claim-ugd.generate-number')]
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

            [$dataUGD, , $idrg] = $this->loadData($rjNo);
            $idrg['claimNumber'] = $claimNumber;
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Claim number: {$claimNumber}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Generate gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-claim-ugd.new')]
    public function new(string $rjNo): void
    {
        try {
            [$dataUGD, $pasien, $idrg] = $this->loadData($rjNo);

            $nomorSep = $idrg['claimNumber'] ?? ($dataUGD['sep']['noSep'] ?? '');
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Nomor SEP / claim_number kosong. Pasang SEP VClaim dulu atau generate_claim_number.'); return; }

            $nomorKartu = $pasien['noKartuBpjs'] ?? ($dataUGD['noKartu'] ?? '');
            $nomorRm = $dataUGD['regNo'] ?? '';
            $namaPasien = $pasien['regName'] ?? ($dataUGD['regName'] ?? '');
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

    #[On('idrg-claim-ugd.set-data')]
    public function setData(string $rjNo, ?array $claimData = null): void
    {
        try {
            [$dataUGD, $pasien, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat (new_claim dulu).'); return; }

            // Auto-build payload dari data UGD kalau caller tidak kirim
            if (empty($claimData)) {
                $claimData = $this->buildClaimDataUGD($rjNo, $dataUGD, $pasien, $nomorSep);
            }

            $res = $this->setClaimData($nomorSep, $claimData)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Simpan Data Klaim'));
                return;
            }

            $idrg['claimData'] = $claimData;
            $idrg['claimDataSavedAt'] = now()->toIso8601String();
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Data klaim tersimpan di E-Klaim.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'set_claim_data gagal: ' . $e->getMessage());
        }
    }

    /**
     * Susun payload set_claim_data untuk UGD (rajal — UGD yang dirawat inap
     * di-klaim dari modul RI, bukan dari sini).
     * Mapping tarif_rs: sama persis dengan RJ karena key calculateUGDCosts identik.
     */
    private function buildClaimDataUGD(string $rjNo, array $dataUGD, array $pasien, string $nomorSep): array
    {
        $cost = $this->calculateUGDCosts((int) $rjNo);
        $rjDate = $this->parseRjDate($dataUGD['rjDate'] ?? '');

        return [
            'nomor_sep'        => $nomorSep,
            'nomor_kartu'      => $pasien['noKartuBpjs'] ?? ($dataUGD['noKartu'] ?? ''),
            'tgl_masuk'        => $rjDate,
            'tgl_pulang'       => $rjDate,
            'cara_masuk'       => 'gp',
            'jenis_rawat'      => '2',
            'kelas_rawat'      => '3',
            'discharge_status' => '1',
            'nomor_kartu_t'    => 'kartu_jkn',
            'tarif_rs' => [
                'prosedur_non_bedah' => (string) $cost['actePrice'],
                'prosedur_bedah'     => '0',
                'konsultasi'         => (string) ($cost['poliPrice'] + $cost['rsAdmin'] + $cost['rjAdmin']),
                'tenaga_ahli'        => (string) $cost['actdPrice'],
                'keperawatan'        => '0',
                'penunjang'          => (string) ($cost['actpPrice'] + $cost['other']),
                'radiologi'          => (string) $cost['rad'],
                'laboratorium'       => (string) $cost['lab'],
                'pelayanan_darah'    => '0',
                'rehabilitasi'       => '0',
                'kamar'              => '0',
                'rawat_intensif'     => '0',
                'obat'               => (string) $cost['obat'],
                'obat_kronis'        => '0',
                'obat_kemoterapi'    => '0',
                'alkes'              => '0',
                'bmhp'               => '0',
                'sewa_alat'          => '0',
            ],
        ];
    }

    private function parseRjDate(string $str): string
    {
        if (empty($str)) return Carbon::now()->format('Y-m-d H:i:s');
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $str)->format('Y-m-d H:i:s'); } catch (\Throwable) {
            try { return Carbon::parse($str)->format('Y-m-d H:i:s'); } catch (\Throwable) { return Carbon::now()->format('Y-m-d H:i:s'); }
        }
    }

    #[On('idrg-claim-ugd.delete')]
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
