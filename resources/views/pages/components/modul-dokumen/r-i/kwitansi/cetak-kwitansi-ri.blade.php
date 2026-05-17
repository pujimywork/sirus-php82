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

        $push('Administrasi (Age + Status)', $costs['adminAge'] + $costs['adminStatus']);
        $push('Kamar (Room)',                $costs['room']);
        $push('Common Service',              $costs['commonService']);
        $push('Perawatan',                   $costs['perawatan']);
        $push('Visit Dokter',                $costs['visit']);
        $push('Konsul Dokter',               $costs['konsul']);
        $push('Jasa Medis',                  $costs['jasaMedis']);
        $push('Jasa Dokter',                 $costs['jasaDokter']);
        $push('Laboratorium',                $costs['lab']);
        $push('Radiologi',                   $costs['rad']);
        $push('Kamar Operasi (OK)',          $costs['ok']);
        $push('Obat (Pinjam + Bon)',         $costs['obatPinjam'] + $costs['bonResep']);
        // Retur Obat — nominal negatif (potongan)
        if ($costs['rtnObat'] > 0) {
            $rincian[] = (object) ['txn_desc' => '(-) Retur Obat', 'txn_nominal' => -$costs['rtnObat']];
        }
        $push('Lain-lain',                   $costs['lainLain']);
        $push('Transfer dari RJ/UGD',        $costs['trfUgdRj']);

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
