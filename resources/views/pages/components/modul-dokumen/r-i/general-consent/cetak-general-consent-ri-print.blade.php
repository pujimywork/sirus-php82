{{-- resources/views/pages/components/modul-dokumen/r-i/general-consent/cetak-general-consent-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PERSETUJUAN UMUM (GENERAL CONSENT) — RAWAT INAP">

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
            :alamat="$alamatPasien">
            @if (!empty($data['dataRi']['riHdrNo']))
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Inap</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] font-bold">{{ $data['dataRi']['riHdrNo'] }}</td>
                </tr>
            @endif
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $consent = $data['consent'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';
        $rsCity = $identitasRs->int_city ?? 'Tulungagung';
        $agreementText = ($consent['agreement'] ?? '1') === '1' ? 'SETUJU' : 'TIDAK SETUJU';
        $agreementClass =
            ($consent['agreement'] ?? '1') === '1' ? 'font-bold text-green-700' : 'font-bold text-red-700';

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
        $hubunganText = $hubunganMap[$consent['waliHubungan'] ?? ''] ?? '-';
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── ISI PERSETUJUAN ── --}}
        <tr>
            <td colspan="4" class="border border-black px-2 py-2 text-[10px] leading-relaxed">
                <p>
                    Saya yang bertanda tangan di bawah ini, <strong>{{ strtoupper($consent['wali'] ?? '-') }}</strong>
                    (sebagai <strong>{{ $hubunganText }}</strong> pasien), menyatakan bahwa saya telah mendapat
                    penjelasan yang cukup mengenai tujuan, prosedur, risiko, dan manfaat dari pelayanan medis yang
                    akan diberikan selama rawat inap di <strong>{{ $rsName }}</strong>, dengan bahasa yang saya
                    pahami.
                </p>
                <br>
                <p>
                    Dengan ini saya menyatakan <strong class="{{ $agreementClass }}">{{ $agreementText }}</strong>
                    untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan
                    standar pelayanan medis yang berlaku di rumah sakit ini selama menjalani rawat inap.
                </p>
                <br>
                <p>
                    Saya memahami bahwa:
                </p>
                <p style="padding-left: 12px;">
                    1. Saya berhak mendapat informasi yang jelas mengenai kondisi kesehatan, diagnosis, prosedur,
                    risiko, dan alternatif tindakan.<br>
                    2. Saya berhak menolak/menghentikan tindakan, termasuk pelayanan resusitasi dan terapi penunjang
                    kehidupan, setelah mendapat penjelasan.<br>
                    3. Saya berhak meminta konsultasi dokter lain (<em>second opinion</em>) bila diperlukan.<br>
                    4. Saya berhak didampingi keluarga, terutama dalam keadaan kritis.<br>
                    5. Rumah sakit menjaga kerahasiaan informasi medis saya sesuai ketentuan yang berlaku.<br>
                    6. Saya bertanggung jawab atas biaya pelayanan rawat inap sesuai ketentuan rumah sakit, termasuk
                    biaya kamar dan tindakan yang dilakukan.<br>
                    7. Untuk tindakan invasif, pembedahan, anestesi, transfusi darah, dan tindakan berisiko tinggi
                    akan diminta <em>persetujuan tindakan (informed consent)</em> tersendiri.<br>
                    8. Rumah sakit tidak bertanggung jawab atas kehilangan atau kerusakan barang berharga yang saya
                    bawa sendiri.
                </p>
                <br>
                <p>
                    <strong>Pihak yang Diberi Akses Informasi Medis:</strong>
                </p>
                @php $pihakList = collect($consent['pihakInfoMedis'] ?? [])->filter(fn($pihak) => !empty(trim($pihak['nama'] ?? ''))); @endphp
                @if ($pihakList->count() > 0)
                    <table class="w-full mt-1 text-[9px] border-collapse">
                        <thead>
                            <tr>
                                <th class="border border-black px-1 py-0.5 w-6">No</th>
                                <th class="border border-black px-1 py-0.5">Nama</th>
                                <th class="border border-black px-1 py-0.5">Hubungan</th>
                                <th class="border border-black px-1 py-0.5">No. HP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pihakList as $index => $pihak)
                                <tr>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ $index + 1 }}</td>
                                    <td class="border border-black px-1 py-0.5">{{ $pihak['nama'] ?? '-' }}</td>
                                    <td class="border border-black px-1 py-0.5">{{ $pihak['hubungan'] ?? '-' }}</td>
                                    <td class="border border-black px-1 py-0.5">{{ $pihak['noHp'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p style="padding-left:12px;"><em>Belum ada pihak yang ditunjuk.</em></p>
                @endif
            </td>
        </tr>

        {{-- ── TANDA TANGAN — 2 kolom: pasien/wali & petugas ── --}}
        <tr>
            <td colspan="4" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Kolom kiri: TTD Pasien/Wali --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pasien / Wali</p>
                            <p class="text-[9px] text-gray-500 mb-2">
                                {{ $data['tglCetak'] ?? '-' }}
                            </p>

                            <div class="text-center my-1">
                                @if (!empty($consent['signature']))
                                    <img src="{{ $consent['signature'] }}" class="h-16" alt="Tanda Tangan Pasien" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div
                                class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($consent['wali'] ?? '-') }}</p>
                                <p class="text-[9px] text-gray-500">{{ $hubunganText }}</p>
                                @if (!empty($consent['signatureDate']))
                                    <p class="text-[9px] text-gray-500">{{ $consent['signatureDate'] }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left: 1px solid #d1d5db; width: 1px;"></td>

                        {{-- Kolom kanan: TTD Petugas Pemberi Penjelasan --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Petugas Pemberi Penjelasan</p>
                            <p class="text-[9px] text-gray-500 mb-2">
                                {{ $consent['petugasPemeriksaDate'] ?? '-' }}
                            </p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdPetugasPath']))
                                    <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Petugas" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div
                                class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($consent['petugasPemeriksa'] ?? '-') }}</p>
                                @if (!empty($consent['petugasPemeriksaCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $consent['petugasPemeriksaCode'] }}
                                    </p>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
        <tr>
            <td colspan="4" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
