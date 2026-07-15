{{-- resources/views/pages/components/modul-dokumen/r-i/surat-kematian-ri/cetak-surat-kematian-ri-print.blade.php --}}

@use('App\Support\SuratKematianClause')

<x-pdf.layout-a4-with-out-background title="SURAT KETERANGAN KEMATIAN">

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
        $rsCity = $identitasRs->int_city ?? 'Tulungagung';

        // Record legacy tanpa stempel versi → render versi TERTUA ('v1'), bukan CURRENT.
        $klausul = SuratKematianClause::get($form['clauseVersion'] ?? 'v1');
        $intro = strtr($klausul['intro'], ['%RS%' => $rsName]);
    @endphp

    <table class="w-full text-[10px] border-collapse">

        <tr>
            <td colspan="2" class="px-2 py-1.5 text-center">
                <p class="text-[12px] font-bold">SURAT KETERANGAN KEMATIAN</p>
                <p class="text-[10px]">No. {{ ($form['nomorSurat'] ?? '') ?: '-' }}</p>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="px-2 py-1.5 leading-relaxed">{{ $intro }}</td>
        </tr>

        {{-- ── IDENTITAS ALMARHUM ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 w-40">Nama</td>
                        <td class="py-0.5 w-3">:</td>
                        <td class="py-0.5 font-bold">{{ strtoupper($data['regName'] ?? '-') }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">No. Rekam Medis</td>
                        <td class="py-0.5">:</td>
                        <td class="py-0.5">{{ $data['regNo'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">NIK</td>
                        <td class="py-0.5">:</td>
                        <td class="py-0.5">{{ ($data['identitas']['nik'] ?? '') ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Jenis Kelamin</td>
                        <td class="py-0.5">:</td>
                        <td class="py-0.5">{{ $data['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Tempat / Tanggal Lahir</td>
                        <td class="py-0.5">:</td>
                        <td class="py-0.5">
                            {{ ($data['tempatLahir'] ?? '') ?: '-' }} / {{ ($data['tglLahir'] ?? '') ?: '-' }}
                            ({{ ($data['thn'] ?? '') ?: '-' }})
                        </td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Alamat</td>
                        <td class="py-0.5 align-top">:</td>
                        <td class="py-0.5">{{ $alamatPasien ?: '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="px-2 pt-2 pb-1 leading-relaxed">{{ $klausul['statement'] }}</td>
        </tr>

        {{-- ── DATA KEMATIAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="py-0.5 w-40">Tanggal</td>
                        <td class="py-0.5 w-3">:</td>
                        <td class="py-0.5 font-bold">{{ ($form['tanggalMeninggal'] ?? '') ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Tempat</td>
                        <td class="py-0.5">:</td>
                        <td class="py-0.5">{{ ($form['tempatMeninggal'] ?? '') ?: '-' }}, {{ $rsName }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 align-top">Sebab Kematian</td>
                        <td class="py-0.5 align-top">:</td>
                        <td class="py-0.5">{{ ($form['sebabKematian'] ?? '') ?: '-' }}</td>
                    </tr>
                    @if (!empty($form['keterangan']))
                        <tr>
                            <td class="py-0.5 align-top">Keterangan</td>
                            <td class="py-0.5 align-top">:</td>
                            <td class="py-0.5">{{ $form['keterangan'] }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="px-2 pt-2 pb-1 leading-relaxed">{{ $klausul['penutup'] }}</td>
        </tr>

        {{-- ── TANDA TANGAN DOKTER (kanan) ── --}}
        <tr>
            <td class="w-1/2 px-2 py-1">&nbsp;</td>
            <td class="w-1/2 px-3 py-2 text-center align-top">
                <p class="text-[10px]">{{ $rsCity }}, {{ $data['tglCetak'] ?? '-' }}</p>
                <p class="font-bold mb-1">Dokter yang Menerangkan</p>

                <div class="text-center my-1">
                    @if (!empty($data['ttdDokterPath']))
                        <img src="{{ $data['ttdDokterPath'] }}" class="h-16" alt="Tanda Tangan Dokter" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                    <p class="font-bold">{{ strtoupper(($form['dokterPenerang'] ?? '') ?: '-') }}</p>
                    @if (!empty($form['dokterPenerangCode']))
                        <p class="text-[9px] text-gray-500">Kode: {{ $form['dokterPenerangCode'] }}</p>
                    @endif
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[9px] text-gray-600 italic leading-relaxed">
                {{ $klausul['catatanHukum'] }}
            </td>
        </tr>

        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}{{ $rsAddress ? ', ' . $rsAddress : '' }}
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
