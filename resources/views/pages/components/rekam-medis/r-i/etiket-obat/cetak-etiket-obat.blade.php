<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /* ═══════════════════════════════════════
     | OPEN & LANGSUNG CETAK — per imtxn_slsdtls.sls_dtl
    ═══════════════════════════════════════ */
    #[On('cetak-etiket-obat-ri.open')]
    public function open(int $slsDtl = 0): mixed
    {
        if (!$slsDtl) {
            $this->dispatch('toast', type: 'error', message: 'ID obat tidak valid.');
            return null;
        }

        $obat = DB::selectOne(
            "
            SELECT
                TO_CHAR(s.sls_date, 'DD/MM/YYYY')   AS sls_date,
                s.reg_no,
                d.reg_name,
                d.sex,
                d.birth_place,
                TO_CHAR(d.birth_date, 'DD/MM/YYYY') AS birth_date,
                d.address,
                b.product_id,
                c.product_name,
                b.resep_carapakai,
                b.resep_takar,
                b.resep_kapsul,
                b.resep_ket,
                TO_CHAR(b.exp_date, 'DD/MM/YYYY')   AS exp_date
            FROM  imtxn_slshdrs  s
            JOIN  imtxn_slsdtls  b ON b.sls_no     = s.sls_no
            JOIN  immst_products c ON c.product_id = b.product_id
            JOIN  rsmst_pasiens  d ON d.reg_no     = s.reg_no
            WHERE b.sls_dtl = :slsdtl
            ",
            ['slsdtl' => $slsDtl],
        );

        if (!$obat) {
            $this->dispatch('toast', type: 'error', message: 'Data obat tidak ditemukan.');
            return null;
        }

        // ── Hitung umur realtime ──
        $umur = '-';
        if (!empty($obat->birth_date)) {
            try {
                $umur = Carbon::createFromFormat('d/m/Y', $obat->birth_date)
                    ->diff(Carbon::now(env('APP_TIMEZONE')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
            }
        }

        $data = [
            'umur' => $umur,
            'obat' => $obat,
        ];

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.rekam-medis.r-i.etiket-obat.cetak-etiket-obat-print', ['data' => $data])->setPaper('A4');

        $filename = 'etiket-ri-' . ($obat->reg_no ?? $slsDtl) . '-' . $slsDtl . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
