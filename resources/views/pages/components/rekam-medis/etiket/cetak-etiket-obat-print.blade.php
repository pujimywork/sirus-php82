{{-- cetak-etiket-obat-print.blade.php --}}
<x-pdf.layout-etiket>

    @php $item = $data['obat']; @endphp

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

    {{-- NO RM --}}
    <div class="mb-1">
        <span class="text-[9px] font-bold tracking-wide text-black">
            {{ $item->reg_no ?? '-' }}
        </span>
    </div>

    {{-- INFO PASIEN --}}
    <table class="w-full" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-[10mm] text-[6.5px] text-gray-500 align-top py-[0.2mm]">Nama</td>
            <td class="w-[2.5mm] text-[6.5px] text-gray-500 align-top py-[0.2mm]">:</td>
            <td class="text-[8.5px] font-bold text-black align-top py-[0.2mm]">
                {{ $item->reg_name ?? '-' }}
                / {{ ($item->sex ?? '') === 'L' ? 'L' : 'P' }}
            </td>
        </tr>
        <tr>
            <td class="w-[10mm] text-[6.5px] text-gray-500 align-top py-[0.2mm]">TTL</td>
            <td class="w-[2.5mm] text-[6.5px] text-gray-500 align-top py-[0.2mm]">:</td>
            <td class="text-[6.5px] text-gray-800 align-top py-[0.2mm]">
                {{ $item->birth_place ?? '-' }} - {{ $item->birth_date ?? '-' }}
                ({{ $data['umur'] ?? '-' }})
            </td>
        </tr>
        <tr>
            <td class="w-[10mm] text-[6.5px] text-gray-500 align-top py-[0.2mm]">Alamat</td>
            <td class="w-[2.5mm] text-[6.5px] text-gray-500 align-top py-[0.2mm]">:</td>
            <td class="text-[6.5px] text-gray-800 align-top py-[0.2mm]">
                {{ \Illuminate\Support\Str::limit($item->address ?? '-', 55) }}
            </td>
        </tr>
    </table>

    {{-- NAMA OBAT + ATURAN PAKAI --}}
    <table class="w-full my-1 py-1 border-t border-b border-dashed border-gray-400" cellpadding="0" cellspacing="0">
        <tr>
            <td class="text-[8.5pt] font-bold text-black align-middle" style="white-space:nowrap;">
                {{ $item->product_name ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="pl-1 text-[7px] font-bold text-black align-middle">
                {{ $item->rj_carapakai ?? '-' }} X SEHARI
                @if (!empty($item->rj_kapsul))
                    {{ $item->rj_kapsul }}
                @endif
                @if (!empty($item->rj_takar))
                    {{ $item->rj_takar }}
                @endif
                @if (!empty($item->rj_ket))
                    <span class="font-normal text-gray-600">({{ $item->rj_ket }})</span>
                @endif
                <span class="text-red-700">ED: {{ $item->exp_date ?? '-' }}</span>
            </td>
        </tr>
    </table>
</x-pdf.layout-etiket>
