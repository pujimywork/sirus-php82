<x-pdf.layout-kwitansi title="KWITANSI OBAT - Rawat Inap">

    {{-- IDENTITAS --}}
    <x-pdf.identitas-pasien
        :rm="$data['regNo'] ?? null"
        :nama="$data['regName'] ?? null"
        :jenisKelamin="($data['sex'] ?? '') === 'L' ? 'Laki-laki' : (($data['sex'] ?? '') === 'P' ? 'Perempuan' : null)"
        :tempatLahir="$data['birthPlace'] ?? null"
        :tglLahir="$data['birthDate'] ?? null"
        :umur="$data['umur'] ?? null"
        :alamat="$data['address'] ?? null"
        class="mb-4">
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. SLS / Tgl</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px] font-bold">
                {{ $data['slsNo'] ?? '-' }} / {{ $data['slsDate'] ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. RI</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px] font-bold">{{ $data['rihdrNo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Ruangan</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $data['roomDesc'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Dokter</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $data['drName'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Cara Bayar</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">
                {{ $data['accName'] ?? '-' }}
                <span class="text-gray-400">·</span>
                {{ $data['klaimName'] ?? '-' }}
            </td>
        </tr>
    </x-pdf.identitas-pasien>

    {{-- RINCIAN OBAT --}}
    <table class="w-full mb-1 text-[11px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-1 text-left font-semibold text-gray-900 w-8">No.</th>
                <th class="py-1 text-left font-semibold text-gray-900">Nama Obat</th>
                <th class="py-1 text-center font-semibold text-gray-900 w-12">Qty</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-24">Harga</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-28">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['items'] as $index => $item)
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $index + 1 }}.</td>
                    <td class="py-1 text-gray-900 uppercase">{{ $item->product_name }}</td>
                    <td class="py-1 text-center text-gray-900">
                        {{ (int) $item->qty }} {{ $item->resep_takar ?? '' }}
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format((int) $item->sales_price, 0, ',', '.') }}
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format((int) $item->subtotal_item, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-3 text-center text-gray-700 italic">
                        Tidak ada data obat.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t border-gray-300">
                <td colspan="4" class="pt-1 pr-3 text-right text-[11px] text-gray-700">Subtotal</td>
                <td class="pt-1 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['subtotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="4" class="pr-3 text-right text-[11px] text-gray-700">Embalase</td>
                <td class="text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['actePrice'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="border-t-2 border-gray-400">
                <td colspan="4" class="pt-2 pb-1 font-bold text-[12px] text-right pr-3 text-gray-900">Total</td>
                <td class="pt-2 pb-1 font-bold text-[13px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['totalObat'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="4" class="pr-3 text-right text-[11px] text-emerald-700">Dibayar</td>
                <td class="text-right tabular-nums font-semibold text-emerald-700">
                    Rp {{ number_format($data['bayar'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            @if (($data['bon'] ?? 0) > 0)
                <tr>
                    <td colspan="4" class="pr-3 text-right text-[11px] text-amber-700">Bon Inap</td>
                    <td class="text-right tabular-nums font-semibold text-amber-700">
                        Rp {{ number_format($data['bon'] ?? 0, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
        </tfoot>
    </table>

    {{-- TERBILANG (dari nominal yang dibayar) --}}
    <div class="mb-5 px-3 py-2 bg-gray-50 border border-gray-400 rounded text-[10px] text-gray-700 italic">
        Terbilang (Dibayar):
        <strong class="not-italic text-gray-900">
            @php
                if (!function_exists('terbilang_ri_obat')) {
                    function terbilang_ri_obat(int $nilai): string
                    {
                        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
                        if ($nilai < 12) return $satuan[$nilai];
                        if ($nilai < 20) return terbilang_ri_obat($nilai - 10) . ' belas';
                        if ($nilai < 100) return terbilang_ri_obat((int) ($nilai / 10)) . ' puluh' . ($nilai % 10 ? ' ' . terbilang_ri_obat($nilai % 10) : '');
                        if ($nilai < 200) return 'seratus' . ($nilai % 100 ? ' ' . terbilang_ri_obat($nilai % 100) : '');
                        if ($nilai < 1_000) return terbilang_ri_obat((int) ($nilai / 100)) . ' ratus' . ($nilai % 100 ? ' ' . terbilang_ri_obat($nilai % 100) : '');
                        if ($nilai < 2_000) return 'seribu' . ($nilai % 1_000 ? ' ' . terbilang_ri_obat($nilai % 1_000) : '');
                        if ($nilai < 1_000_000) return terbilang_ri_obat((int) ($nilai / 1_000)) . ' ribu' . ($nilai % 1_000 ? ' ' . terbilang_ri_obat($nilai % 1_000) : '');
                        if ($nilai < 1_000_000_000) return terbilang_ri_obat((int) ($nilai / 1_000_000)) . ' juta' . ($nilai % 1_000_000 ? ' ' . terbilang_ri_obat($nilai % 1_000_000) : '');
                        return terbilang_ri_obat((int) ($nilai / 1_000_000_000)) . ' miliar' . ($nilai % 1_000_000_000 ? ' ' . terbilang_ri_obat($nilai % 1_000_000_000) : '');
                    }
                }
                echo ucfirst(terbilang_ri_obat((int) ($data['bayar'] ?? 0))) . ' Rupiah';
            @endphp
        </strong>
        @if (($data['bon'] ?? 0) > 0)
            <div class="mt-1 text-[10px] text-amber-700 not-italic">
                Sisa Rp {{ number_format($data['bon'], 0, ',', '.') }} masuk Bon Inap (akan ditagih saat pasien pulang).
            </div>
        @endif
    </div>

    {{-- TANDA TANGAN --}}
    <table class="w-full mt-4 text-[11px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-5/12 text-center align-bottom">
                <p class="mb-16 text-gray-700">Petugas Farmasi</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:140px;">
                    <span class="text-gray-900">
                        {{ $data['kasirName'] ?? '( ................................ )' }}
                    </span>
                </div>
            </td>

            <td class="w-2/12"></td>

            <td class="w-5/12 text-center align-bottom">
                <p class="text-gray-700">Tulungagung, {{ $data['tglCetak'] ?? '' }}</p>
                <p class="mb-16 text-gray-700">Pasien / Wali</p>
                <div class="inline-block pt-1 border-t border-gray-300" style="min-width:140px;">
                    <span class="text-gray-900">
                        {{ $data['regName'] ?? '( ................................ )' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- FOOTER --}}
    <div class="mt-6 pt-2 border-t border-gray-400 text-[9px] text-gray-700 flex justify-between">
        <span>
            Dicetak oleh: {{ $data['cetakOleh'] ?? '-' }} —
            {{ $data['tglCetak'] ?? '' }}, pukul {{ $data['jamCetak'] ?? '' }}
        </span>
        <span>No. SLS: {{ $data['slsNo'] ?? '-' }}</span>
    </div>

</x-pdf.layout-kwitansi>
