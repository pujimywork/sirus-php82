{{-- resources/views/pages/components/modul-dokumen/r-i/instruksi-pasca-bedah-ri/cetak-instruksi-pasca-bedah-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="INSTRUKSI PASCA BEDAH">

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

        $val = fn($nilai) => filled($nilai) ? e($nilai) : '-';

        $monitorList = [];
        if (!empty($form['monitorTensi'])) $monitorList[] = 'Tensi';
        if (!empty($form['monitorNadi'])) $monitorList[] = 'Nadi';
        if (!empty($form['monitorNafas'])) $monitorList[] = 'Nafas';
        $monitorText = $monitorList ? implode(', ', $monitorList) : '-';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Tanggal / Jam:</span> {!! $val($form['tanggal'] ?? '') !!}
            </td>
        </tr>

        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p class="font-bold mb-0.5">Bila Kesakitan</p>
                <p class="leading-relaxed">{!! $val($form['bilaKesakitan'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p class="font-bold mb-0.5">Bila Mual / Muntah</p>
                <p class="leading-relaxed">{!! $val($form['bilaMualMuntah'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Antibiotik</p>
                <p class="leading-relaxed">{!! $val($form['antibiotik'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Obat-obatan Lain</p>
                <p class="leading-relaxed">{!! $val($form['obatLain'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Minum:</span> {!! $val($form['minum'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Infus:</span> {!! $val($form['infus'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Monitor:</span> {{ $monitorText }}
                &nbsp;&bull;&nbsp; <span class="font-bold">Setiap:</span> {!! $val($form['monitorSetiap'] ?? '') !!}
                &nbsp;&bull;&nbsp; <span class="font-bold">Selama:</span> {!! $val($form['monitorSelama'] ?? '') !!}
            </td>
        </tr>

        @if (filled($form['lainLain'] ?? ''))
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                    <p class="font-bold mb-0.5">Lain-lain</p>
                    <p class="leading-relaxed" style="white-space: pre-line;">{!! e($form['lainLain']) !!}</p>
                </td>
            </tr>
        @endif

        {{-- ── TANDA TANGAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-1/2"></td>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Ahli Anestesiologi</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdDate'] ?? $data['tglCetak'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdPath']))
                                    <img src="{{ $data['ttdPath'] }}" class="h-16" alt="Tanda Tangan" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd'] ?? '-') }}</p>
                                @if (!empty($form['ttdCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdCode'] }}</p>
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
