<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {

    #[On('cetak-kwitansi-ri-obat.open')]
    public function open(int $slsNo): mixed
    {
        $hdr = DB::selectOne(
            "
            SELECT
                s.sls_no,
                TO_CHAR(s.sls_date, 'DD/MM/YYYY HH24:MI') AS sls_date,
                s.status,
                s.rihdr_no,
                s.reg_no,
                s.dr_id,
                s.acc_id,
                s.acte_price,
                s.sls_total,
                s.sls_bayar,
                s.sls_bon,
                s.shift,
                s.emp_id,
                p.reg_name,
                p.sex,
                TO_CHAR(p.birth_date, 'DD/MM/YYYY') AS birth_date,
                d.dr_name,
                r.klaim_id,
                k.klaim_desc,
                a.acc_name,
                rm.room_name
            FROM imtxn_slshdrs s
            JOIN rsmst_pasiens p ON p.reg_no = s.reg_no
            LEFT JOIN rsmst_doctors d ON d.dr_id = s.dr_id
            JOIN rstxn_rihdrs r ON r.rihdr_no = s.rihdr_no
            LEFT JOIN rsmst_klaimtypes k ON k.klaim_id = r.klaim_id
            LEFT JOIN acmst_accounts a ON a.acc_id = s.acc_id
            LEFT JOIN (
                SELECT t.rihdr_no, MAX(t.trfr_no) AS trfr_no
                FROM rsmst_trfrooms t
                GROUP BY t.rihdr_no
            ) tlast ON tlast.rihdr_no = s.rihdr_no
            LEFT JOIN rsmst_trfrooms trf ON trf.rihdr_no = tlast.rihdr_no AND trf.trfr_no = tlast.trfr_no
            LEFT JOIN rsmst_rooms rm ON rm.room_id = trf.room_id
            WHERE s.sls_no = :slsno
            ",
            ['slsno' => $slsNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data resep tidak ditemukan.');
            return null;
        }

        $items = DB::select(
            "
            SELECT
                NVL(p.product_name, dtl.product_id) AS product_name,
                dtl.qty,
                dtl.sales_price,
                NVL(dtl.qty,0) * NVL(dtl.sales_price,0) AS subtotal_item,
                dtl.resep_carapakai,
                dtl.resep_kapsul,
                dtl.resep_takar
            FROM imtxn_slsdtls dtl
            LEFT JOIN immst_products p ON p.product_id = dtl.product_id
            WHERE dtl.sls_no = :slsno
            ORDER BY dtl.sls_dtl
            ",
            ['slsno' => $slsNo],
        );

        if (empty($items)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data obat untuk resep ini.');
            return null;
        }

        $subtotal = (int) collect($items)->sum('subtotal_item');
        $actePrice = (int) ($hdr->acte_price ?? 0);
        $totalObat = $subtotal + $actePrice;
        $bayar = (int) ($hdr->sls_bayar ?? 0);
        $bon = max(0, $totalObat - $bayar);

        $kasirName = null;
        if (!empty($hdr->emp_id)) {
            $kasirName = DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name');
        }

        $klaimName = $hdr->klaim_desc ?? ($hdr->klaim_id ?? '-');
        $drName = $hdr->dr_name ?? ($hdr->dr_id ?? '-');

        $data = [
            // Pasien
            'regNo' => $hdr->reg_no,
            'regName' => $hdr->reg_name,
            'sex' => $hdr->sex,
            'birthDate' => $hdr->birth_date ?? '-',

            // Resep / RI
            'slsNo' => $slsNo,
            'slsDate' => $hdr->sls_date ?? '-',
            'rihdrNo' => $hdr->rihdr_no,
            'roomDesc' => $hdr->room_name ?? '-',  // dari kolom rsmst_rooms.room_name
            'drName' => $drName,
            'klaimName' => $klaimName,
            'accName' => $hdr->acc_name ?? ($hdr->acc_id ?? '-'),

            // Rincian
            'items' => $items,
            'subtotal' => $subtotal,
            'actePrice' => $actePrice,
            'totalObat' => $totalObat,
            'bayar' => $bayar,
            'bon' => $bon,
            'isLunas' => $bon === 0,

            // Footer
            'kasirName' => $kasirName,
            'tglCetak' => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak' => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-obat-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-ri-obat-' . ($hdr->reg_no ?? $slsNo) . '-' . $slsNo . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
