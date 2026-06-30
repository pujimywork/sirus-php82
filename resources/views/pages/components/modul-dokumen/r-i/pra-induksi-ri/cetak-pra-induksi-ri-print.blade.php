{{-- resources/views/pages/components/modul-dokumen/r-i/pra-induksi-ri/cetak-pra-induksi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="ASESMEN PRA INDUKSI">

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
        $val = fn($v) => filled($v) ? e($v) : '-';
        $yn = fn($v) => !empty($v) ? 'Ya' : 'Tidak';
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Tanggal:</span> {!! $val($form['tanggal'] ?? '') !!}</p>
                <p><span class="font-bold">Tempat:</span> {!! $val($form['tempat'] ?? '') !!}</p>
                <p><span class="font-bold">Diagnosis Pra Anestesi:</span> {!! $val($form['diagnosisPraAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Rencana Tindakan:</span> {!! $val($form['rencanaTindakan'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Amnanese:</span> {!! $val($form['amnanese'] ?? '') !!}</p>
                <p><span class="font-bold">Riwayat Anestesi:</span> {{ $yn($form['riwayatAnestesi'] ?? false) }}{{ filled($form['riwayatAnestesiJenis'] ?? '') ? ' — ' . e($form['riwayatAnestesiJenis']) : '' }}</p>
                <p><span class="font-bold">Riwayat Alergi:</span> {{ $yn($form['riwayatAlergi'] ?? false) }}{{ filled($form['riwayatAlergiJenis'] ?? '') ? ' — ' . e($form['riwayatAlergiJenis']) : '' }}</p>
                <p><span class="font-bold">Merokok:</span> {{ $yn($form['merokok'] ?? false) }} · <span class="font-bold">Alkohol:</span> {{ $yn($form['alkohol'] ?? false) }}</p>
                <p><span class="font-bold">Persiapan Transfusi:</span> {{ $yn($form['persiapanTransfusi'] ?? false) }}{{ filled($form['transfusiJumlah'] ?? '') ? ' (' . e($form['transfusiJumlah']) . ')' : '' }}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">TTV:</span> TD {!! $val($form['td'] ?? '') !!} · N {!! $val($form['nadi'] ?? '') !!} · RR {!! $val($form['rr'] ?? '') !!} · S {!! $val($form['suhu'] ?? '') !!}
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Pemeriksaan Fisik & Penunjang</p>
                <p>Pernafasan: {!! $val($form['pemFisikPernafasan'] ?? '') !!} · Tulang Belakang: {!! $val($form['pemFisikTulangBelakang'] ?? '') !!} · Jantung/Paru: {!! $val($form['pemFisikJantungParu'] ?? '') !!} · Abdomen: {!! $val($form['pemFisikAbdomen'] ?? '') !!}</p>
                <p>Lab: {!! $val($form['penunjangLab'] ?? '') !!} · EKG: {!! $val($form['penunjangEkg'] ?? '') !!} · Thorak: {!! $val($form['penunjangThorak'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Rencana Anestesi</p>
                <p>
                    <span class="font-bold">ASA:</span> {!! $val($form['klasifikasiAsa'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Rencana Anestesi:</span> {!! $val($form['rencanaAnestesi'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Pemulihan:</span> {!! $val($form['pemulihanPasca'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Manajemen Nyeri:</span> {!! $val($form['manajemenNyeri'] ?? '') !!}
                </p>
                <p><span class="font-bold">Obat Pre-Medikasi:</span> {!! $val($form['obatPreMedikasi'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-1/2"></td>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Dokter Anestesi</p>
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
