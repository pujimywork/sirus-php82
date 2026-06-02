{{-- resources/views/pages/components/modul-dokumen/u-g-d/form-trf-ugd-ri/cetak-form-trf-ugd-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORM TRANSFER PASIEN UGD - RAWAT INAP">

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

    @php $trf = $data['trfUgd'] ?? []; @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── RINGKASAN KLINIS ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-32">KELUHAN UTAMA</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                {!! nl2br(e($trf['keluhanUtama'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">TEMUAN SIGNIFIKAN</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                {!! nl2br(e($trf['temuanSignifikan'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">ALERGI</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                {!! nl2br(e($trf['alergi'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">DIAGNOSIS</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                {!! nl2br(e($trf['diagnosisFreeText'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">TERAPI UGD</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                @if (is_array($trf['terapiUgd'] ?? null))
                    {!! nl2br(e(implode("\n", $trf['terapiUgd']))) !!}
                @else
                    {!! nl2br(e($trf['terapiUgd'] ?? '-')) !!}
                @endif
            </td>
        </tr>

        {{-- ── LEVELING DOKTER ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">LEVELING DOKTER</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                @if (!empty($trf['levelingDokter']))
                    <table class="w-full text-[10px] border-collapse">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Nama Dokter</th>
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Poli</th>
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Tgl Entry</th>
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trf['levelingDokter'] as $dok)
                                <tr>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $dok['drName'] ?? '-' }}</td>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $dok['poliDesc'] ?? '-' }}</td>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $dok['tglEntry'] ?? '-' }}</td>
                                    <td class="border border-gray-400 px-1 py-0.5 font-semibold">
                                        {{ $dok['levelDokter'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    -
                @endif
            </td>
        </tr>

        {{-- ── DATA PEMINDAHAN ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">PEMINDAHAN</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-36">Pindah dari Ruangan</td>
                        <td class="w-4">:</td>
                        <td class="font-semibold">{{ $trf['pindahDariRuangan'] ?? 'UGD' }}</td>
                        <td class="w-36 pl-4">Tgl/Jam Pindah</td>
                        <td class="w-4">:</td>
                        <td>{{ $trf['tglPindah'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Pindah ke Ruangan</td>
                        <td>:</td>
                        <td class="font-semibold">
                            {{ $trf['pindahKeRuangan'] ?? '-' }}
                            @if (!empty($trf['pindahKeBedNo']))
                                — Bed {{ $trf['pindahKeBedNo'] }}
                            @endif
                        </td>
                        <td class="pl-4">Metode Pemindahan</td>
                        <td>:</td>
                        <td>{{ $trf['metodePemindahanPasien'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="align-top">Alasan Pindah</td>
                        <td class="align-top">:</td>
                        <td colspan="4">{!! nl2br(e($trf['alasanPindah'] ?? '-')) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── KONDISI KLINIS ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">KONDISI KLINIS</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                @php
                    $derajat = (int) ($trf['kondisiKlinis'] ?? 0);
                    $derajatLabel = [
                        0 => 'Stabil, tanpa keluhan berat',
                        1 => 'Keluhan ringan-sedang, perlu observasi',
                        2 => 'Kondisi sedang, risiko memburuk, perlu tindakan',
                        3 => 'Gawat Darurat, mengancam jiwa',
                    ];
                @endphp
                <span class="font-semibold">Derajat {{ $derajat }}</span> &mdash;
                {{ $derajatLabel[$derajat] ?? '-' }}
                @if (!empty($trf['fasilitas']))
                    <br><span class="font-semibold">Fasilitas:</span> {{ $trf['fasilitas'] }}
                @endif
                @if (!empty($trf['fasilitasPendukung']))
                    <br><span class="font-semibold">Fasilitas Pendukung:</span> {{ $trf['fasilitasPendukung'] }}
                @endif
            </td>
        </tr>

        {{-- ── KONDISI TTV (nested table side-by-side dalam 1 td colspan=3) ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">KONDISI TTV</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Saat Dikirim --}}
                        <td class="w-1/2 align-top pr-2">
                            <p class="font-bold pb-0.5 mb-0.5 border-b border-gray-400">Saat Dikirim</p>
                            @php $dk = $trf['kondisiSaatDikirim'] ?? []; @endphp
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
                                    <td>GDA</td>
                                    <td>: {{ $dk['gda'] ?? '-' }} mg/dL</td>
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
                            @php $dt = $trf['kondisiSaatDiterima'] ?? []; @endphp
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
                                    <td>GDA</td>
                                    <td>: {{ $dt['gda'] ?? '-' }} mg/dL</td>
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

        {{-- ── ALAT TERPASANG ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">ALAT TERPASANG</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                @if (!empty($trf['alatYangTerpasang']))
                    <table class="w-full text-[10px] border-collapse">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Jenis</th>
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Lokasi</th>
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Ukuran</th>
                                <th class="border border-gray-400 px-1 py-0.5 text-left">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trf['alatYangTerpasang'] as $alat)
                                <tr>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $alat['jenis'] ?? '-' }}</td>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $alat['lokasi'] ?? '-' }}</td>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $alat['ukuran'] ?? '-' }}</td>
                                    <td class="border border-gray-400 px-1 py-0.5">{{ $alat['keterangan'] ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    -
                @endif
            </td>
        </tr>

        {{-- ── RENCANA PERAWATAN ── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">RENCANA PERAWATAN</td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="3">
                @php $rp = $trf['rencanaPerawatan'] ?? []; @endphp
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-32 align-top">Observasi</td>
                        <td class="w-4 align-top">:</td>
                        <td class="align-top">{{ $rp['observasi'] ?? '-' }}</td>
                        <td class="w-32 pl-3 align-top">Pembatasan Cairan</td>
                        <td class="w-4 align-top">:</td>
                        <td class="align-top">{{ $rp['pembatasanCairan'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="align-top">Balance Cairan</td>
                        <td class="align-top">:</td>
                        <td class="align-top">{{ $rp['balanceCairan'] ?? '-' }}</td>
                        <td class="pl-3 align-top">Diet</td>
                        <td class="align-top">:</td>
                        <td class="align-top">{{ $rp['diet'] ?? '-' }}</td>
                    </tr>
                    @if (!empty($rp['lainLain']))
                        <tr>
                            <td class="align-top">Lain-lain</td>
                            <td class="align-top">:</td>
                            <td class="align-top" colspan="4">{{ $rp['lainLain'] }}</td>
                        </tr>
                    @endif
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
                        <td class="w-1/3 text-center align-top px-2">
                            <p class="font-bold mb-1">Petugas Pengirim</p>
                            <div class="h-16">&nbsp;</div>
                            <div class="inline-block pt-0.5 border-t border-black" style="min-width:120px;">
                                <span>{{ $trf['petugasPengirim'] ?? '.................................' }}</span>
                                @if (!empty($trf['petugasPengirimDate']))
                                    <div class="text-[9px] text-gray-500">{{ $trf['petugasPengirimDate'] }}</div>
                                @endif
                            </div>
                        </td>

                        {{-- Dokter Utama --}}
                        <td class="w-1/3 text-center align-top px-2">
                            <p class="font-bold mb-1">Dokter Penanggung Jawab</p>
                            <div class="h-16">&nbsp;</div>
                            <div class="inline-block pt-0.5 border-t border-black" style="min-width:120px;">
                                <span>{{ $data['namaDokter'] ?? '.................................' }}</span>
                                @if (!empty($data['strDokter']))
                                    <div class="text-[9px] text-gray-500">STR: {{ $data['strDokter'] }}</div>
                                @endif
                            </div>
                        </td>

                        {{-- Petugas Penerima --}}
                        <td class="w-1/3 text-center align-top px-2">
                            <p class="font-bold mb-1">Petugas Penerima</p>
                            <div class="h-16">&nbsp;</div>
                            <div class="inline-block pt-0.5 border-t border-black" style="min-width:120px;">
                                <span>{{ $trf['petugasPenerima'] ?? '.................................' }}</span>
                                @if (!empty($trf['petugasPenerimaDate']))
                                    <div class="text-[9px] text-gray-500">{{ $trf['petugasPenerimaDate'] }}</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
