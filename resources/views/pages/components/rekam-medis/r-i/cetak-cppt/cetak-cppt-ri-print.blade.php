{{-- Template print: CPPT (Catatan Perkembangan Pasien Terintegrasi) — per entri --}}
{{-- Pola: x-pdf.layout-a4-with-out-background — header pasien (auto) + body SOAP
     + footer TTD pembuat & review DPJP. Data 1 entri cppt dari datadaftarri_json.cppt[] --}}

@php
    use Carbon\Carbon;

    /* 1) Identitas pasien (auto dari findDataMasterPasien) */
    $pasien = data_get($dataPasien, 'pasien', []);
    $ri = $dataDaftarRi ?? [];

    $rm = (string) data_get($pasien, 'regNo', '');
    $nama = (string) data_get($pasien, 'regName', '');
    $jkDesc = trim((string) data_get($pasien, 'jenisKelamin.jenisKelaminDesc', ''));
    $jkId = (string) data_get($pasien, 'jenisKelamin.jenisKelaminId', '');
    $sexLabel = $jkDesc !== '' ? $jkDesc : ($jkId === '1' ? 'Laki-laki' : ($jkId === '2' ? 'Perempuan' : '-'));
    $tglLahir = (string) data_get($pasien, 'tglLahir', '');
    $tempatLahir = (string) data_get($pasien, 'tempatLahir', '');
    $idn = data_get($pasien, 'identitas', []);
    $alamat = trim(
        (string) data_get($idn, 'alamat', '') .
            (filled(data_get($idn, 'rt')) ? ' RT ' . data_get($idn, 'rt') : '') .
            (filled(data_get($idn, 'rw')) ? '/RW ' . data_get($idn, 'rw') : '') .
            (filled(data_get($idn, 'desaName')) ? ', ' . data_get($idn, 'desaName') : '') .
            (filled(data_get($idn, 'kecamatanName')) ? ', ' . data_get($idn, 'kecamatanName') : ''),
    );

    /* 2) Data Rawat */
    $bangsalDesc = (string) data_get($ri, 'bangsalDesc', '');
    $roomDesc = (string) data_get($ri, 'roomDesc', '');
    $ruangKelas = trim($bangsalDesc . ($roomDesc ? ' / ' . $roomDesc : ''));
    $tglMasuk = (string) data_get($ri, 'entryDate', '');

    // Umur dihitung dari tglLahir terhadap tgl masuk (stabil saat cetak ulang).
    $umurStr = '-';
    try {
        $birth = Carbon::createFromFormat('d/m/Y', trim($tglLahir))->startOfDay();
        $ref = Carbon::now();
        $tglMasukRaw = trim($tglMasuk);
        if ($tglMasukRaw !== '') {
            try {
                $ref = Carbon::createFromFormat('d/m/Y H:i:s', $tglMasukRaw);
            } catch (\Throwable) {
                try {
                    $ref = Carbon::createFromFormat('d/m/Y', substr($tglMasukRaw, 0, 10));
                } catch (\Throwable) {
                }
            }
        }
        $diff = $birth->diff($ref);
        $umurStr = sprintf('%d Thn / %d Bln / %d Hr', $diff->y, $diff->m, $diff->d);
    } catch (\Throwable) {
    }

    /* 3) Isi CPPT */
    $soap = data_get($cppt, 'soap', []);
    $tglCPPT = (string) data_get($cppt, 'tglCPPT', '-');
    $profesi = (string) data_get($cppt, 'profession', '-');
    $petugas = (string) data_get($cppt, 'petugasCPPT', '-');
    $instruksi = trim((string) data_get($cppt, 'instruction', ''));
    $review = trim((string) data_get($cppt, 'review', ''));
    $tindakan = (array) data_get($cppt, 'tindakanDilakukan', []);

    $soapRows = [
        ['S', 'Subjective', trim((string) data_get($soap, 'subjective', ''))],
        ['O', 'Objective', trim((string) data_get($soap, 'objective', ''))],
        ['A', 'Assessment', trim((string) data_get($soap, 'assessment', ''))],
        ['P', 'Plan', trim((string) data_get($soap, 'plan', ''))],
    ];
@endphp

<x-pdf.layout-a4-with-out-background title="Catatan Perkembangan Pasien Terintegrasi (CPPT)">

    <x-slot name="patientData">
        <x-pdf.identitas-pasien
            :rm="$rm" :nama="$nama" :jenisKelamin="$sexLabel"
            :tempatLahir="$tempatLahir" :tglLahir="$tglLahir" :umur="$umurStr ?? null"
            :alamat="$alamat">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Ruang/Kelas</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">{{ $ruangKelas ?: '-' }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    {{-- Judul --}}
    {{-- <div class="mb-2 text-center">
        <p class="text-[13px] font-bold uppercase">Catatan Perkembangan Pasien Terintegrasi (CPPT)</p>
    </div> --}}

    {{-- Meta entri --}}
    <table class="w-full text-[11px] mb-2 border-collapse">
        <tr>
            <td class="py-0.5 text-gray-500 align-top whitespace-nowrap">Tgl. CPPT</td>
            <td class="py-0.5 align-top px-1">:</td>
            <td class="py-0.5 align-top">{{ $tglCPPT ?: '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-gray-500 align-top whitespace-nowrap">Profesi / PPA</td>
            <td class="py-0.5 align-top px-1">:</td>
            <td class="py-0.5 align-top">{{ $profesi ?: '-' }} — {{ $petugas ?: '-' }}</td>
        </tr>
    </table>

    {{-- SOAP --}}
    <table class="w-full text-[11px] mb-2 border-collapse" style="border:1px solid #cbd5e1;">
        @foreach ($soapRows as [$lbl, $name, $val])
            <tr>
                <td class="align-top"
                    style="border:1px solid #cbd5e1; padding:4px 6px; width:90px; background:#f3f4f6; font-weight:bold;">
                    {{ $lbl }} — {{ $name }}
                </td>
                <td class="align-top" style="border:1px solid #cbd5e1; padding:4px 6px; white-space:pre-wrap;">{{ $val !== '' ? $val : '-' }}</td>
            </tr>
        @endforeach
    </table>

    @if (!empty($tindakan))
        <table class="w-full text-[11px] mb-2 border-collapse" style="border:1px solid #cbd5e1;">
            <tr>
                <td class="align-top"
                    style="border:1px solid #cbd5e1; padding:4px 6px; width:90px; background:#f3f4f6; font-weight:bold;">
                    Tindakan</td>
                <td class="align-top" style="border:1px solid #cbd5e1; padding:4px 6px;">
                    <ul style="margin:0; padding-left:16px;">
                        @foreach ($tindakan as $t)
                            <li>{{ is_array($t) ? $t['tindakan'] ?? ($t['desc'] ?? '-') : $t }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        </table>
    @endif

    @if ($instruksi !== '' || $review !== '')
        <table class="w-full text-[11px] mb-2 border-collapse">
            @if ($instruksi !== '')
                <tr>
                    <td class="w-[18%] py-0.5 text-gray-500 align-top">Instruksi</td>
                    <td class="w-[1%] py-0.5 align-top">:</td>
                    <td class="py-0.5 align-top" style="white-space:pre-wrap;">{{ $instruksi }}</td>
                </tr>
            @endif
            @if ($review !== '')
                <tr>
                    <td class="py-0.5 text-gray-500 align-top">Review</td>
                    <td class="py-0.5 align-top">:</td>
                    <td class="py-0.5 align-top" style="white-space:pre-wrap;">{{ $review }}</td>
                </tr>
            @endif
        </table>
    @endif

</x-pdf.layout-a4-with-out-background>
