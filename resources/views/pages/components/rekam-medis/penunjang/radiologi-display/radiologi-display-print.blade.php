{{-- Template print: Hasil Pemeriksaan Radiologi (per-order) --}}
{{-- Layout meniru report Oracle Forms legacy: HASIL PEMERIKSAAN + nama
     pemeriksaan + "Teman sejawat Yth." + isi hasil bacaan + TTD radiolog --}}

<x-pdf.layout-a4-with-out-background title="HASIL PEMERIKSAAN RADIOLOGI">

    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $header->reg_no ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ strtoupper($header->reg_name ?? '-') }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Kelamin</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">
                    @php $sex = strtoupper($header->sex ?? ''); @endphp
                    {{ $sex === 'L' ? 'Laki-laki' : ($sex === 'P' ? 'Perempuan' : '-') }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Lahir</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $header->birth_date ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">{{ $header->address ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Pemeriksaan</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">
                    {{ $header->waktu_entry ? \Carbon\Carbon::parse($header->waktu_entry)->format('d/m/Y H:i') : '-' }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Dokter Pengirim</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $header->dr_pengirim ?? '-' }}</td>
            </tr>
        </table>
    </x-slot>

    {{-- Judul pemeriksaan --}}
    <div class="mb-3 text-center">
        <p class="text-[14px] font-bold uppercase">{{ $header->rad_desc ?? '-' }}</p>
        @if (!empty($header->keterangan))
            <p class="text-[10px] text-gray-600 mt-0.5">{{ $header->keterangan }}</p>
        @endif
    </div>

    {{-- Salam --}}
    <p class="text-[11px] mb-2">Teman sejawat Yth.</p>

    {{-- Isi hasil bacaan — disimpan sebagai HTML (TinyMCE output). --}}
    {{-- Catatan kompat: data legacy dari era Quill bisa punya class
         .ql-align-center / -right / -justify; CSS di bawah masih handle
         keduanya. Default alignment (left) = no class. --}}
    <style>
        .rad-hasil-bacaan { font-size: 11px; line-height: 1.5; color: #1f2937; }
        .rad-hasil-bacaan p { margin: 0 0 6px 0; }
        .rad-hasil-bacaan ol { padding-left: 24px; margin: 0 0 6px 0; }
        .rad-hasil-bacaan ul { padding-left: 24px; margin: 0 0 6px 0; }
        .rad-hasil-bacaan li { margin-bottom: 2px; }
        .rad-hasil-bacaan h1 { font-size: 14px; font-weight: bold; margin: 6px 0; }
        .rad-hasil-bacaan h2 { font-size: 13px; font-weight: bold; margin: 6px 0; }
        .rad-hasil-bacaan h3 { font-size: 12px; font-weight: bold; margin: 6px 0; }
        .rad-hasil-bacaan blockquote { border-left: 3px solid #9ca3af; padding-left: 8px; margin: 6px 0; color: #4b5563; }
        .rad-hasil-bacaan b, .rad-hasil-bacaan strong { font-weight: bold; }
        .rad-hasil-bacaan i, .rad-hasil-bacaan em { font-style: italic; }
        .rad-hasil-bacaan u { text-decoration: underline; }
        .rad-hasil-bacaan s { text-decoration: line-through; }
        .rad-hasil-bacaan .ql-align-center { text-align: center; }
        .rad-hasil-bacaan .ql-align-right { text-align: right; }
        .rad-hasil-bacaan .ql-align-justify { text-align: justify; }
        .rad-hasil-bacaan .ql-indent-1 { padding-left: 24px; }
        .rad-hasil-bacaan .ql-indent-2 { padding-left: 48px; }
        .rad-hasil-bacaan .ql-indent-3 { padding-left: 72px; }
    </style>
    <div class="rad-hasil-bacaan mb-6 px-2">
        {!! !empty($header->hasil_bacaan) ? $header->hasil_bacaan : '<p>-</p>' !!}
    </div>

    {{-- Footer: TTD Dokter Radiolog --}}
    @php
        $drRadiolog = !empty($header->dr_radiologi)
            ? \DB::table('rsmst_doctors')->where('dr_id', $header->dr_radiologi)->first(['dr_id', 'dr_name'])
            : null;

        // Fallback: kalau dr_radiologi kosong, ambil pertama dari poli RADIOLOGI yang aktif
        if (!$drRadiolog) {
            $drRadiolog = \DB::table('rsmst_doctors as d')
                ->join('rsmst_polis as p', 'd.poli_id', '=', 'p.poli_id')
                ->whereRaw("UPPER(p.poli_desc) LIKE '%RADIOLOG%'")
                ->where('d.active_status', '1')
                ->orderBy('d.dr_name', 'asc')
                ->first(['d.dr_id', 'd.dr_name']);
        }

        $ttdDrRadiolog = !empty($drRadiolog->dr_id)
            ? \App\Models\User::where('myuser_code', $drRadiolog->dr_id)->value('myuser_ttd_image')
            : null;
    @endphp

    <table class="w-full text-[10px] mt-4 border-collapse">
        <tr>
            <td class="w-2/3 px-1 align-top"></td>
            <td class="w-1/3 px-1 text-center" style="vertical-align: bottom;">
                <p class="mb-1">Dokter Radiolog,</p>
                @if (!empty($ttdDrRadiolog))
                    <img class="h-16" src="@ttdSrc($ttdDrRadiolog)" alt="">
                @else
                    <div class="h-20"></div>
                @endif
                <div class="inline-block min-w-[130px] border-t border-black pt-0.5">
                    <p class="font-semibold">{{ $drRadiolog->dr_name ?? '-' }}</p>
                </div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
