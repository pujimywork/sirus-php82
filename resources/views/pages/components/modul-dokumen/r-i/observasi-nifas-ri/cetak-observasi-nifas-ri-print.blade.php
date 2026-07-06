{{-- resources/views/pages/components/modul-dokumen/r-i/observasi-nifas-ri/cetak-observasi-nifas-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LEMBAR OBSERVASI NIFAS">

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
        $cell = fn($v) => filled($v) ? e($v) : '-';
        $lochia = function ($r) {
            $s = trim(($r['lochiaJenis'] ?? '') . ' ' . ($r['lochiaJumlah'] ?? ''));
            return $s !== '' ? e($s) : '-';
        };
    @endphp

    <style>
        table.on { width:100%; border-collapse:collapse; font-size:8px; }
        table.on th, table.on td { border:1px solid #999; padding:2px 3px; vertical-align:top; text-align:center; }
        table.on th { background:#eef2ee; font-weight:bold; }
        table.on td.ket { text-align:left; }
    </style>

    <table class="on">
        <thead>
            <tr>
                <th style="width:9%;">Tgl / Jam</th>
                <th style="width:6%;">TD</th>
                <th style="width:4%;">N</th>
                <th style="width:4%;">RR</th>
                <th style="width:4%;">S</th>
                <th style="width:4%;">EWS</th>
                <th style="width:9%;">TFU</th>
                <th style="width:7%;">Kontraksi</th>
                <th style="width:9%;">Lochia</th>
                <th style="width:5%;">Drh (cc)</th>
                <th style="width:7%;">Luka</th>
                <th style="width:6%;">Laktasi</th>
                <th style="width:4%;">ASI</th>
                <th style="width:22%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                @php
                    $ket = trim(
                        (filled($r['keluhan'] ?? null) ? 'Keluhan: ' . $r['keluhan'] . '. ' : '') .
                        (filled($r['asuhanTindakan'] ?? null) ? $r['asuhanTindakan'] . ' ' : '') .
                        (filled($r['ttd'] ?? null) ? '(' . $r['ttd'] . ')' : '')
                    );
                @endphp
                <tr>
                    <td>{{ $cell($r['tglJam'] ?? null) }}</td>
                    <td>{{ $cell($r['td'] ?? null) }}</td>
                    <td>{{ $cell($r['nadi'] ?? null) }}</td>
                    <td>{{ $cell($r['rr'] ?? null) }}</td>
                    <td>{{ $cell($r['suhu'] ?? null) }}</td>
                    <td>{{ $cell($r['ewsScore'] ?? null) }}</td>
                    <td>{{ $cell($r['tfu'] ?? null) }}</td>
                    <td>{{ $cell($r['kontraksiUterus'] ?? null) }}</td>
                    <td>{{ $lochia($r) }}</td>
                    <td>{{ $cell($r['perdarahanCc'] ?? null) }}</td>
                    <td>{{ $cell($r['lukaJalanLahir'] ?? null) }}</td>
                    <td>{{ $cell($r['laktasi'] ?? null) }}</td>
                    <td>{{ $cell($r['asiEksklusif'] ?? null) }}</td>
                    <td class="ket">{{ $ket !== '' ? e($ket) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="14">Belum ada baris observasi nifas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size:8px; margin-top:4px; color:#555;">
        Keterangan: TD = Tekanan Darah, N = Nadi, RR = Respirasi, S = Suhu, EWS = Maternal Early Warning Score,
        TFU = Tinggi Fundus Uteri, Drh = Perdarahan, ASI = ASI Eksklusif. Lochia: Rubra / Sanguinolenta / Serosa / Alba.
    </div>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['tglCetak'] ?? '' }}<br>
                Bidan / Perawat<br>
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
