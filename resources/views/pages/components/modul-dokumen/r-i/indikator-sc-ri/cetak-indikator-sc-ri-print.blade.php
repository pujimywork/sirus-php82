{{-- resources/views/pages/components/modul-dokumen/r-i/indikator-sc-ri/cetak-indikator-sc-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="INDIKATOR PROSES SC">

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
        $indikatorPertanyaan = $data['indikatorPertanyaan'] ?? [];
        $klasifikasiOptions = $data['klasifikasiOptions'] ?? [];
        $indikator = $form['indikator'] ?? [];

        $klasKode = $form['diagnosisKlasifikasi'] ?? '';
        $klasLabel = $klasKode !== '' ? ($klasifikasiOptions[$klasKode] ?? '') : '';

        $indikasi = collect($form['indikasiSc'] ?? [])->filter()->implode(', ');
        if (filled($form['indikasiScLain'] ?? null)) {
            $indikasi = trim($indikasi . ($indikasi ? ', ' : '') . e($form['indikasiScLain']));
        }
    @endphp

    <style>
        .sc-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.sc { width:100%; border-collapse:collapse; font-size:10px; }
        table.sc th, table.sc td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.sc th { background:#f0f0f0; }
        table.sc td.no { width:5%; text-align:center; }
        table.sc td.yn { width:9%; text-align:center; }
        table.sc td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Indikator Proses SC --}}
    <div class="sc-sec">1. INDIKATOR PROSES SC</div>
    <table class="sc">
        <thead>
            <tr>
                <th style="width:5%; text-align:center;">No</th>
                <th>Pertanyaan</th>
                <th style="width:9%; text-align:center;">Ya</th>
                <th style="width:9%; text-align:center;">Tidak</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($indikatorPertanyaan as $i => $pertanyaan)
                @php $val = $indikator[$i] ?? ''; @endphp
                <tr>
                    <td class="no">{{ $i + 1 }}</td>
                    <td>{{ $pertanyaan }}</td>
                    <td class="yn">{{ $val === 'Ya' ? 'X' : '' }}</td>
                    <td class="yn">{{ $val === 'Tidak' ? 'X' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 2. Klasifikasi Diagnosis --}}
    <div class="sc-sec">2. KLASIFIKASI DIAGNOSIS (ROBSON)</div>
    <table class="sc">
        <tr><td class="lbl">Klasifikasi Terpilih</td><td>{{ $klasKode !== '' ? strtoupper($klasKode) . '. ' . e($klasLabel) : '-' }}</td></tr>
    </table>

    {{-- 3. Indikasi SC --}}
    <div class="sc-sec">3. INDIKASI SC</div>
    <table class="sc">
        <tr><td class="lbl">Indikasi</td><td>{{ $indikasi ?: '-' }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                Dokter<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">{{ $form['ttd'] ?? '(Tanda Tangan &amp; Nama Terang)' }}</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
