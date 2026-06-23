{{-- Template print: Ringkasan Pemulangan Pasien (diisi Perawat/Bidan).
     Pola sama Resume Medis: identitas pasien (auto) + body HTML dari TinyMCE
     (datadaftarri_json.ringkasanPulang) + footer 3 TTD (Diserahkan/Diterima/Disetujui). --}}
@php
    use Carbon\Carbon;

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

    $bangsalDesc = (string) data_get($ri, 'bangsalDesc', '');
    $roomDesc = (string) data_get($ri, 'roomDesc', '');
    $ruangKelas = trim($bangsalDesc . ($roomDesc ? ' / ' . $roomDesc : ''));
    $tglMasuk = (string) data_get($ri, 'entryDate', '');
    $tglKeluar = (string) data_get($ri, 'exitDate', '');

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

    $savedBy = (string) data_get($ri, 'ringkasanPulangSavedBy', '');
@endphp

<x-pdf.layout-a4-with-out-background title="RINGKASAN PEMULANGAN PASIEN">

    <x-slot name="patientData">
        <x-pdf.identitas-pasien
            :rm="$rm" :nama="$nama" :jenisKelamin="$sexLabel"
            :tempatLahir="$tempatLahir" :tglLahir="$tglLahir" :umur="$umurStr"
            :alamat="$alamat">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Ruang/Kelas</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">{{ $ruangKelas ?: '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $tglMasuk ?: '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Pulang</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $tglKeluar ?: '-' }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    <style>
        .rp-content { font-size: 11px; line-height: 1.4; color: #1f2937; }
        .rp-content p { margin: 0 0 4px 0; }
        .rp-content ol, .rp-content ul { padding-left: 22px; margin: 0 0 4px 0; }
        .rp-content li { margin-bottom: 2px; }
        .rp-content b, .rp-content strong { font-weight: bold; }
        .rp-content i, .rp-content em { font-style: italic; }
        .rp-content u { text-decoration: underline; }
        .rp-content .text-muted { color: #6b7280; }
        .rp-content table { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .rp-content table td, .rp-content table th { border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top; }
        .rp-content table th { background: #f3f4f6; font-weight: bold; text-align: center; }
    </style>
    <div class="rp-content mb-4 px-2">
        {!! !empty($ringkasanPulang) ? $ringkasanPulang : '<p>-</p>' !!}
    </div>

    {{-- Footer 3 kolom TTD: Diserahkan / Diterima / Disetujui --}}
    <table class="w-full text-[10px] mt-2 border-collapse">
        <tr>
            <td class="w-1/3 px-1 align-top text-center">
                <div class="mb-0.5">Diserahkan,</div>
                <div class="h-16">&nbsp;</div>
                <div><span class="inline-block min-w-[120px] border-t border-black pt-0.5">{{ $savedBy ?: ' ' }}</span></div>
            </td>
            <td class="w-1/3 px-1 align-top text-center">
                <div class="mb-0.5">Diterima,</div>
                <div class="text-[8px]">Pasien / Penanggung Jawab</div>
                <div class="h-16">&nbsp;</div>
                <div><span class="inline-block min-w-[120px] border-t border-black pt-0.5">&nbsp;</span></div>
            </td>
            <td class="w-1/3 px-1 align-top text-center">
                <div class="mb-0.5">Disetujui,</div>
                <div class="text-[8px]">Ka.Ru / PJ Shift / Ka.Tim</div>
                <div class="h-16">&nbsp;</div>
                <div><span class="inline-block min-w-[120px] border-t border-black pt-0.5">&nbsp;</span></div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
