{{-- resources/views/pages/components/modul-dokumen/b-p-j-s/cetak-sep/cetak-sep-print.blade.php --}}

<x-pdf.layout-sep title="SURAT ELEGIBILITAS PESERTA" :data="$data">

    @php
        $sep = $data['sep'] ?? [];
        $reqSep = $data['reqSep'] ?? [];
        $resSep = $data['resSep'] ?? [];
        $pasien = $data['pasien'] ?? [];
        $dataTxn = $data['dataTxn'] ?? [];

        // ── No SEP ──
        $noSep = $sep['noSep'] ?? ($resSep['noSep'] ?? '-');

        // ── Tgl SEP ──
        $tglSep = $resSep['tglSep'] ?? ($reqSep['tglSep'] ?? '-');

        // ── No Kartu BPJS ──
        $noKartu = $resSep['peserta']['noKartu'] ?? ($reqSep['noKartu'] ?? '-');

        // ── No MR ──
        $noMR = $resSep['peserta']['noMr'] ?? ($reqSep['noMR'] ?? ($dataTxn['regNo'] ?? '-'));

        // ── Nama Peserta ──
        $namaPeserta = $resSep['peserta']['nama'] ?? ($dataTxn['regName'] ?? ($pasien['regName'] ?? '-'));

        // ── Tgl Lahir ──
        $tglLahir = $resSep['peserta']['tglLahir'] ?? ($pasien['tglLahir'] ?? '-');

        // ── Kelamin ──
        $kelaminLabel = $resSep['peserta']['kelamin'] ?? '';
        if (empty($kelaminLabel)) {
            $kelaminDesc = $pasien['jenisKelamin']['jenisKelaminDesc'] ?? '';
            $kelaminLabel = !empty($kelaminDesc) ? $kelaminDesc : '-';
        }

        // ── No Telepon ──
        $noTelp = $reqSep['noTelp'] ?? '-';

        // ── Sub/Spesialis (resSep['poli'] = string "PENYAKIT DALAM" / resSep['kdPoli'] = "INT") ──
        $subSpesialis = $resSep['poli'] ?? ($dataTxn['poliDesc'] ?? ($reqSep['poli']['tujuan'] ?? '-'));

        // ── Dokter DPJP ── (di-resolve di component cetak-sep.blade.php)
        $dokterDpjp = $data['dokterDpjp'] ?? ($resSep['dpjp']['nmDPJP'] ?? ($dataTxn['drDesc'] ?? '-'));

        // ── Faskes Perujuk ──
        $faskesPerujuk = $reqSep['rujukan']['ppkRujukanNama']
            ?? ($reqSep['rujukan']['ppkRujukan'] ?? '-');

        // ── Diagnosa ──
        $diagLabel = $resSep['diagnosa'] ?? '';
        if (empty($diagLabel)) {
            $diagLabel = $reqSep['diagAwal'] ?? '-';
        }

        // ── Catatan ──
        $catatan = $resSep['catatan'] ?? ($reqSep['catatan'] ?? '-');

        // ── Jenis Pelayanan ──
        $jnsPelayananLabel = $resSep['jnsPelayanan'] ?? '';
        if (empty($jnsPelayananLabel)) {
            $jnsMap = ['1' => 'Rawat Inap', '2' => 'Rawat Jalan'];
            $jnsPelayananLabel = $jnsMap[$reqSep['jnsPelayanan'] ?? ''] ?? '-';
        }

        // ── Jenis Kunjungan ──
        $tujuanKunjMap = ['0' => 'Normal', '1' => 'Prosedur', '2' => 'Konsul Dokter'];
        $jnsKunjungan = $resSep['tujuanKunj'] ?? ($reqSep['tujuanKunj'] ?? '');
        $jnsKunjunganLabel = $tujuanKunjMap[$jnsKunjungan] ?? $jnsKunjungan;

        // ── Poli Perujuk ──
        $poliPerujuk = $resSep['poliPerujuk'] ?? '';
        if (empty($poliPerujuk)) {
            $poliPerujuk = $reqSep['poli']['tujuan'] ?? '';
        }

        // ── Kelas Hak ──
        $klsHak = $resSep['peserta']['hakKelas'] ?? '';
        if (empty($klsHak)) {
            $klsHakMap = ['1' => '1', '2' => '2', '3' => '3'];
            $klsHak = $klsHakMap[$reqSep['klsRawat']['klsRawatHak'] ?? ''] ?? '-';
        }

        // ── Kelas Rawat ──
        $klsRawat = $resSep['kelasRawat'] ?? '';
        if (empty($klsRawat) || $klsRawat === '-') {
            $klsRawatMap = ['1' => '1', '2' => '2', '3' => '3'];
            $klsRawat = $klsRawatMap[$reqSep['klsRawat']['klsRawatHak'] ?? ''] ?? '-';
        }

        // ── Jenis Peserta ──
        $jnsPesertaLabel = $resSep['peserta']['jnsPeserta'] ?? '-';

        // ── Penjamin ──
        $penjaminLabel = $resSep['penjamin'] ?? '-';

        // ── PRB ──
        $isPRB = !empty($resSep['informasi']['prolanisPRB']);

        // ── Nama RS ──
        $namaRs = strtoupper($data['namaRs'] ?? 'RSI MADINAH');
    @endphp

    {{-- Data SEP: 2 kolom --}}
    <table class="w-full" cellpadding="0" cellspacing="0">
        <tr>
            {{-- ═══ KOLOM KIRI ═══ --}}
            <td class="align-top" style="width: 58%; padding-right: 10px;">
                <table cellpadding="0" cellspacing="0" class="w-full" style="font-size: 10px;">
                    <tr>
                        <td style="width: 95px; padding: 1.5px 0;" class="whitespace-nowrap">No.SEP</td>
                        <td style="width: 8px; padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $noSep }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Tgl.SEP</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $tglSep }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">No.Kartu</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">
                            {{ $noKartu }} <span style="color: #666;">(MR. {{ $noMR }})</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Nama Peserta</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ strtoupper($namaPeserta) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Tgl.Lahir</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">
                            {{ $tglLahir }}&ensp;Kelamin: {{ $kelaminLabel }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">No.Telepon</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $noTelp }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Sub/Spesialis</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $subSpesialis }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Dokter</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $dokterDpjp }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Faskes Perujuk</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $faskesPerujuk }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0; vertical-align: top;">Diagnosa Awal</td>
                        <td style="padding: 1.5px 0; vertical-align: top;">:</td>
                        <td style="padding: 1.5px 0;">{{ $diagLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0; vertical-align: top;">Catatan</td>
                        <td style="padding: 1.5px 0; vertical-align: top;">:</td>
                        <td style="padding: 1.5px 0;">{{ $catatan }}</td>
                    </tr>
                </table>
            </td>

            {{-- ═══ KOLOM KANAN ═══ --}}
            <td class="align-top" style="width: 42%; padding-left: 6px;">

                @if ($isPRB)
                    <div
                        style="font-size: 9px; font-weight: bold; text-align: center; padding: 2px 4px; border: 1px solid #000; margin-bottom: 4px;">
                        * PASIEN POTENSI PRB
                    </div>
                @endif

                <table cellpadding="0" cellspacing="0" class="w-full" style="font-size: 10px;">
                    <tr>
                        <td style="width: 75px; padding: 1.5px 0;" class="whitespace-nowrap">Peserta</td>
                        <td style="width: 8px; padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $jnsPesertaLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Jns.Rawat</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $jnsPelayananLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Jns.Kunjungan</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $jnsKunjunganLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Poli Perujuk</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $poliPerujuk }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Kls.Hak</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $klsHak }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Kls.Rawat</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $klsRawat }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 1.5px 0;">Penjamin</td>
                        <td style="padding: 1.5px 0;">:</td>
                        <td style="padding: 1.5px 0;">{{ $penjaminLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Disclaimer --}}
    <div style="margin-top: 8px; font-size: 8px; color: #444; line-height: 1.4;">
        <p style="margin: 0;">*Saya menyetujui BPJS Kesehatan untuk :</p>
        <p style="margin: 0; padding-left: 8px;">
            a. membuka dan atau menggunakan informasi medis Pasien untuk keperluan administrasi, pembayaran asuransi
            atau jaminan pembiayaan kesehatan
        </p>
        <p style="margin: 0; padding-left: 8px;">
            b. memberikan akses informasi medis atau riwayat pelayanan kepada dokter/tenaga medis pada
            {{ $namaRs }} untuk kepentingan pemeliharaan kesehatan, pengobatan, penyembuhan, dan perawatan Pasien
        </p>
        <p style="margin: 2px 0 0 0;">*Saya mengetahui dan memahami :</p>
        <p style="margin: 0; padding-left: 8px;">
            a. Rumah Sakit dapat melakukan koordinasi dengan PT Jasa Raharja / PT Taspen / PT ASABRI / BPJS
            Ketenagakerjaan atau Penjamin lainnya, jika Peserta merupakan pasien yang mengalami kecelakaan lalulintas
            dan/atau kecelakaan kerja
        </p>
        <p style="margin: 0; padding-left: 8px;">
            b. SEP bukan sebagai bukti penjaminan peserta
        </p>
        <p style="margin: 2px 0 0 0;">
            ** Dengan tampilnya luaran SEP elektronik ini merupakan hasil validasi terhadap eligibilitas Pasien secara
            elektronik (validasi finger print, biometrik, atau sistem lain) dan selanjutnya Pasien dapat mengakses
            pelayanan kesehatan rujukan sesuai ketentuan berlaku. Kebenaran dan keaslian atas data menjadi tanggung
            jawab penuh FKRTL
        </p>
    </div>

    {{-- TTD --}}
    <table class="w-full" cellpadding="0" cellspacing="0" style="margin-top: 4px;">
        <tr>
            <td style="width: 65%;"></td>
            <td style="text-align: right; font-size: 9px; vertical-align: top;">
                <p style="margin: 0;">Persetujuan</p>
                <p style="margin: 0;">Pasien/Keluarga Pasien</p>
                <div style="height: 30px;"></div>
                <p style="margin: 0; font-size: 8px; color: #666;">Catatan</p>
                <p style="margin: 0; font-size: 8px; color: #666;">{{ $data['tglCetak'] ?? '-' }}</p>
            </td>
        </tr>
    </table>

</x-pdf.layout-sep>
