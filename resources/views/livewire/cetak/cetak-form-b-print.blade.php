{{-- resources/views/livewire/cetak/cetak-form-b-print.blade.php --}}

<x-pdf.layout-a4-with-out-background
    title="FORMULIR B — PELAKSANAAN, MONITORING & TERMINASI MPP">

    @php
        $pasien = $dataPasien['pasien'] ?? [];
        $identitas = $pasien['identitas'] ?? [];

        try {
            $thn = !empty($pasien['tglLahir'])
                ? \Carbon\Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(\Carbon\Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr')
                : '-';
        } catch (\Throwable) {
            $thn = '-';
        }

        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        $alamat = trim(
            ($identitas['alamat'] ?? '-') .
                (!empty($identitas['rt']) ? ' RT ' . $identitas['rt'] : '') .
                (!empty($identitas['rw']) ? '/RW ' . $identitas['rw'] : '') .
                (!empty($identitas['desaName']) ? ', ' . $identitas['desaName'] : '') .
                (!empty($identitas['kecamatanName']) ? ', ' . $identitas['kecamatanName'] : ''),
        );

        $ttd = $dataFormB['tandaTanganPetugas'] ?? [];
        $ttdNama = $ttd['petugasName'] ?? '..............................';
        $ttdKode = $ttd['petugasCode'] ?? '';
        $ttdJabatan = $ttd['jabatan'] ?? 'MPP';

        // Cari Form A induk untuk konteks asesmen awal
        $formAInduk = collect($dataDaftarRi['formMPP']['formA'] ?? [])
            ->firstWhere('formA_id', $dataFormB['formA_id'] ?? '');
    @endphp

    {{-- IDENTITAS PASIEN --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $pasien['regNo'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ strtoupper($pasien['regName'] ?? '-') }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Kelamin</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $pasien['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tempat, Tgl. Lahir</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">
                    {{ $pasien['tempatLahir'] ?? '-' }}, {{ $pasien['tglLahir'] ?? '-' }} ({{ $thn }})
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">{{ $alamat }}</td>
            </tr>
            @if (!empty($dataDaftarRi['riHdrNo']))
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Inap</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] font-bold">{{ $dataDaftarRi['riHdrNo'] }}</td>
                </tr>
            @endif
        </table>
    </x-slot>

    <table class="w-full text-[10px] border-collapse">

        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-44">TANGGAL MONITORING</td>
            <td class="border border-black px-1.5 py-0.5 align-top">{{ $dataFormB['tanggal'] ?? '-' }}</td>
        </tr>

        @if ($formAInduk)
            <tr>
                <td class="border border-black px-1.5 py-0.5 font-bold align-top">REFERENSI FORM A</td>
                <td class="border border-black px-1.5 py-0.5 align-top text-gray-700">
                    Form A tgl <strong>{{ $formAInduk['tanggal'] ?? '-' }}</strong>
                    — Identifikasi:
                    <em>{{ \Illuminate\Support\Str::limit($formAInduk['indentifikasiKasus'] ?? '-', 120) }}</em>
                </td>
            </tr>
        @endif

        <tr>
            <td class="border border-black px-1.5 py-1 font-bold align-top">PELAKSANAAN &amp; MONITORING</td>
            <td class="border border-black px-1.5 py-1 align-top">
                @if (!empty($dataFormB['pelaksanaanMonitoring']))
                    {!! nl2br(e($dataFormB['pelaksanaanMonitoring'])) !!}
                @else
                    <span class="text-gray-400 italic">— belum diisi —</span>
                @endif
            </td>
        </tr>

        <tr>
            <td class="border border-black px-1.5 py-1 font-bold align-top">ADVOKASI &amp; KOLABORASI</td>
            <td class="border border-black px-1.5 py-1 align-top">
                @if (!empty($dataFormB['advokasiKolaborasi']))
                    {!! nl2br(e($dataFormB['advokasiKolaborasi'])) !!}
                @else
                    <span class="text-gray-400 italic">— belum diisi —</span>
                @endif
            </td>
        </tr>

        <tr>
            <td class="border border-black px-1.5 py-1 font-bold align-top">TERMINASI</td>
            <td class="border border-black px-1.5 py-1 align-top">
                @if (!empty($dataFormB['terminasi']))
                    {!! nl2br(e($dataFormB['terminasi'])) !!}
                @else
                    <span class="text-gray-400 italic">— belum diisi —</span>
                @endif
            </td>
        </tr>

        {{-- TTD MPP --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-2">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="w-2/3"></td>
                        <td class="w-1/3 align-top text-center px-2 py-1">
                            <p class="font-bold mb-1">Manajer Pelayanan Pasien</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $dataFormB['tanggal'] ?? '-' }}</p>

                            <br><br><br>

                            <div
                                style="border-top:1px solid #000;padding-top:3px;margin-top:4px;min-width:140px;display:inline-block;">
                                <p class="font-bold">{{ strtoupper($ttdNama) }}</p>
                                @if (!empty($ttdKode))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $ttdKode }}</p>
                                @endif
                                <p class="text-[9px] text-gray-500">{{ $ttdJabatan }}</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ \Carbon\Carbon::now(config('app.timezone'))->translatedFormat('d F Y') }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $pasien['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                ID Form: {{ $dataFormB['formB_id'] ?? '-' }}
                @if (!empty($dataFormB['formA_id']))
                    &nbsp;&bull;&nbsp;
                    Ref. Form A: {{ $dataFormB['formA_id'] }}
                @endif
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
