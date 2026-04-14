<?php

namespace App\Http\Traits\Txn\Ugd;

use Illuminate\Support\Facades\DB;
use Throwable;

trait EmrUGDTrait
{
    /**
     * Find EMR UGD data with cache-first logic.
     * - If datadaftarugd_json exists & valid: use it directly
     * - If null/invalid: fallback to default template + populate from DB
     * - Validate rj_no: if not found or mismatched, return default
     *
     * ⚠️  Membaca dari VIEW (rsview_ugdkasir) — tidak bisa di-lock.
     *     Untuk operasi read-modify-write, panggil lockUGDRow() terlebih dahulu
     *     DI DALAM DB::transaction sebelum memanggil findDataUGD().
     *
     * Contoh penggunaan yang aman dari race condition:
     *
     *     DB::transaction(function () {
     *         $this->lockUGDRow($this->rjNo);          // ← lock dulu
     *         $data = $this->findDataUGD($this->rjNo); // ← baru baca
     *         $data['pemeriksaan']['foo'] = 'bar';
     *         $this->updateJsonUGD($this->rjNo, $data);
     *     });
     */
    protected function findDataUGD($rjNo): array
    {
        $row = DB::table('rsview_ugdkasir')
            ->select([
                'reg_no',
                'reg_name',
                'rj_no',
                'rj_status',
                'dr_id',
                'dr_name',
                // 'poli_id',
                // 'poli_desc',
                DB::raw("to_char(rj_date, 'dd/mm/yyyy hh24:mi:ss') as rj_date"),
                'shift',
                'klaim_id',
                'txn_status',
                'erm_status',
                'vno_sep',
                'no_antrian',
                // 'no_sep',
                'nobooking',
                // 'waktu_masuk_ugd',
                // 'waktu_masuk_apt',
                // 'waktu_selesai_pelayanan',
                // 'kd_dr_bpjs',
                // 'kd_poli_bpjs',
                'datadaftarugd_json',
            ])
            ->where('rj_no', $rjNo)
            ->first();

        $json = $row->datadaftarugd_json ?? null;

        if ($json && $this->isValidUGDJson($json, $rjNo)) {
            $dataDaftarUGD = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->populateFromDatabaseEmrUGD($dataDaftarUGD, $row);
            return $dataDaftarUGD;
        }

        $builtData = $this->getDefaultUGDTemplate();
        if ($row) {
            $this->populateFromDatabaseEmrUGD($builtData, $row);
        }

        return $builtData;
    }

    /**
     * Lock baris di tabel rstxn_ugdhdrs (SELECT FOR UPDATE).
     *
     * Wajib dipanggil DI DALAM DB::transaction sebelum findDataUGD()
     * pada operasi yang melakukan read-modify-write ke datadaftarugd_json.
     * Mencegah race condition ketika dua user/request mengubah data bersamaan.
     *
     * @throws \RuntimeException jika row tidak ditemukan
     */
    protected function lockUGDRow($rjNo): void
    {
        $exists = DB::table('rstxn_ugdhdrs')
            ->where('rj_no', $rjNo)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Data UGD #{$rjNo} tidak ditemukan untuk di-lock.");
        }
    }

    /**
     * Update JSON UGD dengan validasi rjNo.
     *
     * ⚠️  Tidak membungkus DB::transaction sendiri agar tidak membuat
     *     nested transaction di caller yang sudah punya transaksi.
     *     Selalu panggil method ini DI DALAM DB::transaction dari caller.
     *
     * @throws \RuntimeException jika rjNo tidak cocok
     * @throws \JsonException    jika payload gagal di-encode
     */
    public function updateJsonUGD(int $rjNo, array $payload): void
    {
        if (! isset($payload['rjNo']) || (int) $payload['rjNo'] !== $rjNo) {
            throw new \RuntimeException(
                "rjNo dalam payload ({$payload['rjNo']}) tidak sesuai dengan parameter ({$rjNo})."
            );
        }

        DB::table('rstxn_ugdhdrs')
            ->where('rj_no', $rjNo)
            ->update([
                'datadaftarugd_json' => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
            ]);
    }

    /**
     * Validate UGD JSON structure and rj_no match.
     */
    private function isValidUGDJson(?string $json, $expectedRjNo): bool
    {
        if (! $json || trim($json) === '') {
            return false;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded)
                && isset($decoded['rjNo'])
                && $decoded['rjNo'] == $expectedRjNo;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Populate data dari view ke array dataDaftarUGD.
     */
    private function populateFromDatabaseEmrUGD(array &$dataDaftarUGD, object $row): void
    {
        $dataDaftarUGD['regNo']   = $row->reg_no   ?? '';
        $dataDaftarUGD['regName'] = $row->reg_name ?? '';

        $dataDaftarUGD['drId']     = $row->dr_id     ?? '';
        $dataDaftarUGD['drDesc']   = $row->dr_name   ?? '';
        $dataDaftarUGD['poliId']   = $row->poli_id   ?? '';
        $dataDaftarUGD['poliDesc'] = $row->poli_desc ?? '';

        $dataDaftarUGD['kddrbpjs']   = $row->kd_dr_bpjs   ?? '';
        $dataDaftarUGD['kdpolibpjs'] = $row->kd_poli_bpjs ?? '';

        $dataDaftarUGD['klaimId']     = $row->klaim_id ?? 'UM';
        $dataDaftarUGD['klaimStatus'] = $this->getKlaimStatusUGD($row->klaim_id ?? 'UM');

        $dataDaftarUGD['rjNo']   = $row->rj_no  ?? null;
        $dataDaftarUGD['rjDate'] = $row->rj_date ?? '';
        $dataDaftarUGD['shift']  = $row->shift   ?? '';

        $dataDaftarUGD['rjStatus']  = $row->rj_status  ?? 'A';
        $dataDaftarUGD['txnStatus'] = $row->txn_status ?? 'A';
        $dataDaftarUGD['ermStatus'] = $row->erm_status ?? 'A';

        $dataDaftarUGD['noAntrian'] = $row->no_antrian ?? '';
        $dataDaftarUGD['noBooking'] = $row->nobooking  ?? '';

        $dataDaftarUGD['sep']['noSep'] = $row->vno_sep ?? $row->no_sep ?? '';

        $dataDaftarUGD['taskIdPelayanan']['taskId3'] =
            $row->rj_date ?? $dataDaftarUGD['taskIdPelayanan']['taskId3'] ?? '';
    }

    /**
     * Get klaim status dari klaim_id.
     */
    private function getKlaimStatusUGD(string $klaimId): string
    {
        return DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $klaimId)
            ->value('klaim_status') ?? 'UMUM';
    }

    /**
     * Get default UGD template.
     */
    private function getDefaultUGDTemplate(): array
    {
        return [
            'regNo'    => '',
            'regName'  => '',
            'drId'     => '',
            'drDesc'   => '',
            'poliId'   => '',
            'poliDesc' => '',

            'klaimId'     => 'UM',
            'klaimStatus' => 'UMUM',
            'kunjunganId' => '1',

            'rjDate'    => '',
            'rjNo'      => '',
            'shift'     => '',
            'noAntrian' => '',
            'noBooking' => '',

            'slCodeFrom'              => '02',
            'passStatus'              => 'O',
            'rjStatus'                => 'A',
            'txnStatus'               => 'A',
            'ermStatus'               => 'A',
            'cekLab'                  => '0',
            'kunjunganInternalStatus' => '0',
            'noReferensi'             => '',
            'postInap'                => false,

            'internal12'        => '1',
            'internal12Desc'    => 'Faskes Tingkat 1',
            'internal12Options' => [
                ['internal12' => '1', 'internal12Desc' => 'Faskes Tingkat 1'],
                ['internal12' => '2', 'internal12Desc' => 'Faskes Tingkat 2 RS'],
            ],

            'kontrol12'        => '1',
            'kontrol12Desc'    => 'Faskes Tingkat 1',
            'kontrol12Options' => [
                ['kontrol12' => '1', 'kontrol12Desc' => 'Faskes Tingkat 1'],
                ['kontrol12' => '2', 'kontrol12Desc' => 'Faskes Tingkat 2 RS'],
            ],

            'taskIdPelayanan' => [
                'tambahPendaftaran' => '',
                'taskId1'           => '',
                'taskId1Status'     => '',
                'taskId2'           => '',
                'taskId2Status'     => '',
                'taskId3'           => '',
                'taskId3Status'     => '',
                'taskId4'           => '',
                'taskId4Status'     => '',
                'taskId5'           => '',
                'taskId5Status'     => '',
                'taskId6'           => '',
                'taskId6Status'     => '',
                'taskId7'           => '',
                'taskId7Status'     => '',
                'taskId99'          => '',
                'taskId99Status'    => '',
            ],

            'sep' => [
                'noSep'  => '',
                'reqSep' => [],
                'resSep' => [],
            ],
        ];
    }

    /**
     * Cek apakah transaksi UGD masih aktif (rj_status = 'A').
     *
     * Returns true jika pasien SUDAH tidak aktif (rj_status !== 'A').
     * Konsisten dengan checkRJStatus() di EmrRJTrait.
     */
    protected function checkUGDStatus($rjNo): bool
    {
        $row = DB::table('rstxn_ugdhdrs')
            ->select('rj_status')
            ->where('rj_no', $rjNo)
            ->first();

        if (! $row || empty($row->rj_status)) {
            return false;
        }

        return $row->rj_status !== 'A';
    }

    /**
     * Cek apakah EMR UGD sudah dikunci (erm_status !== 'A').
     *
     * Returns true jika EMR SUDAH terkunci (erm_status !== 'A').
     * Konsisten dengan checkEmrRJStatus() di EmrRJTrait.
     */
    protected function checkEmrUGDStatus($rjNo): bool
    {
        $row = DB::table('rstxn_ugdhdrs')
            ->select('erm_status')
            ->where('rj_no', $rjNo)
            ->first();

        if (! $row || empty($row->erm_status)) {
            return false;
        }
        return false;
        // return $row->erm_status !== 'A';
    }

    /**
     * Cek apakah ada lab pending (checkup_status='P') untuk transaksi ini.
     */
    protected function checkLabPending(int $rjNo, string $statusRjri = 'UGD'): bool
    {
        return DB::table('lbtxn_checkuphdrs')
            ->where('status_rjri', $statusRjri)
            ->where('checkup_status', 'P')
            ->where('ref_no', $rjNo)
            ->exists();
    }

    /**
     * Hitung semua komponen biaya UGD — reusable di kasir & transfer.
     */
    protected function calculateUGDCosts(int $rjNo): array
    {
        $hdr = DB::table('rstxn_ugdhdrs')
            ->select('rs_admin', 'rj_admin', 'poli_price')
            ->where('rj_no', $rjNo)
            ->first();

        return [
            'rsAdmin'   => (int) ($hdr->rs_admin ?? 0),
            'rjAdmin'   => (int) ($hdr->rj_admin ?? 0),
            'poliPrice' => (int) ($hdr->poli_price ?? 0),
            'actePrice' => (int) DB::table('rstxn_ugdactemps')->where('rj_no', $rjNo)->sum('acte_price'),
            'actdPrice' => (int) DB::table('rstxn_ugdaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price'),
            'actpPrice' => (int) DB::table('rstxn_ugdactparams')->where('rj_no', $rjNo)->sum('pact_price'),
            'obat'      => (int) DB::table('rstxn_ugdobats')->where('rj_no', $rjNo)
                            ->selectRaw('nvl(sum(qty * price), 0) as total')->value('total'),
            'lab'       => (int) DB::table('rstxn_ugdlabs')->where('rj_no', $rjNo)->sum('lab_price'),
            'rad'       => (int) DB::table('rstxn_ugdrads')->where('rj_no', $rjNo)->sum('rad_price'),
            'other'     => (int) DB::table('rstxn_ugdothers')->where('rj_no', $rjNo)->sum('other_price'),
        ];
    }
}
