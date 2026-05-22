<x-pdf.layout-a4-with-out-background title="RESUME RAWAT JALAN">

    {{-- IDENTITAS PASIEN — sejajar dengan logo --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">
                    {{ strtoupper($dataPasien['pasien']['regName'] ?? '-') }}
                    / {{ $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}
                    / {{ $dataPasien['pasien']['thn'] ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Lahir</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $dataPasien['pasien']['tglLahir'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">{{ $dataPasien['pasien']['identitas']['alamat'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No RM</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $dataPasien['pasien']['regNo'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">NIK</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $dataPasien['pasien']['identitas']['nik'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Id BPJS</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $dataPasien['pasien']['identitas']['idbpjs'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $dataDaftarTxn['rjDate'] ?? '-' }}</td>
            </tr>
        </table>
    </x-slot>

    @php
        // Status Psikologis: kumpulkan flag aktif
        $sp = $dataDaftarTxn['anamnesa']['statusPsikologis'] ?? [];
        $psikoFlags = collect([
            ($sp['tidakAdaKelainan'] ?? false) ? 'Tidak Ada Kelainan' : null,
            ($sp['marah'] ?? false) ? 'Marah' : null,
            ($sp['ccemas'] ?? false) ? 'Cemas' : null,
            ($sp['takut'] ?? false) ? 'Takut' : null,
            ($sp['sedih'] ?? false) ? 'Sedih' : null,
            ($sp['cenderungBunuhDiri'] ?? false) ? 'Resiko Bunuh Diri' : null,
        ])->filter()->implode(' / ');
        $psikoKet = $sp['sebutstatusPsikologis'] ?? '';

        // Status Mental
        $sm = $dataDaftarTxn['anamnesa']['statusMental'] ?? [];
        $statMental = $sm['statusMental'] ?? '-';
        $mentalKet = $sm['sebutstatusPsikologis'] ?? ($sm['keteranganStatusMental'] ?? '');

        $keluhan = $dataDaftarTxn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-';
        $diagnosis = $dataDaftarTxn['diagnosisFreeText'] ?? '-';
        $prosedur = $dataDaftarTxn['procedureFreeText'] ?? '-';
        $tindakLanjut = $dataDaftarTxn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
        $tindakLanjutKet = $dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] ?? '';
        $terapi = $dataDaftarTxn['perencanaan']['terapi']['terapi'] ?? '-';
        $tglSelesai = $dataDaftarTxn['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? ($dataDaftarTxn['rjDate'] ?? '');
        $drPemeriksa = $dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? ($namaDokter ?? '');
        $drId = $dataDaftarTxn['drId'] ?? '';
        $ttdDokter = $drId ? \App\Models\User::where('myuser_code', $drId)->value('myuser_ttd_image') : null;
    @endphp

    {{-- TABEL RESUME --}}
    <table cellpadding="0" cellspacing="0" class="w-full text-[11px]" style="border-collapse: collapse;">
        <tbody>
            {{-- PENGKAJIAN PERAWAT --}}
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase" style="width: 22%;">
                    Pengkajian Perawat
                </td>
                <td class="border border-black px-1.5 py-1 align-top" colspan="2">
                    <span class="font-bold">Status Psikologis :</span>
                    {{ $psikoFlags ?: '-' }}
                    /
                    <span class="font-bold">Keterangan Status Psikologis :</span>
                    {{ $psikoKet ?: '-' }}
                    <br>
                    <span class="font-bold">Status Mental :</span>
                    {{ $statMental ?: '-' }}
                    /
                    <span class="font-bold">Keterangan Status Mental :</span>
                    {{ $mentalKet ?: '-' }}
                </td>
            </tr>

            {{-- ANAMNESA --}}
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">Anamnesa</td>
                <td class="border border-black px-1.5 py-1 align-top text-center" colspan="2">
                    <span class="font-bold">Keluhan Utama :</span><br>
                    {!! nl2br(e($keluhan)) !!}
                </td>
            </tr>

            {{-- DIAGNOSIS --}}
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">Diagnosis</td>
                <td class="border border-black px-1.5 py-1 align-top" colspan="2">
                    {!! nl2br(e($diagnosis)) !!}
                </td>
            </tr>

            {{-- PROSEDUR --}}
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">Prosedur</td>
                <td class="border border-black px-1.5 py-1 align-top" colspan="2">
                    {!! nl2br(e($prosedur)) !!}
                </td>
            </tr>

            {{-- TINDAK LANJUT --}}
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">Tindak Lanjut</td>
                <td class="border border-black px-1.5 py-1 align-top text-center" colspan="2">
                    <span class="font-bold">Tindak Lanjut :</span>
                    {{ $tindakLanjut ?: '-' }}@if (!empty($tindakLanjutKet)) / {{ $tindakLanjutKet }} @endif
                </td>
            </tr>

            {{-- TERAPI --}}
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">Terapi</td>
                <td class="border border-black px-1.5 py-1 align-top" colspan="2">
                    {!! nl2br(e($terapi)) !!}
                </td>
            </tr>

            {{-- TTD --}}
            <tr>
                <td class="border border-black px-1.5 py-2 align-bottom" style="width: 35%;">
                    <div class="text-center">
                        <div style="height: 50px;"></div>
                        <div>(.....................)</div>
                        <div>Tanda tangan Pasien</div>
                    </div>
                </td>
                <td class="border border-black px-1.5 py-2 align-bottom" colspan="2">
                    <div class="text-center">
                        <div>Tulungagung, {{ $tglSelesai ?: '-' }}</div>
                        @if (!empty($ttdDokter))
                            <img class="h-20 max-w-[200px] mx-auto object-contain" src="@ttdSrc($ttdDokter)" alt="">
                        @else
                            <div style="height:64px;"></div>
                        @endif
                        <div class="inline-block min-w-[130px] border-t border-black pt-0.5 font-bold">
                            {{ $drPemeriksa ?: 'Dokter Pemeriksa' }}
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- PERNYATAAN PERSETUJUAN --}}
    <div style="margin-top: 20px; border: 1px solid #000; padding: 10px;">
        <div class="text-center font-bold" style="margin-bottom: 6px; font-size: 12px;">PERNYATAAN PERSETUJUAN</div>
        <p style="text-align: justify; font-size: 10px; line-height: 1.4; margin-bottom: 6px;">
            Dengan ini, saya selaku pasien/tertanggung, dengan sadar dan sukarela memberikan izin kepada
            <span class="font-bold">RSI MADINAH</span> untuk membagikan informasi lengkap mengenai riwayat penyakit,
            kondisi medis, atau data perawatan saya kepada pihak ketiga yang sah dan telah ditunjuk, sesuai dengan
            ketentuan yang berlaku.
        </p>
        <p style="text-align: justify; font-size: 10px; line-height: 1.4; margin-bottom: 6px;">
            Pemberian informasi ini dimaksudkan untuk keperluan medis, administrasi, atau legal yang relevan, dengan
            tetap menjaga kerahasiaan dan keamanan data pribadi saya.
        </p>
        <p style="font-size: 9px; color: #555; margin-top: 8px;">
            Catatan: Formulir ini berlaku untuk pemberian informasi yang terkait langsung dengan pelayanan kesehatan
            pasien dan tunduk pada regulasi yang berlaku.
        </p>
    </div>

</x-pdf.layout-a4-with-out-background>
