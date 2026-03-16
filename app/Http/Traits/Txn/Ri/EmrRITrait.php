<?php

namespace App\Http\Traits\Txn\Ri;

use Illuminate\Support\Facades\DB;
use Throwable;

trait EmrRITrait
{
    /**
     * Find EMR RI data with cache-first logic.
     * - If datadaftarri_json exists & valid: use it directly
     * - If null/invalid: fallback to default template + populate from DB
     * - Validate riHdrNo: if not found or mismatched, return default
     *
     * ⚠️  Membaca dari VIEW (rsview_rihdrs) — tidak bisa di-lock.
     *     Untuk operasi read-modify-write, panggil lockRIRow() terlebih dahulu
     *     DI DALAM DB::transaction sebelum memanggil findDataRI().
     *
     * Contoh penggunaan yang aman dari race condition:
     *
     *     DB::transaction(function () {
     *         $this->lockRIRow($this->riHdrNo);          // ← lock dulu
     *         $data = $this->findDataRI($this->riHdrNo); // ← baru baca
     *         $data['pemeriksaan']['foo'] = 'bar';
     *         $this->updateJsonRI($this->riHdrNo, $data);
     *     });
     */
    protected function findDataRI($riHdrNo): array
    {
        $row = DB::table('rsview_rihdrs')
            ->select([
                'rihdr_no',
                'reg_no',
                'reg_name',
                'ri_status',
                'dr_id',
                'dr_name',
                'klaim_id',
                'klaim_desc',
                'entry_id',
                'entry_desc',
                'bangsal_id',
                'bangsal_name',
                'room_id',
                'room_name',
                'bed_no',
                'admin_age',
                'police_case',
                DB::raw("to_char(entry_date, 'dd/mm/yyyy hh24:mi:ss') as entry_date"),
                DB::raw("to_char(exit_date,  'dd/mm/yyyy hh24:mi:ss') as exit_date"),
                'vno_sep',
                'no_sep',
                'datadaftarri_json',
            ])
            ->where('rihdr_no', $riHdrNo)
            ->first();

        $json = $row->datadaftarri_json ?? null;

        if ($json && $this->isValidRIJson($json, $riHdrNo)) {
            $dataDaftarRI = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->populateFromDatabaseEmrRI($dataDaftarRI, $row);
            return $dataDaftarRI;
        }

        $builtData = $this->getDefaultRITemplate();
        if ($row) {
            $this->populateFromDatabaseEmrRI($builtData, $row);
        }

        return $builtData;
    }

    /**
     * Lock baris di tabel rstxn_rihdrs (SELECT FOR UPDATE).
     *
     * Wajib dipanggil DI DALAM DB::transaction sebelum findDataRI()
     * pada operasi yang melakukan read-modify-write ke datadaftarri_json.
     * Mencegah race condition ketika dua user/request mengubah data bersamaan.
     *
     * @throws \RuntimeException jika row tidak ditemukan
     */
    protected function lockRIRow($riHdrNo): void
    {
        $exists = DB::table('rstxn_rihdrs')
            ->where('rihdr_no', $riHdrNo)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Data RI #{$riHdrNo} tidak ditemukan untuk di-lock.");
        }
    }

    /**
     * Update JSON RI dengan validasi riHdrNo.
     *
     * ⚠️  Tidak membungkus DB::transaction sendiri agar tidak membuat
     *     nested transaction di caller yang sudah punya transaksi.
     *     Selalu panggil method ini DI DALAM DB::transaction dari caller.
     *
     * @throws \RuntimeException jika riHdrNo tidak cocok
     * @throws \JsonException    jika payload gagal di-encode
     */
    public function updateJsonRI(int $riHdrNo, array $payload): void
    {
        if (! isset($payload['riHdrNo']) || (int) $payload['riHdrNo'] !== $riHdrNo) {
            throw new \RuntimeException(
                "riHdrNo dalam payload ({$payload['riHdrNo']}) tidak sesuai dengan parameter ({$riHdrNo})."
            );
        }

        DB::table('rstxn_rihdrs')
            ->where('rihdr_no', $riHdrNo)
            ->update([
                'datadaftarri_json' => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
            ]);
    }

    /**
     * Validate RI JSON structure and riHdrNo match.
     */
    private function isValidRIJson(?string $json, $expectedRiHdrNo): bool
    {
        if (! $json || trim($json) === '') {
            return false;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded)
                && isset($decoded['riHdrNo'])
                && $decoded['riHdrNo'] == $expectedRiHdrNo;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Populate data dari view ke array dataDaftarRI.
     *
     * Field yang di-overwrite setiap kali (live dari DB, tidak boleh stale dari JSON):
     * - bangsalId, bangsalDesc, roomId, roomDesc, bedNo  ← bisa pindah kamar
     * - klaimId, klaimDesc, klaimStatus                  ← bisa berubah
     * - entryDate, exitDate                              ← bisa di-update
     * - sep.noSep                                        ← dari vno_sep / no_sep
     * - drId, drDesc                                     ← dokter penanggung jawab
     */
    private function populateFromDatabaseEmrRI(array &$dataDaftarRI, object $row): void
    {
        $dataDaftarRI['riHdrNo'] = $row->rihdr_no ?? null;
        $dataDaftarRI['regNo']   = $row->reg_no   ?? '';
        $dataDaftarRI['regName'] = $row->reg_name ?? '';

        $dataDaftarRI['drId']   = $row->dr_id   ?? '';
        $dataDaftarRI['drDesc'] = $row->dr_name ?? '';

        $dataDaftarRI['entryId']   = $row->entry_id   ?? '';
        $dataDaftarRI['entryDesc'] = $row->entry_desc ?? '';

        $dataDaftarRI['bangsalId']   = $row->bangsal_id   ?? '';
        $dataDaftarRI['bangsalDesc'] = $row->bangsal_name ?? '';
        $dataDaftarRI['roomId']      = $row->room_id      ?? '';
        $dataDaftarRI['roomDesc']    = $row->room_name    ?? '';
        $dataDaftarRI['bedNo']       = $row->bed_no       ?? '';

        $dataDaftarRI['klaimId']     = $row->klaim_id   ?? 'UM';
        $dataDaftarRI['klaimDesc']   = $row->klaim_desc ?? 'UMUM';
        $dataDaftarRI['klaimStatus'] = $this->getKlaimStatusRI($row->klaim_id ?? 'UM');

        // k14th & kPolisi: hanya di-set dari DB saat fallback (JSON tidak menyimpannya secara boolean)
        $dataDaftarRI['k14th']   = (bool) ($row->admin_age   ?? false);
        $dataDaftarRI['kPolisi'] = ($row->police_case ?? 'N') === 'Y';

        $dataDaftarRI['riStatus']  = $row->ri_status ?? 'I';
        $dataDaftarRI['entryDate'] = $row->entry_date ?? '';
        $dataDaftarRI['exitDate']  = $row->exit_date  ?? '';

        $dataDaftarRI['sep']['noSep'] = $row->vno_sep ?? $row->no_sep ?? '';
    }

    /**
     * Get klaim status dari klaim_id.
     */
    private function getKlaimStatusRI(string $klaimId): string
    {
        return DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $klaimId)
            ->value('klaim_status') ?? 'UMUM';
    }

    /**
     * Get default RI template.
     */
    private function getDefaultRITemplate(): array
    {
        return [
            'riHdrNo'  => null,
            'regNo'    => '',
            'regName'  => '',

            'drId'   => '',
            'drDesc' => '',

            'entryId'   => '',
            'entryDesc' => '',

            'bangsalId'   => '',
            'bangsalDesc' => '',
            'roomId'      => '',
            'roomDesc'    => '',
            'bedNo'       => '',

            'klaimId'     => 'UM',
            'klaimDesc'   => 'UMUM',
            'klaimStatus' => 'UMUM',

            'k14th'   => false,   // Kurang dari 14 Tahun
            'kPolisi' => false,   // Kasus Polisi

            // I = Inap (aktif), P = Pulang (selesai)
            'riStatus'  => 'I',
            'entryDate' => '',
            'exitDate'  => '',

            'sep' => [
                'noSep'  => '',
                'reqSep' => [],
                'resSep' => [],
            ],
        ];
    }

    /**
     * Cek apakah pasien masih rawat inap (ri_status = 'I').
     *
     * 'I' = Inap (aktif/sedang dirawat)
     * 'P' = Pulang (sudah selesai, transaksi terkunci)
     *
     * Return true  → pasien sudah pulang (transaksi terkunci)
     * Return false → pasien masih inap atau data tidak ditemukan
     */
    protected function checkRIStatus($riHdrNo): bool
    {
        $row = DB::table('rstxn_rihdrs')
            ->select('ri_status')
            ->where('rihdr_no', $riHdrNo)
            ->first();

        if (! $row || empty($row->ri_status)) {
            return false;
        }

        // Terkunci jika bukan 'I' (sudah Pulang atau status lain)
        return $row->ri_status !== 'I';
    }
}
