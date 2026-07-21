{{-- resources/views/pages/components/modul-dokumen/r-i/akhir-hayat-ri/cetak-akhir-hayat-ri-print.blade.php --}}
{{-- Pengkajian Akhir Hayat (End of Life) — gabungan formulir KARS + RM.RI.62 --}}

<x-pdf.layout-a4-with-out-background title="PENGKAJIAN AKHIR HAYAT">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        @php
            $identitasPasien = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($identitasPasien['alamat'] ?? '-') .
                    (!empty($identitasPasien['rt']) ? ' RT ' . $identitasPasien['rt'] : '') .
                    (!empty($identitasPasien['rw']) ? '/RW ' . $identitasPasien['rw'] : '') .
                    (!empty($identitasPasien['desaName']) ? ', ' . $identitasPasien['desaName'] : '') .
                    (!empty($identitasPasien['kecamatanName']) ? ', ' . $identitasPasien['kecamatanName'] : ''),
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
        $entry = $data['entry'] ?? [];
        $form = $entry['form'] ?? [];
        $label = $data['opsiLabel'] ?? [];
        $clause = $data['clause'] ?? ['persetujuan' => ''];

        $daftar = function (array $keys, array $peta, ?string $lainnya = null): string {
            $teks = collect($keys)->map(fn($k) => $peta[$k] ?? $k)->implode(', ');
            if (filled($lainnya)) {
                $teks = trim($teks . ($teks ? ', ' : '') . $lainnya);
            }
            return $teks !== '' ? $teks : '-';
        };
        $isi = fn(?string $v) => filled($v) ? $v : '-';
        $yaTidak = fn(?string $v) => match ($v) {
            'ya' => 'Ya',
            'tidak' => 'Tidak',
            default => '-',
        };
        $skala = fn(?string $v) => ($label['skala'] ?? [])[$v] ?? '-';

        $jenisAsesmen = ($form['jenisAsesmen'] ?? 'awal') === 'ulang' ? 'ULANG' : 'AWAL';

        $reaksiPasien = $daftar(data_get($form, 'psikososial.reaksiPasien.opsi', []) ?? [], $label['reaksiPasien'] ?? []);
        $masalahPasien = $daftar(data_get($form, 'psikososial.reaksiPasien.masalah', []) ?? [], $label['masalahPasien'] ?? []);
        $kondisiKeluarga = $daftar(data_get($form, 'psikososial.keluarga.opsi', []) ?? [], $label['kondisiKeluarga'] ?? []);
        $masalahKeluarga = $daftar(data_get($form, 'psikososial.keluarga.masalah', []) ?? [], $label['masalahKeluarga'] ?? []);
        $dukungan = $daftar(data_get($form, 'dukungan.opsi', []) ?? [], $label['dukungan'] ?? [], data_get($form, 'dukungan.lainnya'));
        $intervensiKep = $daftar(data_get($form, 'intervensi.keperawatan', []) ?? [], $label['intervensiKeperawatan'] ?? []);
        $intervensiMedis = $daftar(data_get($form, 'intervensi.medis', []) ?? [], $label['intervensiMedis'] ?? []);

        $prognosis = ($label['prognosis'] ?? [])[data_get($form, 'medis.prognosis')] ?? '-';
        if (filled(data_get($form, 'medis.prognosisCatatan'))) {
            $prognosis = trim($prognosis . ' — ' . data_get($form, 'medis.prognosisCatatan'));
        }

        $spiritualBentuk = collect([
            'perluDidoakan' => 'didoakan',
            'perluBimbingan' => 'bimbingan rohani',
            'perluPendampingan' => 'pendampingan rohani',
        ])->filter(fn($labelItem, $key) => data_get($form, 'spiritual.' . $key) === 'ya')->values()->implode(', ');

        $alternatif = match (data_get($form, 'alternatif.pilihan')) {
            'tidak' => 'Tidak ada',
            'autopsi' => 'Autopsi',
            'donasi' => 'Donasi organ: ' . (data_get($form, 'alternatif.donasiOrgan') ?: '-'),
            'lainnya' => data_get($form, 'alternatif.lainnya') ?: 'Lainnya',
            default => '-',
        };

        $rencana = match (data_get($form, 'rencana.pilihan')) {
            'rs' => 'Tetap dirawat di RS',
            'rumah' => 'Dirawat di rumah',
            default => '-',
        };

        $hubunganKeluarga = ($label['hubungan'] ?? [])[data_get($form, 'ttd.keluargaHubungan')] ?? data_get($form, 'ttd.keluargaHubungan', '');

        $ttv = collect([
            'TD' => trim((string) data_get($form, 'fisik.sistolik') . '/' . (string) data_get($form, 'fisik.distolik'), '/') . ' mmHg',
            'Nadi' => data_get($form, 'fisik.nadi') ? data_get($form, 'fisik.nadi') . ' x/mnt' : null,
            'Nafas' => data_get($form, 'fisik.respirasi') ? data_get($form, 'fisik.respirasi') . ' x/mnt' : null,
            'Suhu' => data_get($form, 'fisik.suhu') ? data_get($form, 'fisik.suhu') . ' °C' : null,
            'SpO2' => data_get($form, 'fisik.spo2') ? data_get($form, 'fisik.spo2') . ' %' : null,
        ])->filter(fn($v) => filled($v) && $v !== ' mmHg')->map(fn($v, $k) => $k . ': ' . $v)->implode('  ·  ');

        $antropometri = collect([
            'TB' => data_get($form, 'fisik.tb') ? data_get($form, 'fisik.tb') . ' cm' : null,
            'BB' => data_get($form, 'fisik.bb') ? data_get($form, 'fisik.bb') . ' kg' : null,
            'BMI' => data_get($form, 'fisik.bmi') ?: null,
        ])->filter()->map(fn($v, $k) => $k . ': ' . $v)->implode('  ·  ');
    @endphp

    <style>
        table.ah { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 6px; }
        table.ah td { padding: 2px 4px; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        table.ah td.lbl { width: 32%; color: #374151; }
        .ah-sec { font-size: 10px; font-weight: bold; background: #f3f4f6; padding: 3px 4px; margin: 8px 0 4px; }
    </style>

    <table style="width:100%; font-size:10px; margin-bottom:4px;">
        <tr>
            <td style="width:60%;"><strong>Asesmen {{ $jenisAsesmen }}</strong></td>
            <td style="width:40%; text-align:right;">Tanggal / Pukul: {{ $form['tglAsesmen'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- 1. DIAGNOSIS & KONDISI MEDIS --}}
    <div class="ah-sec">1. DIAGNOSIS &amp; KONDISI MEDIS</div>
    <table class="ah">
        <tr><td class="lbl">Diagnosis utama</td><td>{{ $isi(data_get($form, 'medis.diagnosaUtama')) }}</td></tr>
        <tr><td class="lbl">Diagnosis sekunder</td><td>{{ $isi(data_get($form, 'medis.diagnosaSekunder')) }}</td></tr>
        <tr><td class="lbl">Riwayat penyakit &amp; perawatan</td><td>{{ $isi(data_get($form, 'medis.riwayat')) }}</td></tr>
        <tr><td class="lbl">Prognosis</td><td>{{ $prognosis }}</td></tr>
    </table>

    {{-- 2. PENILAIAN FISIK & SIMPTOM --}}
    <div class="ah-sec">2. PENILAIAN FISIK &amp; SIMPTOM</div>
    <table class="ah">
        <tr><td class="lbl">Antropometri</td><td>{{ $antropometri ?: '-' }}</td></tr>
        <tr><td class="lbl">Tanda-tanda vital</td><td>{{ $ttv ?: '-' }}</td></tr>
        <tr>
            <td class="lbl">Nyeri</td>
            <td>
                {{ $skala(data_get($form, 'simptom.nyeri')) }}@if (filled(data_get($form, 'simptom.nyeriLokasi'))) — lokasi: {{ data_get($form, 'simptom.nyeriLokasi') }}@endif @if (filled(data_get($form, 'simptom.nyeriDeskripsi'))) ({{ data_get($form, 'simptom.nyeriDeskripsi') }})@endif
            </td>
        </tr>
        <tr><td class="lbl">Sesak nafas</td><td>{{ $skala(data_get($form, 'simptom.sesak')) }}</td></tr>
        <tr><td class="lbl">Mual / muntah</td><td>{{ $skala(data_get($form, 'simptom.mualMuntah')) }}</td></tr>
        <tr><td class="lbl">Kelelahan</td><td>{{ $skala(data_get($form, 'simptom.kelelahan')) }}</td></tr>
        <tr><td class="lbl">Gejala lainnya</td><td>{{ $isi(data_get($form, 'simptom.lainnya')) }}</td></tr>
    </table>

    {{-- 3. PSIKOSOSIAL & SPIRITUAL --}}
    <div class="ah-sec">3. PSIKOSOSIAL &amp; SPIRITUAL</div>
    <table class="ah">
        <tr>
            <td class="lbl">Kondisi pasien</td>
            <td>
                Emosional: {{ $isi(data_get($form, 'psikososial.emosional')) }}<br>
                Reaksi atas penyakit: {{ $reaksiPasien }}<br>
                <em>Masalah keperawatan:</em> {{ $masalahPasien }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Kondisi keluarga (saat ini &amp; risiko setelah ditinggalkan)</td>
            <td>
                Emosional: {{ $isi(data_get($form, 'psikososial.emosionalKeluarga')) }}<br>
                Kondisi: {{ $kondisiKeluarga }}<br>
                <em>Masalah keperawatan:</em> {{ $masalahKeluarga }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Kebutuhan spiritual</td>
            <td>
                {{ $yaTidak(data_get($form, 'spiritual.perluPelayanan')) }}@if (data_get($form, 'spiritual.perluPelayanan') === 'ya')@if (filled(data_get($form, 'spiritual.oleh'))) — oleh: {{ data_get($form, 'spiritual.oleh') }}@endif @if ($spiritualBentuk) ; bentuk: {{ $spiritualBentuk }}@endif @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Orang yang ingin dihubungi</td>
            <td>
                @if (data_get($form, 'psikososial.orangDihubungi.ada') === 'ya')
                    {{ $isi(data_get($form, 'psikososial.orangDihubungi.nama')) }}
                    ({{ $isi(data_get($form, 'psikososial.orangDihubungi.hubungan')) }}),
                    {{ $isi(data_get($form, 'psikososial.orangDihubungi.alamat')) }},
                    Telp: {{ $isi(data_get($form, 'psikososial.orangDihubungi.telp')) }}
                @else
                    {{ data_get($form, 'psikososial.orangDihubungi.ada') === 'tidak' ? 'Tidak ada' : '-' }}
                @endif
            </td>
        </tr>
    </table>

    {{-- 4. RENCANA, INTERVENSI & EDUKASI --}}
    <div class="ah-sec">4. RENCANA PERAWATAN, INTERVENSI &amp; EDUKASI</div>
    <table class="ah">
        <tr>
            <td class="lbl">Rencana perawatan</td>
            <td>
                {{ $rencana }}
                @if (data_get($form, 'rencana.pilihan') === 'rumah')
                    — lingkungan siap: {{ $yaTidak(data_get($form, 'rencana.lingkunganSiap')) }};
                    ada yang merawat: {{ $yaTidak(data_get($form, 'rencana.adaPerawat')) }}@if (data_get($form, 'rencana.adaPerawat') === 'ya') ({{ $isi(data_get($form, 'rencana.perawatOleh')) }})@endif;
                    Home Care: {{ $yaTidak(data_get($form, 'rencana.perluHomeCare')) }}
                    @if (filled(data_get($form, 'edukasi.rencanaDiRumah')))
                        <br>Instruksi perawatan di rumah: {{ data_get($form, 'edukasi.rencanaDiRumah') }}
                    @endif
                @endif
            </td>
        </tr>
        <tr><td class="lbl">Dukungan / kelonggaran pelayanan</td><td>{{ $dukungan }}</td></tr>
        <tr><td class="lbl">Kebutuhan pelayanan lain</td><td>{{ $alternatif }}</td></tr>
        <tr><td class="lbl">Intervensi keperawatan</td><td>{{ $intervensiKep }}</td></tr>
        <tr><td class="lbl">Intervensi medis</td><td>{{ $intervensiMedis }}@if (filled(data_get($form, 'intervensi.catatan')))<br>{{ data_get($form, 'intervensi.catatan') }}@endif</td></tr>
        <tr><td class="lbl">Pendidikan kesehatan</td><td>{{ $isi(data_get($form, 'edukasi.pendidikanKesehatan')) }}</td></tr>
    </table>

    <p style="font-size:10px; margin:10px 0 4px;">{{ $clause['persetujuan'] }}</p>

    {{-- TANDA TANGAN — 3 kolom. Pakai <table>: dompdf tanpa flex/grid (docs/ttd-pattern-pdf-print.md) --}}
    <table style="width:100%; margin-top:8px; font-size:10px;">
        <tr>
            <td style="width:34%; text-align:center;">Pasien / Keluarga</td>
            <td style="width:33%; text-align:center;">Saksi</td>
            <td style="width:33%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }},
                {{ data_get($form, 'ttd.petugasDate') ?: ($data['tglCetak'] ?? '') }}<br>
                Petugas (Dokter / Perawat)
            </td>
        </tr>
        <tr>
            <td style="height:64px; text-align:center;">
                @if (!empty(data_get($form, 'ttd.keluargaTTD')))
                    <img src="{{ data_get($form, 'ttd.keluargaTTD') }}" style="height:56px;" alt="TTD Pasien/Keluarga">
                @else
                    &nbsp;
                @endif
            </td>
            <td style="height:64px; text-align:center;">
                @if (!empty(data_get($form, 'ttd.saksiTTD')))
                    <img src="{{ data_get($form, 'ttd.saksiTTD') }}" style="height:56px;" alt="TTD Saksi">
                @else
                    &nbsp;
                @endif
            </td>
            <td style="height:64px; text-align:center;">
                @if (!empty($data['ttdPetugasPath']))
                    <img src="{{ $data['ttdPetugasPath'] }}" style="height:56px;" alt="TTD Petugas">
                @else
                    &nbsp;
                @endif
            </td>
        </tr>
        <tr>
            <td style="text-align:center;">
                <span style="border-top:1px solid #000; padding:0 18px;">
                    {{ data_get($form, 'ttd.keluargaNama') ?: '(Nama terang &amp; tanda tangan)' }}
                </span>
                @if ($hubunganKeluarga)
                    <br><span style="font-size:9px;">({{ $hubunganKeluarga }})</span>
                @endif
            </td>
            <td style="text-align:center;">
                <span style="border-top:1px solid #000; padding:0 18px;">
                    {{ data_get($form, 'ttd.saksiNama') ?: '(Nama terang &amp; tanda tangan)' }}
                </span>
            </td>
            <td style="text-align:center;">
                <span style="border-top:1px solid #000; padding:0 18px;">
                    {{ data_get($form, 'ttd.petugasName') ?: '(Nama terang &amp; tanda tangan)' }}
                </span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
