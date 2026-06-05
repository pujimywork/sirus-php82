<?php
// resources/views/pages/components/rekam-medis/u-g-d/etiket-obat/cetak-etiket-obat-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /* ===============================
     | OPEN & LANGSUNG CETAK — per rjobat_dtl UGD
     =============================== */
    #[On('cetak-etiket-obat-ugd.open')]
    public function open(int $rjObatNo = 0): mixed
    {
        if (!$rjObatNo) {
            $this->dispatch('toast', type: 'error', message: 'ID obat tidak valid.');
            return null;
        }

        $obat = DB::selectOne(
            "
            SELECT
                TO_CHAR(a.rj_date, 'DD/MM/YYYY')    AS rj_date,
                a.reg_no,
                d.reg_name,
                d.sex,
                d.birth_place,
                TO_CHAR(d.birth_date, 'DD/MM/YYYY') AS birth_date,
                d.address,
                b.product_id,
                c.product_name,
                b.ugd_carapakai,
                b.ugd_takar,
                b.ugd_kapsul,
                b.ugd_ket,
                TO_CHAR(b.exp_date, 'DD/MM/YYYY')   AS exp_date
            FROM  rstxn_ugdhdrs  a
            JOIN  rstxn_ugdobats  b ON b.rj_no      = a.rj_no
            JOIN  immst_products  c ON c.product_id  = b.product_id
            JOIN  rsmst_pasiens   d ON d.reg_no      = a.reg_no
            WHERE b.rjobat_dtl = :rjobatno
            ",
            ['rjobatno' => $rjObatNo],
        );

        if (!$obat) {
            $this->dispatch('toast', type: 'error', message: 'Data obat tidak ditemukan.');
            return null;
        }

        // ── Hitung umur realtime — tahun saja (format etiket pasien, mis. "63 tahun") ──
        $umurTahun = null;
        if (!empty($obat->birth_date)) {
            try {
                $umurTahun = Carbon::createFromFormat('d/m/Y', $obat->birth_date)->diff(Carbon::now(config('app.timezone')))->y;
            } catch (\Throwable) {
            }
        }

        $data = [
            'umurTahun' => $umurTahun,
            'obat' => $obat,
        ];

        set_time_limit(300);

        // Paper 6x4cm dalam points — sama dengan layout-etiket (bukan A4!)
        $pdf = Pdf::loadView('pages.components.rekam-medis.u-g-d.etiket-obat.cetak-etiket-obat-print', ['data' => $data])->setPaper([0, 0, 170.08, 113.39]);

        $filename = 'etiket-ugd-' . ($obat->reg_no ?? $rjObatNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
