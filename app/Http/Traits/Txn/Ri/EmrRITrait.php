<?php

namespace App\Http\Traits\Txn\Ri;

use Carbon\Carbon;
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
                // 'no_sep',
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

    /**
     * Append satu entry ke AdministrasiRI.userLogs di JSON.
     * Panggil DI DALAM DB::transaction setelah lockRIRow().
     */
    protected function appendAdminLog(int $riHdrNo, string $keterangan): void
    {
        $data = $this->findDataRI($riHdrNo);

        $data['AdministrasiRI']['userLogs'][] = [
            'userLog'     => auth()->user()->myuser_name ?? auth()->user()->name ?? 'SYSTEM',
            'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'userLogDesc' => $keterangan,
        ];

        $this->updateJsonRI($riHdrNo, $data);
    }

    protected function checkEmrRIStatus($riHdrNo): bool
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

    /**
     * Hitung semua komponen biaya RI — dipakai iDRG & (potensi) kasir.
     * Rumus berasal dari kasir-ri.blade.php (load1sSum).
     */
    protected function calculateRICosts(int $riHdrNo): array
    {
        $hdr = DB::table('rstxn_rihdrs')
            ->select('admin_age', 'admin_status')
            ->where('rihdr_no', $riHdrNo)
            ->first();

        $room = DB::table('rsmst_trfrooms')
            ->where('rihdr_no', $riHdrNo)
            ->selectRaw("nvl(sum(room_price      * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as room_total,
                         nvl(sum(common_service  * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as cs_total,
                         nvl(sum(perawatan_price * ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate)))),0) as perwt_total")
            ->first();

        return [
            'adminAge'      => (int) ($hdr->admin_age    ?? 0),
            'adminStatus'   => (int) ($hdr->admin_status ?? 0),
            'visit'         => (int) DB::table('rstxn_rivisits')->where('rihdr_no', $riHdrNo)->sum('visit_price'),
            'konsul'        => (int) DB::table('rstxn_rikonsuls')->where('rihdr_no', $riHdrNo)->sum('konsul_price'),
            'jasaMedis'     => (int) DB::table('rstxn_riactparams')->where('rihdr_no', $riHdrNo)
                                    ->selectRaw('nvl(sum(actp_price * actp_qty),0) as total')->value('total'),
            'jasaDokter'    => (int) DB::table('rstxn_riactdocs')->where('rihdr_no', $riHdrNo)
                                    ->selectRaw('nvl(sum(actd_price * actd_qty),0) as total')->value('total'),
            'lab'           => (int) DB::table('rstxn_rilabs')->where('rihdr_no', $riHdrNo)->sum('lab_price'),
            'rad'           => (int) DB::table('rstxn_riradiologs')->where('rihdr_no', $riHdrNo)->sum('rirad_price'),
            'trfUgdRj'      => (int) DB::table('rstxn_ritempadmins')->where('rihdr_no', $riHdrNo)
                                    ->selectRaw('nvl(sum(nvl(rj_admin,0)+nvl(poli_price,0)+nvl(acte_price,0)+nvl(actp_price,0)+nvl(actd_price,0)+nvl(obat,0)+nvl(rad,0)+nvl(lab,0)+nvl(other,0)+nvl(rs_admin,0)),0) as total')
                                    ->value('total'),
            'lainLain'      => (int) DB::table('rstxn_riothers')->where('rihdr_no', $riHdrNo)->sum('other_price'),
            'ok'            => (int) DB::table('rstxn_rioks')->where('rihdr_no', $riHdrNo)->sum('ok_price'),
            'room'          => (int) ($room->room_total  ?? 0),
            'commonService' => (int) ($room->cs_total    ?? 0),
            'perawatan'     => (int) ($room->perwt_total ?? 0),
            'bonResep'      => (int) DB::table('rstxn_ribonobats')->where('rihdr_no', $riHdrNo)->sum('ribon_price'),
            'rtnObat'       => (int) DB::table('rstxn_riobatrtns')->where('rihdr_no', $riHdrNo)
                                    ->selectRaw('nvl(sum(riobat_qty * riobat_price),0) as total')->value('total'),
            'obatPinjam'    => (int) DB::table('rstxn_riobats')->where('rihdr_no', $riHdrNo)
                                    ->selectRaw('nvl(sum(riobat_qty * riobat_price),0) as total')->value('total'),
        ];
    }

    /**
     * Ambil class_id kamar terakhir pasien (dipakai iDRG → kelas_rawat BPJS).
     */
    protected function lastKamarClassIdRI(int $riHdrNo): ?string
    {
        $row = DB::table('rsmst_trfrooms as t')
            ->leftJoin('rsmst_rooms as r', 't.room_id', '=', 'r.room_id')
            ->where('t.rihdr_no', $riHdrNo)
            ->orderByDesc('t.trfr_no')
            ->select('r.class_id')
            ->first();
        return $row?->class_id;
    }

    /**
     * Ambil tgl masuk & pulang RI untuk iDRG (format "Y-m-d H:i:s").
     * Fallback: kalau exit_date belum ada, pakai now() — SIRS butuh 2 tgl meski sama.
     */
    protected function riClaimDates(int $riHdrNo): array
    {
        $row = DB::table('rstxn_rihdrs')
            ->select(DB::raw("to_char(entry_date, 'YYYY-MM-DD HH24:MI:SS') as entry_date"),
                     DB::raw("to_char(exit_date,  'YYYY-MM-DD HH24:MI:SS') as exit_date"))
            ->where('rihdr_no', $riHdrNo)
            ->first();

        $entry = $row?->entry_date ?: Carbon::now()->format('Y-m-d H:i:s');
        $exit  = $row?->exit_date  ?: Carbon::now()->format('Y-m-d H:i:s');
        return ['tglMasuk' => $entry, 'tglPulang' => $exit];
    }
}
