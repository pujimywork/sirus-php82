<x-pdf.layout-kwitansi title="RESEP ITER — Rawat Jalan">

    {{-- ══════════════════════════════════════
         IDENTITAS KUNJUNGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mb-4" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-700">No. Rawat Jalan</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['rjNo'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Tanggal Kunjungan</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['rjDate'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Poli</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['poliName'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Dokter</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['drName'] }}</td>
        </tr>
        @if ($data['vnoSep'] ?? null)
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">No. SEP</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['vnoSep'] }}</td>
        </tr>
        @endif
    </table>

    <table class="w-full mb-4" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-700">No. Rekam Medis</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['regNo'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Nama Pasien</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['regName'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Tanggal Lahir / Sex</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['birthDate'] }} / {{ $data['sex'] === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700 align-top">Alamat</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium align-top">: {{ $data['address'] ?? '-' }}</td>
        </tr>
    </table>

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
            @foreach ($data['obat'] as $i => $o)
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $i + 1 }}.</td>
                    <td class="py-1 text-gray-900">
                        {{ $o->product_name }}
                        @if (!empty($o->rj_ket))
                            <div class="text-[10px] text-gray-500 italic">{{ $o->rj_ket }}</div>
                        @endif
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ rtrim(rtrim(number_format((float) $o->qty, 2, ',', '.'), '0'), ',') }}
                    </td>
                    <td class="py-1 text-gray-700">{{ $o->rj_takar }}</td>
                    <td class="py-1 text-gray-700">
                        {{ $o->rj_carapakai }}x{{ $o->rj_kapsul }} {{ $o->rj_takar }}
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900 font-semibold">
                        {{ (int) $o->iter_qty }}x
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
