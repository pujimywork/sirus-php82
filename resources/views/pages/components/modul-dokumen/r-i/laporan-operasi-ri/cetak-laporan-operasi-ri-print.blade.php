{{-- resources/views/pages/components/modul-dokumen/r-i/laporan-operasi-ri/cetak-laporan-operasi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LAPORAN OPERASI">

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

        $kotak = fn($checked) => $checked ? '&#9745;' : '&#9744;';
        $val = fn($nilai) => filled($nilai) ? e($nilai) : '-';
        $implan = !empty($form['implanDipasang']);
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">

        {{-- ── WAKTU & URGENSI ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <span class="font-bold">Tanggal / Jam Operasi:</span> {!! $val($form['tanggalOperasi'] ?? '') !!}
            </td>
            <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                <span class="font-bold">Urgensi:</span>
                @foreach (['Elektif', 'Urgen', 'Cito'] as $opt)
                    <span class="ml-1">{!! $kotak(($form['urgensi'] ?? '') === $opt) !!} {{ $opt }}</span>
                @endforeach
            </td>
        </tr>

        {{-- ── DIAGNOSIS ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Diagnosis Pra-operasi</p>
                <p class="leading-relaxed">{!! $val($form['diagnosisPraOp'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Diagnosis Pasca-operasi</p>
                <p class="leading-relaxed">{!! $val($form['diagnosisPascaOp'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── JENIS TINDAKAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Jenis Tindakan Operasi</p>
                <p class="leading-relaxed">{!! $val($form['jenisTindakan'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── TIM BEDAH & ANESTESI ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Operator:</span> {!! $val($form['namaOperator'] ?? '') !!}</p>
                <p><span class="font-bold">Asisten 1:</span> {!! $val($form['asisten1'] ?? '') !!}</p>
                <p><span class="font-bold">Instrumentor:</span> {!! $val($form['instrumentor'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Anestesi:</span> {!! $val($form['namaAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Asisten Anestesi:</span> {!! $val($form['asistenAnestesi'] ?? '') !!}</p>
                <p><span class="font-bold">Jenis Anestesi:</span> {!! $val($form['jenisAnestesi'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── KLASIFIKASI & WAKTU ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Golongan Operasi:</span> {!! $val($form['golonganOperasi'] ?? '') !!}</p>
                <p><span class="font-bold">Macam Operasi:</span> {!! $val($form['macamOperasi'] ?? '') !!}</p>
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Jam Mulai:</span> {!! $val($form['jamMulai'] ?? '') !!}
                    &nbsp;&bull;&nbsp; <span class="font-bold">Selesai:</span> {!! $val($form['jamSelesai'] ?? '') !!}</p>
                <p><span class="font-bold">Lama Operasi:</span> {!! $val($form['lamaOperasi'] ?? '') !!}</p>
                <p><span class="font-bold">Posisi Pasien:</span> {!! $val($form['posisiPasien'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── PERDARAHAN, PA, KOMPLIKASI ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 align-top">
                <p><span class="font-bold">Jumlah Perdarahan:</span>
                    {{ filled($form['jumlahPerdarahanCc'] ?? '') ? $form['jumlahPerdarahanCc'] . ' cc' : '-' }}</p>
                <p><span class="font-bold">Transfusi Masuk:</span>
                    @if (!empty($form['transfusiDiberikan']))
                        {{ filled($form['transfusiCc'] ?? '') ? $form['transfusiCc'] . ' cc' : '-' }}{{ filled($form['transfusiJenis'] ?? '') ? ' (' . e($form['transfusiJenis']) . ')' : '' }}
                    @else
                        Tidak
                    @endif
                </p>
                <p><span class="font-bold">Pemeriksaan PA:</span>
                    {!! $kotak(($form['pemeriksaanPa'] ?? '') === 'Ya') !!} Ya
                    &nbsp; {!! $kotak(($form['pemeriksaanPa'] ?? '') === 'Tidak') !!} Tidak</p>
                @if (($form['pemeriksaanPa'] ?? '') === 'Ya' && filled($form['spesimenDetail'] ?? ''))
                    <p><span class="font-bold">Spesimen:</span> {!! e($form['spesimenDetail']) !!}</p>
                @endif
            </td>
            <td class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Komplikasi</p>
                <p class="leading-relaxed">{!! $val($form['komplikasi'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── URAIAN LAPORAN OPERASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                <p class="font-bold mb-0.5">Uraian Tindakan & Temuan Operasi</p>
                <p class="leading-relaxed" style="white-space: pre-line;">{!! $val($form['uraianLaporan'] ?? '') !!}</p>
            </td>
        </tr>

        {{-- ── INSTRUKSI PASCA-BEDAH ── --}}
        @if (filled($form['instruksiPascaBedah'] ?? ''))
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                    <p class="font-bold mb-0.5">Instruksi Pasca-bedah</p>
                    <p class="leading-relaxed" style="white-space: pre-line;">{!! e($form['instruksiPascaBedah']) !!}</p>
                </td>
            </tr>
        @endif

        {{-- ── REGISTRY IMPLAN (PAB 7.4) ── --}}
        @if ($implan)
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5 align-top">
                    <p class="font-bold mb-0.5">Registry Implan (PAB 7.4)</p>
                    <p>
                        <span class="font-bold">Jenis:</span> {!! $val($form['jenisImplan'] ?? '') !!} &nbsp;&bull;&nbsp;
                        <span class="font-bold">Merk/Pabrikan:</span> {!! $val($form['merkPabrikan'] ?? '') !!} &nbsp;&bull;&nbsp;
                        <span class="font-bold">No. Serial/Lot:</span> {!! $val($form['nomorSerial'] ?? '') !!}
                    </p>
                    <p>
                        <span class="font-bold">Ukuran:</span> {!! $val($form['ukuranImplan'] ?? '') !!} &nbsp;&bull;&nbsp;
                        <span class="font-bold">Lokasi:</span> {!! $val($form['lokasiPemasangan'] ?? '') !!} &nbsp;&bull;&nbsp;
                        <span class="font-bold">Sifat:</span> {!! $val($form['sifatImplan'] ?? '') !!}
                    </p>
                </td>
            </tr>
        @endif

        {{-- ── TANDA TANGAN OPERATOR ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-1/2"></td>
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Operator</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['operatorTtdDate'] ?? $data['tglCetak'] ?? '-' }}</p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdOperatorPath']))
                                    <img src="{{ $data['ttdOperatorPath'] }}" class="h-16" alt="Tanda Tangan Operator" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[160px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['operatorTtd'] ?? ($form['namaOperator'] ?? '-')) }}</p>
                                @if (!empty($form['operatorTtdCode']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $form['operatorTtdCode'] }}</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
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
