{{-- Template print: Resume Medis Pasien Pulang RM 41 --}}
{{-- Pola: x-pdf.layout-a4-with-out-background — header pasien (auto) +
     body content (typed via TinyMCE, HTML w/ table support) + footer TTD DPJP.
     Body disimpan di `datadaftarri_json.resumeMedis` (HTML string flat). --}}

@php
    use Carbon\Carbon;

    /* 1) Identitas pasien (auto dari findDataMasterPasien) */
    $pasien = data_get($dataPasien, 'pasien', []);
    $ri = $dataDaftarRi ?? [];

    $rm = (string) data_get($pasien, 'regNo', '');
    $nama = (string) data_get($pasien, 'regName', '');
    // Jenis kelamin: MasterPasienTrait simpan di nested jenisKelamin (bukan flat `sex`).
    // jenisKelaminDesc dihitung fresh dari kolom DB `sex` → reliable. Fallback ke Id (1=L,2=P).
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
    $tglKeluar = (string) data_get($ri, 'exitDate', '');

    // Umur: dihitung dari tglLahir terhadap TANGGAL MASUK (episode rawat), bukan now() —
    // supaya stabil saat dicetak ulang & konsisten dgn pola iDRG (computeUmur vs tgl_masuk).
    // Fallback ke now() kalau entryDate kosong / format tak terbaca.
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

    /* 3) DPJP Utama (untuk TTD) */
    $dokterUtamaRow = collect(data_get($ri, 'pengkajianAwalPasienRawatInap.levelingDokter', []))->first(
        fn($r) => strcasecmp((string) data_get($r, 'levelDokter', ''), 'Utama') === 0,
    );
    $dpjpName = (string) data_get($dokterUtamaRow, 'drName', '');
    $dpjpDrId = (string) data_get($dokterUtamaRow, 'drId', '');
    $ttdDpjp = $dpjpDrId ? \App\Models\User::where('myuser_code', $dpjpDrId)->value('myuser_ttd_image') : null;
@endphp

<x-pdf.layout-a4-with-out-background title="RESUME MEDIS">

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
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $tglMasuk ?: '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl Pulang</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $tglKeluar ?: '-' }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    {{-- Judul --}}
    {{-- <div class="mb-3 text-center">
        <p class="text-[14px] font-bold uppercase">RESUME MEDIS</p>
    </div> --}}

    {{-- Body: HTML hasil ketikan via TinyMCE (datadaftarri_json.resumeMedis).
         TinyMCE output <table> HTML proper → DomPDF render native. --}}
    <style>
        .resume-medis-content {
            font-size: 11px;
            line-height: 1.4;
            color: #1f2937;
        }

        .resume-medis-content p {
            margin: 0 0 4px 0;
        }

        .resume-medis-content ol {
            padding-left: 22px;
            margin: 0 0 4px 0;
        }

        .resume-medis-content ul {
            padding-left: 22px;
            margin: 0 0 4px 0;
        }

        .resume-medis-content li {
            margin-bottom: 2px;
        }

        .resume-medis-content h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
        }

        .resume-medis-content h2 {
            font-size: 13px;
            font-weight: bold;
            margin: 5px 0;
        }

        .resume-medis-content h3 {
            font-size: 12px;
            font-weight: bold;
            margin: 5px 0;
        }

        .resume-medis-content blockquote {
            border-left: 2px solid #9ca3af;
            padding-left: 6px;
            margin: 5px 0;
            color: #4b5563;
        }

        .resume-medis-content b,
        .resume-medis-content strong {
            font-weight: bold;
        }

        .resume-medis-content i,
        .resume-medis-content em {
            font-style: italic;
        }

        .resume-medis-content u {
            text-decoration: underline;
        }

        .resume-medis-content s {
            text-decoration: line-through;
        }

        /* Table dari TinyMCE — DomPDF native */
        .resume-medis-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        .resume-medis-content table td,
        .resume-medis-content table th {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            vertical-align: top;
        }

        .resume-medis-content table th {
            background: #f3f4f6;
            font-weight: bold;
            text-align: left;
        }
    </style>
    <div class="resume-medis-content mb-4 px-2">
        {!! !empty($resumeMedis) ? $resumeMedis : '<p>-</p>' !!}
    </div>

    {{-- Footer: TTD DPJP — pola 3-stack standar (docs/ttd-pattern-pdf-print.md §3) --}}
    <table class="w-full text-[10px] mt-3 border-collapse">
        <tr>
            <td class="w-2/3 px-1 align-top"></td>
            <td class="w-1/3 px-1 align-top text-center">
                {{-- Line 1: lokasi & tanggal --}}
                <div class="text-center mb-0.5">Tulungagung, {{ $tglKeluar ?: '-' }}</div>

                {{-- Line 2: judul --}}
                <div class="text-center mb-0.5">Dokter Penanggung Jawab Pelayanan,</div>

                {{-- Line 3: TTD image / fallback h-16 + &nbsp; --}}
                <div class="text-center">
                    @if (!empty($ttdDpjp))
                        <img class="h-16" src="@ttdSrc($ttdDpjp)" alt="TTD DPJP">
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                {{-- Line 4: nama + underline --}}
                <div class="text-center">
                    <span class="inline-block min-w-[150px] border-t border-black pt-0.5 font-bold">
                        {{ $dpjpName ?: '-' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
