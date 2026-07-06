{{-- resources/views/pages/components/modul-dokumen/r-i/catatan-terapi-neonatal-ri/cetak-catatan-terapi-neonatal-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="CATATAN TERAPI & PERENCANAAN KEPERAWATAN NEONATAL">

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
        $terapiDokter = $data['terapiDokter'] ?? [];
        $perencanaan = $data['perencanaan'] ?? [];
        $show = fn($v) => filled($v) ? e($v) : '-';
    @endphp

    <style>
        .cn-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:10px; }
        table.cn { width:100%; border-collapse:collapse; font-size:10px; margin-top:2px; }
        table.cn th, table.cn td { border:1px solid #999; padding:3px 5px; vertical-align:top; }
        table.cn th { background:#f7f7f7; text-align:left; }
        .cn-empty { font-size:10px; color:#666; padding:4px 2px; }
    </style>

    {{-- A. Terapi Dokter --}}
    <div class="cn-sec">A. CATATAN TERAPI DOKTER</div>
    @if (count($terapiDokter) > 0)
        <table class="cn">
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th style="width:20%;">Tgl &amp; Jam</th>
                    <th style="width:52%;">Penatalaksanaan / Terapi</th>
                    <th style="width:23%;">ICD 9 CM</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($terapiDokter as $i => $e)
                    <tr>
                        <td style="text-align:center;">{{ $i + 1 }}</td>
                        <td>{{ $show($e['tglJam'] ?? ($e['createdAt'] ?? '')) }}</td>
                        <td>{{ $show($e['keterangan'] ?? '') }}</td>
                        <td>{{ $show($e['icd9'] ?? '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="cn-empty">Tidak ada catatan terapi dokter.</div>
    @endif

    {{-- B. Perencanaan Keperawatan --}}
    <div class="cn-sec">B. PERENCANAAN KEPERAWATAN</div>
    @if (count($perencanaan) > 0)
        <table class="cn">
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th style="width:20%;">Jam &amp; Tgl</th>
                    <th style="width:52%;">Perencanaan &amp; Tindakan</th>
                    <th style="width:23%;">Nama</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perencanaan as $i => $e)
                    <tr>
                        <td style="text-align:center;">{{ $i + 1 }}</td>
                        <td>{{ $show($e['tglJam'] ?? ($e['createdAt'] ?? '')) }}</td>
                        <td>{{ $show($e['keterangan'] ?? '') }}</td>
                        <td>{{ $show($e['ttd'] ?? '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="cn-empty">Tidak ada perencanaan keperawatan.</div>
    @endif

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:18px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['tglCetak'] ?? '' }}<br>
                Petugas<br>
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
