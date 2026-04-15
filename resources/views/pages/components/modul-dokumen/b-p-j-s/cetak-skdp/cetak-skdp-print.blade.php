{{-- resources/views/pages/components/modul-dokumen/b-p-j-s/cetak-skdp/cetak-skdp-print.blade.php --}}

<x-pdf.layout-sep title="SURAT RENCANA KONTROL" :data="$data">

    @php
        $kontrol = $data['kontrol'] ?? [];
        $pasien = $data['pasien'] ?? [];
        $dataTxn = $data['dataTxn'] ?? [];

        // No SKDP
        $noSkdp = $kontrol['noSKDPBPJS'] ?? '-';

        // Dokter kontrol
        $drKontrolDesc = $kontrol['drKontrolDesc'] ?? '-';

        // Poli kontrol
        $poliKontrolDesc = $kontrol['poliKontrolDesc'] ?? '-';

        // No Kartu BPJS
        $noKartu = $pasien['noKartuBpjs'] ?? ($pasien['identitas']['idbpjs'] ?? '-');

        // Nama peserta + kelamin
        $namaPeserta = strtoupper($pasien['regName'] ?? ($dataTxn['regName'] ?? '-'));
        $kelaminDesc = $pasien['jenisKelamin']['jenisKelaminDesc'] ?? '';
        $namaKelamin = !empty($kelaminDesc) ? $namaPeserta . ' (' . $kelaminDesc . ')' : $namaPeserta;

        // Tgl lahir (formatted)
        $tglLahir = $kontrol['tglLahirFormatted'] ?? ($pasien['tglLahirFormatted'] ?? ($pasien['tglLahir'] ?? '-'));

        // Diagnosa
        $diagnosa = $data['diagnosa'] ?? '-';

        // Rencana kontrol (formatted)
        $tglKontrol = $kontrol['tglKontrolFormatted'] ?? ($kontrol['tglKontrol'] ?? '-');

        // DPJP (dokter yg mengetahui = dokter penanggung jawab)
        $dpjpNama = $dataTxn['drDesc'] ?? '-';
    @endphp

    {{-- No Surat Kontrol (kanan atas) --}}
    <table class="w-full" cellpadding="0" cellspacing="0" style="margin-top: -2px; margin-bottom: 6px;">
        <tr>
            <td></td>
            <td style="text-align: right; font-size: 10px;">
                No. <span style="font-weight: bold;">{{ $noSkdp }}</span>
            </td>
        </tr>
    </table>

    {{-- Kepada Yth --}}
    <table cellpadding="0" cellspacing="0" style="font-size: 10px; margin-bottom: 4px;">
        <tr>
            <td style="width: 70px; vertical-align: top;">Kepada Yth</td>
            <td style="vertical-align: top;">
                <strong>{{ $drKontrolDesc }}</strong><br>
                Sp./Sub. {{ $poliKontrolDesc }}
            </td>
        </tr>
    </table>

    {{-- Mohon pemeriksaan --}}
    <p style="font-size: 10px; margin: 0 0 4px 0;">Mohon Pemeriksaan dan Penanganan Lebih Lanjut :</p>

    {{-- Data pasien --}}
    <table cellpadding="0" cellspacing="0" class="w-full" style="font-size: 10px;">
        <tr>
            <td style="width: 100px; padding: 1.5px 0;">No.Kartu</td>
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
            <td style="padding: 1.5px 0;">Rencana Kontrol</td>
            <td style="padding: 1.5px 0;">:</td>
            <td style="padding: 1.5px 0;">{{ $tglKontrol }}</td>
        </tr>
    </table>

    {{-- Penutup --}}
    <p style="font-size: 10px; margin: 10px 0 0 0;">
        Demikian atas bantuannya, diucapkan banyak terima kasih.
    </p>

    {{-- TTD DPJP --}}
    <table class="w-full" cellpadding="0" cellspacing="0" style="margin-top: 8px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="text-align: center; font-size: 10px;">
                <p style="margin: 0;">Mengetahui DPJP,</p>
                <div style="height: 40px;"></div>
                <p style="margin: 0; font-weight: bold;">{{ strtoupper($dpjpNama) }}</p>
            </td>
        </tr>
    </table>

</x-pdf.layout-sep>
