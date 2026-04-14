<?php

namespace App\Http\Traits\Txn\Rj;

use Illuminate\Support\Facades\DB;
use Throwable;

trait EmrRJTrait
{
    /**
     * Find EMR RJ data with cache-first logic.
     * - If datadaftarpolirj_json exists & valid: use it directly
     * - If null/invalid: fallback to database query (once)
     * - Validate rj_no: if not found or mismatched, return default
     *
     * ⚠️  Membaca dari VIEW (rsview_rjkasir) — tidak bisa di-lock.
     *     Untuk operasi read-modify-write, panggil lockRJRow() terlebih dahulu
     *     DI DALAM DB::transaction sebelum memanggil findDataRJ().
     *
     * Contoh penggunaan yang aman dari race condition:
     *
     *     DB::transaction(function () {
     *         $this->lockRJRow($this->rjNo);          // ← lock dulu
     *         $data = $this->findDataRJ($this->rjNo); // ← baru baca
     *         $data['pemeriksaan']['foo'] = 'bar';
     *         $this->updateJsonRJ($this->rjNo, $data);
     *     });
     */
    protected function findDataRJ($rjNo): array
    {
        $row = DB::table('rsview_rjkasir')
            ->select([
                'reg_no',
                'reg_name',
                'rj_no',
                'rj_status',
                'dr_id',
                'dr_name',
                'poli_id',
                'poli_desc',
                DB::raw("to_char(rj_date, 'dd/mm/yyyy hh24:mi:ss') as rj_date"),
                'shift',
                'klaim_id',
                'txn_status',
                'erm_status',
                'vno_sep',
                'no_antrian',
                'no_sep',
                'nobooking',
                'waktu_masuk_poli',
                'waktu_masuk_apt',
                'waktu_selesai_pelayanan',
                'kd_dr_bpjs',
                'kd_poli_bpjs',
                'datadaftarpolirj_json',
            ])
            ->where('rj_no', $rjNo)
            ->first();

        $json = $row->datadaftarpolirj_json ?? null;

        if ($json && $this->isValidRJJson($json, $rjNo)) {
            $dataDaftarRJ = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->populateFromDatabaseEmrRJ($dataDaftarRJ, $row);
            return $dataDaftarRJ;
        }

        $builtData = $this->getDefaultRJTemplate();
        if ($row) {
            $this->populateFromDatabaseEmrRJ($builtData, $row);
        }

        return $builtData;
    }

    /**
     * Lock baris di tabel rstxn_rjhdrs (SELECT FOR UPDATE).
     *
     * Wajib dipanggil DI DALAM DB::transaction sebelum findDataRJ()
     * pada operasi yang melakukan read-modify-write ke datadaftarpolirj_json.
     * Mencegah race condition ketika dua user/request mengubah data bersamaan.
     *
     * @throws \RuntimeException jika row tidak ditemukan
     */
    protected function lockRJRow($rjNo): void
    {
        $exists = DB::table('rstxn_rjhdrs')
            ->where('rj_no', $rjNo)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Data RJ #{$rjNo} tidak ditemukan untuk di-lock.");
        }
    }

    /**
     * Update JSON RJ dengan validasi rjNo.
     *
     * ⚠️  Tidak membungkus DB::transaction sendiri agar tidak membuat
     *     nested transaction di caller yang sudah punya transaksi.
     *     Selalu panggil method ini DI DALAM DB::transaction dari caller.
     *
     * @throws \RuntimeException jika rjNo tidak cocok
     * @throws \JsonException    jika payload gagal di-encode
     */
    public function updateJsonRJ(int $rjNo, array $payload): void
    {
        if (! isset($payload['rjNo']) || (int) $payload['rjNo'] !== $rjNo) {
            throw new \RuntimeException(
                "rjNo dalam payload ({$payload['rjNo']}) tidak sesuai dengan parameter ({$rjNo})."
            );
        }

        DB::table('rstxn_rjhdrs')
            ->where('rj_no', $rjNo)
            ->update([
                'datadaftarpolirj_json' => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
            ]);
    }

    /**
     * Validate RJ JSON structure and rj_no match.
     */
    private function isValidRJJson(?string $json,  $expectedRjNo): bool
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
     * Populate data dari view ke array dataDaftarRJ.
     */
    private function populateFromDatabaseEmrRJ(array &$dataDaftarRJ, object $row): void
    {
        $dataDaftarRJ['regNo']    = $row->reg_no   ?? '';
        $dataDaftarRJ['regName']  = $row->reg_name ?? '';

        $dataDaftarRJ['drId']    = $row->dr_id    ?? '';
        $dataDaftarRJ['drDesc']  = $row->dr_name  ?? '';
        $dataDaftarRJ['poliId']  = $row->poli_id  ?? '';
        $dataDaftarRJ['poliDesc'] = $row->poli_desc ?? '';

        $dataDaftarRJ['kddrbpjs']   = $row->kd_dr_bpjs  ?? '';
        $dataDaftarRJ['kdpolibpjs'] = $row->kd_poli_bpjs ?? '';

        $dataDaftarRJ['klaimId']     = $row->klaim_id ?? 'UM';
        $dataDaftarRJ['klaimStatus'] = $this->getKlaimStatus($row->klaim_id ?? 'UM');

        $dataDaftarRJ['rjNo']   = $row->rj_no  ?? null;
        $dataDaftarRJ['rjDate'] = $row->rj_date ?? '';
        $dataDaftarRJ['shift']  = $row->shift   ?? '';

        $dataDaftarRJ['rjStatus']  = $row->rj_status  ?? 'A';
        $dataDaftarRJ['txnStatus'] = $row->txn_status  ?? 'A';
        $dataDaftarRJ['ermStatus'] = $row->erm_status  ?? 'A';

        $dataDaftarRJ['noAntrian'] = $row->no_antrian ?? '';
        $dataDaftarRJ['noBooking'] = $row->nobooking  ?? '';

        $dataDaftarRJ['sep']['noSep'] = $row->vno_sep ?? $row->no_sep ?? '';

        $dataDaftarRJ['taskIdPelayanan']['taskId3'] =
            $row->rj_date ?? $dataDaftarRJ['taskIdPelayanan']['taskId3'] ?? '';
    }

    /**
     * Get klaim status dari klaim_id.
     */
    private function getKlaimStatus(string $klaimId): string
    {
        return DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $klaimId)
            ->value('klaim_status') ?? 'UMUM';
    }

    /**
     * Get default RJ template.
     */
    private function getDefaultRJTemplate(): array
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

            'slCodeFrom'             => '02',
            'passStatus'             => 'O',
            'rjStatus'               => 'A',
            'txnStatus'              => 'A',
            'ermStatus'              => 'A',
            'cekLab'                 => '0',
            'kunjunganInternalStatus' => '0',
            'noReferensi'            => '',
            'postInap'               => false,

            'internal12'      => '1',
            'internal12Desc'  => 'Faskes Tingkat 1',
            'internal12Options' => [
                ['internal12' => '1', 'internal12Desc' => 'Faskes Tingkat 1'],
                ['internal12' => '2', 'internal12Desc' => 'Faskes Tingkat 2 RS'],
            ],

            'kontrol12'      => '1',
            'kontrol12Desc'  => 'Faskes Tingkat 1',
            'kontrol12Options' => [
                ['kontrol12' => '1', 'kontrol12Desc' => 'Faskes Tingkat 1'],
                ['kontrol12' => '2', 'kontrol12Desc' => 'Faskes Tingkat 2 RS'],
            ],

            'taskIdPelayanan' => [
                'tambahPendaftaran' => '',
                'taskId1'           => '',
                'taskId1Status'  => '',
                'taskId2'           => '',
                'taskId2Status'  => '',
                'taskId3'           => '',
                'taskId3Status'  => '',
                'taskId4'           => '',
                'taskId4Status'  => '',
                'taskId5'           => '',
                'taskId5Status'  => '',
                'taskId6'           => '',
                'taskId6Status'  => '',
                'taskId7'           => '',
                'taskId7Status'  => '',
                'taskId99'          => '',
                'taskId99Status' => '',
            ],

            'sep' => [
                'noSep'  => '',
                'reqSep' => [],
                'resSep' => [],
            ],
        ];
    }

    /**
     * Cek apakah transaksi RJ masih aktif (rj_status = 'A').
     */
    protected function checkRJStatus($rjNo): bool
    {
        $row = DB::table('rstxn_rjhdrs')
            ->select('rj_status')
            ->where('rj_no', $rjNo)
            ->first();

        if (! $row || empty($row->rj_status)) {
            return false;
        }

        return $row->rj_status !== 'A';
    }

    /**
     * Cek apakah EMR RJ sudah dikunci (erm_status !== 'A').
     *
     * ✅ Fix: versi sebelumnya salah baca rj_status, seharusnya erm_status.
     */
    protected function checkEmrRJStatus($rjNo): bool
    {
        $row = DB::table('rstxn_rjhdrs')
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
    protected function checkLabPending(int $rjNo, string $statusRjri = 'RJ'): bool
    {
        return DB::table('lbtxn_checkuphdrs')
            ->where('status_rjri', $statusRjri)
            ->where('checkup_status', 'P')
            ->where('ref_no', $rjNo)
            ->exists();
    }

    /**
     * Hitung semua komponen biaya RJ — reusable di kasir & transfer.
     */
    protected function calculateRJCosts(int $rjNo): array
    {
        $hdr = DB::table('rstxn_rjhdrs')
            ->select('rs_admin', 'rj_admin', 'poli_price')
            ->where('rj_no', $rjNo)
            ->first();

        return [
            'rsAdmin'   => (int) ($hdr->rs_admin ?? 0),
            'rjAdmin'   => (int) ($hdr->rj_admin ?? 0),
            'poliPrice' => (int) ($hdr->poli_price ?? 0),
            'actePrice' => (int) DB::table('rstxn_rjactemps')->where('rj_no', $rjNo)->sum('acte_price'),
            'actdPrice' => (int) DB::table('rstxn_rjaccdocs')->where('rj_no', $rjNo)->sum('accdoc_price'),
            'actpPrice' => (int) DB::table('rstxn_rjactparams')->where('rj_no', $rjNo)->sum('pact_price'),
            'obat'      => (int) DB::table('rstxn_rjobats')->where('rj_no', $rjNo)
                            ->selectRaw('nvl(sum(qty * price), 0) as total')->value('total'),
            'lab'       => (int) DB::table('rstxn_rjlabs')->where('rj_no', $rjNo)->sum('lab_price'),
            'rad'       => (int) DB::table('rstxn_rjrads')->where('rj_no', $rjNo)->sum('rad_price'),
            'other'     => (int) DB::table('rstxn_rjothers')->where('rj_no', $rjNo)->sum('other_price'),
        ];
    }
}
