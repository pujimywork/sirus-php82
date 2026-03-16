<?php
// resources/views/pages/components/modul-dokumen/u-g-d/kwitansi/cetak-kwitansi-ugd.blade.php

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
    #[On('cetak-kwitansi-ugd.open')]
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
                b.address,
                b.sex,
                TO_CHAR(b.birth_date, 'DD/MM/YYYY')       AS birth_date,
                a.emp_id,
                a.rj_diskon,
                a.klaim_id,
                a.dr_id,
                a.vno_sep,
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

        // ── Query 2: Rincian Biaya ──
        $rincian = DB::select(
            "
            SELECT txn_id, txn_desc, txn_nominal, txn_no
            FROM   rsview_ugdstrs
            WHERE  rj_no       = :rjno
              AND  txn_nominal > 0
            ORDER  BY txn_no
        ",
            ['rjno' => $rjNo],
        );

        // ── Kalkulasi Biaya ──
        $subtotal = (int) collect($rincian)->sum('txn_nominal');
        $diskon = (int) ($hdr->rj_diskon ?? 0);
        $grandTotal = max(0, $subtotal - $diskon);

        // ── Bayar dari riwayat kasir ──
        $sudahBayar = (int) DB::table('rstxn_ugdcashins')->where('rj_no', $rjNo)->sum('rjc_nominal');

        // ── Sisa tagihan ──
        $sisa = max(0, $grandTotal - $sudahBayar);

        // ── Nama Kasir dari immst_employers via emp_id di header ──
        $kasirName = null;
        if (!empty($hdr->emp_id)) {
            $kasirName = DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name');
        }

        // ── Data JSON UGD ──
        $dataUGD = $this->findDataUGD($rjNo) ?? [];

        // ── Klaim ──
        $klaimRow = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $hdr->klaim_id ?? '')
            ->select('klaim_desc')
            ->first();

        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        // ── Deteksi BPJS ──
        $isBpjs = ($dataUGD['klaimStatus'] ?? '') === 'BPJS' || ($dataUGD['klaimId'] ?? '') === 'JM';

        // ── Unit / Poli ──
        $poliDesc =
            DB::table('rsmst_polis')
                ->where('poli_id', $hdr->poli_id ?? '')
                ->value('poli_desc') ??
            ($dataUGD['poliName'] ?? 'UGD');

        // ── Dokter ──
        $drName =
            DB::table('rsmst_doctors')
                ->where('dr_id', $hdr->dr_id ?? '')
                ->value('dr_name') ??
            ($hdr->dr_id ?? '-');

        // ── Data SEP BPJS ──
        $sepData = null;
        if ($isBpjs && !empty($hdr->vno_sep)) {
            $sepData = [
                'noSep' => $hdr->vno_sep,
                'noReferensi' => $dataUGD['noReferensi'] ?? null,
                'masaRujukan' => null,
            ];

            $tglRujukan = $dataUGD['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan'] ?? null;
            if ($tglRujukan) {
                $tgl = Carbon::parse($tglRujukan);
                $batas = $tgl->copy()->addMonths(3);
                $sepData['masaRujukan'] = [
                    'tglMulai' => $tgl->format('d/m/Y'),
                    'tglAkhir' => $batas->format('d/m/Y'),
                    'sisaHari' => (int) now()->diffInDays($batas, false),
                ];
            }
        }

        $data = [
            // ── Pasien ──
            'regNo' => $hdr->reg_no,
            'regName' => $hdr->reg_name,
            'address' => $hdr->address,
            'sex' => $hdr->sex,
            'birthDate' => $hdr->birth_date ?? '-',

            // ── Kunjungan ──
            'rjNo' => $rjNo,
            'rjDate' => $hdr->rj_date ?? '-',
            'rjDiskon' => $diskon,
            'vnoSep' => $hdr->vno_sep,
            'drId' => $hdr->dr_id ?? '-',
            'drName' => $drName,
            'poliName' => $poliDesc,
            'klaimId' => $hdr->klaim_id ?? '-',
            'klaimName' => $klaimName,
            'isBpjs' => $isBpjs,

            // ── SEP ──
            'sep' => $sepData,

            // ── Biaya ──
            'rincian' => $rincian,
            'subtotal' => $subtotal,
            'grandTotal' => $grandTotal,
            'sudahBayar' => $sudahBayar,
            'sisa' => $sisa,

            // ── Kasir / cetak ──
            'kasirName' => $kasirName,
            'kasirLog' => $dataUGD['AdministrasiRj'] ?? null,
            'tglCetak' => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak' => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        // ── Generate PDF ──
        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.kwitansi.cetak-kwitansi-ugd-print', [
            'data' => $data,
        ])->setPaper('A4');

        $filename = 'kwitansi-ugd-' . ($hdr->reg_no ?? $rjNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
