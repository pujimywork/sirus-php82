{{-- pages/components/rekam-medis/r-i/cetak-rekam-medis/asesmen-ri-tab.blade.php

     Ringkasan ASSESSMENT DAN TINDAKAN selama rawat inap (read-only) — dipakai sebagai tab
     pertama di preview `cetak-rekam-medis-ri`. Tujuannya sama dgn preview RJ/UGD:
     satu layar untuk melihat apa saja yang sudah dikerjakan pada satu episode ranap,
     tanpa membuka EMR-nya satu per satu.

     Sumbernya JSON RI (rstxn_rihdrs.datadaftarri_json) — variabel $ri disiapkan induk. --}}

@php
    $ambil = fn($path, $bawaan = '') => data_get($ri, $path, $bawaan);

    // — Ringkas ranap
    $dpjp = (string) $ambil('drDesc');
    $caraMasuk = (string) $ambil('entryDesc');
    $ruang = trim((string) $ambil('bangsalDesc') . ((string) $ambil('roomDesc') ? ' / ' . $ambil('roomDesc') : ''));
    $tanggalMasukRanap = (string) $ambil('entryDate');
    $tanggalKeluarRanap = (string) $ambil('exitDate');
    $klaim = (string) $ambil('klaimStatus');
    $noSep = (string) $ambil('sep.noSep');
    $noReferensi = (string) $ambil('noReferensi');
    $lamaRawat = $this->hitungLamaRawat($tanggalMasukRanap, $tanggalKeluarRanap);

    // — Pengkajian awal (perawat) & pengkajian dokter
    $pengkajianAwal = (array) $ambil('pengkajianAwalPasienRawatInap', []);
    $tandaVitalAwal = (array) data_get($pengkajianAwal, 'bagian4PemeriksaanFisik.tandaVital', []);
    $pengkajianDokter = (array) $ambil('pengkajianDokter', []);
    $rekonsiliasiObat = (array) data_get($pengkajianDokter, 'anamnesa.rekonsiliasiObat', []);

    // — Diagnosis & prosedur (ICD-10 / ICD-9) yang ditegakkan selama ranap
    $daftarDiagnosis = (array) $ambil('diagnosis', []);
    $diagnosisFreeText = (string) $ambil('diagnosisFreeText');
    $daftarProsedur = (array) $ambil('procedure', []);
    $prosedurFreeText = (string) $ambil('procedureFreeText');

    // — Penilaian (nyeri / risiko jatuh / gizi / dekubitus / C-SSRS)
    $penilaian = (array) $ambil('penilaian', []);

    // — SPRI (perintah rawat inap) & rencana kontrol setelah pulang (SKDP)
    $spri = (array) $ambil('spri', []);
    $kontrol = (array) $ambil('kontrol', []);

    // — Perencanaan kepulangan & edukasi (versi lama + terintegrasi)
    $perencanaan = (array) $ambil('perencanaan', []);
    $edukasiLama = (array) $ambil('edukasiPasien', []);
    $daftarEdukasi = (array) $ambil('edukasiPasienTerintegrasi', []);

    // — Tindakan selama ranap, semuanya diurutkan kronologis lewat method component
    $lembarEresep = $this->urutkanKronologis((array) $ambil('eresepHdr', []), 'resepDate');
    $observasiTandaVital = $this->urutkanKronologis((array) $ambil('observasi.observasiLanjutan.tandaVital', []), 'waktuPemeriksaan');
    $obatDanCairan = $this->urutkanKronologis((array) $ambil('observasi.obatDanCairan.pemberianObatDanCairan', []), 'waktuPemberian');
    $daftarCppt = $this->urutkanKronologis((array) $ambil('cppt', []), 'tglCPPT');
    $daftarSbar = $this->urutkanKronologis((array) $ambil('sbar', []), 'tglSBAR');
    $daftarAskep = (array) $ambil('asuhanKeperawatan', []);

    // Blok warna S/O/A/P & S/B/A/R — disamakan dgn tampilan CPPT & SBAR di EMR RI
    // supaya user melihat pola yang sama di dua tempat.
    $gayaSoap = [
        'subjective' => ['lbl' => 'S', 'name' => 'Subjective', 'wrap' => 'border-l-4 border-blue-500 bg-blue-50/40 dark:bg-blue-900/10', 'text' => 'text-blue-700 dark:text-blue-400'],
        'objective' => ['lbl' => 'O', 'name' => 'Objective', 'wrap' => 'border-l-4 border-emerald-500 bg-emerald-50/40 dark:bg-emerald-900/10', 'text' => 'text-success dark:text-success'],
        'assessment' => ['lbl' => 'A', 'name' => 'Assessment', 'wrap' => 'border-l-4 border-amber-500 bg-amber-50/40 dark:bg-amber-900/10', 'text' => 'text-amber-700 dark:text-amber-400'],
        'plan' => ['lbl' => 'P', 'name' => 'Plan', 'wrap' => 'border-l-4 border-rose-500 bg-rose-50/40 dark:bg-rose-900/10', 'text' => 'text-error dark:text-rose-400'],
    ];
    $gayaSbar = [
        'situation' => ['lbl' => 'S', 'name' => 'Situation', 'wrap' => 'border-l-4 border-blue-500 bg-blue-50/40 dark:bg-blue-900/10', 'text' => 'text-blue-700 dark:text-blue-400'],
        'background' => ['lbl' => 'B', 'name' => 'Background', 'wrap' => 'border-l-4 border-emerald-500 bg-emerald-50/40 dark:bg-emerald-900/10', 'text' => 'text-success dark:text-success'],
        'assessment' => ['lbl' => 'A', 'name' => 'Assessment', 'wrap' => 'border-l-4 border-amber-500 bg-amber-50/40 dark:bg-amber-900/10', 'text' => 'text-amber-700 dark:text-amber-400'],
        'recommendation' => ['lbl' => 'R', 'name' => 'Recommendation', 'wrap' => 'border-l-4 border-rose-500 bg-rose-50/40 dark:bg-rose-900/10', 'text' => 'text-error dark:text-rose-400'],
    ];
@endphp

<div class="px-6 py-5 space-y-4">


    {{-- ═══════ RINGKAS RAWAT INAP ═══════ --}}
    <x-border-form title="Ringkas Rawat Inap" :collapsible="true" :open="true">
        <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([['DPJP', $dpjp], ['Cara Masuk', $caraMasuk], ['Ruang / Kamar', $ruang], ['Tanggal Masuk', $tanggalMasukRanap], ['Tanggal Keluar', $tanggalKeluarRanap ?: 'Masih dirawat'], ['Lama Rawat', $lamaRawat], ['Penjamin', $klaim], ['No. SEP', $noSep], ['No. Referensi', $noReferensi]] as [$judul, $nilai])
                <div class="flex gap-2 py-1 border-b border-hairline-soft dark:border-gray-700/60">
                    <span class="w-40 shrink-0 text-muted">{{ $judul }}</span>
                    <span class="font-medium text-ink dark:text-gray-100">{{ filled($nilai) ? $nilai : '-' }}</span>
                </div>
            @endforeach
        </div>
    </x-border-form>

    {{-- ═══════ PENGKAJIAN AWAL (PERAWAT) ═══════ --}}
    <x-border-form title="Pengkajian Awal Rawat Inap (Perawat)" :collapsible="true" :open="false">
        @if (empty($pengkajianAwal))
            <p class="italic text-muted-soft">Belum ada pengkajian awal.</p>
        @else
            <div class="space-y-2">
                @foreach ([['Keluhan Utama', data_get($pengkajianAwal, 'bagian4PemeriksaanFisik.keluhanUtama')], ['Diagnosa Masuk', data_get($pengkajianAwal, 'bagian1DataUmum.diagnosaMasuk')], ['Kondisi Saat Masuk', data_get($pengkajianAwal, 'bagian1DataUmum.kondisiSaatMasuk')], ['Catatan', data_get($pengkajianAwal, 'bagian5CatatanDanTandaTangan.catatanUmum')]] as [$judul, $nilai])
                    <div class="flex flex-col gap-1 py-1 border-b sm:flex-row sm:gap-2 border-hairline-soft dark:border-gray-700/60">
                        <span class="w-48 shrink-0 text-muted">{{ $judul }}</span>
                        <span class="text-ink dark:text-gray-100">{{ filled($nilai) ? $nilai : '-' }}</span>
                    </div>
                @endforeach

                @if (array_filter($tandaVitalAwal, fn($nilaiVital) => filled($nilaiVital)))
                    <div class="pt-2">
                        <p class="mb-1 font-semibold text-ink dark:text-gray-100">Tanda Vital Awal</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([['TD', ($tandaVitalAwal['sistolik'] ?? '-') . '/' . ($tandaVitalAwal['distolik'] ?? '-') . ' mmHg'], ['Nadi', ($tandaVitalAwal['frekuensiNadi'] ?? '-') . ' x/mnt'], ['Nafas', ($tandaVitalAwal['frekuensiNafas'] ?? '-') . ' x/mnt'], ['Suhu', ($tandaVitalAwal['suhu'] ?? '-') . ' °C'], ['SpO₂', ($tandaVitalAwal['spo2'] ?? '-') . ' %'], ['GDA', ($tandaVitalAwal['gda'] ?? '-') . ' mg/dL'], ['TB/BB', ($tandaVitalAwal['tb'] ?? '-') . ' cm / ' . ($tandaVitalAwal['bb'] ?? '-') . ' kg']] as [$judul, $nilai])
                                <span class="px-3 py-1 rounded-lg bg-surface-soft dark:bg-gray-800">
                                    <span class="text-muted">{{ $judul }}:</span>
                                    <span class="font-medium text-ink dark:text-gray-100">{{ $nilai }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="pt-2 text-muted">
                    Pengkaji: <span class="font-medium text-ink dark:text-gray-100">{{ data_get($pengkajianAwal, 'bagian5CatatanDanTandaTangan.petugasPengkaji', '-') }}</span>
                    &middot; {{ data_get($pengkajianAwal, 'bagian5CatatanDanTandaTangan.jamPengkaji', '-') }}
                </p>
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ PENGKAJIAN DOKTER ═══════ --}}
    <x-border-form title="Pengkajian Dokter" :collapsible="true" :open="false">
        @if (empty($pengkajianDokter))
            <p class="italic text-muted-soft">Belum ada pengkajian dokter.</p>
        @else
            <div class="space-y-2">
                @foreach ([['Keluhan Utama', data_get($pengkajianDokter, 'anamnesa.keluhanUtama')], ['Riwayat Penyakit Sekarang', data_get($pengkajianDokter, 'anamnesa.riwayatPenyakit.sekarang')], ['Riwayat Penyakit Dahulu', data_get($pengkajianDokter, 'anamnesa.riwayatPenyakit.dahulu')], ['Alergi', data_get($pengkajianDokter, 'anamnesa.jenisAlergi')], ['Pemeriksaan Fisik', data_get($pengkajianDokter, 'fisik')], ['Diagnosa Awal', data_get($pengkajianDokter, 'diagnosaAssesment.diagnosaAwal')], ['Rencana Terapi', data_get($pengkajianDokter, 'rencana.terapi')], ['Rencana Monitoring', data_get($pengkajianDokter, 'rencana.monitoring')]] as [$judul, $nilai])
                    <div class="flex flex-col gap-1 py-1 border-b sm:flex-row sm:gap-2 border-hairline-soft dark:border-gray-700/60">
                        <span class="w-48 shrink-0 text-muted">{{ $judul }}</span>
                        <span class="whitespace-pre-line text-ink dark:text-gray-100">{{ filled($nilai) ? $nilai : '-' }}</span>
                    </div>
                @endforeach

                <div class="pt-2">
                    <p class="mb-1 font-semibold text-ink dark:text-gray-100">Rekonsiliasi Obat</p>
                    @if (empty($rekonsiliasiObat))
                        <p class="italic text-muted-soft">Belum ada riwayat pemakaian obat.</p>
                    @else
                        <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
                            <table class="ds-table">
                                <thead>
                                    <tr>
                                        <th class="ds-c w-10">No</th>
                                        <th>Obat (Dosis &middot; Rute)</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rekonsiliasiObat as $index => $obat)
                                        @php
                                            $dosisRute = collect([$obat['dosis'] ?? null, $obat['rute'] ?? null])
                                                ->filter(fn($bagianDosis) => filled($bagianDosis))
                                                ->implode(' · ');
                                        @endphp
                                        <tr>
                                            <td class="ds-c ds-td-meta">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="ds-td-strong">{{ $obat['namaObat'] ?? '-' }}</div>
                                                @if ($dosisRute)
                                                    <div class="text-muted dark:text-gray-400">{{ $dosisRute }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                Dibawa saat ranap: {{ ($obat['dibawaRanap'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}<br>
                                                Lanjut saat pulang: {{ ($obat['lanjutPulang'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <p class="pt-2 text-muted">
                    Dokter Pengkaji: <span class="font-medium text-ink dark:text-gray-100">{{ data_get($pengkajianDokter, 'tandaTanganDokter.dokterPengkaji', '-') }}</span>
                    &middot; {{ data_get($pengkajianDokter, 'tandaTanganDokter.jamDokterPengkaji', '-') }}
                </p>
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ DIAGNOSIS & PROSEDUR ═══════ --}}
    <x-border-form title="Diagnosis & Prosedur" :collapsible="true" :open="false">
        <div class="space-y-3">
            <div>
                <p class="mb-1 font-semibold text-ink dark:text-gray-100">Diagnosis (ICD-10)</p>
                @if (empty($daftarDiagnosis))
                    <p class="italic text-muted-soft">Belum ada diagnosis.</p>
                @else
                    <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                        <table class="ds-table">
                            <thead>
                                <tr>
                                    <th class="ds-c w-24">ICD-10</th>
                                    <th>Diagnosis</th>
                                    <th class="w-32">Kategori</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($daftarDiagnosis as $diagnosis)
                                    <tr>
                                        <td class="ds-c ds-td-strong">{{ $diagnosis['icdX'] ?? '-' }}</td>
                                        <td>{{ $diagnosis['diagDesc'] ?? '-' }}</td>
                                        <td>{{ $diagnosis['kategoriDiagnosa'] ?? '-' }}</td>
                                        <td>{{ filled($diagnosis['ketdiagnosa'] ?? null) ? $diagnosis['ketdiagnosa'] : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if (filled($diagnosisFreeText))
                    <p class="mt-1"><span class="text-muted">Free text:</span> {{ $diagnosisFreeText }}</p>
                @endif
            </div>

            <div>
                <p class="mb-1 font-semibold text-ink dark:text-gray-100">Prosedur / Tindakan (ICD-9-CM)</p>
                @if (empty($daftarProsedur))
                    <p class="italic text-muted-soft">Belum ada prosedur.</p>
                @else
                    <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                        <table class="ds-table">
                            <thead>
                                <tr>
                                    <th class="ds-c w-24">ICD-9</th>
                                    <th>Prosedur</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($daftarProsedur as $prosedur)
                                    <tr>
                                        <td class="ds-c ds-td-strong">{{ $prosedur['icd9'] ?? ($prosedur['procedureId'] ?? '-') }}</td>
                                        <td>{{ $prosedur['procedureDesc'] ?? '-' }}</td>
                                        <td>{{ filled($prosedur['ketprocedure'] ?? null) ? $prosedur['ketprocedure'] : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if (filled($prosedurFreeText))
                    <p class="mt-1"><span class="text-muted">Free text:</span> {{ $prosedurFreeText }}</p>
                @endif
            </div>
        </div>
    </x-border-form>

    {{-- ═══════ PENILAIAN ═══════
         Tiap sub-penilaian menyimpan daftar entri dgn pola sama: tglPenilaian,
         petugasPenilai, lalu satu node bernama sama dgn sub-penilaiannya. --}}
    <x-border-form title="Penilaian (Nyeri / Risiko Jatuh / Gizi / Dekubitus / Risiko Bunuh Diri)" :collapsible="true"
        :open="false">
        @php
            $daftarJenisPenilaian = [
                ['nyeri', 'Nyeri'],
                ['resikoJatuh', 'Risiko Jatuh'],
                ['gizi', 'Gizi'],
                ['dekubitus', 'Dekubitus'],
                ['resikoBunuhDiri', 'Risiko Bunuh Diri (C-SSRS)'],
            ];
            $adaPenilaian = collect($daftarJenisPenilaian)->contains(fn($jenis) => !empty(data_get($penilaian, $jenis[0])));
        @endphp

        @if (!$adaPenilaian)
            <p class="italic text-muted-soft">Belum ada penilaian.</p>
        @else
            <div class="space-y-3">
                @foreach ($daftarJenisPenilaian as [$jenisPenilaian, $judulPenilaian])
                    @php
                        $daftarEntriPenilaian = $this->urutkanKronologis((array) data_get($penilaian, $jenisPenilaian, []), 'tglPenilaian');
                    @endphp
                    @if (!empty($daftarEntriPenilaian))
                        <div>
                            <p class="mb-1 font-semibold text-ink dark:text-gray-100">{{ $judulPenilaian }}
                                ({{ count($daftarEntriPenilaian) }})</p>
                            <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Hasil</th>
                                            <th class="ds-c w-24">Skor</th>
                                            <th>Penilai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($daftarEntriPenilaian as $entriPenilaian)
                                            @php $ringkasan = $this->ringkasPenilaian($jenisPenilaian, $entriPenilaian); @endphp
                                            <tr>
                                                <td class="ds-td-strong">{{ $entriPenilaian['tglPenilaian'] ?? '-' }}</td>
                                                <td>
                                                    {{ $ringkasan['hasil'] }}
                                                    @if (filled($ringkasan['rekomendasi']))
                                                        <div class="text-muted dark:text-gray-400">Rekomendasi:
                                                            {{ $ringkasan['rekomendasi'] }}</div>
                                                    @endif
                                                    @if (filled($ringkasan['tindakLanjut']))
                                                        <div class="text-muted dark:text-gray-400">Tindak lanjut:
                                                            {{ $ringkasan['tindakLanjut'] }}</div>
                                                    @endif
                                                    @if (filled($ringkasan['catatanKlinis']))
                                                        <div class="text-muted dark:text-gray-400">Catatan:
                                                            {{ $ringkasan['catatanKlinis'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="ds-c">
                                                    {{ filled($ringkasan['skor']) ? $ringkasan['skor'] : '-' }}
                                                    @if (filled($ringkasan['metode']))
                                                        <div class="text-muted dark:text-gray-400">{{ $ringkasan['metode'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="ds-td-meta">{{ $entriPenilaian['petugasPenilai'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ OBSERVASI TANDA VITAL ═══════ --}}
    <x-border-form title="Observasi Tanda Vital ({{ count($observasiTandaVital) }} kali)" :collapsible="true" :open="false">
        <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th class="ds-c">TD</th>
                        <th class="ds-c">Nadi</th>
                        <th class="ds-c">Nafas</th>
                        <th class="ds-c">Suhu</th>
                        <th class="ds-c">SpO₂</th>
                        <th class="ds-c">GDA</th>
                        <th class="ds-c">GCS</th>
                        <th>Cairan / Tetesan</th>
                        <th>Pemeriksa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($observasiTandaVital as $tandaVital)
                        @php
                            $cairan = trim((string) ($tandaVital['cairan'] ?? ''));
                            $tetesan = trim((string) ($tandaVital['tetesan'] ?? ''));
                            $cairanTetesan = collect([$cairan ? $cairan . ' ml' : null, $tetesan ? $tetesan . ' gtt/mnt' : null])
                                ->filter()
                                ->implode(' · ');
                        @endphp
                        <tr>
                            <td class="ds-td-strong">{{ $tandaVital['waktuPemeriksaan'] ?? '-' }}</td>
                            <td class="ds-c">{{ ($tandaVital['sistolik'] ?? '-') . '/' . ($tandaVital['distolik'] ?? '-') }}</td>
                            <td class="ds-c">{{ $tandaVital['frekuensiNadi'] ?? '-' }}</td>
                            <td class="ds-c">{{ $tandaVital['frekuensiNafas'] ?? '-' }}</td>
                            <td class="ds-c">{{ $tandaVital['suhu'] ?? '-' }}</td>
                            <td class="ds-c">{{ $tandaVital['spo2'] ?? '-' }}</td>
                            <td class="ds-c">{{ filled($tandaVital['gda'] ?? null) ? $tandaVital['gda'] : '-' }}</td>
                            <td class="ds-c">{{ filled($tandaVital['gcs'] ?? null) ? $tandaVital['gcs'] : '-' }}</td>
                            <td>{{ $cairanTetesan ?: '-' }}</td>
                            <td class="ds-td-meta">{{ $tandaVital['pemeriksa'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="ds-c italic text-muted-soft">Belum ada observasi tanda vital.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-border-form>

    {{-- ═══════ PEMBERIAN OBAT & CAIRAN ═══════ --}}
    <x-border-form title="Pemberian Obat & Cairan ({{ count($obatDanCairan) }} pemberian)" :collapsible="true" :open="false">
        <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Obat / Cairan</th>
                        <th class="ds-c">Jumlah</th>
                        <th>Dosis</th>
                        <th>Rute</th>
                        <th>Keterangan</th>
                        <th>Pemberi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($obatDanCairan as $pemberian)
                        <tr>
                            <td class="ds-td-strong">{{ $pemberian['waktuPemberian'] ?? '-' }}</td>
                            <td>{{ $pemberian['namaObatAtauJenisCairan'] ?? '-' }}</td>
                            <td class="ds-c">{{ $pemberian['jumlah'] ?? '-' }}</td>
                            <td>{{ $pemberian['dosis'] ?? '-' }}</td>
                            <td>{{ $pemberian['rute'] ?? '-' }}</td>
                            <td>{{ $pemberian['keterangan'] ?? '-' }}</td>
                            <td class="ds-td-meta">{{ $pemberian['pemeriksa'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="ds-c italic text-muted-soft">Belum ada pemberian obat / cairan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-border-form>

    {{-- ═══════ E-RESEP ═══════
         E-resep RI disimpan BERLEMBAR (eresepHdr[]): tiap lembar punya nomor, tanggal,
         daftar non-racikan (eresep[]) & racikan (eresepRacikan[], dikelompokkan noRacikan). --}}
    <x-border-form title="E-Resep ({{ count($lembarEresep) }} lembar)" :collapsible="true" :open="false">
        @if (empty($lembarEresep))
            <p class="italic text-muted-soft">Belum ada e-resep.</p>
        @else
            <div class="space-y-3">
                @foreach ($lembarEresep as $lembar)
                    @php
                        $obatNonRacikan = (array) data_get($lembar, 'eresep', []);
                        $obatRacikan = collect((array) data_get($lembar, 'eresepRacikan', []))->groupBy('noRacikan');
                        // Key JSON-nya `dokterPeresep` (bukan dokterResep). Lembar lama
                        // sebelum fitur TTD dokter belum punya node ini → tampil '-'.
                        $dokterPeresep = (string) data_get($lembar, 'tandaTanganDokter.dokterPeresep', '');
                    @endphp

                    <div class="p-3 border rounded-xl border-hairline bg-canvas dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-hairline-soft dark:border-gray-700/60">
                            <span class="font-semibold text-ink dark:text-gray-100">
                                Lembar {{ data_get($lembar, 'resepNo', '-') }}
                                &middot; {{ data_get($lembar, 'resepDate', '-') }}
                            </span>
                            <span class="text-muted">
                                Pemberi Resep:
                                <span
                                    class="font-medium text-ink dark:text-gray-100">{{ filled($dokterPeresep) ? $dokterPeresep : '-' }}</span>
                            </span>
                        </div>

                        @if (empty($obatNonRacikan) && $obatRacikan->isEmpty())
                            <p class="italic text-muted-soft">Lembar kosong.</p>
                        @endif

                        @if (!empty($obatNonRacikan))
                            <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Obat (Non Racikan)</th>
                                            <th class="ds-c w-24">Signa</th>
                                            <th class="ds-c w-20">Jumlah</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($obatNonRacikan as $obat)
                                            <tr>
                                                <td class="ds-td-strong">{{ $obat['productName'] ?? '-' }}</td>
                                                <td class="ds-c">{{ $this->rakitSigna($obat) }}</td>
                                                <td class="ds-c">{{ $obat['qty'] ?? '-' }}</td>
                                                <td>{{ filled($obat['catatanKhusus'] ?? null) ? $obat['catatanKhusus'] : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @foreach ($obatRacikan as $noRacikan => $komponen)
                            <div class="mt-2 overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Racikan {{ $noRacikan }}
                                                @php $racikanPertama = $komponen->first(); @endphp
                                                @if (filled(data_get($racikanPertama, 'catatan')))
                                                    <span class="font-normal text-muted">&mdash; {{ data_get($racikanPertama, 'catatan') }}</span>
                                                @endif
                                            </th>
                                            <th class="ds-c w-24">Dosis</th>
                                            <th class="ds-c w-24">Signa</th>
                                            <th class="ds-c w-20">Jumlah</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($komponen as $obat)
                                            <tr>
                                                <td class="ds-td-strong">{{ $obat['productName'] ?? '-' }}</td>
                                                <td class="ds-c">{{ filled($obat['dosis'] ?? null) ? $obat['dosis'] : '-' }}</td>
                                                <td class="ds-c">{{ $this->rakitSigna($obat) }}</td>
                                                <td class="ds-c">{{ $obat['qty'] ?? '-' }}</td>
                                                <td>{{ filled($obat['catatanKhusus'] ?? null) ? $obat['catatanKhusus'] : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ CPPT ═══════ --}}
    <x-border-form title="CPPT — Catatan Perkembangan Pasien Terintegrasi ({{ count($daftarCppt) }} catatan)" :collapsible="true" :open="false">
        @if (empty($daftarCppt))
            <p class="italic text-muted-soft">Belum ada catatan CPPT.</p>
        @else
            <div class="space-y-3">
                @foreach ($daftarCppt as $catatan)
                    <div class="p-3 border rounded-xl border-hairline bg-canvas dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-hairline-soft dark:border-gray-700/60">
                            <span class="font-semibold text-ink dark:text-gray-100">{{ $catatan['tglCPPT'] ?? '-' }}</span>
                            <span class="text-muted">
                                {{ $catatan['petugasCPPT'] ?? '-' }}
                                @if (filled($catatan['profession'] ?? null))
                                    <span class="px-2 py-0.5 ml-1 rounded-full bg-surface-soft dark:bg-gray-800">{{ $catatan['profession'] }}</span>
                                @endif
                            </span>
                        </div>

                        {{-- SOAP dua kolom + blok berwarna — gaya sama dgn tampilan CPPT di EMR RI --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($gayaSoap as $kunciSoap => $gaya)
                                <div class="{{ $gaya['wrap'] }} pl-3 py-1 rounded-r-md">
                                    <span class="font-bold {{ $gaya['text'] }}">{{ $gaya['lbl'] }}</span>
                                    <span class="text-muted"> — {{ $gaya['name'] }}</span>
                                    <p class="mt-0.5 whitespace-pre-wrap leading-relaxed text-body dark:text-gray-300">
                                        {{ trim((string) data_get($catatan, 'soap.' . $kunciSoap)) ?: '-' }}</p>
                                </div>
                            @endforeach

                            @if (filled(trim((string) ($catatan['instruction'] ?? ''))))
                                <div>
                                    <span class="font-semibold text-body dark:text-gray-300">Instruksi:</span>
                                    <p class="mt-0.5 whitespace-pre-wrap text-body dark:text-gray-300">
                                        {{ $catatan['instruction'] }}</p>
                                </div>
                            @endif

                            @if (filled(trim((string) ($catatan['review'] ?? ''))))
                                <div>
                                    <span class="font-semibold text-body dark:text-gray-300">Review DPJP:</span>
                                    <p class="mt-0.5 whitespace-pre-wrap text-body dark:text-gray-300">
                                        {{ $catatan['review'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ SBAR ═══════ --}}
    <x-border-form title="SBAR — Komunikasi Efektif ({{ count($daftarSbar) }} catatan)" :collapsible="true" :open="false">
        @if (empty($daftarSbar))
            <p class="italic text-muted-soft">Belum ada catatan SBAR.</p>
        @else
            <div class="space-y-3">
                @foreach ($daftarSbar as $catatan)
                    <div class="p-3 border rounded-xl border-hairline bg-canvas dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-hairline-soft dark:border-gray-700/60">
                            <span class="font-semibold text-ink dark:text-gray-100">{{ $catatan['tglSBAR'] ?? '-' }}</span>
                            <span class="text-muted">
                                {{ $catatan['petugasSBAR'] ?? '-' }}
                                @if (filled($catatan['profession'] ?? null))
                                    <span class="px-2 py-0.5 ml-1 rounded-full bg-surface-soft dark:bg-gray-800">{{ $catatan['profession'] }}</span>
                                @endif
                            </span>
                        </div>

                        {{-- SBAR dua kolom + blok berwarna — gaya sama dgn tampilan SBAR di EMR RI --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($gayaSbar as $kunciSbar => $gaya)
                                <div class="{{ $gaya['wrap'] }} pl-3 py-1 rounded-r-md">
                                    <span class="font-bold {{ $gaya['text'] }}">{{ $gaya['lbl'] }}</span>
                                    <span class="text-muted"> — {{ $gaya['name'] }}</span>
                                    <p class="mt-0.5 whitespace-pre-wrap leading-relaxed text-body dark:text-gray-300">
                                        {{ trim((string) data_get($catatan, 'sbar.' . $kunciSbar)) ?: '-' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ ASUHAN KEPERAWATAN ═══════ --}}
    <x-border-form title="Asuhan Keperawatan ({{ count($daftarAskep) }} diagnosis)" :collapsible="true" :open="false">
        <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Diagnosis Keperawatan (SDKI)</th>
                        <th>Perawat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarAskep as $askep)
                        <tr>
                            <td class="ds-td-strong">{{ $askep['tglAsuhanKeperawatan'] ?? '-' }}</td>
                            <td>
                                <span class="font-medium">{{ $askep['diagKepId'] ?? '-' }}</span>
                                &mdash; {{ $askep['diagKepDesc'] ?? '-' }}
                            </td>
                            <td class="ds-td-meta">{{ $askep['petugasAsuhanKeperawatan'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="ds-c italic text-muted-soft">Belum ada asuhan keperawatan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-border-form>

    {{-- ═══════ SPRI & RENCANA KONTROL ═══════
         spri  = Surat Perintah Rawat Inap (dasar masuk ranap dari BPJS)
         kontrol = SKDP / rencana kontrol setelah pulang. Struktur keduanya mirip. --}}
    <x-border-form title="SPRI & Rencana Kontrol" :collapsible="true" :open="false">
        <div class="space-y-3">
            @foreach ([['Surat Perintah Rawat Inap (SPRI)', $spri, 'noSPRIBPJS', 'No. SPRI BPJS'], ['Rencana Kontrol (SKDP)', $kontrol, 'noSKDPBPJS', 'No. SKDP BPJS']] as [$judulKartu, $isiSurat, $kunciNoBpjs, $labelNoBpjs])
                <div>
                    <p class="mb-1 font-semibold text-ink dark:text-gray-100">{{ $judulKartu }}</p>
                    @if (empty($isiSurat))
                        <p class="italic text-muted-soft">Belum ada data.</p>
                    @else
                        <div class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                            @foreach ([['No. Kontrol RS', data_get($isiSurat, 'noKontrolRS')], [$labelNoBpjs, data_get($isiSurat, $kunciNoBpjs)], ['Tanggal', data_get($isiSurat, 'tglKontrol')], ['Poli Tujuan', data_get($isiSurat, 'poliKontrolDesc')], ['Dokter', data_get($isiSurat, 'drKontrolDesc')], ['Catatan', data_get($isiSurat, 'catatan')]] as [$judulBaris, $nilaiBaris])
                                <div class="flex gap-2 py-1 border-b border-hairline-soft dark:border-gray-700/60">
                                    <span class="w-40 shrink-0 text-muted">{{ $judulBaris }}</span>
                                    <span class="text-ink dark:text-gray-100">{{ filled($nilaiBaris) ? $nilaiBaris : '-' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-border-form>

    {{-- ═══════ PERENCANAAN / TINDAK LANJUT ═══════ --}}
    <x-border-form title="Perencanaan & Tindak Lanjut Kepulangan" :collapsible="true" :open="false">
        @if (empty($perencanaan))
            <p class="italic text-muted-soft">Belum ada perencanaan.</p>
        @else
            @php
                $statusPulangMap = [1 => 'Pulang', 2 => 'Rujuk', 3 => 'Pulang Paksa', 4 => 'Meninggal'];
                $statusPulang = data_get($perencanaan, 'tindakLanjut.statusPulang');
            @endphp
            <div class="space-y-2">
                @foreach ([['Status Pulang', $statusPulangMap[$statusPulang] ?? $statusPulang], ['Tanggal Pulang', data_get($perencanaan, 'tindakLanjut.tglPulang')], ['Kode Tindak Lanjut', data_get($perencanaan, 'tindakLanjut.tindakLanjutKode')], ['No. SEP', data_get($perencanaan, 'tindakLanjut.noSep')], ['Tanggal Meninggal', data_get($perencanaan, 'tindakLanjut.tglMeninggal')], ['Pelayanan Berkelanjutan', data_get($perencanaan, 'dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan')], ['Penggunaan Alat Bantu', data_get($perencanaan, 'dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu')]] as [$judul, $nilai])
                    <div
                        class="flex flex-col gap-1 py-1 border-b sm:flex-row sm:gap-2 border-hairline-soft dark:border-gray-700/60">
                        <span class="w-56 shrink-0 text-muted">{{ $judul }}</span>
                        <span class="text-ink dark:text-gray-100">{{ filled($nilai) ? $nilai : '-' }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-border-form>

    {{-- ═══════ EDUKASI (VERSI LAMA) ═══════
         Dokumen edukasi sebelum format terintegrasi — masih dipakai banyak record,
         jadi tetap ditampilkan supaya riwayat lama tidak terlihat kosong. --}}
    @if (!empty($edukasiLama))
        <x-border-form title="Edukasi Pasien ({{ count($edukasiLama) }} entri)" :collapsible="true" :open="false">
            <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Diagnosis / Rencana</th>
                            <th>Pemberi Informasi</th>
                            <th>Penerima</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($edukasiLama as $edukasi)
                            <tr>
                                <td class="ds-td-strong">{{ data_get($edukasi, 'tglEdukasi', '-') }}</td>
                                <td>
                                    <div>{{ data_get($edukasi, 'detailInformasi.diagnosis.desc', '-') }}</div>
                                    @if (filled(data_get($edukasi, 'detailInformasi.rencana.desc')))
                                        <div class="whitespace-pre-line text-muted dark:text-gray-400">
                                            {{ data_get($edukasi, 'detailInformasi.rencana.desc') }}</div>
                                    @endif
                                </td>
                                <td class="ds-td-meta">{{ data_get($edukasi, 'pemberiInformasi.petugasName', '-') }}</td>
                                <td class="ds-td-meta">
                                    {{ data_get($edukasi, 'penerimaInformasi.name', '-') }}
                                    @if (filled(data_get($edukasi, 'penerimaInformasi.hubungan')))
                                        <div class="text-muted dark:text-gray-400">
                                            {{ data_get($edukasi, 'penerimaInformasi.hubungan') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @endif

    {{-- ═══════ EDUKASI TERINTEGRASI ═══════ --}}
    <x-border-form title="Edukasi Pasien Terintegrasi ({{ count($daftarEdukasi) }} entri)" :collapsible="true" :open="false">
        <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tujuan Edukasi</th>
                        <th>Metode / Media</th>
                        <th>Pemberi Informasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarEdukasi as $edukasi)
                        @php
                            $tujuan = collect((array) data_get($edukasi, 'form.tujuan.opsi', []))
                                ->push(data_get($edukasi, 'form.tujuan.lainnya'))
                                ->filter()
                                ->implode(', ');
                            $metode = collect((array) data_get($edukasi, 'form.metodeMedia.opsi', []))
                                ->push(data_get($edukasi, 'form.metodeMedia.lainnya'))
                                ->filter()
                                ->implode(', ');
                        @endphp
                        <tr>
                            <td class="ds-td-strong">{{ data_get($edukasi, 'form.tglEdukasi', '-') }}</td>
                            <td>{{ $tujuan ?: '-' }}</td>
                            <td>{{ $metode ?: '-' }}</td>
                            <td class="ds-td-meta">{{ data_get($edukasi, 'form.pemberiInformasi.petugasName', '-') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="ds-c italic text-muted-soft">Belum ada edukasi terintegrasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-border-form>

</div>
