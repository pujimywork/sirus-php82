{{-- cetak-kwitansi-rj-obat-print.blade.php --}}

<x-pdf.layout-kwitansi :title="$data['judul'] ?? 'KWITANSI OBAT - Rawat Jalan'">

    {{-- ══════════════════════════════════════
         IDENTITAS KUNJUNGAN
    ══════════════════════════════════════ --}}
    <x-pdf.identitas-pasien
        :rm="$data['regNo'] ?? null"
        :nama="$data['regName'] ?? null"
        :jenisKelamin="($data['sex'] ?? '') === 'L' ? 'Laki-laki' : (($data['sex'] ?? '') === 'P' ? 'Perempuan' : null)"
        :tglLahir="$data['birthDate'] ?? null"
        class="mb-4">
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Jalan</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px] font-bold">{{ $data['rjNo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px] font-bold">{{ $data['rjDate'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Poli / Klinik</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $data['poliName'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Dokter</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $data['drName'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Pembayaran</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $data['klaimName'] ?? '-' }}</td>
        </tr>
    </x-pdf.identitas-pasien>

    {{-- ══════════════════════════════════════
         TABEL RINCIAN OBAT
    ══════════════════════════════════════ --}}
    <table class="w-full mb-1 text-[11px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-1 text-left font-semibold text-gray-900 w-8">No.</th>
                <th class="py-1 text-left font-semibold text-gray-900">Nama Obat</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-16">Qty</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-28">Harga (Rp)</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-32">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rincianObat'] as $i => $item)
                @php
                    $qtyVal = (float) ($item->qty ?? 0);
                    $totalVal = (int) ($item->obat ?? 0);
                    $hargaSatuan = $qtyVal > 0 ? $totalVal / $qtyVal : 0;
                    $qtyDisplay = floor($qtyVal) == $qtyVal
                        ? number_format($qtyVal, 0, ',', '.')
                        : rtrim(rtrim(number_format($qtyVal, 2, ',', '.'), '0'), ',');
                @endphp
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $i + 1 }}.</td>
                    <td class="py-1 text-gray-900">{{ $item->keterangan }}</td>
                    <td class="py-1 text-right tabular-nums text-gray-900">{{ $qtyDisplay }}</td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format((int) round($hargaSatuan), 0, ',', '.') }}
                    </td>
                    <td class="py-1 text-right tabular-nums text-gray-900">
                        {{ number_format($totalVal, 0, ',', '.') }}
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
            {{-- Total Obat --}}
            <tr class="border-t-2 border-gray-400">
                <td colspan="4" class="pt-2 pb-1 font-bold text-[12px] text-right pr-3 text-gray-900">Total</td>
                <td class="pt-2 pb-1 font-bold text-[13px] text-right tabular-nums text-gray-900">
                    Rp {{ number_format($data['totalObat'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Terbilang — dari totalObat --}}
    <div class="mb-5 px-3 py-2 bg-gray-50 border border-gray-400 rounded text-[10px] text-gray-700 italic">
        Terbilang:
        <strong class="not-italic text-gray-900">
            @php
                if (!function_exists('terbilang_obat')) {
                    function terbilang_obat(int $n): string
                    {
                        $satuan = [
                            '',
                            'satu',
                            'dua',
                            'tiga',
                            'empat',
                            'lima',
                            'enam',
                            'tujuh',
                            'delapan',
                            'sembilan',
                            'sepuluh',
                            'sebelas',
                        ];
                        if ($n < 12) {
                            return $satuan[$n];
                        }
                        if ($n < 20) {
                            return terbilang_obat($n - 10) . ' belas';
                        }
                        if ($n < 100) {
                            return terbilang_obat((int) ($n / 10)) .
                                ' puluh' .
                                ($n % 10 ? ' ' . terbilang_obat($n % 10) : '');
                        }
                        if ($n < 200) {
                            return 'seratus' . ($n % 100 ? ' ' . terbilang_obat($n % 100) : '');
                        }
                        if ($n < 1_000) {
                            return terbilang_obat((int) ($n / 100)) .
                                ' ratus' .
                                ($n % 100 ? ' ' . terbilang_obat($n % 100) : '');
                        }
                        if ($n < 2_000) {
                            return 'seribu' . ($n % 1_000 ? ' ' . terbilang_obat($n % 1_000) : '');
                        }
                        if ($n < 1_000_000) {
                            return terbilang_obat((int) ($n / 1_000)) .
                                ' ribu' .
                                ($n % 1_000 ? ' ' . terbilang_obat($n % 1_000) : '');
                        }
                        if ($n < 1_000_000_000) {
                            return terbilang_obat((int) ($n / 1_000_000)) .
                                ' juta' .
                                ($n % 1_000_000 ? ' ' . terbilang_obat($n % 1_000_000) : '');
                        }
                        return terbilang_obat((int) ($n / 1_000_000_000)) .
                            ' miliar' .
                            ($n % 1_000_000_000 ? ' ' . terbilang_obat($n % 1_000_000_000) : '');
                    }
                }
                echo ucfirst(terbilang_obat((int) ($data['totalObat'] ?? 0))) . ' Rupiah';
            @endphp
        </strong>
    </div>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
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

    {{-- ══════════════════════════════════════
         FOOTER INFO CETAK
    ══════════════════════════════════════ --}}
    <div class="mt-6 pt-2 border-t border-gray-400 text-[9px] text-gray-700 flex justify-between">
        <span>
            Dicetak oleh: {{ $data['cetakOleh'] ?? '-' }} —
            {{ $data['tglCetak'] ?? '' }}, pukul {{ $data['jamCetak'] ?? '' }}
        </span>
        <span>No. RJ: {{ $data['rjNo'] ?? '-' }}</span>
    </div>

</x-pdf.layout-kwitansi>
