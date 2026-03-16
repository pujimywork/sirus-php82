<?php
// resources/views/pages/components/modul-dokumen/u-g-d/kwitansi/cetak-kwitansi-ugd-obat.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    /* ═══════════════════════════════════════
     | OPEN & LANGSUNG CETAK
    ═══════════════════════════════════════ */
    #[On('cetak-kwitansi-ugd-obat.open')]
    public function open(int $rjNo): mixed
    {
        // ── Query 1: Header UGD + Data Pasien ──
        $hdr = DB::selectOne(
            "
            SELECT
                a.rj_no,
                TO_CHAR(a.rj_date, 'DD/MM/YYYY HH24:MI') AS rj_date,
                a.reg_no,
                b.reg_name,
                b.sex,
                TO_CHAR(b.birth_date, 'DD/MM/YYYY')       AS birth_date,
                a.emp_id,
                a.klaim_id,
                a.dr_id,
                a.poli_id
            FROM  rstxn_ugdhdrs  a
            JOIN  rsmst_pasiens  b ON b.reg_no = a.reg_no
            WHERE a.rj_no = :rjno
        ",
            ['rjno' => $rjNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        // ── Query 2: Rincian Obat ──
        $rincianObat = DB::select(
            "
            SELECT
                (b.product_name || '   ' || COUNT(*) || ' (X)') AS keterangan,
                SUM(NVL(a.qty, 0) * NVL(a.price, 0))            AS obat
            FROM  rstxn_ugdobats  a
            JOIN  immst_products  b ON b.product_id = a.product_id
            WHERE a.rj_no = :rjno
            GROUP BY b.product_name
            ORDER BY b.product_name
        ",
            ['rjno' => $rjNo],
        );

        if (empty($rincianObat)) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data obat untuk kunjungan ini.');
            return null;
        }

        // ── Kalkulasi Total Obat ──
        $totalObat = (int) collect($rincianObat)->sum('obat');

        // ── Nama Kasir ──
        $kasirName = null;
        if (!empty($hdr->emp_id)) {
            $kasirName = DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name');
        }

        // ── Klaim ──
        $klaimRow = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $hdr->klaim_id ?? '')
            ->select('klaim_desc')
            ->first();

        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        // ── Unit ──
        $poliDesc =
            DB::table('rsmst_polis')
                ->where('poli_id', $hdr->poli_id ?? '')
                ->value('poli_desc') ?? 'UGD';

        // ── Dokter ──
        $drName =
            DB::table('rsmst_doctors')
                ->where('dr_id', $hdr->dr_id ?? '')
                ->value('dr_name') ??
            ($hdr->dr_id ?? '-');

        $data = [
            // ── Pasien ──
            'regNo' => $hdr->reg_no,
            'regName' => $hdr->reg_name,
            'sex' => $hdr->sex,
            'birthDate' => $hdr->birth_date ?? '-',

            // ── Kunjungan ──
            'rjNo' => $rjNo,
            'rjDate' => $hdr->rj_date ?? '-',
            'drName' => $drName,
            'poliName' => $poliDesc,
            'klaimName' => $klaimName,

            // ── Rincian Obat ──
            'rincianObat' => $rincianObat,
            'totalObat' => $totalObat,

            // ── Kasir / Cetak ──
            'kasirName' => $kasirName,
            'tglCetak' => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak' => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        // ── Generate PDF ──
        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.kwitansi.cetak-kwitansi-ugd-obat-print', [
            'data' => $data,
        ])->setPaper('A4');

        $filename = 'kwitansi-obat-ugd-' . ($hdr->reg_no ?? $rjNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
