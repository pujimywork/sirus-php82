{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-awal-bayi-ri/cetak-pengkajian-awal-bayi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AWAL BAYI">

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
        $v = fn($k) => filled($form[$k] ?? null) ? e($form[$k]) : '-';

        $apgarRows = [
            ['Warna Kulit', 'warnaKulit'],
            ['Reflek', 'reflek'],
            ['Denyut Jantung', 'denyutJantung'],
            ['Tonus', 'tonus'],
            ['Usaha Bernafas', 'usahaNafas'],
        ];
        $apgarMenit = ['1' => "1'", '5' => "5'", '10' => "10'"];
        $cell = fn($k) => filled($form[$k] ?? null) ? e($form[$k]) : '-';
        $sumMenit = fn($m) => collect($apgarRows)->sum(fn($r) => (int) ($form[$r[1] . $m] ?? 0));
        $lahirKeadaan = fn($sel, $ket) =>
            trim((filled($form[$sel] ?? null) ? e($form[$sel]) : '-') . (filled($form[$ket] ?? null) ? ' — ' . e($form[$ket]) : ''));
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
        table.apgar { width:100%; border-collapse:collapse; font-size:10px; text-align:center; }
        table.apgar td, table.apgar th { border:1px solid #999; padding:2px 5px; }
        table.apgar th { background:#f7f7f7; }
        table.apgar td.lbl { text-align:left; background:#f7f7f7; width:40%; }
        table.apgar tr.sum td { font-weight:bold; background:#eef2ee; }
    </style>

    {{-- 1. Identitas Bayi --}}
    <div class="pa-sec">1. IDENTITAS BAYI</div>
    <table class="pa">
        <tr><td class="lbl">Tanggal / Jam Lahir</td><td>{{ $v('tglLahir') }} {{ filled($form['jamLahir'] ?? null) ? e($form['jamLahir']) : '' }}</td><td class="lbl">Cara Persalinan</td><td>{{ $v('caraPersalinan') }}</td></tr>
        <tr><td class="lbl">Nama Ayah</td><td>{{ $v('namaAyah') }}</td><td class="lbl">Nama Ibu</td><td>{{ $v('namaIbu') }}</td></tr>
        <tr><td class="lbl">Ruangan Ibu</td><td>{{ $v('ruanganIbu') }}</td><td class="lbl">No. RM Ibu</td><td>{{ $v('noRmIbu') }}</td></tr>
    </table>

    {{-- 2. Nilai APGAR --}}
    <div class="pa-sec">2. NILAI APGAR</div>
    <table class="apgar">
        <thead>
            <tr>
                <th class="lbl">Komponen</th>
                @foreach ($apgarMenit as $mk => $ml)<th>Menit {{ $ml }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($apgarRows as $row)
                <tr>
                    <td class="lbl">{{ $row[0] }}</td>
                    @foreach ($apgarMenit as $mk => $ml)<td>{{ $cell($row[1] . $mk) }}</td>@endforeach
                </tr>
            @endforeach
            <tr class="sum">
                <td class="lbl">Jumlah</td>
                @foreach ($apgarMenit as $mk => $ml)<td>{{ $sumMenit($mk) }}</td>@endforeach
            </tr>
        </tbody>
    </table>

    {{-- 3. Pemeriksaan Fisik --}}
    <div class="pa-sec">3. PEMERIKSAAN FISIK</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan Tali Pusat</td><td>{{ $v('keadaanTaliPusat') }}</td><td class="lbl">Jantung</td><td>{{ $v('jantung') }}</td></tr>
        <tr><td class="lbl">Paru</td><td>{{ $v('paru') }}</td><td class="lbl">Abdomen / Hati</td><td>{{ $v('abdomenHati') }}</td></tr>
        <tr><td class="lbl">Limpa</td><td>{{ $v('limpa') }}</td><td class="lbl">Anus</td><td>{{ $v('anus') }}</td></tr>
        <tr><td class="lbl">Ekstremitas</td><td>{{ $v('ekstremitas') }}</td><td class="lbl">Imunisasi</td><td>{{ $v('imunisasi') }}</td></tr>
    </table>

    {{-- 4. Antropometri --}}
    <div class="pa-sec">4. ANTROPOMETRI</div>
    <table class="pa">
        <tr><td class="lbl">Lingkar Kepala</td><td>{{ $v('lingkarKepala') }} cm</td><td class="lbl">Berat Badan</td><td>{{ $v('beratBadan') }} gr</td></tr>
        <tr><td class="lbl">Tinggi Badan</td><td>{{ $v('tinggiBadan') }} cm</td><td class="lbl">Lingkar Dada</td><td>{{ $v('lingkarDada') }} cm</td></tr>
        <tr><td class="lbl">Jenis Kelamin</td><td colspan="3">{{ $v('jenisKelamin') }}</td></tr>
    </table>

    {{-- 5. Keadaan Bayi Waktu Lahir --}}
    <div class="pa-sec">5. KEADAAN BAYI WAKTU LAHIR</div>
    <table class="pa">
        <tr><td class="lbl">Sianosis</td><td colspan="3">{{ $lahirKeadaan('sianosis', 'sianosisKet') }}</td></tr>
        <tr><td class="lbl">Asphyxia</td><td colspan="3">{{ $lahirKeadaan('asphyxia', 'asphyxiaKet') }}</td></tr>
        <tr><td class="lbl">Trauma Lahir</td><td colspan="3">{{ $lahirKeadaan('traumaLahir', 'traumaLahirKet') }}</td></tr>
    </table>

    {{-- 6. Diagnosa --}}
    <div class="pa-sec">6. DIAGNOSA</div>
    <table class="pa">
        <tr><td class="lbl">Diagnosa Utama</td><td colspan="3">{{ $v('diagnosaUtama') }}</td></tr>
    </table>

    {{-- 7. Rencana --}}
    <div class="pa-sec">7. RENCANA</div>
    <table class="pa">
        <tr><td class="lbl">Rencana Diagnosa</td><td colspan="3">{{ $v('rencanaDiagnosa') }}</td></tr>
        <tr><td class="lbl">Terapi</td><td colspan="3">{{ $v('terapi') }}</td></tr>
        <tr><td class="lbl">Diet</td><td colspan="3">{{ $v('diet') }}</td></tr>
        <tr><td class="lbl">Edukasi</td><td colspan="3">{{ $v('edukasi') }}</td></tr>
        <tr><td class="lbl">Monitoring</td><td colspan="3">{{ $v('monitoring') }}</td></tr>
        <tr><td class="lbl">Discharge Planning</td><td colspan="3">{{ $v('dischargePlanning') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['tglCetak'] ?? '' }}<br>
                Dokter<br>
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
