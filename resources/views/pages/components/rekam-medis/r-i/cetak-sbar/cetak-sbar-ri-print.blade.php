{{-- Template print: SBAR (Situation, Background, Assessment, Recommendation) — per entri --}}
{{-- Pola: x-pdf.layout-a4-with-out-background — header pasien (auto) + body SBAR.
     Data 1 entri sbar dari datadaftarri_json.sbar[] --}}

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
    $bedNo = (string) data_get($ri, 'bedNo', '');
    $ruangKelas = trim($bangsalDesc . ($roomDesc ? ' / ' . $roomDesc : '') . ($bedNo ? ' / Bed ' . $bedNo : ''));
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

    /* 3) Isi SBAR */
    $isi = data_get($sbar, 'sbar', []);
    $tglSBAR = (string) data_get($sbar, 'tglSBAR', '-');
    $profesi = (string) data_get($sbar, 'profession', '-');
    $petugas = (string) data_get($sbar, 'petugasSBAR', '-');

    $sbarRows = [
        ['S', 'Situation', trim((string) data_get($isi, 'situation', ''))],
        ['B', 'Background', trim((string) data_get($isi, 'background', ''))],
        ['A', 'Assessment', trim((string) data_get($isi, 'assessment', ''))],
        ['R', 'Recommendation', trim((string) data_get($isi, 'recommendation', ''))],
    ];
@endphp

<x-pdf.layout-a4-with-out-background title="SBAR (Situation, Background, Assessment, Recommendation)">

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

    {{-- Meta entri --}}
    <table class="w-full text-[11px] mb-2 border-collapse">
        <tr>
            <td class="py-0.5 text-gray-500 align-top whitespace-nowrap">Tgl. SBAR</td>
            <td class="py-0.5 align-top px-1">:</td>
            <td class="py-0.5 align-top">{{ $tglSBAR ?: '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-gray-500 align-top whitespace-nowrap">Profesi / PPA</td>
            <td class="py-0.5 align-top px-1">:</td>
            <td class="py-0.5 align-top">{{ $profesi ?: '-' }} — {{ $petugas ?: '-' }}</td>
        </tr>
    </table>

    {{-- SBAR --}}
    <table class="w-full text-[11px] mb-2 border-collapse" style="border:1px solid #cbd5e1;">
        @foreach ($sbarRows as [$lbl, $name, $val])
            <tr>
                <td class="align-top"
                    style="border:1px solid #cbd5e1; padding:4px 6px; width:120px; background:#f3f4f6; font-weight:bold;">
                    {{ $lbl }} — {{ $name }}
                </td>
                <td class="align-top" style="border:1px solid #cbd5e1; padding:4px 6px; white-space:pre-wrap;">
                    {{ $val !== '' ? $val : '-' }}</td>
            </tr>
        @endforeach
    </table>

</x-pdf.layout-a4-with-out-background>
