<x-pdf.layout-a4-with-out-background title="RESUME RAWAT JALAN">

    {{-- IDENTITAS PASIEN — sejajar dengan logo --}}
    <x-slot name="patientData">
        <x-pdf.identitas-pasien :rm="$dataPasien['pasien']['regNo'] ?? null" :nama="$dataPasien['pasien']['regName'] ?? null" :jenisKelamin="$dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? null" :tempatLahir="$dataPasien['pasien']['tempatLahir'] ?? null"
            :tglLahir="$dataPasien['pasien']['tglLahir'] ?? null" :umur="$dataPasien['pasien']['thn'] ?? null" :alamat="$dataPasien['pasien']['identitas']['alamat'] ?? null">
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
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $dataDaftarTxn['rjDate'] ?? '-' }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        // Status Psikologis: kumpulkan flag aktif
        $sp = $dataDaftarTxn['anamnesa']['statusPsikologis'] ?? [];
        $psikoFlags = collect([
            $sp['tidakAdaKelainan'] ?? false ? 'Tidak Ada Kelainan' : null,
            $sp['marah'] ?? false ? 'Marah' : null,
            $sp['ccemas'] ?? false ? 'Cemas' : null,
            $sp['takut'] ?? false ? 'Takut' : null,
            $sp['sedih'] ?? false ? 'Sedih' : null,
            $sp['cenderungBunuhDiri'] ?? false ? 'Resiko Bunuh Diri' : null,
        ])
            ->filter()
            ->implode(' / ');
        $psikoKet = $sp['sebutstatusPsikologis'] ?? '';

        // Status Mental
        $sm = $dataDaftarTxn['anamnesa']['statusMental'] ?? [];
        $statMental = $sm['statusMental'] ?? '-';
        $mentalKet = $sm['sebutstatusPsikologis'] ?? ($sm['keteranganStatusMental'] ?? '');

        $keluhan = $dataDaftarTxn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-';

        // Prioritas freetext dari dokter; fallback ke keterangan ICD-10 (kode disembunyikan).
        $diagnosis = trim((string) ($dataDaftarTxn['diagnosisFreeText'] ?? ''));
        if ($diagnosis === '') {
            $diagnosisDescriptions = collect($dataDaftarTxn['diagnosis'] ?? [])
                ->pluck('diagDesc')
                ->map(fn($desc) => trim((string) $desc))
                ->filter()
                ->values()
                ->all();
            $diagnosis = $diagnosisDescriptions ? implode("\n", $diagnosisDescriptions) : '-';
        }

        // Prioritas freetext dari dokter; fallback ke keterangan ICD-9-CM (kode disembunyikan).
        $prosedur = trim((string) ($dataDaftarTxn['procedureFreeText'] ?? ''));
        if ($prosedur === '') {
            $procedureDescriptions = collect($dataDaftarTxn['procedure'] ?? [])
                ->pluck('procedureDesc')
                ->map(fn($desc) => trim((string) $desc))
                ->filter()
                ->values()
                ->all();
            $prosedur = $procedureDescriptions ? implode("\n", $procedureDescriptions) : '-';
        }
        $tindakLanjut = $dataDaftarTxn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-';
        $tindakLanjutKet = $dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] ?? '';
        $terapi = $dataDaftarTxn['perencanaan']['terapi']['terapi'] ?? '-';
        $tglRj = trim(explode(' ', (string) ($dataDaftarTxn['rjDate'] ?? ''))[0] ?? '');
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
                    {{ $tindakLanjut ?: '-' }}@if (!empty($tindakLanjutKet))
                        / {{ $tindakLanjutKet }}
                    @endif
                </td>
            </tr>

            {{-- TERAPI --}}
            {{-- <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">Terapi</td>
                <td class="border border-black px-1.5 py-1 align-top" colspan="2">
                    {!! nl2br(e($terapi)) !!}
                </td>
            </tr> --}}

            {{-- TTD --}}
            <tr>
                <td class="border border-black px-1.5 py-2 w-[35%] align-top text-center">
                    <div class="text-center mb-0.5">&nbsp;</div>
                    <div class="text-center">
                        <div class="h-16">&nbsp;</div>
                    </div>
                    <div class="text-center">
                        <span class="inline-block min-w-[150px] border-t border-black pt-0.5">
                            Tanda tangan Pasien
                        </span>
                    </div>
                </td>
                <td class="border border-black px-1.5 py-2 align-top text-center" colspan="2">
                    <div class="text-center mb-0.5">
                        Tulungagung, {{ $tglRj ?: '-' }}
                    </div>
                    <div class="text-center">
                        @if (!empty($ttdDokter))
                            <img class="h-16" src="@ttdSrc($ttdDokter)" alt="">
                        @else
                            <div class="h-16">&nbsp;</div>
                        @endif
                    </div>
                    <div class="text-center">
                        <span class="inline-block min-w-[150px] border-t border-black pt-0.5 font-bold">
                            {{ $drPemeriksa ?: 'Dokter Pemeriksa' }}
                        </span>
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
