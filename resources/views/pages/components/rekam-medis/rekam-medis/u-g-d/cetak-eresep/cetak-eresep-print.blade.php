{{-- resources/views/pages/components/rekam-medis/u-g-d/cetak-eresep/cetak-eresep-ugd-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="RESEP UGD">

    @php
        $isBpjs = ($dataDaftarUGD['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarUGD['klaimId'] ?? '') === 'JM';
        $klaimClass = $isBpjs ? 'text-brand-green' : 'text-red-600';
    @endphp

    {{-- IDENTITAS PASIEN --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Resep / Tanggal</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold text-gray-900">
                    {{ $dataDaftarUGD['rjNo'] ?? '-' }} / {{ $dataDaftarUGD['rjDate'] ?? '-' }}
                    @if ($dataDaftarUGD['statusPRB']['penanggungJawab']['statusPRB'] ?? false)
                        <span style="color:#dc2626"> PRB</span>
                    @endif
                    @if (($dataDaftarUGD['statusResep']['status'] ?? null) === 'DITINGGAL')
                        <span style="color:#dc2626"> Ditinggal</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold text-gray-900">
                    {{ strtoupper($dataPasien['pasien']['regName'] ?? '-') }}
                    / {{ $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}
                    / {{ $dataPasien['pasien']['regNo'] ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl Lahir / Usia</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] text-gray-900">
                    {{ $dataPasien['pasien']['tglLahir'] ?? '-' }}
                    / {{ $dataPasien['pasien']['thn'] ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px] text-gray-900">
                    {{ $dataPasien['pasien']['identitas']['alamat'] ?? '-' }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Klaim / Unit</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">
                    <span class="font-bold {{ $klaimClass }}">{{ $klaim->klaim_desc ?? 'Asuransi Lain' }}</span>
                    / {{ $dataDaftarUGD['poliDesc'] ?? 'UGD' }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">NIK / Id BPJS</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] text-gray-900">
                    {{ $dataPasien['pasien']['identitas']['nik'] ?? '-' }}
                    / {{ $dataPasien['pasien']['identitas']['idbpjs'] ?? '-' }}
                </td>
            </tr>
            @if ($isBpjs)
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No SEP</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] font-bold tracking-wide text-gray-900">
                        {{ $dataDaftarUGD['sep']['noSep'] ?? '-' }}
                    </td>
                </tr>
            @endif
        </table>
    </x-slot>

    {{-- KONTEN UTAMA: Obat (kiri) + Telaah (kanan) --}}
    <table class="w-full mb-4" cellpadding="0" cellspacing="0">
        <tr>

            {{-- KIRI: Daftar Obat --}}
            <td class="w-1/2 align-top" style="padding-right:12px;">

                {{-- Non Racikan --}}
                @isset($dataDaftarUGD['eresep'])
                    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
                        <thead>
                            <tr class="bg-gray-50 border-t border-b border-gray-400">
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-left w-8">Jenis</th>
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-left">Nama Obat</th>
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-center w-12">Jml</th>
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-center w-24">Signa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dataDaftarUGD['eresep'] as $eresep)
                                <tr class="border-b border-gray-100">
                                    <td class="py-1 text-[10px] text-gray-700 text-center uppercase">R/</td>
                                    <td class="py-1 text-[10px] text-gray-900 uppercase">
                                        {{ $eresep['productName'] ?? '-' }}</td>
                                    <td class="py-1 text-[10px] text-gray-900 text-center uppercase">No.
                                        {{ $eresep['qty'] ?? '-' }}</td>
                                    <td class="py-1 text-[10px] text-gray-900 text-center uppercase">
                                        S {{ $eresep['signaX'] ?? '-' }}dd{{ $eresep['signaHari'] ?? '-' }}
                                        @if (!empty($eresep['catatanKhusus']))
                                            ({{ $eresep['catatanKhusus'] }})
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endisset

                {{-- Racikan --}}
                @isset($dataDaftarUGD['eresepRacikan'])
                    @php $prevRacikan = null; @endphp
                    <table class="w-full" cellpadding="0" cellspacing="0">
                        <thead>
                            <tr class="bg-gray-50 border-t border-b border-gray-400">
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-left w-12">Racikan</th>
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-left">Nama Obat</th>
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-center w-16">Jml</th>
                                <th class="py-1 text-[10px] font-semibold text-gray-900 text-center w-24">Signa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dataDaftarUGD['eresepRacikan'] as $eresep)
                                @isset($eresep['jenisKeterangan'])
                                    <tr
                                        class="{{ $prevRacikan !== ($eresep['noRacikan'] ?? null) ? 'border-t-2 border-gray-400' : 'border-t border-gray-100' }}">
                                        <td class="py-1 text-[10px] text-gray-700 text-center uppercase">
                                            {{ $eresep['noRacikan'] ?? '-' }}/</td>
                                        <td class="py-1 text-[10px] text-gray-900 uppercase">
                                            {{ $eresep['productName'] ?? '-' }}
                                            @if (!empty($eresep['dosis']))
                                                &mdash; {{ $eresep['dosis'] }}
                                            @endif
                                        </td>
                                        <td class="py-1 text-[10px] text-gray-900 text-center uppercase">
                                            @if (!empty($eresep['qty']))
                                                Jml {{ $eresep['qty'] }}
                                            @endif
                                        </td>
                                        <td class="py-1 text-[10px] text-gray-900 text-center uppercase">
                                            @if (!empty($eresep['qty']))
                                                ({{ $eresep['catatan'] ?? '' }})
                                                S {{ $eresep['catatanKhusus'] ?? '' }}
                                            @endif
                                        </td>
                                    </tr>
                                    @php $prevRacikan = $eresep['noRacikan']; @endphp
                                @endisset
                            @endforeach
                        </tbody>
                    </table>
                @endisset

            </td>

            {{-- KANAN: Telaah Resep + Telaah Obat --}}
            <td class="w-1/2 align-top" style="padding-left:12px; border-left:1px solid #d1d5db;">

                {{-- Telaah Resep --}}
                <table class="w-full mb-3" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr class="bg-gray-50 border-t border-b border-gray-400">
                            <th class="py-1 text-[10px] font-semibold text-gray-900 text-left">Pengkajian Resep</th>
                            <th class="py-1 text-[10px] font-semibold text-gray-900 text-center w-8">*</th>
                            <th class="py-1 text-[10px] font-semibold text-gray-900 text-left w-20">Ket.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $telaahResepFields = [
                                'kejelasanTulisanResep' => 'Kejelasan Tulisan Resep',
                                'tepatObat' => 'Tepat Obat',
                                'tepatDosis' => 'Tepat Dosis',
                                'tepatRute' => 'Tepat Rute',
                                'tepatWaktu' => 'Tepat Waktu',
                                'duplikasi' => 'Duplikasi',
                                'alergi' => 'Alergi',
                                'interaksiObat' => 'Interaksi Obat',
                                'bbPasienAnak' => 'Berat Badan Pasien Anak',
                                'kontraIndikasiLain' => 'Kontra Indikasi Lain',
                            ];
                        @endphp
                        @foreach ($telaahResepFields as $key => $label)
                            <tr class="border-b border-gray-100">
                                <td class="py-0.5 text-[10px] text-gray-700">{{ $label }}</td>
                                <td class="py-0.5 text-[10px] text-gray-900 text-center">
                                    {{ $dataDaftarUGD['telaahResep'][$key][$key] ?? '-' }}</td>
                                <td class="py-0.5 text-[10px] text-gray-700">
                                    {{ $dataDaftarUGD['telaahResep'][$key]['desc'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Telaah Obat --}}
                <table class="w-full" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr class="bg-gray-50 border-t border-b border-gray-400">
                            <th class="py-1 text-[10px] font-semibold text-gray-900 text-left">Pengkajian Obat</th>
                            <th class="py-1 text-[10px] font-semibold text-gray-900 text-center w-8">*</th>
                            <th class="py-1 text-[10px] font-semibold text-gray-900 text-left w-20">Ket.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $telaahObatFields = [
                                'obatdgnResep' => 'Obat dgn Resep',
                                'jmlDosisdgnResep' => 'Jml / Dosis dgn Resep',
                                'rutedgnResep' => 'Rute dgn Resep',
                                'waktuFrekPemberiandgnResep' => 'Waktu dan Frekuensi Pemberian',
                            ];
                        @endphp
                        @foreach ($telaahObatFields as $key => $label)
                            <tr class="border-b border-gray-100">
                                <td class="py-0.5 text-[10px] text-gray-700">{{ $label }}</td>
                                <td class="py-0.5 text-[10px] text-gray-900 text-center">
                                    {{ $dataDaftarUGD['telaahObat'][$key][$key] ?? '-' }}</td>
                                <td class="py-0.5 text-[10px] text-gray-700">
                                    {{ $dataDaftarUGD['telaahObat'][$key]['desc'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </td>
        </tr>
    </table>

    <hr class="mb-4 border-gray-300">

    {{-- TANDA TANGAN --}}
    <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
        <tr>

            {{-- Dokter Pemeriksa --}}
            <td class="w-1/4 text-center align-top">
                <p class="text-gray-700">Tulungagung,
                    {{ $dataDaftarUGD['rjDate'] ?? \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
                <p class="mb-1 text-gray-700">Dokter Pemeriksa</p>
                @php
                    $drId = $dataDaftarUGD['drId'] ?? null;
                    $drUser = $drId ? \App\Models\User::where('myuser_code', $drId)->first() : null;
                @endphp
                @if ($drUser && $drUser->myuser_ttd_image)
                    <img class="h-16 mx-auto" src="{{ 'storage/' . $drUser->myuser_ttd_image }}" alt="">
                @else
                    <div style="height:64px;"></div>
                @endif
                <div class="inline-block pt-1 border-t border-gray-400" style="min-width:120px;">
                    <span class="text-gray-900">
                        {{ $dataDaftarUGD['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? ($dokter->dr_name ?? 'Dokter Pemeriksa') }}
                    </span>
                </div>
                @if ($drUser && $drUser->myuser_sip)
                    <div class="text-gray-500">SIP: {{ $drUser->myuser_sip }}</div>
                @endif
            </td>

            {{-- Pengkajian Resep --}}
            <td class="w-1/4 text-center align-top">
                <p class="mb-1 text-gray-700">Pengkajian Resep</p>
                @php
                    $trCode = $dataDaftarUGD['telaahResep']['penanggungJawab']['userLogCode'] ?? null;
                    $trUser = $trCode ? \App\Models\User::where('myuser_code', $trCode)->first() : null;
                @endphp
                @if ($trUser && $trUser->myuser_ttd_image)
                    <img class="h-16 mx-auto" src="{{ 'storage/' . $trUser->myuser_ttd_image }}" alt="">
                @else
                    <div style="height:64px;"></div>
                @endif
                <div class="inline-block pt-1 border-t border-gray-400" style="min-width:120px;">
                    <span
                        class="text-gray-900">{{ $dataDaftarUGD['telaahResep']['penanggungJawab']['userLog'] ?? 'Pengkajian Resep' }}</span>
                </div>
            </td>

            {{-- Pengkajian Obat --}}
            <td class="w-1/4 text-center align-top">
                <p class="mb-1 text-gray-700">Pengkajian Obat</p>
                @php
                    $toCode = $dataDaftarUGD['telaahObat']['penanggungJawab']['userLogCode'] ?? null;
                    $toUser = $toCode ? \App\Models\User::where('myuser_code', $toCode)->first() : null;
                @endphp
                @if ($toUser && $toUser->myuser_ttd_image)
                    <img class="h-16 mx-auto" src="{{ 'storage/' . $toUser->myuser_ttd_image }}" alt="">
                @else
                    <div style="height:64px;"></div>
                @endif
                <div class="inline-block pt-1 border-t border-gray-400" style="min-width:120px;">
                    <span
                        class="text-gray-900">{{ $dataDaftarUGD['telaahObat']['penanggungJawab']['userLog'] ?? 'Pengkajian Obat' }}</span>
                </div>
            </td>

            {{-- Tanda Tangan Pasien --}}
            <td class="w-1/4 text-center align-top">
                <p class="mb-1 text-gray-700">Tanda Tangan Pasien</p>
                <div style="height:64px;"></div>
                <div class="inline-block pt-1 border-t border-gray-400" style="min-width:120px;">
                    <span class="text-gray-900">{{ strtoupper($dataPasien['pasien']['regName'] ?? '') }}</span>
                </div>
            </td>

        </tr>
    </table>

    {{-- FOOTER --}}
    <div class="mt-4 pt-2 border-t border-gray-300 text-[9px] text-gray-500">
        No. UGD: {{ $dataDaftarUGD['rjNo'] ?? '-' }}
    </div>

</x-pdf.layout-a4-with-out-background>
