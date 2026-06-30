{{-- resources/views/pages/components/modul-dokumen/r-i/pra-anestesi-ri/cetak-pra-anestesi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN PRA ANESTESI & PRA SEDASI">

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
                <p><span class="font-bold">Kriteria:</span> {!! $val($form['kriteria'] ?? '') !!}</p>
                <p><span class="font-bold">Diagnosis Pra Anestesi:</span> {!! $val($form['diagnosisPraAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Rencana Tindakan:</span> {!! $val($form['rencanaTindakan'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <p><span class="font-bold">Anamnese:</span> {!! $val($form['anamnese'] ?? '') !!}</p>
                <p><span class="font-bold">Riwayat Anestesi:</span> {{ $yn($form['riwayatAnestesi'] ?? false) }}{{ filled($form['riwayatAnestesiKet'] ?? '') ? ' — ' . e($form['riwayatAnestesiKet']) : '' }}</p>
                <p><span class="font-bold">Riwayat Alergi:</span> {{ $yn($form['riwayatAlergi'] ?? false) }}{{ filled($form['riwayatAlergiKet'] ?? '') ? ' — ' . e($form['riwayatAlergiKet']) : '' }}</p>
                <p><span class="font-bold">Obat dikonsumsi:</span> {!! $val($form['obatDikonsumsi'] ?? '') !!}</p>
                <p><span class="font-bold">Merokok:</span> {{ $yn($form['merokok'] ?? false) }} · <span class="font-bold">Alkohol:</span> {{ $yn($form['alkohol'] ?? false) }}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Antropometri/TTV:</span>
                BB {!! $val($form['bb'] ?? '') !!} · TB {!! $val($form['tb'] ?? '') !!} · BMI {!! $val($form['bmi'] ?? '') !!} ·
                TD {!! $val($form['td'] ?? '') !!} · N {!! $val($form['nadi'] ?? '') !!} · RR {!! $val($form['rr'] ?? '') !!} ·
                S {!! $val($form['suhu'] ?? '') !!} · Nyeri {!! $val($form['skorNyeri'] ?? '') !!}
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Jalan Nafas:</span>
                Mallampati {!! $val($form['mallampati'] ?? '') !!} · Buka mulut {!! $val($form['bukaMulut'] ?? '') !!} ·
                Gerak leher {!! $val($form['gerakLeher'] ?? '') !!} · Gigi palsu {{ $yn($form['gigiPalsu'] ?? false) }} ·
                Obesitas {{ $yn($form['obesitas'] ?? false) }} · Prediksi sulit ventilasi {{ $yn($form['sulitVentilasi'] ?? false) }}
            </td>
        </tr>

        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Fungsi Sistem Organ</p>
                <p>{!! $val($form['fungsiOrgan'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Lab:</span> {!! $val($form['pemeriksaanLab'] ?? '') !!}</p>
                <p><span class="font-bold">Penunjang:</span> {!! $val($form['pemeriksaanPenunjang'] ?? '') !!}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-0.5">Kesimpulan Evaluasi Pra Anestesi</p>
                <p>
                    <span class="font-bold">Jenis Anestesi:</span> {!! $val($form['jenisAnestesi'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">PS ASA:</span> {!! $val($form['psAsa'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Induksi:</span> {!! $val($form['induksiPraAnestesi'] ?? '') !!}
                </p>
                <p>
                    <span class="font-bold">Penyulit:</span> {!! $val($form['penyulit'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Komplikasi:</span> {!! $val($form['komplikasi'] ?? '') !!} &nbsp;&bull;&nbsp;
                    <span class="font-bold">Analgesik Pasca-op:</span> {!! $val($form['obatAnalgesikPascaOp'] ?? '') !!}
                </p>
            </td>
        </tr>

        {{-- ── TTD ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Dokter Anestesi</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['ttdDate'] ?? $data['tglCetak'] ?? '-' }}</p>
                            <div class="text-center my-1">
                                @if (!empty($data['ttdPath']))
                                    <img src="{{ $data['ttdPath'] }}" class="h-16" alt="TTD Dokter Anestesi" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd'] ?? '-') }}</p>
                                @if (!empty($form['ttdCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $form['ttdCode'] }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pasien / Keluarga</p>
                            <div class="text-center my-1">
                                @if (!empty($form['signaturePasien']))
                                    <img src="{{ $form['signaturePasien'] }}" class="h-16" alt="TTD Pasien" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($data['regName'] ?? '-') }}</p>
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
