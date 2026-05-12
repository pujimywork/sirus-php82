<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    /* ═══════════════════════════════════════
     | OPEN & LANGSUNG CETAK
     | $mode:
     |   'full'   → semua obat × full qty (default; backward-compat)
     |   'bpjs'   → obat dengan qty efektif BPJS (status_kronis=Y → qty_bpjs; lainnya → qty)
     |   'kronis' → hanya obat status_kronis=Y, qty = qty_kronis
    ═══════════════════════════════════════ */
    #[On('cetak-kwitansi-obat.open')]
    public function open(int $rjNo, string $mode = 'full'): mixed
    {
        $mode = in_array($mode, ['full', 'bpjs', 'kronis'], true) ? $mode : 'full';

        // ── Query 1: Header RJ + Data Pasien ──
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

        // ── Query 2: Rincian Obat (branch berdasarkan mode) ──
        if ($mode === 'bpjs') {
            // BPJS: obat non-kronis full qty + obat kronis qty_bpjs only
            $rincianObat = DB::select(
                "
                SELECT
                    b.product_name AS keterangan,
                    SUM(
                        CASE WHEN NVL(a.status_kronis,'N')='Y' THEN NVL(a.qty_bpjs,0) ELSE NVL(a.qty,0) END
                    ) AS qty,
                    SUM(
                        CASE WHEN NVL(a.status_kronis,'N')='Y' THEN NVL(a.qty_bpjs,0) ELSE NVL(a.qty,0) END
                        * NVL(a.price, 0)
                    ) AS obat
                FROM  rstxn_rjobats   a
                JOIN  immst_products  b ON b.product_id = a.product_id
                WHERE a.rj_no = :rjno
                GROUP BY b.product_name
                HAVING SUM(
                    CASE WHEN NVL(a.status_kronis,'N')='Y' THEN NVL(a.qty_bpjs,0) ELSE NVL(a.qty,0) END
                ) > 0
                ORDER BY b.product_name
                ",
                ['rjno' => $rjNo],
            );
        } elseif ($mode === 'kronis') {
            // Kronis: hanya obat status_kronis='Y', qty = qty_kronis
            $rincianObat = DB::select(
                "
                SELECT
                    b.product_name AS keterangan,
                    SUM(NVL(a.qty_kronis, 0)) AS qty,
                    SUM(NVL(a.qty_kronis, 0) * NVL(a.price, 0)) AS obat
                FROM  rstxn_rjobats   a
                JOIN  immst_products  b ON b.product_id = a.product_id
                WHERE a.rj_no = :rjno
                  AND NVL(a.status_kronis,'N') = 'Y'
                  AND NVL(a.qty_kronis,0) > 0
                GROUP BY b.product_name
                ORDER BY b.product_name
                ",
                ['rjno' => $rjNo],
            );
        } else {
            // full (default — perilaku existing, qty = total qty obat)
            $rincianObat = DB::select(
                "
                SELECT
                    b.product_name AS keterangan,
                    SUM(NVL(a.qty, 0)) AS qty,
                    SUM(NVL(a.qty, 0) * NVL(a.price, 0)) AS obat
                FROM  rstxn_rjobats   a
                JOIN  immst_products  b ON b.product_id = a.product_id
                WHERE a.rj_no = :rjno
                GROUP BY b.product_name
                ORDER BY b.product_name
                ",
                ['rjno' => $rjNo],
            );
        }

        if (empty($rincianObat)) {
            $msg = match ($mode) {
                'bpjs' => 'Tidak ada obat berqty BPJS untuk kunjungan ini.',
                'kronis' => 'Tidak ada obat kronis (split) untuk kunjungan ini.',
                default => 'Tidak ada data obat untuk kunjungan ini.',
            };
            $this->dispatch('toast', type: 'warning', message: $msg);
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

        // ── Poli ──
        $poliDesc =
            DB::table('rsmst_polis')
                ->where('poli_id', $hdr->poli_id ?? '')
                ->value('poli_desc') ?? '-';

        // ── Dokter ──
        $drName =
            DB::table('rsmst_doctors')
                ->where('dr_id', $hdr->dr_id ?? '')
                ->value('dr_name') ??
            ($hdr->dr_id ?? '-');

        // ── Judul & filename suffix per mode ──
        $judul = match ($mode) {
            'bpjs' => 'KWITANSI OBAT BPJS (InaCBG) - Rawat Jalan',
            'kronis' => 'KWITANSI OBAT KRONIS - Rawat Jalan',
            default => 'KWITANSI OBAT - Rawat Jalan',
        };
        $fileSuffix = match ($mode) {
            'bpjs' => '-bpjs',
            'kronis' => '-kronis',
            default => '',
        };

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

            // ── Mode & judul (untuk header PDF) ──
            'mode' => $mode,
            'judul' => $judul,

            // ── Kasir / Cetak ──
            'kasirName' => $kasirName,
            'tglCetak' => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak' => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        // ── Generate PDF ──
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.kwitansi.cetak-kwitansi-rj-obat-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-obat' . $fileSuffix . '-' . ($hdr->reg_no ?? $rjNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
