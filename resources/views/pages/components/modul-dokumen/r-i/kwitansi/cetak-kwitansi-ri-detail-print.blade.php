<x-pdf.layout-a4-with-out-background title="PERINCIAN BIAYA PENGOBATAN DAN PERAWATAN">

    {{-- ══════════════════════════════════════
         HEADER PASIEN (slot patientData)
    ══════════════════════════════════════ --}}
    <x-slot name="patientData">
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="($data['sex'] ?? '') === 'L' ? 'Laki-laki' : (($data['sex'] ?? '') === 'P' ? 'Perempuan' : null)"
            :alamat="$data['address'] ?? null">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['entryDate'] }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Keluar</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['exitDate'] }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Klaim</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['klaimName'] }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $rp = fn(int $n) => number_format($n, 0, ',', '.');

        if (!function_exists('terbilang')) {
            function terbilang($x)
            {
                $angka = ['', 'satu', 'dua', 'tiga', 'empat', 'lima',
                          'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
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

        // Class helpers (4 kolom: label | qty | amount | subtotal section)
        $hdrSection = 'pt-1 pb-px font-bold';
        $cellLabel  = 'py-px pl-3.5 pr-1';
        $cellQty    = 'py-px px-1.5 text-center text-gray-600 w-[60px]';
        $cellAmt    = 'py-px px-1.5 text-right tabular-nums';
        $cellSubTot = 'py-0.5 px-1.5 text-right tabular-nums border-t border-gray-600';
    @endphp

    {{-- ══════════════════════════════════════
         MAIN TABLE — 4 kolom konsisten:
         (1) label | (2) qty (X) | (3) nominal item | (4) subtotal section
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] mt-2 border-collapse">

        {{-- ─── A. BIAYA KAMAR ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">A. BIAYA KAMAR</td></tr>
        <tr>
            <td colspan="4" class="p-0">
                <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] border-collapse">
                    <tr class="text-gray-700">
                        <td class="py-px pl-3.5 pr-1.5 w-[115px]">Tgl. Masuk</td>
                        <td class="py-px px-1.5 w-[115px]">Tgl. Keluar</td>
                        <td class="py-px px-1.5">Kamar</td>
                        <td class="py-px px-1.5 w-10 text-center">Hari</td>
                        <td class="py-px px-1.5 w-[110px] text-right">Biaya Kamar</td>
                        <td class="py-px px-1.5 w-[110px] text-right">Total Biaya</td>
                    </tr>
                    @forelse ($data['trfrooms'] as $tr)
                        <tr>
                            <td class="py-px pl-3.5 pr-1.5">{{ $tr->start_date }}</td>
                            <td class="py-px px-1.5">{{ $tr->end_date ?? '-' }}</td>
                            <td class="py-px px-1.5">{{ $tr->room_label }}</td>
                            <td class="py-px px-1.5 text-center">{{ $tr->day }}</td>
                            <td class="py-px px-1.5 text-right tabular-nums">{{ $rp($tr->room_price) }}</td>
                            <td class="py-px px-1.5 text-right tabular-nums">{{ $rp($tr->room_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-1.5 px-1.5 text-center italic text-gray-500">-</td></tr>
                    @endforelse
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }} w-[110px]">{{ $rp($data['aTotal']) }}</td>
        </tr>

        {{-- ─── B. JASA MEDIS — per-item (dgn qty X) ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">B. JASA MEDIS</td></tr>
        @foreach ($data['bItems'] as $it)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $it->desc }}</td>
            <td class="{{ $cellQty }}">@if (!is_null($it->qty)){{ $it->qty }} (X)@endif</td>
            <td class="{{ $cellAmt }} w-[110px]">{{ $rp($it->total) }}</td>
            <td class="w-[110px]"></td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['bTotal']) }}</td>
        </tr>

        {{-- ─── C. PENUNJANG DIAGNOSTIK ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">C. PENUNJANG DIAGNOSTIK</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">LABORATORIUM</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['cLab']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="{{ $cellLabel }}">RADIOLOGI</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['cRad']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['cTotal']) }}</td>
        </tr>

        {{-- ─── D. PEMAKAIAN OBAT ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">D. PEMAKAIAN OBAT</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">OBAT PINJAM</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['dObatPinjam']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="{{ $cellLabel }}">BON RESEP</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['dBonResep']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="{{ $cellLabel }}">RESEP LUNAS</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['dResepLunas']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['dTotal']) }}</td>
        </tr>

        {{-- ─── E. OPERASI (hanya tampil bila ada) ─── --}}
        @if ($data['eOperasi'] > 0)
        <tr><td colspan="4" class="{{ $hdrSection }}">E. OPERASI</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">KAMAR OPERASI (OK)</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['eOperasi']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['eOperasi']) }}</td>
        </tr>
        @endif

        {{-- ─── F. ADMINISTRASI DAN LAIN-LAIN ─── --}}
        <tr><td colspan="4" class="{{ $hdrSection }}">F. ADMINISTRASI DAN LAIN-LAIN</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">BIAYA SELAMA DI RAWAT JALAN DAN UGD</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">{{ $rp($data['fTrfRjUgd']) }}</td>
            <td></td>
        </tr>
        @foreach ($data['fOthers'] as $oth)
        <tr>
            <td class="{{ $cellLabel }} uppercase">{{ $oth->desc }}</td>
            <td class="{{ $cellQty }}">{{ $oth->qty }} (X)</td>
            <td class="{{ $cellAmt }}">{{ $rp($oth->total) }}</td>
            <td></td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" class="{{ $cellSubTot }}"></td>
            <td class="{{ $cellSubTot }}">{{ $rp($data['fTotal']) }}</td>
        </tr>

        {{-- ─── G. RETURN OBAT ─── --}}
        @if ($data['gReturObat'] > 0)
        <tr><td colspan="4" class="{{ $hdrSection }}">G. RETURN OBAT</td></tr>
        <tr>
            <td class="{{ $cellLabel }}">RETURN OBAT</td>
            <td class="{{ $cellQty }}"></td>
            <td class="{{ $cellAmt }}">( {{ $rp($data['gReturObat']) }} )</td>
            <td class="{{ $cellAmt }}">( {{ $rp($data['gReturObat']) }} )</td>
        </tr>
        @endif

        {{-- ─── FOOTER ─── --}}
        <tr><td colspan="4" class="p-0 border-t border-gray-800"></td></tr>
        <tr>
            <td colspan="3" class="py-0.5 px-1.5 text-right font-semibold">TOTAL BIAYA</td>
            <td class="py-0.5 px-1.5 text-right tabular-nums font-semibold">{{ $rp($data['subtotal']) }}</td>
        </tr>
        @if ($data['subsidi'] > 0)
        <tr>
            <td colspan="3" class="py-px px-1.5 text-right">SUBSIDI</td>
            <td class="py-px px-1.5 text-right tabular-nums">( {{ $rp($data['subsidi']) }} )</td>
        </tr>
        @endif
        @if ($data['sudahBayar'] > 0)
        <tr>
            <td colspan="3" class="py-px px-1.5 text-right">SUDAH DIBAYAR</td>
            <td class="py-px px-1.5 text-right tabular-nums">( {{ $rp($data['sudahBayar']) }} )</td>
        </tr>
        @if ($data['sisa'] > 0)
        <tr>
            <td colspan="3" class="py-0.5 px-1.5 text-right font-semibold border-t border-gray-400">SISA TAGIHAN</td>
            <td class="py-0.5 px-1.5 text-right tabular-nums font-semibold border-t border-gray-400 text-red-600">{{ $rp($data['sisa']) }}</td>
        </tr>
        @endif
        @endif
    </table>

    {{-- ══════════════════════════════════════
         TELAH DIBAYAR — TERBILANG
    ══════════════════════════════════════ --}}
    <p class="mt-2 mb-px text-[10px]">Telah dibayar secara tunai sebesar :</p>
    <p class="m-0 text-[10px] font-semibold uppercase italic">
        {{ trim(terbilang((int) $data['grandTotal'])) }} Rupiah
    </p>

    {{-- ══════════════════════════════════════
         TANDA TANGAN
    ══════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" class="text-[10px] mt-2.5">
        <tr class="align-top">
            <td width="50%" class="text-center pr-4">
                <p class="m-0">Yang Membayar</p>
                <p class="m-0 text-gray-600">ttd</p>
                <p class="mt-9 mb-0">( ......................................... )</p>
            </td>
            <td width="50%" class="text-center pl-4">
                <p class="m-0">Tulungagung, {{ $data['tglCetak'] }}</p>
                <p class="m-0 text-gray-600">Petugas Administrasi</p>
                <p class="mt-9 mb-0 font-semibold">( {{ $data['kasirName'] ?? '.........................................' }} )</p>
            </td>
        </tr>
    </table>

    <p class="mt-2.5 text-[10px] text-center text-gray-500 italic">
        TERIMAKASIH ATAS KEPERCAYAAN ANDA TERHADAP PELAYANAN KAMI
    </p>

    <p class="mt-1 text-[9px] text-gray-500 italic">
        Dicetak: {{ $data['tglCetak'] }} {{ $data['jamCetak'] }} oleh {{ $data['cetakOleh'] }}
    </p>

</x-pdf.layout-a4-with-out-background>
