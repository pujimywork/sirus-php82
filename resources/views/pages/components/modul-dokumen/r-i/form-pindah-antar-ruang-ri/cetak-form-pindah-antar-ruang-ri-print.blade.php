{{-- resources/views/pages/components/modul-dokumen/r-i/form-pindah-antar-ruang-ri/cetak-form-pindah-antar-ruang-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORM PINDAH ANTAR RUANG - RAWAT INAP">

    {{-- IDENTITAS PASIEN --}}
    <x-slot name="patientData">
        @php
            $id = $data['identitas'] ?? [];
            $alamatFull = trim(
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
            :alamat="$alamatFull" />
    </x-slot>

    @php $p = $data['pindah'] ?? []; @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── ASAL & TUJUAN ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-32">PEMINDAHAN</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-36">Pindah dari Ruangan</td>
                        <td class="w-4">:</td>
                        <td class="font-semibold">
                            {{ $p['dariRoomDesc'] ?? '-' }}
                            @if (!empty($p['dariBedNo']))
                                &mdash; Bed {{ $p['dariBedNo'] }}
                            @endif
                        </td>
                        <td class="w-32 pl-4">Tgl/Jam Pindah</td>
                        <td class="w-4">:</td>
                        <td>{{ $p['tglPindah'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Pindah ke Ruangan</td>
                        <td>:</td>
                        <td class="font-semibold">
                            {{ $p['keRoomDesc'] ?? '-' }}
                            @if (!empty($p['keBedNo']))
                                &mdash; Bed {{ $p['keBedNo'] }}
                            @endif
                        </td>
                        <td class="pl-4">Tgl/Jam Terima</td>
                        <td>:</td>
                        <td>{{ $p['tglTerima'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="align-top">Alasan Pindah</td>
                        <td class="align-top">:</td>
                        <td colspan="4">{!! nl2br(e($p['alasanPindah'] ?? '-')) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── KONDISI TTV (kirim & terima side-by-side) ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">KONDISI PASIEN</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Saat Dikirim --}}
                        <td class="w-1/2 align-top pr-2">
                            <p class="font-bold pb-0.5 mb-0.5 border-b border-gray-400">Saat Dikirim</p>
                            @php $dk = $p['kondisiKirim'] ?? []; @endphp
                            <table cellpadding="0" cellspacing="0" class="w-full">
                                <tr>
                                    <td class="w-20">TD</td>
                                    <td>: {{ $dk['sistolik'] ?? '-' }}/{{ $dk['diastolik'] ?? '-' }} mmHg</td>
                                </tr>
                                <tr>
                                    <td>Nadi</td>
                                    <td>: {{ $dk['frekuensiNadi'] ?? '-' }} x/mnt</td>
                                </tr>
                                <tr>
                                    <td>Nafas</td>
                                    <td>: {{ $dk['frekuensiNafas'] ?? '-' }} x/mnt</td>
                                </tr>
                                <tr>
                                    <td>Suhu</td>
                                    <td>: {{ $dk['suhu'] ?? '-' }} °C</td>
                                </tr>
                                <tr>
                                    <td>SpO2</td>
                                    <td>: {{ $dk['spo2'] ?? '-' }} %</td>
                                </tr>
                                <tr>
                                    <td>GCS</td>
                                    <td>: {{ $dk['gcs'] ?? '-' }}</td>
                                </tr>
                                @if (!empty($dk['keadaanPasien']))
                                    <tr>
                                        <td class="align-top">Keadaan Umum</td>
                                        <td>: {{ $dk['keadaanPasien'] }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                        {{-- Saat Diterima --}}
                        <td class="w-1/2 align-top pl-2" style="border-left: 1px solid #9ca3af;">
                            <p class="font-bold pb-0.5 mb-0.5 border-b border-gray-400">Saat Diterima</p>
                            @php $dt = $p['kondisiTerima'] ?? []; @endphp
                            <table cellpadding="0" cellspacing="0" class="w-full">
                                <tr>
                                    <td class="w-20">TD</td>
                                    <td>: {{ $dt['sistolik'] ?? '-' }}/{{ $dt['diastolik'] ?? '-' }} mmHg</td>
                                </tr>
                                <tr>
                                    <td>Nadi</td>
                                    <td>: {{ $dt['frekuensiNadi'] ?? '-' }} x/mnt</td>
                                </tr>
                                <tr>
                                    <td>Nafas</td>
                                    <td>: {{ $dt['frekuensiNafas'] ?? '-' }} x/mnt</td>
                                </tr>
                                <tr>
                                    <td>Suhu</td>
                                    <td>: {{ $dt['suhu'] ?? '-' }} °C</td>
                                </tr>
                                <tr>
                                    <td>SpO2</td>
                                    <td>: {{ $dt['spo2'] ?? '-' }} %</td>
                                </tr>
                                <tr>
                                    <td>GCS</td>
                                    <td>: {{ $dt['gcs'] ?? '-' }}</td>
                                </tr>
                                @if (!empty($dt['keadaanPasien']))
                                    <tr>
                                        <td class="align-top">Keadaan Umum</td>
                                        <td>: {{ $dt['keadaanPasien'] }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── TANDA TANGAN ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">PETUGAS</td>
            <td class="border border-black px-1.5 py-0.5 align-top text-center" colspan="3">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Petugas Pengirim --}}
                        <td class="w-1/2 text-center align-top px-2">
                            <p class="font-bold mb-1">Petugas Pengirim</p>
                            <div class="h-16">&nbsp;</div>
                            <div class="inline-block pt-0.5 border-t border-black" style="min-width:120px;">
                                <span>{{ $p['petugasPengirim'] ?? '.................................' }}</span>
                                @if (!empty($p['petugasPengirimDate']))
                                    <div class="text-[9px] text-gray-500">{{ $p['petugasPengirimDate'] }}</div>
                                @endif
                            </div>
                        </td>

                        {{-- Petugas Penerima --}}
                        <td class="w-1/2 text-center align-top px-2">
                            <p class="font-bold mb-1">Petugas Penerima</p>
                            <div class="h-16">&nbsp;</div>
                            <div class="inline-block pt-0.5 border-t border-black" style="min-width:120px;">
                                <span>{{ $p['petugasPenerima'] ?? '.................................' }}</span>
                                @if (!empty($p['petugasPenerimaDate']))
                                    <div class="text-[9px] text-gray-500">{{ $p['petugasPenerimaDate'] }}</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
