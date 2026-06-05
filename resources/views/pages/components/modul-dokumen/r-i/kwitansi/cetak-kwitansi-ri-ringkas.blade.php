<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    #[On('cetak-kwitansi-ri-ringkas.open')]
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

        // Detail transfer kamar (Section A — sama dgn Model 1)
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
                // Label kompak: room_name saja (skip bangsal_name supaya tidak wrap)
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

        // ─── Mapping 7 section seperti Oracle Reports legacy ───
        $bDokter         = (int) $costs['visit'] + (int) $costs['konsul'];
        $bTindakanDokter = (int) $costs['jasaDokter'];
        $bTindakanMedis  = (int) $costs['jasaMedis'];
        $bJasaPerawatan  = (int) $costs['perawatan'];
        $bPelayananUmum  = (int) $costs['commonService'];
        $bSubtotal       = $bDokter + $bTindakanDokter + $bTindakanMedis + $bJasaPerawatan + $bPelayananUmum;

        $cLab = (int) $costs['lab'];
        $cRad = (int) $costs['rad'];
        $cSub = $cLab + $cRad;

        $dObatPinjam = (int) $costs['obatPinjam'];
        $dBonResep   = (int) $costs['bonResep'];
        // RESEP LUNAS = porsi obat yg sudah dilunasi di apotek (belum ada flag eksplisit di sistem;
        //  placeholder 0 — bisa di-isi nanti dari sumber penjualan apotek bila tabelnya difinalkan)
        $dResepLunas = 0;
        $dSub        = $dObatPinjam + $dBonResep + $dResepLunas;

        $eOperasi = (int) $costs['ok'];

        $fAdminAge       = (int) $costs['adminAge'];
        $fStatus         = (int) $costs['adminStatus'];
        $fTrfRjUgd       = (int) $costs['trfUgdRj'];

        // LAIN-LAIN per other_id (ABHP, sewa alat, oksigen, darah, dst) — qty = COUNT(*)
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
        $fLainLain       = (int) $fOthers->sum('total');
        $fSub            = $fAdminAge + $fStatus + $fTrfRjUgd + $fLainLain;

        $gRetur          = (int) $costs['rtnObat'];

        // Section A (room) total
        $aRoom = (int) $costs['room'];

        $subtotal   = $aRoom + $bSubtotal + $cSub + $dSub + $eOperasi + $fSub - $gRetur;
        $subsidi    = (int) ($hdr->ri_diskon ?? 0);
        $resepLunasFooter = $dResepLunas;
        $grandTotal = max(0, $subtotal - $subsidi - $resepLunasFooter);

        // Pembayaran (angsuran) yang sudah masuk
        $sudahBayar = (int) DB::table('rstxn_ripaymentpdtls')->where('rihdr_no', $riHdrNo)->sum('ripay_bayar');
        $sisa       = max(0, $grandTotal - $sudahBayar);

        // Kasir & dokter
        $kasirName = !empty($hdr->emp_id)
            ? DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name')
            : null;

        $drName = DB::table('rsmst_doctors')->where('dr_id', $hdr->dr_id ?? '')->value('dr_name') ?? ($hdr->dr_id ?? '-');

        $klaimRow  = DB::table('rsmst_klaimtypes')->where('klaim_id', $hdr->klaim_id ?? '')->select('klaim_desc')->first();
        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        // Umur dihitung dari birth_date (kolom thn/bln/hari snapshot, jangan dipakai)
        $umurLabel = '-';
        if (!empty($hdr->birth_date)) {
            try {
                $birth = Carbon::parse($hdr->birth_date);
                $umurLabel = $birth->age . ' th';
            } catch (\Throwable $e) {
                $umurLabel = '-';
            }
        }

        $data = [
            'regNo'      => $hdr->reg_no,
            'regName'    => $hdr->reg_name,
            'address'    => $hdr->address,
            'sex'        => $hdr->sex,
            'umur'       => $umurLabel,
            'riHdrNo'    => $riHdrNo,
            'entryDate'  => $hdr->entry_date ?? '-',
            'exitDate'   => $hdr->exit_date ?? '-',
            'vnoSep'     => $hdr->vno_sep,
            'drName'     => $drName,
            'klaimName'  => $klaimName,

            // SECTION A
            'trfrooms'   => $trfrooms,
            'aTotal'     => $aRoom,

            // SECTION B (5 line ringkas)
            'bDokter'          => $bDokter,
            'bTindakanDokter'  => $bTindakanDokter,
            'bTindakanMedis'   => $bTindakanMedis,
            'bJasaPerawatan'   => $bJasaPerawatan,
            'bPelayananUmum'   => $bPelayananUmum,
            'bTotal'           => $bSubtotal,

            // SECTION C
            'cLab'   => $cLab,
            'cRad'   => $cRad,
            'cTotal' => $cSub,

            // SECTION D
            'dObatPinjam' => $dObatPinjam,
            'dBonResep'   => $dBonResep,
            'dResepLunas' => $dResepLunas,
            'dTotal'      => $dSub,

            // SECTION E
            'eOperasi' => $eOperasi,

            // SECTION F
            'fAdministrasi' => $fAdminAge,
            'fStatus'       => $fStatus,
            'fTrfRjUgd'     => $fTrfRjUgd,
            'fOthers'       => $fOthers,
            'fTotal'        => $fSub,

            // SECTION G
            'gReturObat' => $gRetur,

            // FOOTER
            'subtotal'         => $subtotal,
            'resepLunasFooter' => $resepLunasFooter,
            'subsidi'          => $subsidi,
            'grandTotal'       => $grandTotal,
            'sudahBayar'       => $sudahBayar,
            'sisa'             => $sisa,

            'kasirName'  => $kasirName,
            'tglCetak'   => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d F Y'),
            'jamCetak'   => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh'  => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-ringkas-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-ri-ringkas-' . ($hdr->reg_no ?? $riHdrNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>

<div></div>
