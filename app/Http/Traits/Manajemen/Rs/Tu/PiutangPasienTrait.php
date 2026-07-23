<?php

namespace App\Http\Traits\Manajemen\Rs\Tu;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait untuk laporan Piutang Pasien — transaksi belum lunas.
 *
 * Sumber & penanda hutang:
 *   - RJ  : rstxn_rjhdrs.txn_status  = 'H'   (tanggal: rj_date)
 *   - UGD : rstxn_ugdhdrs.txn_status = 'H'   (tanggal: rj_date)
 *   - RI  : rstxn_rihdrs.status_pulang = 'H' (tanggal: exit_date) — RI TAK punya txn_status
 *
 * klaim_status (rsmst_klaimtypes) ada 4 kategori: BPJS, UMUM, KRONIS, DOKEL.
 * Filter klaim = pencocokan EKSAK kategori (JM/JKN-Mobile diperlakukan BPJS).
 *
 * Rumus per transaksi (identik proses kasir masing-masing jalur):
 *   sisa = GREATEST(0, total − diskon − sudah_bayar)
 *   - RJ/UGD: total = Σ pos biaya; bayar = Σ rjc_nominal (rstxn_*cashins); diskon = rj_diskon
 *   - RI    : total = Σ pos biaya RI (lihat kasir-ri totalAll); diskon = ri_diskon;
 *             bayar = Σ ripaymentdtls.ripay_bayar (angsuran) + Σ ripaymentpdtls.ripay_bayar (pelunasan)
 *
 * Query tiap jalur ditulis TERPISAH dengan nama tabel LITERAL (rstxn_rj, rstxn_ugd,
 * rstxn_ri) agar mudah di-grep saat audit — sengaja tidak memakai prefix dinamis.
 *
 * PERFORMA: skala 'H' besar (puluhan ribu). Semua kalkulasi, filter sisa>0, urutan,
 * dan slice halaman dilakukan di level SQL — JANGAN materialisasi ke Collection PHP.
 */
trait PiutangPasienTrait
{
    /** Filter kategori klaim (eksak). Alias tabel: header=h, rsmst_klaimtypes=k. */
    private function applyPiutangKlaim(Builder $query, string $klaim): Builder
    {
        return match ($klaim) {
            'BPJS'   => $query->where(fn($subQuery) => $subQuery->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM')),
            'UMUM'   => $query->where('k.klaim_status', 'UMUM'),
            'KRONIS' => $query->where('k.klaim_status', 'KRONIS'),
            'DOKEL'  => $query->where('k.klaim_status', 'DOKEL'),
            default  => $query,
        };
    }

    /** Filter pencarian (nama / No.RM / No.transaksi). $kolomPk = kolom PK header. */
    private function applyPiutangSearch(Builder $query, string $search, string $kolomPk): void
    {
        if ($search === '' || mb_strlen($search) < 2) {
            return;
        }
        $keyword = mb_strtoupper($search);
        $query->where(function ($grup) use ($keyword, $search, $kolomPk) {
            if (ctype_digit($search)) {
                $grup->orWhere($kolomPk, 'like', "%{$search}%")->orWhere('h.reg_no', 'like', "%{$search}%");
            }
            $grup->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$keyword}%")
                ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$keyword}%");
        });
    }

    /**
     * Query mentah PIUTANG RAWAT JALAN — rstxn_rjhdrs.txn_status='H'.
     * Nama tabel LITERAL (rstxn_rj*) agar mudah di-grep saat audit.
     */
    private function piutangRj(?Carbon $start, ?Carbon $end, string $klaim, string $search): Builder
    {
        $ekspresiBiaya = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                          + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                          + NVL(obt.v,0)  + NVL(lab.v,0)  + NVL(rad.v,0) + NVL(oth.v,0)";

        // Batasi agregasi tiap pos biaya HANYA ke transaksi belum lunas (txn_status='H').
        // Tanpa ini, GROUP BY menggilas seluruh tabel biaya (mis. rstxn_rjobats jutaan baris).
        $hanyaH = fn($sub) => $sub->select('rj_no')->from('rstxn_rjhdrs')->where('txn_status', 'H');

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoinSub(DB::table('rstxn_rjactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),   'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'), 'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'), 'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),    'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),       'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),       'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),   'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_rjcashins')->select('rj_no', DB::raw('NVL(SUM(rjc_nominal),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),  'cash', 'cash.rj_no', '=', 'h.rj_no')
            ->where('h.txn_status', 'H')
            ->when($start && $end, fn($subQuery) => $subQuery->whereBetween('h.rj_date', [$start, $end]));

        $query = $this->applyPiutangKlaim($query, $klaim);
        $this->applyPiutangSearch($query, $search, 'h.rj_no');

        return $query->selectRaw("'RJ' as jalur, h.rj_no as no_transaksi, h.reg_no, p.reg_name, p.sex,
            to_char(p.birth_date,'dd/mm/yyyy') as birth_date, p.address,
            to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as tgl,
            CAST(NULL AS VARCHAR2(30)) as tgl_masuk,
            po.poli_desc as unit, NVL(d.dr_name,'-') as dokter,
            k.klaim_status, k.klaim_desc, h.klaim_id,
            ({$ekspresiBiaya}) as total, NVL(h.rj_diskon,0) as diskon, NVL(cash.v,0) as bayar");
    }

    /**
     * Query mentah PIUTANG UGD — rstxn_ugdhdrs.txn_status='H'.
     * Nama tabel LITERAL (rstxn_ugd*) agar mudah di-grep saat audit.
     */
    private function piutangUgd(?Carbon $start, ?Carbon $end, string $klaim, string $search): Builder
    {
        $ekspresiBiaya = "NVL(h.rs_admin,0) + NVL(h.rj_admin,0) + NVL(h.poli_price,0)
                          + NVL(acte.v,0) + NVL(actp.v,0) + NVL(actd.v,0)
                          + NVL(obt.v,0)  + NVL(lab.v,0)  + NVL(rad.v,0) + NVL(oth.v,0)";

        // Batasi agregasi tiap pos biaya HANYA ke transaksi belum lunas (txn_status='H').
        $hanyaH = fn($sub) => $sub->select('rj_no')->from('rstxn_ugdhdrs')->where('txn_status', 'H');

        $query = DB::table('rstxn_ugdhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoinSub(DB::table('rstxn_ugdactemps')->select('rj_no', DB::raw('NVL(SUM(acte_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),   'acte', 'acte.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdactparams')->select('rj_no', DB::raw('NVL(SUM(pact_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'), 'actp', 'actp.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdaccdocs')->select('rj_no', DB::raw('NVL(SUM(accdoc_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'), 'actd', 'actd.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdobats')->select('rj_no', DB::raw('NVL(SUM(qty * price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),    'obt',  'obt.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdlabs')->select('rj_no', DB::raw('NVL(SUM(lab_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),       'lab',  'lab.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdrads')->select('rj_no', DB::raw('NVL(SUM(rad_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),       'rad',  'rad.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdothers')->select('rj_no', DB::raw('NVL(SUM(other_price),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),   'oth',  'oth.rj_no', '=', 'h.rj_no')
            ->leftJoinSub(DB::table('rstxn_ugdcashins')->select('rj_no', DB::raw('NVL(SUM(rjc_nominal),0) as v'))->whereIn('rj_no', $hanyaH)->groupBy('rj_no'),  'cash', 'cash.rj_no', '=', 'h.rj_no')
            ->where('h.txn_status', 'H')
            ->when($start && $end, fn($subQuery) => $subQuery->whereBetween('h.rj_date', [$start, $end]));

        $query = $this->applyPiutangKlaim($query, $klaim);
        $this->applyPiutangSearch($query, $search, 'h.rj_no');

        return $query->selectRaw("'UGD' as jalur, h.rj_no as no_transaksi, h.reg_no, p.reg_name, p.sex,
            to_char(p.birth_date,'dd/mm/yyyy') as birth_date, p.address,
            to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as tgl,
            CAST(NULL AS VARCHAR2(30)) as tgl_masuk,
            CAST(NULL AS VARCHAR2(200)) as unit, NVL(d.dr_name,'-') as dokter,
            k.klaim_status, k.klaim_desc, h.klaim_id,
            ({$ekspresiBiaya}) as total, NVL(h.rj_diskon,0) as diskon, NVL(cash.v,0) as bayar");
    }

    /**
     * Query mentah PIUTANG RAWAT INAP — rstxn_rihdrs.status_pulang='H'.
     * Total & pembayaran meniru kasir-ri (obat = pinjam + bonResep − retur).
     * Nama tabel LITERAL (rstxn_ri*) agar mudah di-grep saat audit.
     */
    private function piutangRi(?Carbon $start, ?Carbon $end, string $klaim, string $search): Builder
    {
        $ekspresiBiaya = "NVL(h.admin_age,0) + NVL(h.admin_status,0)
                          + NVL(vis.v,0) + NVL(kon.v,0) + NVL(jm.v,0) + NVL(jd.v,0)
                          + NVL(lab.v,0) + NVL(rad.v,0) + NVL(trf.v,0)
                          + NVL(oth.v,0) + NVL(ok.v,0)  + NVL(rm.v,0)
                          + NVL(bon.v,0) + NVL(obt.v,0) - NVL(rtn.v,0)";

        // Batasi agregasi tiap pos biaya HANYA ke transaksi RI belum lunas (status_pulang='H').
        $hanyaH = fn($sub) => $sub->select('rihdr_no')->from('rstxn_rihdrs')->where('status_pulang', 'H');

        $subQueryKamar = DB::table('rsmst_trfrooms')
            ->select('rihdr_no', DB::raw(
                "NVL(SUM((NVL(room_price,0)+NVL(common_service,0)+NVL(perawatan_price,0))
                  * ROUND(NVL(day, NVL(end_date,sysdate+1)-NVL(start_date,sysdate)))),0) as v"
            ))
            ->whereIn('rihdr_no', $hanyaH)
            ->groupBy('rihdr_no');

        $query = DB::table('rstxn_rihdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoinSub(DB::table('rstxn_rivisits')->select('rihdr_no', DB::raw('NVL(SUM(visit_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                       'vis', 'vis.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rikonsuls')->select('rihdr_no', DB::raw('NVL(SUM(konsul_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                     'kon', 'kon.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riactparams')->select('rihdr_no', DB::raw('NVL(SUM(actp_price * actp_qty),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),          'jm',  'jm.rihdr_no',  '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riactdocs')->select('rihdr_no', DB::raw('NVL(SUM(actd_price * actd_qty),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),            'jd',  'jd.rihdr_no',  '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rilabs')->select('rihdr_no', DB::raw('NVL(SUM(lab_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                           'lab', 'lab.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riradiologs')->select('rihdr_no', DB::raw('NVL(SUM(rirad_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                    'rad', 'rad.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_ritempadmins')->select('rihdr_no', DB::raw('NVL(SUM(NVL(rj_admin,0)+NVL(poli_price,0)+NVL(acte_price,0)+NVL(actp_price,0)+NVL(actd_price,0)+NVL(obat,0)+NVL(rad,0)+NVL(lab,0)+NVL(other,0)+NVL(rs_admin,0)),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'), 'trf', 'trf.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riothers')->select('rihdr_no', DB::raw('NVL(SUM(other_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                       'oth', 'oth.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_rioks')->select('rihdr_no', DB::raw('NVL(SUM(ok_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                             'ok',  'ok.rihdr_no',  '=', 'h.rihdr_no')
            ->leftJoinSub($subQueryKamar, 'rm', 'rm.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_ribonobats')->select('rihdr_no', DB::raw('NVL(SUM(ribon_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                     'bon', 'bon.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riobats')->select('rihdr_no', DB::raw('NVL(SUM(riobat_qty * riobat_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),          'obt', 'obt.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_riobatrtns')->select('rihdr_no', DB::raw('NVL(SUM(riobat_qty * riobat_price),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),       'rtn', 'rtn.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_ripaymentdtls')->select('rihdr_no', DB::raw('NVL(SUM(ripay_bayar),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                  'pd',  'pd.rihdr_no',  '=', 'h.rihdr_no')
            ->leftJoinSub(DB::table('rstxn_ripaymentpdtls')->select('rihdr_no', DB::raw('NVL(SUM(ripay_bayar),0) as v'))->whereIn('rihdr_no', $hanyaH)->groupBy('rihdr_no'),                 'pp',  'pp.rihdr_no',  '=', 'h.rihdr_no')
            ->where('h.status_pulang', 'H')
            ->when($start && $end, fn($subQuery) => $subQuery->whereBetween('h.exit_date', [$start, $end]));

        $query = $this->applyPiutangKlaim($query, $klaim);
        $this->applyPiutangSearch($query, $search, 'h.rihdr_no');

        return $query->selectRaw("'RI' as jalur, h.rihdr_no as no_transaksi, h.reg_no, p.reg_name, p.sex,
            to_char(p.birth_date,'dd/mm/yyyy') as birth_date, p.address,
            to_char(h.exit_date,'dd/mm/yyyy hh24:mi:ss') as tgl,
            to_char(h.entry_date,'dd/mm/yyyy hh24:mi:ss') as tgl_masuk,
            CAST(NULL AS VARCHAR2(200)) as unit, '-' as dokter,
            k.klaim_status, k.klaim_desc, h.klaim_id,
            ({$ekspresiBiaya}) as total, NVL(h.ri_diskon,0) as diskon, (NVL(pd.v,0) + NVL(pp.v,0)) as bayar");
    }

    /**
     * Isi kolom dokter untuk baris RAWAT INAP dari DPJP Utama (leveling dokter di JSON).
     * RI tak punya kolom dr_id di header; DPJP diambil dari
     * pengkajianAwalPasienRawatInap.levelingDokter[*] where levelDokter='Utama'.
     * Hanya baris RI di halaman aktif yang diproses (±perPage, ambil JSON by PK — ringan).
     */
    protected function isiDokterRiLeveling($barisHalaman): void
    {
        $barisRi = $barisHalaman->where('jalur', 'RI');
        if ($barisRi->isEmpty()) {
            return;
        }

        $nomorRiList = $barisRi->pluck('no_transaksi')->all();

        // Extract drId DPJP Utama per rihdr_no (Oracle JSON_VALUE tak tersedia → decode PHP).
        $drIdPerRi = [];
        $recordJson = DB::table('rstxn_rihdrs')
            ->whereIn('rihdr_no', $nomorRiList)
            ->selectRaw('rihdr_no, datadaftarri_json as json')
            ->get();
        foreach ($recordJson as $record) {
            $data = is_string($record->json) ? (json_decode($record->json, true) ?: []) : [];
            $drId = $this->extractDpjpUtamaLeveling($data);
            if ($drId !== null) {
                $drIdPerRi[(string) $record->rihdr_no] = $drId;
            }
        }

        // Lookup nama dokter sekali.
        $namaDokterMap = $drIdPerRi
            ? DB::table('rsmst_doctors')->whereIn('dr_id', array_unique(array_values($drIdPerRi)))->pluck('dr_name', 'dr_id')->toArray()
            : [];

        foreach ($barisRi as $baris) {
            $drId = $drIdPerRi[(string) $baris->no_transaksi] ?? null;
            $baris->dokter = $drId !== null ? ($namaDokterMap[$drId] ?? '-') : '-';
        }
    }

    /** Ambil drId DPJP Utama dari struktur JSON leveling dokter RI. */
    private function extractDpjpUtamaLeveling(array $json): ?string
    {
        $levelingList = $json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
        if (!is_array($levelingList)) {
            return null;
        }
        foreach ($levelingList as $entri) {
            if (!is_array($entri)) {
                continue;
            }
            if (strcasecmp((string) ($entri['levelDokter'] ?? ''), 'Utama') === 0) {
                $drId = (string) ($entri['drId'] ?? '');
                return $drId !== '' ? $drId : null;
            }
        }
        return null;
    }

    /**
     * Sub-query gabungan RJ+UGD+RI (sesuai filterJalur), sudah menghitung `sisa`.
     */
    protected function piutangUnionSub(?Carbon $start, ?Carbon $end, string $jalur, string $klaim, string $search): Builder
    {
        $bagianList = [];
        if ($jalur === '' || $jalur === 'RJ')  $bagianList[] = $this->piutangRj($start, $end, $klaim, $search);
        if ($jalur === '' || $jalur === 'UGD') $bagianList[] = $this->piutangUgd($start, $end, $klaim, $search);
        if ($jalur === '' || $jalur === 'RI')  $bagianList[] = $this->piutangRi($start, $end, $klaim, $search);

        $union = array_shift($bagianList);
        foreach ($bagianList as $bagian) {
            $union->unionAll($bagian);
        }

        return DB::query()
            ->fromSub($union, 'u')
            ->selectRaw('u.*, GREATEST(0, u.total - u.diskon - u.bayar) as sisa');
    }

    /**
     * Baris piutang untuk SATU halaman (sisa>0, urut sisa terbesar).
     * Ambil $perPage baris via forPage() — TIDAK menarik semua ke PHP.
     * Total halaman dirakit pemanggil pakai jumlah dari piutangSummary() (di-cache).
     */
    protected function piutangPageItems(?Carbon $start, ?Carbon $end, string $jalur, string $klaim, string $search, int $perPage, int $page)
    {
        return DB::query()
            ->fromSub($this->piutangUnionSub($start, $end, $jalur, $klaim, $search), 'p')
            ->where('sisa', '>', 0)
            ->orderByDesc('sisa')
            ->orderByDesc('no_transaksi')
            ->forPage($page, $perPage)
            ->get();
    }

    /**
     * Agregat ringkasan (COUNT + SUM di SQL), di-cache 120 detik per-kombinasi filter
     * agar navigasi antar-halaman tak mengulang agregat berat.
     */
    protected function piutangSummary(?Carbon $start, ?Carbon $end, string $jalur, string $klaim, string $search): array
    {
        $cacheKey = 'piutang-pasien:summary:' . md5(implode('|', [
            $start?->toDateString() ?? '', $end?->toDateString() ?? '', $jalur, $klaim, $search,
        ]));

        return Cache::remember($cacheKey, 120, function () use ($start, $end, $jalur, $klaim, $search) {
            $ringkasan = DB::query()
                ->fromSub($this->piutangUnionSub($start, $end, $jalur, $klaim, $search), 'p')
                ->where('sisa', '>', 0)
                ->selectRaw("COUNT(*) as jumlah,
                    NVL(SUM(sisa),0)  as sisa,
                    NVL(SUM(total),0) as tagihan,
                    NVL(SUM(bayar),0) as bayar,
                    NVL(SUM(CASE WHEN jalur='RJ'  THEN sisa ELSE 0 END),0) as rj,
                    NVL(SUM(CASE WHEN jalur='UGD' THEN sisa ELSE 0 END),0) as ugd,
                    NVL(SUM(CASE WHEN jalur='RI'  THEN sisa ELSE 0 END),0) as ri")
                ->first();

            return [
                'jumlah'  => (int) ($ringkasan->jumlah ?? 0),
                'sisa'    => (int) ($ringkasan->sisa ?? 0),
                'tagihan' => (int) ($ringkasan->tagihan ?? 0),
                'bayar'   => (int) ($ringkasan->bayar ?? 0),
                'rj'      => (int) ($ringkasan->rj ?? 0),
                'ugd'     => (int) ($ringkasan->ugd ?? 0),
                'ri'      => (int) ($ringkasan->ri ?? 0),
            ];
        });
    }
}
