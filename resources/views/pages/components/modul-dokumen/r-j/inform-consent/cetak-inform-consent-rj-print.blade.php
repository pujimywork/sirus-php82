{{-- resources/views/pages/components/modul-dokumen/r-j/inform-consent/cetak-inform-consent-rj-print.blade.php --}}

@php
    $isSetuju = ($data['consent']['agreement'] ?? '1') === '1';
    $cetakTitle = $isSetuju
        ? 'FORMULIR PERSETUJUAN TINDAKAN MEDIS (INFORM CONSENT)'
        : 'FORMULIR PENOLAKAN TINDAKAN MEDIS';
@endphp

<x-pdf.layout-a4-with-out-background :title="$cetakTitle">

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
        $consent = $data['consent'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';
        $rsCity = $identitasRs->int_city ?? 'Tulungagung';
        $isSetuju = ($consent['agreement'] ?? '1') === '1';
        $agreementText = $isSetuju ? 'MENYETUJUI' : 'MENOLAK';
        $agreementClass = $isSetuju ? 'font-bold' : 'font-bold text-red-700';

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
        $hubunganKey = $consent['waliHubungan'] ?? '';
        $hubunganText = $hubunganMap[$hubunganKey] ?? '-';
        $isPasienSendiri = $hubunganKey === 'pasien';
        $waliRoleText = $isPasienSendiri ? 'Pasien sendiri' : 'Wali — ' . $hubunganText;
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- PPA (Profesional Pemberi Asuhan) — sebelumnya "Dokter Tindakan" --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-36">Profesional Pemberi Asuhan</td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                {{ strtoupper($data['dokterTindakanName'] ?? '-') }}
                @if (!empty($consent['petugasPemeriksaCode']))
                    <span class="text-gray-500">(ID: {{ $consent['petugasPemeriksaCode'] }})</span>
                @endif
            </td>
        </tr>

        @if (!empty($consent['diagnosa']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top w-36">DIAGNOSA</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {{ strtoupper($consent['diagnosa']) }}
                </td>
            </tr>
        @endif

        @if (!empty($consent['komplikasi']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top w-36">KOMPLIKASI</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {{ strtoupper($consent['komplikasi']) }}
                </td>
            </tr>
        @endif

        {{-- Nama Tindakan --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-36">NAMA TINDAKAN / TERAPI</td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                {{ strtoupper($consent['tindakan'] ?? '-') }}
            </td>
        </tr>

        {{-- Tujuan / Penjelasan --}}
        @if (!empty($consent['tujuan']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">TUJUAN TINDAKAN / TERAPI</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {!! nl2br(e($consent['tujuan'])) !!}
                </td>
            </tr>
        @endif

        {{-- Risiko --}}
        @if (!empty($consent['resiko']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">RISIKO TINDAKAN / TERAPI</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {!! nl2br(e($consent['resiko'])) !!}
                </td>
            </tr>
        @endif

        {{-- Alternatif --}}
        @if (!empty($consent['alternatif']))
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">ALTERNATIF TINDAKAN / TERAPI</td>
                <td class="border border-black px-1.5 py-0.5 align-top">
                    {!! nl2br(e($consent['alternatif'])) !!}
                </td>
            </tr>
        @endif

        {{-- Pernyataan --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-2 text-[10px] leading-relaxed">
                <p>
                    Saya yang bertanda tangan di bawah ini,
                    <strong>{{ strtoupper($consent['wali'] ?? '-') }}</strong>
                    @if ($isPasienSendiri)
                        selaku <strong>pasien sendiri</strong>,
                    @else
                        selaku <strong>{{ $hubunganText }}</strong> dari pihak pasien tersebut di atas,
                    @endif
                    dengan ini menyatakan bahwa saya telah mendapatkan penjelasan yang cukup
                    mengenai tindakan medis di atas dari dokter / petugas yang berwenang,
                    dengan bahasa yang saya pahami.
                </p>
                <br>
                @if ($isSetuju)
                    <p>
                        Setelah mempertimbangkan dengan seksama, saya dengan penuh kesadaran
                        <strong class="{{ $agreementClass }}">MENYETUJUI</strong>
                        dilakukannya tindakan tersebut pada diri saya / pasien, beserta segala konsekuensi medis yang
                        mungkin timbul.
                    </p>
                @else
                    <p>
                        Setelah mempertimbangkan dengan seksama, saya dengan penuh kesadaran
                        <strong class="{{ $agreementClass }}">MENOLAK</strong>
                        dilakukannya tindakan tersebut pada diri saya / pasien. Saya memahami sepenuhnya risiko medis
                        yang dapat timbul akibat penolakan ini, dan dengan ini membebaskan dokter, petugas, serta pihak
                        rumah sakit dari segala tuntutan atas akibat dari penolakan tersebut. Saya menandatangani
                        dokumen ini sebagai bukti penolakan yang sah.
                    </p>
                @endif
            </td>
        </tr>

        {{-- TTD 3 kolom: Pasien/Wali | Saksi | Pemberi Informasi --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>

                        {{-- Kolom 1: Pasien / Wali --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Pasien / Wali</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $consent['signatureDate'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($consent['signature']))
                                    <img src="{{ $consent['signature'] }}" class="h-16" alt="TTD Pasien/Wali" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div
                                class="border-t border-black pt-[3px] mt-1 min-w-[120px] inline-block">
                                <p class="font-bold">{{ strtoupper($consent['wali'] ?? '-') }}</p>
                                <p class="text-[9px] text-gray-500">{{ $waliRoleText }}</p>
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left:1px solid #d1d5db;width:1px;"></td>

                        {{-- Kolom 2: Saksi --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Saksi</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $consent['signatureSaksiDate'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($consent['signatureSaksi']))
                                    <img src="{{ $consent['signatureSaksi'] }}" class="h-16" alt="TTD Saksi" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div
                                class="border-t border-black pt-[3px] mt-1 min-w-[120px] inline-block">
                                <p>{{ strtoupper($consent['saksi'] ?? '..............................') }}</p>
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left:1px solid #d1d5db;width:1px;"></td>

                        {{-- Kolom 3: Pemberi Informasi --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Pemberi Informasi</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $consent['dokterDate'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdDokterPath']))
                                    <img src="{{ $data['ttdDokterPath'] }}" class="h-16" alt="TTD Dokter" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div
                                class="border-t border-black pt-[3px] mt-1 min-w-[120px] inline-block">
                                <p class="font-bold">
                                    {{ strtoupper($consent['dokter'] ?? '..............................') }}</p>
                                @if (!empty($consent['dokterCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $consent['dokterCode'] }}</p>
                                @endif
                            </div>
                        </td>

                    </tr>
                </table>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
