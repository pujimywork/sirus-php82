{{-- resources/views/pages/components/modul-dokumen/r-i/pengkajian-awal-ginekologi-ri/cetak-pengkajian-awal-ginekologi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AWAL GINEKOLOGI">

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

    {{-- 5. Riwayat Ginekologi --}}
    <div class="pa-sec">5. RIWAYAT GINEKOLOGI</div>
    <table class="pa">
        <tr><td class="lbl">HPHT</td><td>{{ $v('hpht') }}</td><td class="lbl">Menarche</td><td>{{ $v('menarcheUmur') }} th</td></tr>
        <tr><td class="lbl">Menopause</td><td>{{ $v('menopause') }}</td><td class="lbl">Kontrasepsi</td><td>{{ $v('kontrasepsi') }}</td></tr>
        <tr><td class="lbl">Menikah</td><td>{{ $v('menikahKali') }} kali, lama {{ $v('menikahLama') }} th</td><td class="lbl">Anak Hidup / Mati</td><td>{{ $v('anakHidup') }} / {{ $v('anakMati') }}</td></tr>
        <tr><td class="lbl">Umur Anak Terkecil</td><td>{{ $v('anakTerkecilUmur') }}</td><td class="lbl">Riwayat Haid</td><td>{{ $v('riwayatHaid') }}</td></tr>
        <tr><td class="lbl">Riwayat Keputihan</td><td colspan="3">{{ $v('riwayatKeputihan') }}</td></tr>
        <tr><td class="lbl">Riwayat Persalinan Lalu</td><td colspan="3">{{ $v('riwayatPersalinanLalu') }}</td></tr>
    </table>

    {{-- 6. Keluhan --}}
    <div class="pa-sec">6. KELUHAN</div>
    <table class="pa">
        <tr><td class="lbl">Keluhan Utama</td><td colspan="3">{{ $v('keluhanUtama') }}</td></tr>
        <tr><td class="lbl">Riwayat Penyakit Sekarang</td><td colspan="3">{{ $v('riwayatPenyakitSekarang') }}</td></tr>
    </table>

    {{-- 7. Status Umum / TTV --}}
    <div class="pa-sec">7. STATUS UMUM & TANDA VITAL</div>
    <table class="pa">
        <tr><td class="lbl">Keadaan Umum</td><td>{{ $v('keadaanUmum') }}</td><td class="lbl">TD</td><td>{{ $v('td') }} mmHg</td></tr>
        <tr><td class="lbl">Nadi / RR</td><td>{{ $v('nadi') }} / {{ $v('respirasi') }} x/mnt</td><td class="lbl">Suhu (R/Ax)</td><td>{{ $v('suhuRectal') }} / {{ $v('suhuAxiler') }} °C</td></tr>
        <tr><td class="lbl">Conjungtiva / Edema</td><td>{{ $v('conjungtiva') }} / {{ $v('edema') }}</td><td class="lbl">Cor / Pulmo</td><td>{{ $v('cor') }} / {{ $v('pulmo') }}</td></tr>
    </table>

    {{-- 8. Pemeriksaan Dalam --}}
    <div class="pa-sec">8. PEMERIKSAAN DALAM</div>
    <table class="pa">
        <tr><td class="lbl">Jenis Pemeriksaan</td><td>{{ $v('jenisPemeriksaan') }}</td><td class="lbl">Vulva / Vagina</td><td>{{ $v('vulvaVagina') }}</td></tr>
        <tr><td class="lbl">Corpus Uteri</td><td>{{ $v('corpusUteri') }}</td><td class="lbl">Portio</td><td>{{ $v('portio') }}</td></tr>
        <tr><td class="lbl">Adnexa Kanan / Kiri</td><td>{{ $v('adnexaKanan') }} / {{ $v('adnexaKiri') }}</td><td class="lbl">Cavum Douglasi</td><td>{{ $v('cavumDouglasi') }}</td></tr>
    </table>

    {{-- 9. Skrining --}}
    <div class="pa-sec">9. SKRINING (PP 1.2)</div>
    <table class="pa">
        <tr><td class="lbl">Skala Nyeri</td><td>{{ $v('skalaNyeri') }}</td><td class="lbl">Risiko Jatuh</td><td>{{ $v('risikoJatuh') }}</td></tr>
        <tr><td class="lbl">Skrining Gizi</td><td>{{ $v('skriningGizi') }}</td><td class="lbl">Pengkajian Fungsional</td><td>{{ $v('pengkajianFungsional') }}</td></tr>
        <tr><td class="lbl">Kebutuhan Edukasi</td><td colspan="3">{{ $v('kebutuhanEdukasi') }}</td></tr>
    </table>

    {{-- 10. Status Lokalis (Dokter) --}}
    <div class="pa-sec">10. STATUS LOKALIS (DOKTER)</div>
    <table class="pa">
        <tr><td class="lbl">Abdomen</td><td colspan="3">{{ $v('abdomen') }}</td></tr>
        <tr><td class="lbl">Genitalia</td><td colspan="3">{{ $v('genitalia') }}</td></tr>
    </table>

    {{-- 11. Diagnosa & Rencana --}}
    <div class="pa-sec">11. DIAGNOSA & RENCANA</div>
    <table class="pa">
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
