<x-pdf.layout-kwitansi title="KWITANSI PEMBAYARAN - Rawat Inap">

    {{-- ══════════════════════════════════════
         IDENTITAS KUNJUNGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-700">No. Rawat Inap</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['riHdrNo'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Tgl Masuk</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['entryDate'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Tgl Pulang</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['exitDate'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Bangsal / Kamar</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['bangsalName'] }} / {{ $data['roomName'] }} (Bed {{ $data['bedNo'] }})</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">DPJP</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['drName'] }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700">Klaim</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['klaimName'] }} ({{ $data['klaimId'] }})</td>
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
            <td class="py-0.5 text-[11px] text-gray-700">Tgl Lahir / Sex</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium">: {{ $data['birthDate'] }} / {{ $data['sex'] === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-700 align-top">Alamat</td>
            <td class="py-0.5 text-[11px] text-gray-900 font-medium align-top">: {{ $data['address'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         RINCIAN BIAYA
    ══════════════════════════════════════ --}}
    <table class="w-full mb-1 text-[11px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-gray-400">
                <th class="py-1 text-left font-semibold text-gray-900 w-8">No.</th>
                <th class="py-1 text-left font-semibold text-gray-900">Keterangan</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-36">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rincian'] as $i => $item)
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $i + 1 }}.</td>
                    <td class="py-1 text-gray-900">{{ $item->txn_desc }}</td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format((int) $item->txn_nominal, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-3 text-center text-gray-700 italic">
                        Tidak ada rincian biaya.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t border-gray-300">
                <td colspan="2" class="pt-1 pb-0.5 text-right pr-3 text-gray-700">Subtotal</td>
                <td class="pt-1 pb-0.5 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['subtotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="pb-0.5 text-right pr-3 text-gray-700">Diskon</td>
                <td class="pb-0.5 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['rjDiskon'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="border-t-2 border-gray-400">
                <td colspan="2" class="pt-2 pb-1 font-bold text-[12px] text-right pr-3 text-gray-900">Total</td>
                <td class="pt-2 pb-1 font-bold text-[13px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['grandTotal'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="pt-1 pb-0.5 text-right pr-3 text-gray-700">Sudah Dibayar</td>
                <td class="pt-1 pb-0.5 text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['sudahBayar'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="border-t border-gray-300">
                <td colspan="2" class="pt-1 pb-0.5 text-right pr-3 font-semibold text-gray-800">Sisa</td>
                <td class="pt-1 pb-0.5 text-right tabular-nums font-semibold {{ ($data['sisa'] ?? 0) > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                    Rp {{ number_format($data['sisa'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="pt-1 text-right pr-3 text-gray-700">Status</td>
                <td class="pt-1 text-right tabular-nums font-semibold {{ $data['statusPulang'] === 'L' ? 'text-emerald-700' : ($data['statusPulang'] === 'H' ? 'text-amber-700' : 'text-gray-700') }}">
                    {{ $data['statusLabel'] }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Terbilang — dari grandTotal --}}
    <table class="w-full mt-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-32 py-0.5 text-[10px] text-gray-700 align-top">Terbilang</td>
            <td class="py-0.5 text-[10px] text-gray-900 italic align-top">
                :
                @php
                    if (!function_exists('terbilang')) {
                        function terbilang($x)
                        {
                            $angka = [
                                '', 'satu', 'dua', 'tiga', 'empat', 'lima',
                                'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
                            ];
                            if ($x < 12) return ' ' . $angka[$x];
                            if ($x < 20) return terbilang($x - 10) . ' belas';
                            if ($x < 100) return terbilang(intdiv($x, 10)) . ' puluh' . terbilang($x % 10);
                            if ($x < 200) return ' seratus' . terbilang($x - 100);
                            if ($x < 1000) return terbilang(intdiv($x, 100)) . ' ratus' . terbilang($x % 100);
                            if ($x < 2000) return ' seribu' . terbilang($x - 1000);
                            if ($x < 1000000) return terbilang(intdiv($x, 1000)) . ' ribu' . terbilang($x % 1000);
                            if ($x < 1000000000) return terbilang(intdiv($x, 1000000)) . ' juta' . terbilang($x % 1000000);
                            return '';
                        }
                    }
                    echo ucfirst(trim(terbilang((int) ($data['grandTotal'] ?? 0)))) . ' Rupiah';
                @endphp
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mt-6 text-[11px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-2/3"></td>
            <td class="text-center">
                <p class="text-gray-700">Kasir / Petugas Administrasi</p>
                <p class="mb-16 text-gray-700">{{ $data['tglCetak'] }}</p>
                <p class="font-semibold text-gray-900">{{ $data['kasirName'] ?? '-' }}</p>
            </td>
        </tr>
    </table>

    <p class="mt-4 text-[9px] text-gray-500 italic">
        Dicetak: {{ $data['tglCetak'] }} {{ $data['jamCetak'] }} oleh {{ $data['cetakOleh'] }}
    </p>

</x-pdf.layout-kwitansi>
