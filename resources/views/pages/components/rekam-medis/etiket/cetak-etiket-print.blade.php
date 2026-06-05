<x-pdf.layout-etiket>

    {{-- HEADER --}}
    <table class="w-full pb-1 mb-1 border-b border-gray-400" cellpadding="0" cellspacing="0">
        <tr>
            <td class="pr-1 align-middle" style="width:auto;">
                <img src="{{ public_path('images/Logo Persegi.png') }}" alt="Logo RS" class="object-contain"
                    style="height:6mm; width:auto;">
            </td>
            <td class="text-left align-middle">
                <div class="font-bold text-gray-900" style="font-size:7pt;">RUMAH SAKIT ISLAM MADINAH</div>
            </td>
        </tr>
    </table>

    {{-- INFO PASIEN — format mengikuti etiket SIMRS lama --}}
    @php
        // L/P satu huruf — mapping eksplisit 3 cabang (jenisKelaminOptions > 2 nilai)
        $jkDesc = strtoupper($data['jenisKelamin']['jenisKelaminDesc'] ?? '');
        $lp = str_starts_with($jkDesc, 'LAKI') ? 'L' : (str_starts_with($jkDesc, 'PEREMPUAN') ? 'P' : '-');

        $nik = $data['identitas']['nik'] ?? '';
        $phone = $data['kontak']['nomerTelponSelulerPasien'] ?? '';

        // Alamat singkat: alamat + rt/rw + desa (tanpa kecamatan, model lama 1 baris).
        // Skip rt/rw & desa kalau sudah tertulis di field alamat (hindari dobel).
        $alamat = $data['identitas']['alamat'] ?? '-';
        $rt = $data['identitas']['rt'] ?? '';
        $rw = $data['identitas']['rw'] ?? '';
        $desa = $data['identitas']['desaName'] ?? '';
        $rtRw = $rt !== '' && $rw !== '' ? ltrim($rt, '0') . '/' . ltrim($rw, '0') : '';
        $adaRtRw = preg_match('#\d+\s*/\s*\d+#', $alamat);
        $adaDesa = $desa !== '' && stripos($alamat, $desa) !== false;
        $full = trim($alamat . (!$adaRtRw && $rtRw !== '' ? ' ' . $rtRw : '') . (!$adaDesa && $desa !== '' ? ' ' . $desa : ''));
    @endphp

    {{-- font-size pakai inline style — kelas arbitrary (text-[..px]) belum tentu ada di CSS build PDF --}}
    <div class="font-bold text-black" style="font-size:11px; line-height:1.25;">
        No. RM : {{ $data['regNo'] ?? '-' }}
    </div>
    <div class="font-bold text-black" style="font-size:10px; line-height:1.25;">
        NIK : {{ $nik !== '' ? $nik : '-' }}
    </div>
    <div class="font-bold text-black" style="font-size:12px; line-height:1.25; margin-top:0.7mm;">
        {{ strtoupper($data['regName'] ?? '-') }} / {{ $lp }}
    </div>
    <div class="text-black" style="font-size:10px; line-height:1.3; margin-top:0.7mm;">
        {{ $data['tglLahir'] ?? '-' }} / {{ isset($data['umurTahun']) ? $data['umurTahun'] . ' tahun' : '-' }} /
        {{ strtoupper($data['tempatLahir'] ?? '-') }}
    </div>
    @if ($phone !== '')
        <div class="text-black" style="font-size:10px; line-height:1.3;">({{ $phone }})</div>
    @endif
    <div class="text-black" style="font-size:10px; line-height:1.3;">
        {{ strtoupper(\Illuminate\Support\Str::limit($full, 50)) }}
    </div>

    {{-- BARCODE: di-off dulu — space dipakai memperbesar tulisan.
         Nyalakan lagi: {!! DNS1D::getBarcodeHTML($data['regNo'] ?? '0', 'C39', 1.1, 16, 'black', false) !!} --}}

</x-pdf.layout-etiket>
