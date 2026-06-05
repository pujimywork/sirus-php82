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
    ═══════════════════════════════════════ */
    #[On('cetak-kwitansi.open')]
    public function open(int $rjNo, string $mode = 'full'): mixed
    {
        $mode = in_array($mode, ['full', 'bpjs'], true) ? $mode : 'full';

        // ── Query 1: Header RJ + Data Pasien ──
        $hdr = DB::selectOne(
            "
            SELECT
                a.rj_no,
                TO_CHAR(a.rj_date, 'DD/MM/YYYY HH24:MI') AS rj_date,
                a.reg_no,
                b.reg_name,
                b.address,
                b.sex,
                b.birth_place,
                TO_CHAR(b.birth_date, 'DD/MM/YYYY')       AS birth_date,
                a.emp_id,
                a.rj_diskon,
                a.klaim_id,
                a.dr_id,
                a.vno_sep,
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

        // ── Query 2: Rincian Biaya ──
        $rincian = DB::select(
            "
            SELECT txn_id, txn_desc, txn_nominal, txn_no
            FROM   rsview_rjstrs
            WHERE  rj_no       = :rjno
              AND  txn_nominal > 0
            ORDER  BY txn_no
            ",
            ['rjno' => $rjNo],
        );

        // ── Mode BPJS: replace baris OBAT pakai qty BPJS-effective ──
        // RSVIEW_RJSTRS baris OBAT (txn_id='OBAT') = sum(qty*price) full.
        // Untuk kwitansi BPJS, obat kronis dibayar terpisah jadi qty efektif:
        //   - status_kronis='Y' → qty_bpjs (porsi yang dicover InaCBG)
        //   - status_kronis='N' → qty (full, semua dicover InaCBG)
        // Hardcode override baris OBAT di rincian, sumber tetap rsview_rjstrs.
        if ($mode === 'bpjs') {
            $obatBpjsTotal = (int) DB::table('rstxn_rjobats')
                ->where('rj_no', $rjNo)
                ->selectRaw(
                    "NVL(SUM(
                    CASE WHEN NVL(status_kronis,'N')='Y' THEN NVL(qty_bpjs,0) ELSE NVL(qty,0) END
                    * NVL(price,0)
                ),0) AS total",
                )
                ->value('total');

            $rincian = collect($rincian)
                ->map(function ($row) use ($obatBpjsTotal) {
                    if (($row->txn_id ?? '') === 'OBAT') {
                        $row->txn_nominal = $obatBpjsTotal;
                        $row->txn_desc = 'BIAYA OBAT RAWAT JALAN (BPJS)';
                    }
                    return $row;
                })
                ->reject(fn($r) => ($r->txn_id ?? '') === 'OBAT' && (int) $r->txn_nominal === 0)
                ->values()
                ->all();
        }

        // ── Kalkulasi Biaya ──
        $subtotal = (int) collect($rincian)->sum('txn_nominal');
        $diskon = (int) ($hdr->rj_diskon ?? 0);
        $grandTotal = max(0, $subtotal - $diskon);

        // ── Bayar dari riwayat kasir ──
        $sudahBayar = (int) DB::table('rstxn_rjcashins')->where('rj_no', $rjNo)->sum('rjc_nominal');

        // ── Sisa tagihan ──
        $sisa = max(0, $grandTotal - $sudahBayar);

        // ── Nama Kasir dari immst_employers via emp_id di header ──
        $kasirName = null;
        if (!empty($hdr->emp_id)) {
            $kasirName = DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name');
        }

        // ── Data JSON RJ ──
        $dataRJ = $this->findDataRJ($rjNo) ?? [];

        // ── Klaim ──
        $klaimRow = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $hdr->klaim_id ?? '')
            ->select('klaim_desc')
            ->first();

        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        // ── Deteksi BPJS ──
        $isBpjs = ($dataRJ['klaimStatus'] ?? '') === 'BPJS' || ($dataRJ['klaimId'] ?? '') === 'JM';

        // ── Poli ──
        $poliDesc =
            DB::table('rsmst_polis')
                ->where('poli_id', $hdr->poli_id ?? '')
                ->value('poli_desc') ??
            ($dataRJ['poliName'] ?? '-');

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
                'noReferensi' => $dataRJ['noReferensi'] ?? null,
                'masaRujukan' => null,
            ];

            $tglRujukan = $dataRJ['sep']['reqSep']['request']['t_sep']['rujukan']['tglRujukan'] ?? null;
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

        // Umur dihitung ulang dari birth_date (kolom thn/bln/hari snapshot, jangan dipakai)
        $umurLabel = '-';
        if (!empty($hdr->birth_date)) {
            try {
                $diff = Carbon::createFromFormat('d/m/Y', $hdr->birth_date)->diff(now());
                $umurLabel = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
            } catch (\Throwable $e) {
                $umurLabel = '-';
            }
        }

        $data = [
            // ── Pasien ──
            'regNo' => $hdr->reg_no,
            'regName' => $hdr->reg_name,
            'address' => $hdr->address,
            'sex' => $hdr->sex,
            'birthPlace' => $hdr->birth_place,
            'birthDate' => $hdr->birth_date ?? '-',
            'umur' => $umurLabel,

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
            'kasirName' => $kasirName, // ✅ dari immst_employers
            'kasirLog' => $dataRJ['AdministrasiRj'] ?? null,
            'tglCetak' => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak' => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        // ── Judul kwitansi per mode ──
        $data['judul'] = $mode === 'bpjs' ? 'KWITANSI BPJS (InaCBG) — Rawat Jalan' : 'KWITANSI — Rawat Jalan';
        $data['mode'] = $mode;

        // ── Generate PDF ──
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.kwitansi.cetak-kwitansi-rj-print', ['data' => $data])->setPaper('A4');

        $fileSuffix = $mode === 'bpjs' ? '-bpjs' : '';
        $filename = 'kwitansi-' . ($hdr->reg_no ?? $rjNo) . $fileSuffix . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
