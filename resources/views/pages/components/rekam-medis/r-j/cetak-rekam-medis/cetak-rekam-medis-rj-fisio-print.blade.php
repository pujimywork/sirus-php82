<x-pdf.layout-a4-with-out-background title="RESUME REHABILITASI MEDIK">

    {{-- IDENTITAS PASIEN — sejajar dengan logo --}}
    <x-slot name="patientData">
        <x-pdf.identitas-pasien
            :rm="$dataPasien['pasien']['regNo'] ?? null"
            :nama="$dataPasien['pasien']['regName'] ?? null"
            :jenisKelamin="$dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$dataPasien['pasien']['tempatLahir'] ?? null"
            :tglLahir="$dataPasien['pasien']['tglLahir'] ?? null"
            :umur="$dataPasien['pasien']['thn'] ?? null"
            :alamat="$dataPasien['pasien']['identitas']['alamat'] ?? null">
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
        $keluhan = $dataDaftarTxn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-';
        $ujiFungsi = $dataDaftarTxn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '-';
        $diagnosis = $dataDaftarTxn['diagnosisFreeText'] ?? '-';
        $penunjang = $dataDaftarTxn['pemeriksaan']['penunjang'] ?? '-';
        $tataLaksana = $dataDaftarTxn['procedureFreeText'] ?? '-';
        $anjuran = $dataDaftarTxn['perencanaan']['terapi']['terapi'] ?? '-';
        $suspekAK = $dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] ?? '-';
        $ketAK = $dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['keteranganSuspekAkibatKerja'] ?? '';

        $tglRj = trim(explode(' ', (string) ($dataDaftarTxn['rjDate'] ?? ''))[0] ?? '');
        $drPemeriksa = $dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? ($namaDokter ?? '');
        $drId = $dataDaftarTxn['drId'] ?? '';
        $ttdDokter = $drId ? \App\Models\User::where('myuser_code', $drId)->value('myuser_ttd_image') : null;
    @endphp

    {{-- TABEL FISIO --}}
    <table cellpadding="0" cellspacing="0" class="w-full text-[11px]" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase" style="width: 25%;">
                    Anamnesa
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {!! nl2br(e($keluhan)) !!}
                </td>
            </tr>

            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">
                    Pemeriksaan Uji Fungsi
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {!! nl2br(e($ujiFungsi)) !!}
                </td>
            </tr>

            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">
                    Diagnosa Medis
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {!! nl2br(e($diagnosis)) !!}
                </td>
            </tr>

            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">
                    Pemeriksaan Penunjang
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {!! nl2br(e($penunjang)) !!}
                </td>
            </tr>

            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">
                    Tata Laksana KFR
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {!! nl2br(e($tataLaksana)) !!}
                </td>
            </tr>

            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">
                    Anjuran dan Evaluasi
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {!! nl2br(e($anjuran)) !!}
                </td>
            </tr>

            <tr>
                <td class="border border-black px-1.5 py-1 font-bold align-top uppercase">
                    Akibat Kecelakaan Kerja
                </td>
                <td class="border border-black px-1.5 py-1 align-top">
                    {{ $suspekAK }}@if ($suspekAK === 'Ya' && !empty($ketAK)) — {{ $ketAK }}@endif
                </td>
            </tr>

            {{-- TTD dokter (kanan) --}}
            <tr>
                <td class="border border-black"></td>
                <td class="border border-black px-1.5 py-2 align-top text-center">
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

</x-pdf.layout-a4-with-out-background>
