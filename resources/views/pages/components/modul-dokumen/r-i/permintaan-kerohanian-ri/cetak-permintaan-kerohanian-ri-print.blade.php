{{-- resources/views/pages/components/modul-dokumen/r-i/permintaan-kerohanian-ri/cetak-permintaan-kerohanian-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PERMINTAAN PELAYANAN KEROHANIAWAN">

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
        $agamaText = ($form['agama'] ?? '') ?: '-';
    @endphp

    {{-- ── SALAM PEMBUKA ── --}}
    <div class="text-[11px] leading-relaxed mb-3">
        <p class="font-bold">Yth. Petugas Bimbingan Rohani RS Islam Madinah</p>
        <p>Mohon diberikan Bimbingan Rohani sebagai Pasien Rawat Inap.</p>
    </div>

    {{-- ── DATA PEMOHON ── --}}
    <div class="text-[11px] leading-relaxed mb-2">
        <p class="mb-1">Yang bertanda tangan di bawah ini:</p>
    </div>
    <table class="w-full text-[10px] border-collapse mb-3">
        <tr>
            <td class="px-1 py-0.5 w-1/3">Nama</td>
            <td class="px-1 py-0.5 w-2">:</td>
            <td class="px-1 py-0.5">{{ $form['pemohonNama'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="px-1 py-0.5">Umur</td>
            <td class="px-1 py-0.5">:</td>
            <td class="px-1 py-0.5">{{ ($form['pemohonUmur'] ?? '') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="px-1 py-0.5 align-top">Alamat</td>
            <td class="px-1 py-0.5 align-top">:</td>
            <td class="px-1 py-0.5">{{ ($form['pemohonAlamat'] ?? '') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="px-1 py-0.5">Hubungan dengan Pasien</td>
            <td class="px-1 py-0.5">:</td>
            <td class="px-1 py-0.5">{{ $hubunganText }}</td>
        </tr>
    </table>

    {{-- ── PERNYATAAN ── (data pasien TIDAK diulang di body — cukup header identitas standar di atas) --}}
    <div class="text-[11px] leading-relaxed mb-3">
        <p>Dengan ini menyatakan permintaan pendampingan pelayanan kerohanian Agama/Kepercayaan
            <strong>{{ $agamaText }}</strong> kepada Rumah Sakit Islam Madinah terhadap pasien tersebut
            sebagaimana identitas tercantum di atas.</p>
    </div>

    {{-- ── PENUTUP ── --}}
    <div class="text-[11px] leading-relaxed mb-4">
        <p>Demikian surat permohonan permintaan pelayanan kerohaniawan ini saya buat sebagaimana mestinya.</p>
    </div>

    {{-- ── TANDA TANGAN ── --}}
    <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Yang membuat pernyataan / Pemohon (KIRI — standar 2 kolom: pemohon/pasien kiri, petugas kanan) --}}
            <td class="w-1/2 align-top text-center px-3 py-2">
                <p class="font-bold mb-1">Yang membuat pernyataan</p>
                <p class="text-[9px] text-gray-500 mb-2">{{ $data['tglCetak'] ?? '-' }}</p>

                <div class="text-center my-1">
                    @if (!empty($form['signature']))
                        <img src="{{ $form['signature'] }}" class="h-16" alt="Tanda Tangan Pemohon" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                    <p class="font-bold">{{ strtoupper($form['pemohonNama'] ?? '-') }}</p>
                    <p class="text-[9px] text-gray-500">{{ $hubunganText }}</p>
                    @if (!empty($form['signatureDate']))
                        <p class="text-[9px] text-gray-500">{{ $form['signatureDate'] }}</p>
                    @endif
                </div>
            </td>

            {{-- Petugas RS (KANAN) --}}
            <td class="w-1/2 align-top text-center px-3 py-2">
                <p class="font-bold mb-1">Petugas RS</p>
                <p class="text-[9px] text-gray-500 mb-2">{{ $form['petugasDate'] ?? '-' }}</p>

                <div class="text-center my-1">
                    @if (!empty($data['ttdPetugasPath']))
                        <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Petugas RS" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                    <p class="font-bold">{{ strtoupper($form['petugas'] ?? '-') }}</p>
                    @if (!empty($form['petugasCode']))
                        <p class="text-[9px] text-gray-500">Kode: {{ $form['petugasCode'] }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ── FOOTER INFO ── --}}
    <table class="w-full text-[9px] mt-4">
        <tr>
            <td class="px-1.5 py-1 text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
