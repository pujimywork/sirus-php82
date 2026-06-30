{{-- resources/views/pages/components/modul-dokumen/r-i/site-marking-ri/cetak-site-marking-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENANDAAN LOKASI OPERASI">

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

        $val = fn($v) => filled($v) ? e($v) : '-';
        $perlu = ($form['perluPenandaan'] ?? '') === 'Ya';
        $marks = $form['marks'] ?? [];
        $bodyPath = 'M60 8 c-7 0 -12 5 -12 12 c0 5 2 8 5 10 c-1 3 -2 5 -5 6 c-8 2 -14 7 -16 15 l-6 34 c-1 4 5 6 7 1 l5 -22 c1 6 1 10 0 16 l-2 30 c-1 8 0 16 1 24 l2 40 l-3 40 c-1 6 8 7 9 1 l4 -38 l3 -34 l3 34 l4 38 c1 6 10 5 9 -1 l-3 -40 l2 -40 c1 -8 2 -16 1 -24 l-2 -30 c-1 -6 -1 -10 0 -16 l5 22 c2 5 8 3 7 -1 l-6 -34 c-2 -8 -8 -13 -16 -15 c-3 -1 -4 -3 -5 -6 c3 -2 5 -5 5 -10 c0 -7 -5 -12 -12 -12 z';
        $bodyViews = ['anterior' => 'Depan', 'posterior' => 'Belakang'];
    @endphp

    <table class="w-full text-[10px] border-collapse" cellpadding="0" cellspacing="0">

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Tanggal / Jam:</span> {!! $val($form['tanggal'] ?? '') !!}
                &nbsp;&bull;&nbsp; <span class="font-bold">Rencana Tindakan:</span> {!! $val($form['rencanaTindakan'] ?? '') !!}
            </td>
        </tr>

        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <span class="font-bold">Penandaan Lokasi:</span> {!! $val($form['perluPenandaan'] ?? '') !!}
                @if (!$perlu && filled($form['alasanTidakPerlu'] ?? ''))
                    &nbsp;— <span class="italic">{!! e($form['alasanTidakPerlu']) !!}</span>
                @endif
            </td>
        </tr>

        @if ($perlu)
            <tr>
                <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                    <p><span class="font-bold">Region Anatomi:</span> {!! $val($form['regionAnatomi'] ?? '') !!}</p>
                    <p><span class="font-bold">Sisi / Lateralitas:</span> {!! $val($form['sisi'] ?? '') !!}</p>
                    <p><span class="font-bold">Detail Lokasi:</span> {!! $val($form['detailLokasi'] ?? '') !!}</p>
                </td>
                <td class="border border-black px-2 py-1.5 w-1/2 align-top">
                    <p><span class="font-bold">Metode Penandaan:</span> {!! $val($form['metodePenandaan'] ?? '') !!}</p>
                    <p><span class="font-bold">Pasien Dilibatkan:</span> {{ !empty($form['pasienDilibatkan']) ? 'Ya' : 'Tidak' }}</p>
                </td>
            </tr>

            {{-- ── DIAGRAM PENANDAAN ── --}}
            <tr>
                <td colspan="2" class="border border-black px-2 py-2 text-center">
                    <p class="font-bold mb-1 text-left">Diagram Penandaan Lokasi</p>
                    <table class="w-full" cellpadding="0" cellspacing="0">
                        <tr>
                            @foreach ($bodyViews as $view => $label)
                                <td class="w-1/2 text-center align-top">
                                    <p class="font-bold mb-1">{{ $label }}</p>
                                    <svg viewBox="0 0 120 280" width="110" height="257">
                                        <path d="{{ $bodyPath }}" fill="#eeeeee" stroke="#888888" stroke-width="1.5" />
                                        @php $n = 0; @endphp
                                        @foreach ($marks as $m)
                                            @if (($m['view'] ?? '') === $view)
                                                @php $n++; @endphp
                                                <circle cx="{{ $m['x'] }}" cy="{{ $m['y'] }}" r="6" fill="#dc2626" stroke="#ffffff" stroke-width="1.5" />
                                                <text x="{{ $m['x'] }}" y="{{ $m['y'] + 2 }}" text-anchor="middle" font-size="7" font-weight="bold" fill="#ffffff">{{ $n }}</text>
                                            @endif
                                        @endforeach
                                    </svg>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        {{-- ── TANDA TANGAN 3 PIHAK ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Perawat Ruangan --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Perawat Ruangan</p>
                            <div class="text-center my-1">
                                @if ($perlu && !empty($form['signaturePerawatRuangan']))
                                    <img src="{{ $form['signaturePerawatRuangan'] }}" class="h-16" alt="TTD Perawat Ruangan" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 inline-block min-w-[110px]">
                                <p class="font-bold">{{ strtoupper($form['namaPerawatRuangan'] ?? '-') }}</p>
                            </div>
                        </td>

                        {{-- Perawat Kamar Bedah --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Perawat Kamar Bedah</p>
                            <div class="text-center my-1">
                                @if ($perlu && !empty($form['signaturePerawatKamarBedah']))
                                    <img src="{{ $form['signaturePerawatKamarBedah'] }}" class="h-16" alt="TTD Perawat Kamar Bedah" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 inline-block min-w-[110px]">
                                <p class="font-bold">{{ strtoupper($form['namaPerawatKamarBedah'] ?? '-') }}</p>
                            </div>
                        </td>

                        {{-- Dokter Operator --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Dokter Operator</p>
                            <div class="text-center my-1">
                                @if (!empty($data['ttdOperatorPath']))
                                    <img src="{{ $data['ttdOperatorPath'] }}" class="h-16" alt="TTD Operator" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>
                            <div class="border-t border-black pt-[3px] mt-1 inline-block min-w-[110px]">
                                <p class="font-bold">{{ strtoupper($form['operatorTtd'] ?? '-') }}</p>
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
