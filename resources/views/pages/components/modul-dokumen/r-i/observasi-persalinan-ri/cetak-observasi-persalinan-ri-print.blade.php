{{-- resources/views/pages/components/modul-dokumen/r-i/observasi-persalinan-ri/cetak-observasi-persalinan-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="OBSERVASI PERSALINAN">

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
        $rows = $data['rows'] ?? [];
        $diagnosa = $data['diagnosa'] ?? '';
        $cell = fn($v) => filled($v) ? e($v) : '-';
    @endphp

    <style>
        .op-diag { font-size:10px; margin-top:6px; margin-bottom:4px; }
        table.op { width:100%; border-collapse:collapse; font-size:9px; }
        table.op th, table.op td { border:1px solid #999; padding:3px 4px; vertical-align:top; text-align:center; }
        table.op th { background:#eef2ee; font-weight:bold; }
        table.op td.ket { text-align:left; }
    </style>

    @if (filled($diagnosa))
        <div class="op-diag"><strong>Diagnosa:</strong> {{ e($diagnosa) }}</div>
    @endif

    <table class="op">
        <thead>
            <tr>
                <th style="width:8%;">Jam</th>
                <th style="width:10%;">TD (mmHg)</th>
                <th style="width:7%;">N (x/mnt)</th>
                <th style="width:7%;">RR (x/mnt)</th>
                <th style="width:7%;">S (&deg;C)</th>
                <th style="width:8%;">DJJ (x/mnt)</th>
                <th style="width:13%;">His</th>
                <th style="width:7%;">EWS</th>
                <th style="width:23%;">Obat / Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $cell($r['jam'] ?? null) }}</td>
                    <td>{{ $cell($r['td'] ?? null) }}</td>
                    <td>{{ $cell($r['nadi'] ?? null) }}</td>
                    <td>{{ $cell($r['rr'] ?? null) }}</td>
                    <td>{{ $cell($r['suhu'] ?? null) }}</td>
                    <td>{{ $cell($r['djj'] ?? null) }}</td>
                    <td>{{ $cell($r['his'] ?? null) }}</td>
                    <td>{{ $cell($r['ewsScore'] ?? null) }}</td>
                    <td class="ket">{{ $cell($r['obatKeterangan'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="9">Belum ada baris observasi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size:8px; margin-top:4px; color:#555;">
        Keterangan: TD = Tekanan Darah, N = Nadi, RR = Respirasi, S = Suhu, DJJ = Denyut Jantung Janin,
        EWS = Maternal Early Warning Score.
    </div>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $data['ttd'] ?? 'Bidan / Perawat' }}<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">Tanda Tangan &amp; Nama Terang</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
