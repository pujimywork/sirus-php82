{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-awal-obstetri-ri/cetak-pengkajian-awal-obstetri-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AWAL OBSTETRI">

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
        $penyakit = collect($form['penyakitPenting'] ?? [])->filter()->implode(', ');
        if (filled($form['penyakitLain'] ?? null)) {
            $penyakit = trim($penyakit . ($penyakit ? ', ' : '') . e($form['penyakitLain']));
        }
    @endphp

    <style>
        .pa-sec { font-size:11px; font-weight:bold; background:#eef2ee; padding:3px 6px; border:1px solid #999; margin-top:6px; }
        table.pa { width:100%; border-collapse:collapse; font-size:10px; }
        table.pa td { border:1px solid #999; padding:2px 5px; vertical-align:top; }
        table.pa td.lbl { width:22%; color:#333; background:#f7f7f7; }
    </style>

    {{-- 1. Data Pengkajian --}}
    <div class="pa-sec">1. DATA PENGKAJIAN</div>
    <table class="pa">
        <tr><td class="lbl">Jam Pengkajian</td><td>{{ $v('jamPengkajian') }}</td><td class="lbl">Cara Masuk</td><td>{{ $v('caraMasuk') }}{{ filled($form['caraMasukRujukan'] ?? null) ? ' — ' . e($form['caraMasukRujukan']) : '' }}</td></tr>
    </table>

    {{-- 2 & 3. Sosial --}}
    <div class="pa-sec">2. DATA SOSIAL PASIEN & SUAMI/PENANGGUNG JAWAB</div>
    <table class="pa">
        <tr><td class="lbl">Pekerjaan</td><td>{{ $v('pekerjaan') }}</td><td class="lbl">Pendidikan</td><td>{{ $v('pendidikan') }}</td></tr>
        <tr><td class="lbl">Agama</td><td>{{ $v('agama') }}</td><td class="lbl">Suku Bangsa</td><td>{{ $v('suku') }}</td></tr>
        <tr><td class="lbl">Psiko-sosio-spiritual</td><td>{{ $v('psikososial') }}</td><td class="lbl">Ekonomi</td><td>{{ $v('ekonomi') }}</td></tr>
        <tr><td class="lbl">Nama Suami/PJ</td><td>{{ $v('namaSuami') }}</td><td class="lbl">Umur</td><td>{{ $v('umurSuami') }}</td></tr>
        <tr><td class="lbl">Pekerjaan Suami</td><td>{{ $v('pekerjaanSuami') }}</td><td class="lbl">Pendidikan Suami</td><td>{{ $v('pendidikanSuami') }}</td></tr>
    </table>

    {{-- 4. Riwayat --}}
    <div class="pa-sec">4. RIWAYAT</div>
    <table class="pa">
        <tr><td class="lbl">Alergi Obat</td><td>{{ $v('alergiObat') }}</td><td class="lbl">Riwayat Obat</td><td>{{ $v('riwayatObat') }}</td></tr>
        <tr><td class="lbl">Penyakit Penting</td><td colspan="3">{{ $penyakit ?: '-' }}</td></tr>
    </table>

    {{-- 5. Status Obstetri & KB --}}
    <div class="pa-sec">5. STATUS OBSTETRI & KB</div>
    <table class="pa">
        <tr><td class="lbl">G - P - A</td><td>{{ $v('gravida') }} - {{ $v('para') }} - {{ $v('abortus') }}</td><td class="lbl">KB Terakhir</td><td>{{ $v('kbTerakhir') }}</td></tr>
        <tr><td class="lbl">ANC</td><td>{{ $v('anc') }}</td><td class="lbl">TT</td><td>{{ $v('tt') }}</td></tr>
        <tr><td class="lbl">Menikah</td><td>{{ $v('menikahKali') }} kali, lama {{ $v('menikahLama') }} th</td><td class="lbl">TB / BB</td><td>{{ $v('tinggiBadan') }} cm / {{ $v('beratBadan') }} kg</td></tr>
        <tr><td class="lbl">HPHT</td><td>{{ $v('hpht') }}</td><td class="lbl">HPL / TP</td><td>{{ $v('hpl') }}</td></tr>
    </table>

    {{-- 6. Riwayat Persalinan Sekarang --}}
    <div class="pa-sec">6. RIWAYAT PERSALINAN SEKARANG</div>
    <table class="pa">
        <tr><td class="lbl">ANC dilakukan di</td><td>{{ $v('ancDilakukanDi') }}</td><td class="lbl">Ketuban</td><td>{{ $v('ketubanStatus') }}</td></tr>
        <tr><td class="lbl">His mulai</td><td>{{ $tgljam('hisMulaiTgl','hisMulaiJam') }}</td><td class="lbl">Ketuban pecah</td><td>{{ $tgljam('ketubanTgl','ketubanJam') }}</td></tr>
        <tr><td class="lbl">Darah/lendir</td><td>{{ $tgljam('keluarDarahTgl','keluarDarahJam') }}</td><td class="lbl">Rasa mengejan</td><td>{{ $tgljam('rasaMengejanTgl','rasaMengejanJam') }}</td></tr>
        <tr><td class="lbl">Perawatan sebelumnya</td><td colspan="3">{{ $v('perawatanSebelumnya') }}</td></tr>
    </table>

    {{-- 7. Status Umum / TTV --}}
    <div class="pa-sec">7. STATUS UMUM & TANDA VITAL</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan Umum</td><td>{{ $v('keadaanUmum') }}</td><td class="lbl">TD</td><td>{{ $v('td') }} mmHg</td></tr>
        <tr><td class="lbl">Nadi / RR</td><td>{{ $v('nadi') }} / {{ $v('respirasi') }} x/mnt</td><td class="lbl">Suhu (R/Ax)</td><td>{{ $v('suhuRectal') }} / {{ $v('suhuAxiler') }} °C</td></tr>
        <tr><td class="lbl">Conjungtiva / Edema</td><td>{{ $v('conjungtiva') }} / {{ $v('edema') }}</td><td class="lbl">Cor / Pulmo</td><td>{{ $v('cor') }} / {{ $v('pulmo') }}</td></tr>
    </table>

    {{-- 8 & 9. Status Obstetri + VT --}}
    <div class="pa-sec">8. STATUS OBSTETRI (LUAR) & 9. PEMERIKSAAN DALAM (VT)</div>
    <table class="pa">
        <tr><td class="lbl">TFU</td><td>{{ $v('tfu') }} cm</td><td class="lbl">Letak Janin</td><td>{{ $v('letakJanin') }}</td></tr>
        <tr><td class="lbl">His / DJJ</td><td>{{ $v('his') }} / {{ $v('djj') }} x/mnt</td><td class="lbl">TBJ</td><td>{{ $v('tbj') }} gr</td></tr>
        <tr><td class="lbl">VT — Pembukaan</td><td>{{ $v('vtPembukaan') }}</td><td class="lbl">Effacement</td><td>{{ $v('vtEffacement') }}</td></tr>
        <tr><td class="lbl">Presentasi / Denominator</td><td>{{ $v('vtPresentasi') }} / {{ $v('vtDenominator') }}</td><td class="lbl">Ketuban / Hodge</td><td>{{ $v('vtKetuban') }} / {{ $v('vtHodge') }}</td></tr>
        <tr><td class="lbl">Ukuran Panggul Dalam</td><td colspan="3">{{ $v('vtPanggul') }}</td></tr>
    </table>

    {{-- 10. Skrining --}}
    <div class="pa-sec">10. SKRINING (PP 1.2)</div>
    <table class="pa">
        <tr><td class="lbl">Skala Nyeri</td><td>{{ $v('skalaNyeri') }}</td><td class="lbl">Risiko Jatuh</td><td>{{ $v('risikoJatuh') }}</td></tr>
        <tr><td class="lbl">Skrining Gizi</td><td>{{ $v('skriningGizi') }}</td><td class="lbl">Pengkajian Fungsional</td><td>{{ $v('pengkajianFungsional') }}</td></tr>
        <tr><td class="lbl">Kebutuhan Edukasi</td><td colspan="3">{{ $v('kebutuhanEdukasi') }}</td></tr>
    </table>

    {{-- 11 & 12. Lab, Diagnosa & Rencana --}}
    <div class="pa-sec">11. LABORATORIUM &nbsp; · &nbsp; 12. DIAGNOSA & RENCANA</div>
    <table class="pa">
        <tr><td class="lbl">Lab Darah / Urine</td><td colspan="3">{{ $v('labDarah') }} / {{ $v('labUrine') }}</td></tr>
        <tr><td class="lbl">Diagnosa</td><td colspan="3">{{ $v('diagnosa') }}</td></tr>
        <tr><td class="lbl">Rencana Tindakan/Terapi</td><td colspan="3">{{ $v('rencanaTindakan') }}</td></tr>
        <tr><td class="lbl">Discharge Planning</td><td colspan="3">{{ $v('dischargePlanning') }}</td></tr>
    </table>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $form['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $form['ttd'] ?? 'Bidan/Dokter' }}<br>
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
