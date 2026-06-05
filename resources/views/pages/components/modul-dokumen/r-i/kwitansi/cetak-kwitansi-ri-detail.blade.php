<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    #[On('cetak-kwitansi-ri-detail.open')]
    public function open(int $riHdrNo): mixed
    {
        $hdr = DB::selectOne(
            "
            SELECT
                a.rihdr_no,
                a.reg_no,
                b.reg_name,
                b.address,
                b.sex,
                b.birth_date,
                TO_CHAR(a.entry_date, 'DD/MM/YYYY HH24:MI') AS entry_date,
                TO_CHAR(a.exit_date,  'DD/MM/YYYY HH24:MI') AS exit_date,
                a.emp_id,
                a.ri_diskon,
                a.klaim_id,
                a.dr_id,
                a.vno_sep,
                a.status_pulang,
                a.ri_status,
                a.out_no
            FROM  rstxn_rihdrs  a
            JOIN  rsmst_pasiens b ON b.reg_no = a.reg_no
            WHERE a.rihdr_no = :rihdr
            ",
            ['rihdr' => $riHdrNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $costs = $this->calculateRICosts($riHdrNo);

        // ─── Section A: transfer kamar ───
        $trfrooms = DB::table('rsmst_trfrooms as t')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 't.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->where('t.rihdr_no', $riHdrNo)
            ->orderBy('t.trfr_no')
            ->select(
                'b.bangsal_name', 'r.room_name', 't.bed_no', 't.room_price', 't.day',
                DB::raw("TO_CHAR(t.start_date, 'DD/MM/YYYY HH24:MI') AS start_date"),
                DB::raw("TO_CHAR(t.end_date,   'DD/MM/YYYY HH24:MI') AS end_date"),
                DB::raw("ROUND(NVL(t.day, NVL(t.end_date, sysdate+1) - NVL(t.start_date, sysdate))) AS effective_day"),
            )
            ->get()
            ->map(function ($r) {
                $days  = (int) ($r->effective_day ?? 0);
                $price = (int) ($r->room_price ?? 0);
                $room  = $r->room_name ?? '-';
                return (object) [
                    'start_date'   => $r->start_date,
                    'end_date'     => $r->end_date,
                    'room_label'   => $room,
                    'day'          => $days,
                    'room_price'   => $price,
                    'room_total'   => $price * $days,
                ];
            });

        // ─── Section B per-item ───
        // Pola: tiap entry {desc, qty (nullable), nominal}
        $bItems = collect();

        // KONSUL per dokter
        $konsulGrp = DB::table('rstxn_rikonsuls as k')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'k.dr_id')
            ->where('k.rihdr_no', $riHdrNo)
            ->selectRaw('k.dr_id, d.dr_name, COUNT(*) as qty, SUM(NVL(k.konsul_price,0)) as total')
            ->groupBy('k.dr_id', 'd.dr_name')
            ->orderBy('d.dr_name')
            ->get();
        foreach ($konsulGrp as $g) {
            $bItems->push((object) [
                'desc'  => 'KONSUL - ' . ($g->dr_name ?: $g->dr_id),
                'qty'   => (int) $g->qty,
                'total' => (int) $g->total,
            ]);
        }

        // VISITE per dokter
        $visitGrp = DB::table('rstxn_rivisits as v')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'v.dr_id')
            ->where('v.rihdr_no', $riHdrNo)
            ->selectRaw('v.dr_id, d.dr_name, COUNT(*) as qty, SUM(NVL(v.visit_price,0)) as total')
            ->groupBy('v.dr_id', 'd.dr_name')
            ->orderBy('d.dr_name')
            ->get();
        foreach ($visitGrp as $g) {
            $bItems->push((object) [
                'desc'  => 'VISITE - ' . ($g->dr_name ?: $g->dr_id),
                'qty'   => (int) $g->qty,
                'total' => (int) $g->total,
            ]);
        }

        // TINDAKAN DOKTER per accdoc_id — qty = SUM(actd_qty)
        $tdGrp = DB::table('rstxn_riactdocs as a')
            ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'a.accdoc_id')
            ->where('a.rihdr_no', $riHdrNo)
            ->selectRaw('a.accdoc_id, m.accdoc_desc, SUM(NVL(a.actd_qty,0)) as qty, SUM(NVL(a.actd_price,0) * NVL(a.actd_qty,0)) as total')
            ->groupBy('a.accdoc_id', 'm.accdoc_desc')
            ->orderBy('m.accdoc_desc')
            ->get();
        foreach ($tdGrp as $g) {
            $bItems->push((object) [
                'desc'  => 'TINDAKAN DOKTER - ' . ($g->accdoc_desc ?: $g->accdoc_id),
                'qty'   => (int) $g->qty,
                'total' => (int) $g->total,
            ]);
        }

        // PARAMEDIS per pact_id — qty = SUM(actp_qty)
        $pmGrp = DB::table('rstxn_riactparams as a')
            ->leftJoin('rsmst_actparamedics as m', 'm.pact_id', '=', 'a.pact_id')
            ->where('a.rihdr_no', $riHdrNo)
            ->selectRaw('a.pact_id, m.pact_desc, SUM(NVL(a.actp_qty,0)) as qty, SUM(NVL(a.actp_price,0) * NVL(a.actp_qty,0)) as total')
            ->groupBy('a.pact_id', 'm.pact_desc')
            ->orderBy('m.pact_desc')
            ->get();
        foreach ($pmGrp as $g) {
            $bItems->push((object) [
                'desc'  => 'PARAMEDIS - ' . ($g->pact_desc ?: $g->pact_id),
                'qty'   => (int) $g->qty,
                'total' => (int) $g->total,
            ]);
        }

        // Summary line — JASA PERAWATAN / PELAYANAN UMUM / ADMINISTRASI RI (tanpa qty)
        if (($v = (int) $costs['perawatan']) > 0) {
            $bItems->push((object) ['desc' => 'JASA PERAWATAN', 'qty' => null, 'total' => $v]);
        }
        if (($v = (int) $costs['commonService']) > 0) {
            $bItems->push((object) ['desc' => 'PELAYANAN UMUM', 'qty' => null, 'total' => $v]);
        }
        if (($v = (int) $costs['adminAge'] + (int) $costs['adminStatus']) > 0) {
            $bItems->push((object) ['desc' => 'ADMINISTRASI RAWAT INAP', 'qty' => null, 'total' => $v]);
        }
        $bTotal = (int) $bItems->sum('total');

        // ─── Section C/D/E ───
        $cLab = (int) $costs['lab'];
        $cRad = (int) $costs['rad'];
        $cTotal = $cLab + $cRad;

        $dObatPinjam = (int) $costs['obatPinjam'];
        $dBonResep   = (int) $costs['bonResep'];
        $dResepLunas = 0;
        $dTotal      = $dObatPinjam + $dBonResep + $dResepLunas;

        $eOperasi = (int) $costs['ok'];

        // ─── Section F: BIAYA RJ/UGD + per-item LAIN-LAIN ───
        $fTrfRjUgd = (int) $costs['trfUgdRj'];
        $fOthers = DB::table('rstxn_riothers as o')
            ->leftJoin('rsmst_others as m', 'm.other_id', '=', 'o.other_id')
            ->where('o.rihdr_no', $riHdrNo)
            ->selectRaw('o.other_id, m.other_desc, COUNT(*) as qty, SUM(NVL(o.other_price,0)) as total')
            ->groupBy('o.other_id', 'm.other_desc')
            ->orderBy('m.other_desc')
            ->get()
            ->map(fn($r) => (object) [
                'desc'  => $r->other_desc ?: ('LAIN-LAIN - ' . $r->other_id),
                'qty'   => (int) $r->qty,
                'total' => (int) $r->total,
            ]);
        $fTotal = $fTrfRjUgd + (int) $fOthers->sum('total');

        // ─── Section G ───
        $gRetur = (int) $costs['rtnObat'];

        // ─── Total / pembayaran ───
        $aRoom = (int) $costs['room'];
        $subtotal   = $aRoom + $bTotal + $cTotal + $dTotal + $eOperasi + $fTotal - $gRetur;
        $subsidi    = (int) ($hdr->ri_diskon ?? 0);
        $grandTotal = max(0, $subtotal - $subsidi);

        $sudahBayar = (int) DB::table('rstxn_ripaymentpdtls')->where('rihdr_no', $riHdrNo)->sum('ripay_bayar');
        $sisa       = max(0, $grandTotal - $sudahBayar);

        $kasirName = !empty($hdr->emp_id)
            ? DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name')
            : null;

        $klaimRow  = DB::table('rsmst_klaimtypes')->where('klaim_id', $hdr->klaim_id ?? '')->select('klaim_desc')->first();
        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        $data = [
            'regNo'      => $hdr->reg_no,
            'regName'    => $hdr->reg_name,
            'address'    => $hdr->address,
            'sex'        => $hdr->sex,
            'riHdrNo'    => $riHdrNo,
            'entryDate'  => $hdr->entry_date ?? '-',
            'exitDate'   => $hdr->exit_date ?? '-',
            'vnoSep'     => $hdr->vno_sep,
            'klaimName'  => $klaimName,

            'trfrooms'   => $trfrooms,
            'aTotal'     => $aRoom,

            'bItems'     => $bItems,
            'bTotal'     => $bTotal,

            'cLab'   => $cLab,
            'cRad'   => $cRad,
            'cTotal' => $cTotal,

            'dObatPinjam' => $dObatPinjam,
            'dBonResep'   => $dBonResep,
            'dResepLunas' => $dResepLunas,
            'dTotal'      => $dTotal,

            'eOperasi' => $eOperasi,

            'fTrfRjUgd' => $fTrfRjUgd,
            'fOthers'   => $fOthers,
            'fTotal'    => $fTotal,

            'gReturObat' => $gRetur,

            'subtotal'   => $subtotal,
            'subsidi'    => $subsidi,
            'grandTotal' => $grandTotal,
            'sudahBayar' => $sudahBayar,
            'sisa'       => $sisa,

            'kasirName' => $kasirName,
            'tglCetak'  => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d F Y'),
            'jamCetak'  => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-detail-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-ri-detail-' . ($hdr->reg_no ?? $riHdrNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>

<div></div>
