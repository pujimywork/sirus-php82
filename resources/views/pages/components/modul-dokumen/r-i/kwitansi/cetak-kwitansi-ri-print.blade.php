<x-pdf.layout-a4-with-out-background title="KWITANSI PEMBAYARAN — RAWAT INAP">

    {{-- ══════════════════════════════════════
         DATA PASIEN — slot patientData (sejajar dgn logo)
    ══════════════════════════════════════ --}}
    <x-slot name="patientData">
        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:11px;">
            <tr>
                <td width="38%" style="padding:1px 0; color:#555;">No. Rekam Medis</td>
                <td style="padding:1px 0; font-weight:600;">: {{ $data['regNo'] }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; color:#555;">Nama Pasien</td>
                <td style="padding:1px 0; font-weight:600;">: {{ $data['regName'] }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; color:#555;">Tgl Lahir</td>
                <td style="padding:1px 0; font-weight:600;">: {{ $data['birthDate'] }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; color:#555;">Jenis Kelamin</td>
                <td style="padding:1px 0; font-weight:600;">: {{ $data['sex'] === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
            </tr>
            <tr>
                <td style="padding:1px 0; color:#555; vertical-align:top;">Alamat</td>
                <td style="padding:1px 0; font-weight:600; vertical-align:top;">: {{ $data['address'] ?? '-' }}</td>
            </tr>
        </table>
    </x-slot>

    {{-- ══════════════════════════════════════
         INFO KUNJUNGAN
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:11px; margin-top:8px; margin-bottom:8px;">
        <tr class="align-top">
            <td width="50%" style="padding-right:6px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="35%" style="padding:1px 0; color:#555;">No. Rawat Inap</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['riHdrNo'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:1px 0; color:#555;">Tgl Masuk</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['entryDate'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:1px 0; color:#555;">Tgl Pulang</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['exitDate'] }}</td>
                    </tr>
                </table>
            </td>
            <td width="50%" style="padding-left:6px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="35%" style="padding:1px 0; color:#555;">Ruang / Kamar / Bed</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['bangsalName'] }} / {{ $data['roomName'] }} / {{ $data['bedNo'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:1px 0; color:#555;">DPJP</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['drName'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding:1px 0; color:#555;">Klaim</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['klaimName'] }} ({{ $data['klaimId'] }})</td>
                    </tr>
                    @if ($data['vnoSep'] ?? null)
                    <tr>
                        <td style="padding:1px 0; color:#555;">No. SEP</td>
                        <td style="padding:1px 0; font-weight:600;">: {{ $data['vnoSep'] }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         RINCIAN BIAYA
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:11px; border-collapse:collapse;">
        <thead>
            <tr style="background-color:#f9fafb; border-top:1px solid #555; border-bottom:1px solid #555;">
                <th style="padding:4px 6px; text-align:left; font-weight:700; width:28px;">No.</th>
                <th style="padding:4px 6px; text-align:left; font-weight:700;">Keterangan</th>
                <th style="padding:4px 6px; text-align:right; font-weight:700; width:140px;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rincian'] as $i => $item)
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:3px 6px; color:#555;">{{ $i + 1 }}.</td>
                    <td style="padding:3px 6px; text-transform:uppercase;">{{ $item->txn_desc }}</td>
                    <td style="padding:3px 6px; text-align:right; font-variant-numeric:tabular-nums;">
                        {{ number_format((int) $item->txn_nominal, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="padding:12px 6px; text-align:center; font-style:italic; color:#666;">
                        Tidak ada rincian biaya.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="border-top:1px solid #555;">
                <td colspan="2" style="padding:3px 6px; text-align:right; font-weight:600; color:#444;">RI NETT VALUE</td>
                <td style="padding:3px 6px; text-align:right; font-variant-numeric:tabular-nums; font-weight:600;">
                    {{ number_format($data['subtotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding:2px 6px; text-align:right; color:#555;">TOTAL DISKON</td>
                <td style="padding:2px 6px; text-align:right; font-variant-numeric:tabular-nums;">
                    ({{ number_format($data['rjDiskon'] ?? 0, 0, ',', '.') }})
                </td>
            </tr>
            <tr style="border-top:2px solid #333;">
                <td colspan="2" style="padding:5px 6px; text-align:right; font-weight:bold; font-size:12px;">TOTAL</td>
                <td style="padding:5px 6px; text-align:right; font-variant-numeric:tabular-nums; font-weight:bold; font-size:13px;">
                    Rp {{ number_format($data['grandTotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding:3px 6px; text-align:right; color:#555;">BAYAR</td>
                <td style="padding:3px 6px; text-align:right; font-variant-numeric:tabular-nums;">
                    {{ number_format($data['sudahBayar'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr style="border-top:1px solid #555;">
                <td colspan="2" style="padding:3px 6px; text-align:right; font-weight:600;">SISA</td>
                <td style="padding:3px 6px; text-align:right; font-variant-numeric:tabular-nums; font-weight:600; color:{{ ($data['sisa'] ?? 0) > 0 ? '#dc2626' : '#059669' }};">
                    Rp {{ number_format($data['sisa'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding:3px 6px; text-align:right; color:#555;">STATUS PEMBAYARAN</td>
                <td style="padding:3px 6px; text-align:right; font-weight:600; color:{{ $data['statusPulang'] === 'L' ? '#059669' : ($data['statusPulang'] === 'H' ? '#b45309' : '#444') }};">
                    {{ $data['statusLabel'] }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- ══════════════════════════════════════
         TERBILANG
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:11px; margin-top:10px;">
        <tr>
            <td width="80" style="padding:2px 0; color:#555; vertical-align:top;">Terbilang</td>
            <td style="padding:2px 0; font-style:italic; vertical-align:top;">
                :
                @php
                    if (!function_exists('terbilang')) {
                        function terbilang($x)
                        {
                            $angka = [
                                '', 'satu', 'dua', 'tiga', 'empat', 'lima',
                                'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
                            ];
                            if ($x < 12) return ' ' . $angka[$x];
                            if ($x < 20) return terbilang($x - 10) . ' belas';
                            if ($x < 100) return terbilang(intdiv($x, 10)) . ' puluh' . terbilang($x % 10);
                            if ($x < 200) return ' seratus' . terbilang($x - 100);
                            if ($x < 1000) return terbilang(intdiv($x, 100)) . ' ratus' . terbilang($x % 100);
                            if ($x < 2000) return ' seribu' . terbilang($x - 1000);
                            if ($x < 1000000) return terbilang(intdiv($x, 1000)) . ' ribu' . terbilang($x % 1000);
                            if ($x < 1000000000) return terbilang(intdiv($x, 1000000)) . ' juta' . terbilang($x % 1000000);
                            return '';
                        }
                    }
                    echo ucfirst(trim(terbilang((int) ($data['grandTotal'] ?? 0)))) . ' Rupiah';
                @endphp
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:11px; margin-top:30px;">
        <tr style="vertical-align:top;">
            <td width="50%" style="padding-right:16px; text-align:center;">
                <p style="margin:0; color:#444;">Petugas Administrasi</p>
                <p style="margin:0 0 60px 0; color:#444;">{{ $data['tglCetak'] }}</p>
                <p style="margin:0; font-weight:600; border-top:1px solid #555; padding-top:3px;">
                    {{ $data['kasirName'] ?? '-' }}
                </p>
            </td>
            <td width="50%" style="padding-left:16px; text-align:center;">
                <p style="margin:0; color:#444;">Yang Menyetujui,</p>
                <p style="margin:0 0 60px 0; color:#444;">{{ $data['tglCetak'] }}</p>
                <p style="margin:0; font-weight:600; border-top:1px solid #555; padding-top:3px;">
                    ( ......................................... )
                </p>
            </td>
        </tr>
    </table>

    <p style="margin-top:14px; font-size:9px; color:#888; font-style:italic;">
        Dicetak: {{ $data['tglCetak'] }} {{ $data['jamCetak'] }} oleh {{ $data['cetakOleh'] }}
    </p>

</x-pdf.layout-a4-with-out-background>
