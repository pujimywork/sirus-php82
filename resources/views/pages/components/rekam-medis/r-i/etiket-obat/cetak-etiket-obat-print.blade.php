{{-- cetak-etiket-obat-print.blade.php (RI) --}}
<x-pdf.layout-etiket>

    @php
        $item = $data['obat'];
        $lp = ($item->sex ?? '') === 'L' ? 'L' : ((($item->sex ?? '') === 'P') ? 'P' : '-');
    @endphp

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

    {{-- IDENTITAS PASIEN — format etiket pasien; font-size inline (kelas arbitrary tak ada di CSS build PDF) --}}
    <div class="font-bold text-black" style="font-size:9px; line-height:1.25;">
        No. RM : {{ $item->reg_no ?? '-' }}
    </div>
    <div class="font-bold text-black" style="font-size:10px; line-height:1.25;">
        {{ strtoupper($item->reg_name ?? '-') }} / {{ $lp }}
    </div>
    <div class="text-black" style="font-size:8px; line-height:1.3;">
        {{ $item->birth_date ?? '-' }} / {{ isset($data['umurTahun']) ? $data['umurTahun'] . ' tahun' : '-' }} /
        {{ strtoupper($item->birth_place ?? '-') }}
    </div>
    <div class="text-black" style="font-size:8px; line-height:1.3;">
        {{ strtoupper(\Illuminate\Support\Str::limit($item->address ?? '-', 50)) }}
    </div>

    {{-- NAMA OBAT + ATURAN PAKAI — bagian utama: font besar, nama obat boleh wrap (jangan nowrap, kepotong) --}}
    <table class="w-full mt-1 border-t border-dashed border-gray-400" cellpadding="0" cellspacing="0">
        <tr>
            <td class="font-bold text-black" style="font-size:11px; line-height:1.25; padding-top:1mm;">
                {{ $item->product_name ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="font-bold text-black" style="font-size:10px; line-height:1.3;">
                {{ $item->resep_carapakai ?? '-' }} X SEHARI
                @if (!empty($item->resep_kapsul))
                    {{ $item->resep_kapsul }}
                @endif
                @if (!empty($item->resep_takar))
                    {{ $item->resep_takar }}
                @endif
            </td>
        </tr>
        <tr>
            <td style="font-size:9px; line-height:1.3;">
                @if (!empty($item->resep_ket))
                    <span class="text-gray-700">({{ $item->resep_ket }})</span>
                @endif
                <span class="font-bold text-red-700">ED: {{ $item->exp_date ?? '-' }}</span>
            </td>
        </tr>
    </table>
</x-pdf.layout-etiket>
