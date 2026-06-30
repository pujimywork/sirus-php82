<x-pdf.layout-kwitansi title="RESEP ITER — Rawat Jalan">

    {{-- ══════════════════════════════════════
         IDENTITAS PASIEN
    ══════════════════════════════════════ --}}
    @php
        $sexLabel = ($data['sex'] ?? '') === 'L' ? 'Laki-laki' : (($data['sex'] ?? '') === 'P' ? 'Perempuan' : null);
    @endphp
    <div class="mb-4">
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$sexLabel"
            :tempatLahir="$data['birthPlace'] ?? null"
            :tglLahir="$data['birthDate'] ?? null"
            :umur="$data['umur'] ?? null"
            :alamat="$data['address'] ?? null">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Jalan</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] text-gray-900 font-medium">{{ $data['rjNo'] }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Kunjungan</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] text-gray-900 font-medium">{{ $data['rjDate'] }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Poli</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] text-gray-900 font-medium">{{ $data['poliName'] }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Dokter</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] text-gray-900 font-medium">{{ $data['drName'] }}</td>
            </tr>
            @if ($data['vnoSep'] ?? null)
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. SEP</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] text-gray-900 font-medium">{{ $data['vnoSep'] }}</td>
                </tr>
            @endif
        </x-pdf.identitas-pasien>
    </div>

    {{-- ══════════════════════════════════════
         DAFTAR OBAT ITER
    ══════════════════════════════════════ --}}
    <table class="w-full mb-1 text-[11px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-gray-400">
                <th class="py-1 text-left font-semibold text-gray-900 w-8">No.</th>
                <th class="py-1 text-left font-semibold text-gray-900">Nama Obat</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-20">Qty</th>
                <th class="py-1 text-left font-semibold text-gray-900 w-20">Satuan</th>
                <th class="py-1 text-left font-semibold text-gray-900 w-32">Signa</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-20">Iter</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['obat'] as $index => $obat)
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $index + 1 }}.</td>
                    <td class="py-1 text-gray-900">
                        {{ $obat->product_name }}
                        @if (!empty($obat->rj_ket))
                            <div class="text-[10px] text-gray-500 italic">{{ $obat->rj_ket }}</div>
                        @endif
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ rtrim(rtrim(number_format((float) $obat->qty, 2, ',', '.'), '0'), ',') }}
                    </td>
                    <td class="py-1 text-gray-700">{{ $obat->rj_takar }}</td>
                    <td class="py-1 text-gray-700">
                        {{ $obat->rj_carapakai }}x{{ $obat->rj_kapsul }} {{ $obat->rj_takar }}
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900 font-semibold">
                        {{ (int) $obat->iter_qty }}x
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="mt-3 text-[10px] text-gray-600 italic">
        Resep ini dapat diulang sebanyak iterasi yang tercantum tanpa konsultasi ulang dokter,
        kecuali ada perubahan kondisi pasien.
    </p>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mt-6 text-[11px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-2/3"></td>
            <td class="text-center">
                <p class="text-gray-700">Dokter,</p>
                <p class="mb-16 text-gray-700">{{ $data['tglCetak'] }}</p>
                <p class="font-semibold text-gray-900">{{ $data['drName'] }}</p>
            </td>
        </tr>
    </table>

    <p class="mt-4 text-[9px] text-gray-500 italic">
        Dicetak: {{ $data['tglCetak'] }} {{ $data['jamCetak'] }} oleh {{ $data['cetakOleh'] }}
    </p>

</x-pdf.layout-kwitansi>
