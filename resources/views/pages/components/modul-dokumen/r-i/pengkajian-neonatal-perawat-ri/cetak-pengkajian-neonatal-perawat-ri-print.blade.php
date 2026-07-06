{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-neonatal-perawat-ri/cetak-pengkajian-neonatal-perawat-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN KEPERAWATAN NEONATAL">

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
        $vlist = fn($k) => collect($form[$k] ?? [])->filter()->implode(', ') ?: '-';
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Riwayat Penyakit --}}
    <div class="pa-sec">1. RIWAYAT PENYAKIT</div>
    <table class="pa">
        <tr><td class="lbl">Keluhan Utama</td><td>{{ $v('keluhanUtama') }}</td></tr>
    </table>

    {{-- 2. Antenatal --}}
    <div class="pa-sec">2. ANTENATAL</div>
    <table class="pa">
        <tr><td class="lbl">ANC</td><td>{{ $v('anc') }}{{ filled($form['ancTempat'] ?? null) ? ' — ' . e($form['ancTempat']) : '' }}</td><td class="lbl">TT</td><td>{{ $v('tt') }}{{ filled($form['ttKali'] ?? null) ? ' (' . e($form['ttKali']) . ' kali)' : '' }}</td></tr>
        <tr><td class="lbl">Penyulit Kehamilan</td><td>{{ $vlist('penyulitKehamilan') }}</td><td class="lbl">Penyakit Menyertai</td><td>{{ $vlist('penyakitMenyertai') }}</td></tr>
    </table>

    {{-- 3. Intranatal --}}
    <div class="pa-sec">3. INTRANATAL</div>
    <table class="pa">
        <tr><td class="lbl">Umur Kehamilan</td><td>{{ $v('umurKehamilan') }} minggu</td><td class="lbl">Kondisi Kelahiran</td><td>{{ $v('kondisiKelahiran') }}</td></tr>
        <tr><td class="lbl">Jenis Persalinan</td><td>{{ $v('jenisPersalinan') }}</td><td class="lbl">Penolong</td><td>{{ $v('penolong') }}</td></tr>
        <tr><td class="lbl">Penyulit Persalinan</td><td>{{ $vlist('penyulitPersalinan') }}</td><td class="lbl">Komplikasi</td><td>{{ $vlist('komplikasi') }}{{ filled($form['kpdLamaJam'] ?? null) ? ' (KPD ' . e($form['kpdLamaJam']) . ' jam)' : '' }}</td></tr>
    </table>

    {{-- 4. Postnatal — Antropometri --}}
    <div class="pa-sec">4. POSTNATAL — ANTROPOMETRI</div>
    <table class="pa">
        <tr><td class="lbl">BBL / PB</td><td>{{ $v('bbl') }} gr / {{ $v('pb') }} cm</td><td class="lbl">LK / LD</td><td>{{ $v('lk') }} / {{ $v('ld') }} cm</td></tr>
        <tr><td class="lbl">LILA / Lingkar Perut</td><td>{{ $v('lila') }} / {{ $v('lingkarPerut') }} cm</td><td class="lbl">APGAR (1'/5')</td><td>{{ $v('apgar1') }} / {{ $v('apgar5') }}</td></tr>
        <tr><td class="lbl">Trauma Lahir</td><td>{{ $v('traumaLahir') }}{{ filled($form['traumaKet'] ?? null) ? ' — ' . e($form['traumaKet']) : '' }}</td><td class="lbl">Usaha Nafas</td><td>{{ $v('usahaNafas') }}</td></tr>
        <tr><td class="lbl">Imunisasi</td><td colspan="3">{{ $v('imunisasi') }}{{ filled($form['imunisasiKet'] ?? null) ? ' — ' . e($form['imunisasiKet']) : '' }}</td></tr>
    </table>

    {{-- 5. Pemeriksaan Fisik --}}
    <div class="pa-sec">5. PEMERIKSAAN FISIK</div>
    <table class="pa">
        <tr><td class="lbl">Kepala</td><td>{{ $v('kepalaBentuk') }}</td><td class="lbl">Mata (Konjungtiva/Sklera)</td><td>{{ $v('mataKonjungtiva') }} / {{ $v('mataSklera') }}</td></tr>
        <tr><td class="lbl">Telinga / Hidung</td><td>{{ $v('telinga') }} / {{ $v('hidung') }}</td><td class="lbl">Mulut (Reflek Isap/Bentuk)</td><td>{{ $v('mulutReflekIsap') }} / {{ $v('mulutBentuk') }}</td></tr>
        <tr><td class="lbl">Dada / Perut</td><td>{{ $v('dada') }} / {{ $v('perutBentuk') }}</td><td class="lbl">Tali Pusat</td><td>{{ $v('taliPusat') }}</td></tr>
        <tr><td class="lbl">Anus / Ekstremitas</td><td colspan="3">{{ $v('anus') }} / {{ $v('ekstremitas') }}</td></tr>
    </table>

    {{-- 6. Review Sistem --}}
    <div class="pa-sec">6. REVIEW SISTEM (B1–B6)</div>
    <table class="pa">
        <tr><td class="lbl">B1 Pernafasan</td><td>{{ $v('b1Pernafasan') }}, RR {{ $v('b1FrekuensiNafas') }} x/mnt, {{ $vlist('b1SuaraNafas') }}</td></tr>
        <tr><td class="lbl">B2 Kardiovaskuler</td><td>Bunyi {{ $v('b2Bunyi') }}, CRT {{ $v('b2CRT') }}, Akral {{ $v('b2Akral') }}, Nadi {{ $v('b2Nadi') }} x/mnt, Suhu {{ $v('b2Suhu') }} °C</td></tr>
        <tr><td class="lbl">B3 Persyarafan</td><td>Kesadaran {{ $v('b3Kesadaran') }}, Reflek {{ $vlist('b3Reflek') }}</td></tr>
        <tr><td class="lbl">B4 Perkemihan</td><td>BAK {{ $v('b4Bak') }}, Warna {{ $v('b4Warna') }}</td></tr>
        <tr><td class="lbl">B5 Pencernaan</td><td>BAB {{ $vlist('b5Bab') }}, Minum {{ $vlist('b5Minum') }}, Jenis Susu {{ $v('b5JenisSusu') }}</td></tr>
        <tr><td class="lbl">B6 Musk. & Integumen</td><td>Pergerakan {{ $v('b6Pergerakan') }}, Kulit {{ $vlist('b6Kulit') }}, Turgor {{ $v('b6Turgor') }}</td></tr>
    </table>

    {{-- 7. Skala Nyeri NIPS & 8. Diagnosa Keperawatan --}}
    <div class="pa-sec">7. SKALA NYERI (NIPS) &nbsp; · &nbsp; 8. DIAGNOSA KEPERAWATAN</div>
    <table class="pa">
        <tr><td class="lbl">NIPS</td><td colspan="3">Total {{ $v('nipsTotal') }} — {{ $v('nipsInterpretasi') }}</td></tr>
        <tr><td class="lbl">Diagnosa Keperawatan</td><td colspan="3">{{ $vlist('diagnosaKeperawatan') }}</td></tr>
    </table>

    {{-- 9. Penunjang --}}
    <div class="pa-sec">9. PENUNJANG</div>
    <table class="pa">
        <tr><td class="lbl">Laboratorium</td><td colspan="3">{{ $v('labPenunjang') }}</td></tr>
        <tr><td class="lbl">Lain-lain</td><td colspan="3">{{ $v('lainPenunjang') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Perawat/Bidan' }}<br>
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
