{{-- resources/views/pages/components/modul-dokumen/r-i/laporan-persalinan-ri/cetak-laporan-persalinan-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LAPORAN TINDAKAN PERSALINAN">

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
        $tgljam = fn($t, $j) => trim((filled($form[$t] ?? null) ? e($form[$t]) : '') . ' ' . (filled($form[$j] ?? null) ? e($form[$j]) : '')) ?: '-';
        $ukKepala = collect(['ukKepalaBt' => 'BT', 'ukKepalaBp' => 'BP', 'ukKepalaFo' => 'FO', 'ukKepalaMo' => 'MO', 'ukKepalaOb' => 'OB'])
            ->filter(fn($lbl, $k) => filled($form[$k] ?? null))
            ->map(fn($lbl, $k) => $lbl . ' ' . e($form[$k]) . ' cm')
            ->implode(', ');
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Jenis Partus --}}
    <div class="pa-sec">1. JENIS PARTUS</div>
    <table class="pa">
        <tr><td class="lbl">Jenis Partus</td><td>{{ $v('jenisPartus') }}</td><td class="lbl">Indikasi</td><td>{{ $v('indikasi') }}</td></tr>
    </table>

    {{-- 2. Bayi --}}
    <div class="pa-sec">2. BAYI</div>
    <table class="pa">
        <tr><td class="lbl">Lahir</td><td>{{ $tgljam('bayiLahirTgl','bayiLahirJam') }}</td><td class="lbl">Jenis Kelamin</td><td>{{ $v('bayiJenisKelamin') }}</td></tr>
        <tr><td class="lbl">Berat / Panjang</td><td>{{ $v('bayiBb') }} gr / {{ $v('bayiPb') }} cm</td><td class="lbl">APGAR Score</td><td>{{ $v('bayiApgar') }}</td></tr>
        <tr><td class="lbl">Resusitasi</td><td>{{ $v('bayiResusitasi') }}</td><td class="lbl">Keadaan</td><td>{{ $v('bayiKeadaan') }}</td></tr>
        <tr><td class="lbl">Ukuran Kepala</td><td colspan="3">{{ $ukKepala ?: '-' }}</td></tr>
        <tr><td class="lbl">Caput Suksedanium</td><td>{{ $v('caputSuksedanium') }}</td><td class="lbl">Cephal Hematoma</td><td>{{ $v('cephalHematoma') }}</td></tr>
        <tr><td class="lbl">Atresia Ani</td><td>{{ $v('atresiaAni') }}</td><td class="lbl">Lain-lain</td><td>{{ $v('bayiLain') }}</td></tr>
    </table>

    {{-- 3. Plasenta --}}
    <div class="pa-sec">3. PLASENTA</div>
    <table class="pa">
        <tr><td class="lbl">Lahir</td><td>{{ $tgljam('plasentaLahirTgl','plasentaLahirJam') }}</td><td class="lbl">Cara Lahir</td><td>{{ $v('plasentaCara') }}</td></tr>
        <tr><td class="lbl">Jenis</td><td>{{ $v('plasentaJenis') }}</td><td class="lbl">Berat / Diameter</td><td>{{ $v('plasentaBerat') }} gr / {{ $v('plasentaDiameter') }} cm</td></tr>
    </table>

    {{-- 4. Tali Pusat --}}
    <div class="pa-sec">4. TALI PUSAT</div>
    <table class="pa">
        <tr><td class="lbl">Insersi</td><td>{{ $v('taliPusatInsersi') }}</td><td class="lbl">Panjang</td><td>{{ $v('taliPusatPanjang') }} cm</td></tr>
    </table>

    {{-- 5. Selaput Janin --}}
    <div class="pa-sec">5. SELAPUT JANIN</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan</td><td>{{ $v('selaputKeadaan') }}</td><td class="lbl">Robekan</td><td>{{ $v('selaputRobekan') }}</td></tr>
        <tr><td class="lbl">Lain-lain</td><td colspan="3">{{ $v('selaputLain') }}</td></tr>
    </table>

    {{-- 6. Perlukaan Jalan Lahir --}}
    <div class="pa-sec">6. PERLUKAAN JALAN LAHIR</div>
    <table class="pa">
        <tr><td class="lbl">Luka Perineum</td><td>{{ $v('lukaPerineum') }}</td><td class="lbl">Episiotomi</td><td>{{ $v('episiotomi') }}</td></tr>
        <tr><td class="lbl">Ruptura Perinei</td><td>{{ $v('rupturaPerinei') }}</td><td class="lbl">Luka Vagina</td><td>{{ $v('lukaVagina') }}</td></tr>
        <tr><td class="lbl">Luka Serviks</td><td colspan="3">{{ $v('lukaServiks') }}</td></tr>
    </table>

    {{-- 7. Kala IV --}}
    <div class="pa-sec">7. KALA IV</div>
    <table class="pa">
        <tr><td class="lbl">Hb</td><td>{{ $v('kalaIvHb') }}</td><td class="lbl">Suhu</td><td>{{ $v('kalaIvSuhu') }} °C</td></tr>
        <tr><td class="lbl">TD</td><td>{{ $v('kalaIvTd') }} mmHg</td><td class="lbl">Nadi / RR</td><td>{{ $v('kalaIvNadi') }} / {{ $v('kalaIvRr') }} x/mnt</td></tr>
        <tr><td class="lbl">TFU</td><td>{{ $v('kalaIvTfu') }}</td><td class="lbl">Kontraksi Uterus</td><td>{{ $v('kalaIvKontraksi') }}</td></tr>
        <tr><td class="lbl">Perdarahan Kala III</td><td>{{ $v('perdarahanKalaIii') }} cc</td><td class="lbl">Perdarahan Kala IV</td><td>{{ $v('perdarahanKalaIv') }} cc</td></tr>
    </table>

    {{-- 8. IMD, Rawat Gabung & ASI (PONEK / Prognas 1) --}}
    <div class="pa-sec">8. IMD, RAWAT GABUNG &amp; ASI (PONEK / PROGNAS 1)</div>
    <table class="pa">
        <tr><td class="lbl">IMD Dilakukan</td><td>{{ $v('imdDilakukan') }}{{ filled($form['imdJam'] ?? null) ? ' — jam ' . e($form['imdJam']) : '' }}{{ filled($form['imdDurasiMenit'] ?? null) ? ' (' . e($form['imdDurasiMenit']) . ' menit)' : '' }}</td><td class="lbl">Alasan bila tidak</td><td>{{ $v('imdAlasanTidak') }}</td></tr>
        <tr><td class="lbl">Rawat Gabung</td><td>{{ $v('rawatGabung') }}</td><td class="lbl">Konseling ASI</td><td>{{ $v('asiKonseling') }}</td></tr>
        <tr><td class="lbl">PMK (Metode Kanguru)</td><td colspan="3">{{ $v('pmkDilakukan') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Dokter' }}<br>
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
