<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    #[On('cetak-kwitansi-ri.open')]
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
                TO_CHAR(b.birth_date, 'DD/MM/YYYY') AS birth_date,
                TO_CHAR(a.entry_date, 'DD/MM/YYYY HH24:MI') AS entry_date,
                TO_CHAR(a.exit_date,  'DD/MM/YYYY HH24:MI') AS exit_date,
                a.emp_id,
                a.ri_diskon,
                a.ri_bayar,
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

        // Rincian per kategori (skip yang nominalnya 0 supaya kwitansi ringkas)
        $rincian = [];
        $push = function (string $desc, int $nominal) use (&$rincian) {
            if ($nominal !== 0) {
                $rincian[] = (object) ['txn_desc' => $desc, 'txn_nominal' => $nominal];
            }
        };

        // Detail RUANG dari rsmst_trfrooms — tiap baris transfer kamar = 1 line item dengan
        // breakdown jumlah hari × harga (mirror Oracle legacy yang tampil per-ruang).
        $trfrooms = DB::table('rsmst_trfrooms as t')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 't.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->where('t.rihdr_no', $riHdrNo)
            ->orderBy('t.trfr_no')
            ->select(
                'b.bangsal_name', 'r.room_name', 't.bed_no',
                't.room_price', 't.common_service', 't.perawatan_price', 't.day',
                DB::raw("ROUND(NVL(t.day, NVL(t.end_date, sysdate+1) - NVL(t.start_date, sysdate))) AS effective_day"),
            )
            ->get();

        // Naming mirror Oracle Reports legacy (sirus 6i)
        $push('ADMINISTRASI',                $costs['adminAge'] + $costs['adminStatus']);

        // RUANG / KAMAR — detail per-ruang
        foreach ($trfrooms as $tr) {
            $days = (int) ($tr->effective_day ?? 0);
            if ($days <= 0) continue;
            $roomTotal = (int) ($tr->room_price ?? 0) * $days;
            $csTotal   = (int) ($tr->common_service ?? 0) * $days;
            $perwTotal = (int) ($tr->perawatan_price ?? 0) * $days;
            $label = trim(($tr->bangsal_name ?? '') . ' / ' . ($tr->room_name ?? '') . ' / Bed ' . ($tr->bed_no ?? '-'));
            if ($roomTotal > 0) {
                $push('RUANG — ' . $label . ' (' . $days . ' hari × ' . number_format((int) $tr->room_price, 0, ',', '.') . ')', $roomTotal);
            }
            if ($csTotal > 0) {
                $push('COMMON SERVICE — ' . $label . ' (' . $days . ' hari × ' . number_format((int) $tr->common_service, 0, ',', '.') . ')', $csTotal);
            }
            if ($perwTotal > 0) {
                $push('JASA PERAWATAN — ' . $label . ' (' . $days . ' hari × ' . number_format((int) $tr->perawatan_price, 0, ',', '.') . ')', $perwTotal);
            }
        }

        // V & K DOKTER — pisah jelas
        $push('VISIT DOKTER',                $costs['visit']);
        $push('KONSUL DOKTER',               $costs['konsul']);

        $push('JASA MEDIS',                  $costs['jasaMedis']);
        $push('JASA DOKTER',                 $costs['jasaDokter']);
        $push('LABORATORIUM',                $costs['lab']);
        $push('RONTGEN / RADIOLOGI',         $costs['rad']);
        $push('KAMAR OPERASI (OK)',          $costs['ok']);
        $push('OBAT',                        $costs['obatPinjam'] + $costs['bonResep']);
        // Retur Obat — nominal negatif (potongan)
        if ($costs['rtnObat'] > 0) {
            $rincian[] = (object) ['txn_desc' => '(-) RETUR OBAT', 'txn_nominal' => -$costs['rtnObat']];
        }
        $push('LAIN-LAIN',                   $costs['lainLain']);
        $push('TRANSFER DARI RJ/UGD',        $costs['trfUgdRj']);

        $subtotal   = (int) collect($rincian)->sum('txn_nominal');
        $diskon     = (int) ($hdr->ri_diskon ?? 0);
        $grandTotal = max(0, $subtotal - $diskon);

        // Pembayaran dari rstxn_ripaymentpdtls
        $sudahBayar = (int) DB::table('rstxn_ripaymentpdtls')->where('rihdr_no', $riHdrNo)->sum('ripay_bayar');
        $sisa       = max(0, $grandTotal - $sudahBayar);

        // Kasir name
        $kasirName = null;
        if (!empty($hdr->emp_id)) {
            $kasirName = DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name');
        }

        // Dokter DPJP
        $drName = DB::table('rsmst_doctors')->where('dr_id', $hdr->dr_id ?? '')->value('dr_name') ?? ($hdr->dr_id ?? '-');

        // Klaim
        $klaimRow = DB::table('rsmst_klaimtypes')->where('klaim_id', $hdr->klaim_id ?? '')->select('klaim_desc')->first();
        $klaimName = $klaimRow->klaim_desc ?? ($hdr->klaim_id ?? '-');

        // Kamar terakhir (bangsal + room + bed)
        $kamar = DB::table('rsmst_trfrooms as t')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 't.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->where('t.rihdr_no', $riHdrNo)
            ->orderByDesc('t.trfr_no')
            ->select('b.bangsal_name', 'r.room_name', 't.bed_no')
            ->first();

        // Status pembayaran label
        $statusLabel = match ($hdr->status_pulang ?? '') {
            'L'     => 'LUNAS',
            'H'     => 'BON / HUTANG',
            default => '-',
        };

        $data = [
            'regNo'         => $hdr->reg_no,
            'regName'       => $hdr->reg_name,
            'address'       => $hdr->address,
            'sex'           => $hdr->sex,
            'birthDate'     => $hdr->birth_date ?? '-',
            'riHdrNo'       => $riHdrNo,
            'entryDate'     => $hdr->entry_date ?? '-',
            'exitDate'      => $hdr->exit_date ?? '-',
            'vnoSep'        => $hdr->vno_sep,
            'drName'        => $drName,
            'klaimName'     => $klaimName,
            'klaimId'       => $hdr->klaim_id ?? '-',
            'bangsalName'   => $kamar->bangsal_name ?? '-',
            'roomName'      => $kamar->room_name ?? '-',
            'bedNo'         => $kamar->bed_no ?? '-',
            'statusPulang'  => $hdr->status_pulang ?? '-',
            'statusLabel'   => $statusLabel,

            // Biaya
            'rincian'       => $rincian,
            'subtotal'      => $subtotal,
            'rjDiskon'      => $diskon,
            'grandTotal'    => $grandTotal,
            'sudahBayar'    => $sudahBayar,
            'sisa'          => $sisa,

            // Kasir
            'kasirName'     => $kasirName,
            'tglCetak'      => Carbon::now(env('APP_TIMEZONE'))->translatedFormat('d/m/Y'),
            'jamCetak'      => Carbon::now(env('APP_TIMEZONE'))->format('H:i'),
            'cetakOleh'     => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-ri-' . ($hdr->reg_no ?? $riHdrNo) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>

<div></div>
