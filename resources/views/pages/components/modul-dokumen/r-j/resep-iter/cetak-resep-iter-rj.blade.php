<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    #[On('cetak-resep-iter-rj.open')]
    public function open(int $rjNo): mixed
    {
        $hdr = DB::selectOne(
            "
            SELECT
                a.rj_no,
                TO_CHAR(a.rj_date, 'DD/MM/YYYY HH24:MI') AS rj_date,
                a.reg_no,
                b.reg_name,
                b.address,
                b.sex,
                TO_CHAR(b.birth_date, 'DD/MM/YYYY') AS birth_date,
                a.dr_id,
                a.klaim_id,
                a.poli_id,
                a.vno_sep
            FROM  rstxn_rjhdrs  a
            JOIN  rsmst_pasiens b ON b.reg_no = a.reg_no
            WHERE a.rj_no = :rjno
            ",
            ['rjno' => $rjNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return null;
        }

        $obatIter = DB::table('rstxn_rjobats as a')
            ->join('immst_products as b', 'b.product_id', '=', 'a.product_id')
            ->where('a.rj_no', $rjNo)
            ->where('a.status_iter', 'Y')
            ->orderBy('a.rjobat_dtl')
            ->select([
                'b.product_name',
                'a.qty',
                'a.iter_qty',
                'a.rj_carapakai',
                'a.rj_kapsul',
                'a.rj_takar',
                'a.rj_ket',
            ])
            ->get();

        if ($obatIter->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada obat iter untuk kunjungan ini.');
            return null;
        }

        $drName = DB::table('rsmst_doctors')->where('dr_id', $hdr->dr_id ?? '')->value('dr_name') ?? ($hdr->dr_id ?? '-');
        $poliDesc = DB::table('rsmst_polis')->where('poli_id', $hdr->poli_id ?? '')->value('poli_desc') ?? '-';

        $data = [
            'regNo'      => $hdr->reg_no,
            'regName'    => $hdr->reg_name,
            'address'    => $hdr->address,
            'sex'        => $hdr->sex,
            'birthDate'  => $hdr->birth_date ?? '-',
            'rjNo'       => $rjNo,
            'rjDate'     => $hdr->rj_date ?? '-',
            'vnoSep'     => $hdr->vno_sep,
            'drName'     => $drName,
            'poliName'   => $poliDesc,
            'obat'       => $obatIter,
            'tglCetak'   => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak'   => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh'  => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.resep-iter.cetak-resep-iter-rj-print', ['data' => $data])->setPaper('A4');

        $filename = 'resep-iter-' . ($hdr->reg_no ?? $rjNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>

<div></div>
