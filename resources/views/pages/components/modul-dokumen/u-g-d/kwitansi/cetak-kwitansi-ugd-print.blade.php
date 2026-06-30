{{-- resources/views/pages/components/modul-dokumen/u-g-d/kwitansi/cetak-kwitansi-ugd-print.blade.php --}}

<x-pdf.layout-kwitansi title="KWITANSI PEMBAYARAN - Unit Gawat Darurat">

    {{-- ══════════════════════════════════════
         IDENTITAS KUNJUNGAN
    ══════════════════════════════════════ --}}
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
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. UGD</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px] font-bold">{{ $data['rjNo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Kunjungan</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px] font-bold">{{ $data['rjDate'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Unit</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $data['poliName'] ?? 'UGD' }}</td>
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

        @if (!empty($data['isBpjs']) && !empty($data['sep']))
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. SEP</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold tracking-wide">
                    {{ $data['sep']['noSep'] ?? '-' }}
                </td>
            </tr>
            @if (!empty($data['sep']['noReferensi']))
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Referensi</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px]">{{ $data['sep']['noReferensi'] }}</td>
                </tr>
            @endif
        @endif
    </x-pdf.identitas-pasien>

    {{-- ══════════════════════════════════════
         TABEL RINCIAN BIAYA
    ══════════════════════════════════════ --}}
    <table class="w-full mb-1 text-[11px]" cellpadding="0" cellspacing="0">
        <thead>
            <tr class="border-b border-t border-gray-400">
                <th class="py-1 text-left font-semibold text-gray-900 w-8">No.</th>
                <th class="py-1 text-left font-semibold text-gray-900">Keterangan</th>
                <th class="py-1 text-right font-semibold text-gray-900 w-36">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rincian'] as $index => $item)
                <tr class="border-b border-gray-100">
                    <td class="py-1 text-gray-700">{{ $index + 1 }}.</td>
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
        </tfoot>
    </table>

    {{-- Terbilang --}}
    <div class="mb-5 px-3 py-2 bg-gray-50 border border-gray-400 rounded text-[10px] text-gray-700 italic">
        Terbilang:
        <strong class="not-italic text-gray-900">
            @php
                if (!function_exists('terbilang')) {
                    function terbilang(int $nilai): string
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
                        if ($nilai < 12) {
                            return $satuan[$nilai];
                        }
                        if ($nilai < 20) {
                            return terbilang($nilai - 10) . ' belas';
                        }
                        if ($nilai < 100) {
                            return terbilang((int) ($nilai / 10)) . ' puluh' . ($nilai % 10 ? ' ' . terbilang($nilai % 10) : '');
                        }
                        if ($nilai < 200) {
                            return 'seratus' . ($nilai % 100 ? ' ' . terbilang($nilai % 100) : '');
                        }
                        if ($nilai < 1_000) {
                            return terbilang((int) ($nilai / 100)) . ' ratus' . ($nilai % 100 ? ' ' . terbilang($nilai % 100) : '');
                        }
                        if ($nilai < 2_000) {
                            return 'seribu' . ($nilai % 1_000 ? ' ' . terbilang($nilai % 1_000) : '');
                        }
                        if ($nilai < 1_000_000) {
                            return terbilang((int) ($nilai / 1_000)) .
                                ' ribu' .
                                ($nilai % 1_000 ? ' ' . terbilang($nilai % 1_000) : '');
                        }
                        if ($nilai < 1_000_000_000) {
                            return terbilang((int) ($nilai / 1_000_000)) .
                                ' juta' .
                                ($nilai % 1_000_000 ? ' ' . terbilang($nilai % 1_000_000) : '');
                        }
                        return terbilang((int) ($nilai / 1_000_000_000)) .
                            ' miliar' .
                            ($nilai % 1_000_000_000 ? ' ' . terbilang($nilai % 1_000_000_000) : '');
                    }
                }
                echo ucfirst(terbilang((int) ($data['grandTotal'] ?? 0))) . ' Rupiah';
            @endphp
        </strong>
    </div>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table class="w-full mt-4 text-[11px]" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-5/12 text-center align-bottom">
                <p class="mb-16 text-gray-700">Kasir / Petugas Administrasi</p>
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
        <span>No. UGD: {{ $data['rjNo'] ?? '-' }}</span>
    </div>

</x-pdf.layout-kwitansi>
