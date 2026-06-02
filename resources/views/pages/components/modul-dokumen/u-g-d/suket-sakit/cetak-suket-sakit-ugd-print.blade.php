{{-- resources/views/pages/components/modul-dokumen/u-g-d/suket-sakit/cetak-suket-sakit-ugd-print.blade.php --}}

<x-pdf.layout-a4 title="SURAT KETERANGAN SAKIT">

    {{-- IDENTITAS PASIEN --}}
    @php
        $alamat = $data['identitas']['alamat'] ?? '-';
        $rt = $data['identitas']['rt'] ?? '';
        $rw = $data['identitas']['rw'] ?? '';
        $desa = $data['identitas']['desaName'] ?? '';
        $kec = $data['identitas']['kecamatanName'] ?? '';
        $alamatPasien = trim(
            $alamat .
                ($rt ? ' RT ' . $rt : '') .
                ($rw ? '/RW ' . $rw : '') .
                ($desa ? ', ' . $desa : '') .
                ($kec ? ', ' . $kec : ''),
        );
    @endphp
    <div class="mb-4">
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien" />
    </div>

    {{-- GARIS PEMBATAS --}}
    <hr class="mb-4 border-gray-300">

    {{-- ISI SURAT --}}
    <p class="mb-3 text-[12px] leading-relaxed">
        Yang bertanda tangan di bawah ini, dokter pemeriksa di
        <strong>Rumah Sakit Islam Madinah Tulungagung</strong>,
        menerangkan bahwa pasien tersebut di atas telah diperiksa pada hari ini dan
        dinyatakan <strong>SAKIT</strong>, sehingga memerlukan istirahat selama:
        <span class="pb-0.5 text-[20px] font-bold border-b-2 border-black">
            {{ $data['lamaIstirahat'] ?? '-' }} Hari
        </span>
    </p>

    {{-- TANGGAL --}}
    <p class="mb-4 text-[12px] leading-relaxed">
        Terhitung mulai tanggal
        <strong>{{ $data['tglMulai'] ?? '-' }}</strong>
        sampai dengan
        <strong>{{ $data['tglSelesai'] ?? '-' }}</strong>.
    </p>

    <p class="mb-5 text-[12px] leading-relaxed">
        Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan
        sebagaimana mestinya.
    </p>

    {{-- TANDA TANGAN --}}
    <table class="w-full mt-6" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-7/12"></td>
            <td class="text-[12px] text-center">
                Tulungagung, {{ $data['tglCetak'] ?? \Carbon\Carbon::now()->translatedFormat('d F Y') }}
                <div class="text-center my-1.5">
                    @if (!empty($data['ttdDokterPath']))
                        <img class="h-16" src="{{ $data['ttdDokterPath'] }}" alt="TTD Dokter" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>
                <div class="inline-block pt-1 border-t border-black" style="min-width:140px;">
                    <span class="text-[11px]">
                        {{ $data['namaDokter'] ?? 'dr. ............................................' }}
                    </span>
                    <div class="text-[10px] text-gray-500 mt-0.5">Dokter Pemeriksa</div>
                    @if (!empty($data['strDokter']))
                        <div class="text-[10px] text-gray-500">
                            STR: {{ $data['strDokter'] }}
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4>
