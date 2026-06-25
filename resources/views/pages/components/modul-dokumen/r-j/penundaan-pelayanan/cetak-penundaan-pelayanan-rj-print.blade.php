{{-- resources/views/pages/components/modul-dokumen/r-j/penundaan-pelayanan/cetak-penundaan-pelayanan-rj-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PEMBERITAHUAN PENUNDAAN / KELAMBATAN PELAYANAN">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        @php
            $id = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($id['alamat'] ?? '-') .
                    (!empty($id['rt']) ? ' RT ' . $id['rt'] : '') .
                    (!empty($id['rw']) ? '/RW ' . $id['rw'] : '') .
                    (!empty($id['desaName']) ? ', ' . $id['desaName'] : '') .
                    (!empty($id['kecamatanName']) ? ', ' . $id['kecamatanName'] : ''),
            );
        @endphp
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien" />
    </x-slot>

    @php
        $form = $data['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        $responOptions = ['Menerima penundaan', 'Memilih alternatif', 'Menolak'];
        $responDipilih = $form['respon'] ?? '';

        $hubunganMap = [
            'pasien' => 'Pasien Sendiri',
            'suami' => 'Suami',
            'istri' => 'Istri',
            'ayah' => 'Ayah',
            'ibu' => 'Ibu',
            'anak' => 'Anak',
            'saudara' => 'Saudara',
            'wali_hukum' => 'Wali Hukum',
            'lainnya' => 'Lainnya',
        ];
        $hubunganText = $hubunganMap[$form['hubunganPasien'] ?? ''] ?? '-';

        // Helper kotak centang aman-font (DejaVu): centang &#10003; di kotak border.
        $kotak = fn(bool $aktif) =>
            '<span style="display:inline-block;width:9px;height:9px;border:1px solid #000;text-align:center;line-height:9px;font-size:8px;">' .
            ($aktif ? '&#10003;' : '&nbsp;') .
            '</span>';
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── TANGGAL/JAM PEMBERITAHUAN ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3"><strong>Tanggal / Jam Pemberitahuan</strong></td>
            <td class="border border-black px-2 py-1.5">{{ $form['tglPemberitahuan'] ?? '-' }}</td>
        </tr>

        {{-- ── JENIS PELAYANAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-1">PELAYANAN YANG DITUNDA / TERLAMBAT</p>
                <p class="leading-relaxed">{{ $form['jenis'] ?: '-' }}</p>
            </td>
        </tr>

        {{-- ── ALASAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-1">Alasan Penundaan / Kelambatan</p>
                <p class="leading-relaxed">{{ $form['alasan'] ?? '-' }}</p>
            </td>
        </tr>

        {{-- ── JADWAL ULANG ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3"><strong>Jadwal Ulang</strong></td>
            <td class="border border-black px-2 py-1.5">{{ $form['jadwalUlang'] ?: '-' }}</td>
        </tr>

        {{-- ── ALTERNATIF ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-1">Alternatif yang Ditawarkan (sesuai kebutuhan klinis)</p>
                <p class="leading-relaxed">{{ $form['alternatif'] ?: '-' }}</p>
            </td>
        </tr>

        {{-- ── RESPON ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-1">Respon Pasien / Keluarga</p>
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        @foreach ($responOptions as $opt)
                            <td class="w-1/3 py-0.5">
                                {!! $kotak($responDipilih === $opt) !!}
                                <span class="ml-1">{{ $opt }}</span>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── CATATAN KEBIJAKAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[9px] text-gray-600 italic">
                Tidak berlaku untuk keterlambatan staf medis di RJ / IGD penuh. Onkologi &amp; transplantasi mengikuti
                norma nasional. Dicatat di rekam medis (Lihat KE 2).
            </td>
        </tr>

        {{-- ── TANDA TANGAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Pemberi Informasi (DPJP/PPA) --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pemberi Informasi (DPJP / PPA)</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['pemberiInfoDate'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdPemberiPath']))
                                    <img src="{{ $data['ttdPemberiPath'] }}" class="h-16"
                                        alt="Tanda Tangan Pemberi Informasi" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['pemberiInfo'] ?? '-') }}</p>
                                @if (!empty($form['pemberiInfoCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $form['pemberiInfoCode'] }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Pemisah garis vertikal --}}
                        <td style="border-left: 1px solid #d1d5db; width: 1px;"></td>

                        {{-- Pasien / Keluarga --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pasien / Keluarga</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $data['tglCetak'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($form['signature']))
                                    <img src="{{ $form['signature'] }}" class="h-16" alt="Tanda Tangan Pasien" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['namaPenanda'] ?? ($data['regName'] ?? '-')) }}</p>
                                <p class="text-[9px] text-gray-500">{{ $hubunganText }}</p>
                                @if (!empty($form['signatureDate']))
                                    <p class="text-[9px] text-gray-500">{{ $form['signatureDate'] }}</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
