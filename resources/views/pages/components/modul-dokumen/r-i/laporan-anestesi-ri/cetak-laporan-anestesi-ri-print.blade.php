{{-- resources/views/pages/components/modul-dokumen/r-i/laporan-anestesi-ri/cetak-laporan-anestesi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LAPORAN ANESTESI">

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
            :rm="$data['regNo'] ?? null" :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null" :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null" :alamat="$alamatPasien" />
    </x-slot>

    @php
        $form = $data['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';
        $val = fn($nilai) => filled($nilai) ? e($nilai) : '-';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Tanggal:</span> {!! $val($form['tanggal'] ?? '') !!}</p>
                <p><span class="font-bold">Jenis Pembedahan:</span> {!! $val($form['jenisPembedahan'] ?? '') !!}</p>
                <p><span class="font-bold">Diagnosa Pra Bedah:</span> {!! $val($form['diagnosaPraBedah'] ?? '') !!}</p>
                <p><span class="font-bold">Diagnosa Pasca Bedah:</span> {!! $val($form['diagnosaPascaBedah'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Jenis Anestesi:</span> {!! $val($form['jenisAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Lama Operasi:</span> {!! $val($form['lamaOperasi'] ?? '') !!} · <span class="font-bold">Lama Anestesi:</span> {!! $val($form['lamaAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">ASA:</span> {!! $val($form['asa'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Keadaan Pra Bedah:</span>
                TB {!! $val($form['tb'] ?? '') !!} · BB {!! $val($form['bb'] ?? '') !!} · Gol {!! $val($form['golDarah'] ?? '') !!} ·
                Tensi {!! $val($form['tensi'] ?? '') !!} · N {!! $val($form['nadi'] ?? '') !!} · S {!! $val($form['suhu'] ?? '') !!} · Hb {!! $val($form['hb'] ?? '') !!}
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Teknik Anestesi</p>
                <p>Jalan Nafas: {!! $val($form['jalanNafas'] ?? '') !!} · Pernafasan: {!! $val($form['pernafasan'] ?? '') !!} · Posisi: {!! $val($form['posisi'] ?? '') !!} · Infus: {!! $val($form['infus'] ?? '') !!}</p>
                <p style="white-space: pre-line;">{!! $val($form['teknikAnestesi'] ?? '') !!}</p>
                <p>Teknik Khusus: {!! $val($form['teknikKhusus'] ?? '') !!} · Penyulit selama pembedahan: {!! $val($form['penyulitSelamaPembedahan'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Monitoring Sistem Organ</p>
                <p>Saraf/GCS: {!! $val($form['saraf'] ?? '') !!} · Sirkulasi: {!! $val($form['sirkulasi'] ?? '') !!} · Perfusi: {!! $val($form['perfusi'] ?? '') !!} · GI: {!! $val($form['gastrointestinal'] ?? '') !!}</p>
                <p>Ginjal: {!! $val($form['ginjal'] ?? '') !!} · Metabolik: {!! $val($form['metabolik'] ?? '') !!} · Hati: {!! $val($form['hati'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p>
                    <span class="font-bold">Medikasi Pra Bedah:</span> {!! $val($form['medikasiPraBedah'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Masalah Anestesi:</span> {!! $val($form['masalahAnestesi'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Masalah Bedah:</span> {!! $val($form['masalahBedah'] ?? '') !!}
                </p>
                <p>
                    <span class="font-bold">Keadaan Akhir Pembedahan:</span> {!! $val($form['keadaanAkhirPembedahan'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Penyulit Pasca Bedah:</span> {!! $val($form['penyulitPascaBedah'] ?? '') !!}
                </p>
            </td>
        </tr>

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
                                    <img src="{{ $data['ttdPath'] }}" class="h-16" alt="TTD" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd'] ?? '-') }}</p>
                                @if (!empty($form['ttdCode'])) <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdCode'] }}</p> @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }} &nbsp;&bull;&nbsp; No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp; {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
