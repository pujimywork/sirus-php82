<?php
// resources/views/pages/transaksi/ri/idrg/kirim-claim.blade.php
// Step 1: Buat claim baru + set data klaim (iDRG) untuk Rawat Inap

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, iDrgTrait;

    #[On('idrg-claim-ri.generate-number')]
    public function generateNumber(string $riHdrNo): void
    {
        try {
            $res = $this->generateClaimNumber()->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'Generate claim_number gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }
            $claimNumber = $res['response']['claim_number'] ?? null;
            if (empty($claimNumber)) { $this->dispatch('toast', type: 'error', message: 'claim_number kosong.'); return; }

            [$dataRI, , $idrg] = $this->loadData($riHdrNo);
            $idrg['claimNumber'] = $claimNumber;
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Claim number: {$claimNumber}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Generate gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-claim-ri.new')]
    public function new(string $riHdrNo): void
    {
        try {
            [$dataRI, $pasien, $idrg] = $this->loadData($riHdrNo);

            $nomorSep = $idrg['claimNumber'] ?? ($dataRI['sep']['noSep'] ?? '');
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Nomor SEP / claim_number kosong. Pasang SEP VClaim dulu atau generate_claim_number.'); return; }

            $nomorKartu = $pasien['noKartuBpjs'] ?? ($dataRI['noKartu'] ?? '');
            $nomorRm = $dataRI['regNo'] ?? '';
            $namaPasien = $pasien['regName'] ?? ($dataRI['regName'] ?? '');
            $tglLahir = $this->parseBirth($pasien['regBirth'] ?? '');
            $gender = ($pasien['regSex'] ?? 'L') === 'P' ? 2 : 1;

            $res = $this->newClaim($nomorKartu, $nomorSep, $nomorRm, $namaPasien, $tglLahir, $gender)
                ->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'new_claim gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['nomorSep'] = $nomorSep;
            $idrg['patientId'] = $res['response']['patient_id'] ?? null;
            $idrg['admissionId'] = $res['response']['admission_id'] ?? null;
            $idrg['hospitalAdmissionId'] = $res['response']['hospital_admission_id'] ?? null;
            $idrg['createdAt'] = now()->toIso8601String();
            $this->saveResult($riHdrNo, $idrg);

            $this->dispatch('toast', type: 'success', message: "Claim dibuat untuk SEP {$nomorSep}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'new_claim gagal: ' . $e->getMessage());
        }
    }

    /**
     * Set / update discharge_status yang dipakai saat buildClaimDataRI.
     * Dipanggil oleh form dropdown di modal (wire:model.live → dispatch).
     */
    #[On('idrg-claim-ri.set-discharge')]
    public function setDischarge(string $riHdrNo, string $dischargeStatus): void
    {
        try {
            [, , $idrg] = $this->loadData($riHdrNo);
            $idrg['dischargeStatus'] = $dischargeStatus;
            $this->saveResult($riHdrNo, $idrg);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set discharge_status gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-claim-ri.set-data')]
    public function setData(string $riHdrNo, ?array $claimData = null): void
    {
        try {
            [$dataRI, $pasien, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat (new_claim dulu).'); return; }

            // Auto-build payload dari data RI kalau caller tidak kirim
            if (empty($claimData)) {
                $claimData = $this->buildClaimDataRI($riHdrNo, $dataRI, $pasien, $idrg, $nomorSep);
            }

            $res = $this->setClaimData($nomorSep, $claimData)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'set_claim_data gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $idrg['claimData'] = $claimData;
            $idrg['claimDataSavedAt'] = now()->toIso8601String();
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Data klaim tersimpan di E-Klaim.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'set_claim_data gagal: ' . $e->getMessage());
        }
    }

    /**
     * Susun payload set_claim_data untuk RI (rawat inap).
     * Mapping tarif_rs mengikuti keputusan user (konfirmasi 2026-04-21):
     *   - room + commonService + perawatan     → kamar
     *   - ok                                   → prosedur_bedah
     *   - jasaMedis                            → prosedur_non_bedah
     *   - konsul + adminAge + adminStatus
     *     + trfUgdRj                           → konsultasi
     *   - jasaDokter                           → tenaga_ahli
     *   - lainLain                             → penunjang
     *   - rad                                  → radiologi
     *   - lab                                  → laboratorium
     *   - obatPinjam + bonResep − rtnObat      → obat
     *
     * jenis_rawat = '1' (inap).
     * kelas_rawat = class_id kamar terakhir pasien (fallback '3').
     * discharge_status = dari form user ($idrg.dischargeStatus, default '1').
     */
    private function buildClaimDataRI(string $riHdrNo, array $dataRI, array $pasien, array $idrg, string $nomorSep): array
    {
        $cost      = $this->calculateRICosts((int) $riHdrNo);
        $dates     = $this->riClaimDates((int) $riHdrNo);
        $kelas     = $this->lastKamarClassIdRI((int) $riHdrNo) ?: '3';
        $discharge = (string) ($idrg['dischargeStatus'] ?? '1');

        $obatTotal = max(0, $cost['obatPinjam'] + $cost['bonResep'] - $cost['rtnObat']);

        return [
            'nomor_sep'        => $nomorSep,
            'nomor_kartu'      => $pasien['noKartuBpjs'] ?? ($dataRI['noKartu'] ?? ''),
            'tgl_masuk'        => $dates['tglMasuk'],
            'tgl_pulang'       => $dates['tglPulang'],
            'cara_masuk'       => 'gp',
            'jenis_rawat'      => '1',
            'kelas_rawat'      => (string) $kelas,
            'discharge_status' => $discharge,
            'nomor_kartu_t'    => 'kartu_jkn',
            'tarif_rs' => [
                'prosedur_non_bedah' => (string) $cost['jasaMedis'],
                'prosedur_bedah'     => (string) $cost['ok'],
                'konsultasi'         => (string) ($cost['konsul'] + $cost['adminAge'] + $cost['adminStatus'] + $cost['trfUgdRj']),
                'tenaga_ahli'        => (string) $cost['jasaDokter'],
                'keperawatan'        => '0',
                'penunjang'          => (string) $cost['lainLain'],
                'radiologi'          => (string) $cost['rad'],
                'laboratorium'       => (string) $cost['lab'],
                'pelayanan_darah'    => '0',
                'rehabilitasi'       => '0',
                'kamar'              => (string) ($cost['room'] + $cost['commonService'] + $cost['perawatan']),
                'rawat_intensif'     => '0',
                'obat'               => (string) $obatTotal,
                'obat_kronis'        => '0',
                'obat_kemoterapi'    => '0',
                'alkes'              => '0',
                'bmhp'               => '0',
                'sewa_alat'          => '0',
            ],
        ];
    }

    #[On('idrg-claim-ri.delete')]
    public function delete(string $riHdrNo, ?string $coderNik = null): void
    {
        try {
            [, $pasien, $idrg] = $this->loadData($riHdrNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) { $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.'); return; }

            // coder = emp_id user aktif (pola kasir-ri)
            $coderNik = $coderNik ?: (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }

            $res = $this->deleteClaim($nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: 'delete_claim gagal: ' . ($res['metadata']['message'] ?? '-'));
                return;
            }

            $this->saveResult($riHdrNo, []);
            $this->dispatch('toast', type: 'success', message: "Klaim {$nomorSep} dihapus.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'delete_claim gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $riHdrNo): array
    {
        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) throw new \RuntimeException('Data RI tidak ditemukan.');
        $pasienData = $this->findDataMasterPasien($dataRI['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        return [$dataRI, $pasien, $dataRI['idrg'] ?? []];
    }

    private function saveResult(string $riHdrNo, array $idrg): void
    {
        DB::transaction(function () use ($riHdrNo, $idrg) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI((int) $riHdrNo, $data);
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
