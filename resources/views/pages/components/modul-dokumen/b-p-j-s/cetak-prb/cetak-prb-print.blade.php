{{-- resources/views/pages/components/modul-dokumen/b-p-j-s/cetak-prb/cetak-prb-print.blade.php --}}

<x-pdf.layout-sep title="SURAT RUJUK BALIK (PRB)" :data="$data">

    @php
        $prb = $data['prb'] ?? [];
        $pasien = $data['pasien'] ?? [];
        $dataTxn = $data['dataTxn'] ?? [];

        // No SRB
        $noSrb = $prb['noSrb'] ?? '-';

        // Tanggal SRB
        $tglSRB = $data['tglSRB'] ?? '-';

        // No Kartu BPJS
        $noKartu = $prb['noKartu'] ?? ($pasien['noKartuBpjs'] ?? ($pasien['identitas']['idbpjs'] ?? '-'));

        // Nama peserta + kelamin
        $namaPeserta = strtoupper($pasien['regName'] ?? ($dataTxn['regName'] ?? '-'));
        $kelaminDesc = $pasien['jenisKelamin']['jenisKelaminDesc'] ?? '';
        $namaKelamin = !empty($kelaminDesc) ? $namaPeserta . ' (' . substr($kelaminDesc, 0, 1) . ')' : $namaPeserta;

        // Tgl lahir
        $tglLahir = $pasien['tglLahirFormatted'] ?? ($pasien['tglLahir'] ?? '-');

        // Diagnosa
        $diagnosa = $data['diagnosa'] ?? '-';

        // Program PRB
        $programNama = $data['programNama'] ?? '-';

        // Keterangan & Saran
        $keterangan = $prb['keterangan'] ?? '-';
        $saran = $prb['saran'] ?? '-';

        // Obat
        $obatList = $prb['obat'] ?? [];

        // DPJP
        $dpjpNama = $prb['kodeDPJPNama'] ?? ($dataTxn['drDesc'] ?? '-');
    @endphp

    {{-- No.SRB + Tanggal (kanan atas) --}}
    <table class="w-full" cellpadding="0" cellspacing="0" style="margin-top: -2px; margin-bottom: 6px;">
        <tr>
            <td></td>
            <td style="text-align: right; font-size: 10px;">
                No.SRB. <span style="font-weight: bold;">{{ $noSrb }}</span><br>
                Tanggal. {{ $tglSRB }}
            </td>
        </tr>
    </table>

    {{-- Kepada Yth --}}
    <table cellpadding="0" cellspacing="0" style="font-size: 10px; margin-bottom: 6px;">
        <tr>
            <td style="width: 70px; vertical-align: top;">Kepada Yth</td>
            <td style="vertical-align: top;">:</td>
        </tr>
    </table>

    {{-- Mohon pemeriksaan --}}
    <p style="font-size: 10px; margin: 0 0 4px 0;">Mohon Pemeriksaan dan Penanganan Lebih Lanjut :</p>

    {{-- Data pasien (kiri) + Resep obat (kanan) --}}
    <table class="w-full" cellpadding="0" cellspacing="0" style="font-size: 10px;">
        <tr>
            {{-- KOLOM KIRI: Data pasien --}}
            <td style="width: 50%; vertical-align: top; padding-right: 8px;">
                <table cellpadding="0" cellspacing="0" class="w-full" style="font-size: 10px;">
                    <tr>
                        <td style="width: 90px; padding: 1.5px 0;">No.Kartu</td>
                        <td style="width: 8px; padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $noKartu }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Nama Peserta</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $namaKelamin }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Tgl.Lahir</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $tglLahir }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0; vertical-align: top;">Diagnosa</td>
                        <td style="padding: 1.5px 0; vertical-align: top;">:</td>
                        <td style="padding: 1.5px 0;">{{ $diagnosa }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Program PRB</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $programNama }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0; vertical-align: top;">Keterangan</td>
                        <td style="padding: 1.5px 0; vertical-align: top;">:</td>
                        <td style="padding: 1.5px 0;">{{ $keterangan }}</td>
                    </tr>
                </table>

                {{-- Saran --}}
                <div style="margin-top: 4px; font-size: 10px;">
                    <span>Saran Pengelolaan lanjutan di FKTP :</span><br>
                    <span style="padding-left: 8px;">{{ $saran }}</span>
                </div>
            </td>

            {{-- KOLOM KANAN: Resep obat --}}
            <td style="width: 50%; vertical-align: top; padding-left: 8px;">
                <div style="font-size: 10px;">
                    <p style="margin: 0 0 2px 0; font-weight: bold;">R/.</p>
                    @if (!empty($obatList))
                        <table cellpadding="0" cellspacing="0" class="w-full" style="font-size: 10px;">
                            @foreach ($obatList as $idx => $obat)
                                <tr>
                                    <td style="width: 14px; padding: 1px 0; vertical-align: top;">{{ $idx + 1 }}.</td>
                                    <td style="padding: 1px 0; vertical-align: top;">
                                        {{ ($obat['signa1'] ?? '') . 'x' . ($obat['signa2'] ?? '') }}
                                    </td>
                                    <td style="padding: 1px 4px; vertical-align: top;">
                                        {{ $obat['namaObat'] ?? ($obat['kdObat'] ?? '') }}
                                    </td>
                                    <td style="width: 30px; padding: 1px 0; text-align: right; vertical-align: top;">
                                        {{ $obat['jmlObat'] ?? '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <p style="margin: 0; color: #999;">Tidak ada obat</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Penutup --}}
    <p style="font-size: 10px; margin: 10px 0 0 0;">
        Demikian atas bantuannya, diucapkan banyak terima kasih.
    </p>

    {{-- TTD --}}
    <table class="w-full" cellpadding="0" cellspacing="0" style="margin-top: 8px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="text-align: center; font-size: 10px;">
                <p style="margin: 0;">Mengetahui,</p>
                <div style="height: 40px;"></div>
                <p style="margin: 0; font-weight: bold;">{{ strtoupper($dpjpNama) }}</p>
            </td>
        </tr>
    </table>

    {{-- Footer tgl cetak --}}
    <div style="position: fixed; bottom: 2mm; left: 6mm; font-size: 7px; color: #999;">
        Tgl Cetak: {{ $data['tglCetak'] ?? '' }}
    </div>

</x-pdf.layout-sep>
