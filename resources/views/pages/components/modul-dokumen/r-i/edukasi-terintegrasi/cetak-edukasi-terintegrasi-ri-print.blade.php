{{-- resources/views/pages/components/modul-dokumen/r-i/edukasi-terintegrasi/cetak-edukasi-terintegrasi-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR EDUKASI PASIEN TERINTEGRASI — RAWAT INAP">

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
            :alamat="$alamatPasien">
            @if (!empty($data['dataRi']['riHdrNo']))
                <tr>
                    <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rawat Inap</td>
                    <td class="py-0.5 text-[11px] px-1">:</td>
                    <td class="py-0.5 text-[11px] font-bold">{{ $data['dataRi']['riHdrNo'] }}</td>
                </tr>
            @endif
        </x-pdf.identitas-pasien>
    </x-slot>

    @php
        $entry = $data['entry'] ?? [];
        $form = $entry['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';

        // ── Maps key → label ──
        $mapTujuan = [
            'penyakit' => 'Pemahaman penyakit/diagnosis',
            'obat' => 'Penggunaan obat yang aman',
            'nutrisi' => 'Nutrisi & diet',
            'aktivitas' => 'Aktivitas & latihan',
            'perawatanRumah' => 'Perawatan di rumah',
            'pencegahan' => 'Pencegahan komplikasi',
            'lainnya' => 'Lainnya',
        ];
        $mapPref = [
            'lisan' => 'Lisan',
            'tulisan' => 'Tulisan',
            'demonstrasi' => 'Demonstrasi',
            'video' => 'Video',
            'poster' => 'Poster',
            'lainnya' => 'Lainnya',
        ];
        $mapKebutuhan = [
            'penyakitHasil' => 'Penjelasan penyakit & hasil pemeriksaan',
            'prosedur' => 'Prosedur / tindakan medis',
            'rencanaAsuhan' => 'Rencana asuhan & tindak lanjut',
            'obatEfek' => 'Penggunaan obat & efek samping',
            'cuciTangan' => 'Cuci tangan & pencegahan infeksi',
            'alatRumah' => 'Penggunaan alat medis di rumah',
            'warningSign' => 'Tanda bahaya yang perlu diwaspadai',
            'lainnya' => 'Lainnya',
        ];
        $mapMetode = [
            'lisan' => 'Penjelasan lisan',
            'demonstrasi' => 'Demonstrasi / praktik langsung',
            'leaflet' => 'Leaflet / brosur',
            'video' => 'Video edukasi',
            'poster' => 'Poster / peraga',
            'lainnya' => 'Lainnya',
        ];
        $mapHasil = [
            'paham' => 'Pasien/keluarga memahami informasi',
            'mampuMengulang' => 'Dapat mengulang kembali informasi',
            'tunjukkanSkill' => 'Menunjukkan keterampilan yang diajarkan',
            'sesuaiNilai' => 'Edukasi sesuai nilai & keyakinan pasien',
            'perluEdukasiUlang' => 'Diperlukan edukasi ulang',
        ];
        $mapRujuk = [
            'dietisien' => 'Dietisien',
            'farmasi' => 'Farmasi',
            'rehabilitasi' => 'Rehabilitasi',
            'psikologi' => 'Psikologi',
            'lainnya' => 'Lainnya',
        ];

        // Helper boolean → label
        $boolLabel = function ($nilai) {
            if (in_array($nilai, [true, 1, '1'], true)) {
                return 'Ya';
            }
            if (in_array($nilai, [false, 0, '0'], true)) {
                return 'Tidak';
            }
            return '-';
        };

        // Data section
        $tglEdukasi = $form['tglEdukasi'] ?? '-';
        $petugasName = $form['pemberiInformasi']['petugasName'] ?? '-';

        $tujuanOpsi = (array) ($form['tujuan']['opsi'] ?? []);
        $tujuanLain = $form['tujuan']['lainnya'] ?? '';

        $evaluasiAwal = $form['evaluasiAwal'] ?? [];
        $literasi = $evaluasiAwal['literasi'] ?? '-';
        $bahasa = $evaluasiAwal['bahasaAtauPendidikan'] ?? '-';
        $prefOpsi = (array) ($evaluasiAwal['preferensiInformasi']['opsi'] ?? []);
        $prefLain = $evaluasiAwal['preferensiInformasi']['lainnya'] ?? '';

        $kebutuhanOpsi = (array) ($form['kebutuhan']['opsi'] ?? []);
        $kebutuhanLain = $form['kebutuhan']['lainnya'] ?? '';

        $metodeOpsi = (array) ($form['metodeMedia']['opsi'] ?? []);
        $metodeLain = $form['metodeMedia']['lainnya'] ?? '';

        $hasil = $form['hasil'] ?? [];

        $tindakLanjut = $form['tindakLanjut'] ?? [];
        $tlTgl = $tindakLanjut['edukasiLanjutanTanggal'] ?? '';
        $tlRujuk = (array) ($tindakLanjut['dirujukKe'] ?? []);
        $tlSkip = !empty($tindakLanjut['tidakPerluTL']);
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── HEADER: Tanggal & Pemberi ── --}}
        <tr>
            <td class="border border-black px-2 py-1 w-1/2 text-[10px]">
                <strong>Tanggal Edukasi:</strong> {{ $tglEdukasi }}
            </td>
            <td class="border border-black px-2 py-1 w-1/2 text-[10px]">
                <strong>Pemberi Informasi:</strong> {{ $petugasName }}
            </td>
        </tr>

        {{-- ── 1. TUJUAN EDUKASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">1. Tujuan Edukasi</p>
                @if (count($tujuanOpsi) > 0)
                    @foreach ($tujuanOpsi as $opsi)
                        <div>&#10003; {{ $mapTujuan[$opsi] ?? $opsi }}@if ($opsi === 'lainnya' && !empty($tujuanLain)): {{ $tujuanLain }}@endif</div>
                    @endforeach
                @else
                    <span class="text-gray-500">-</span>
                @endif
            </td>
        </tr>

        {{-- ── 2. EVALUASI AWAL ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">2. Evaluasi Awal Kemampuan & Nilai</p>
                <div>&bull; <strong>Kemampuan membaca/menulis:</strong> {{ $literasi ?: '-' }}</div>
                <div>&bull; <strong>Bahasa / pendidikan:</strong> {{ $bahasa ?: '-' }}</div>
                @php $hEmo = $evaluasiAwal['hambatanEmosional'] ?? []; @endphp
                <div>&bull; <strong>Hambatan emosional / motivasi:</strong> {{ $boolLabel($hEmo['ada'] ?? null) }}@if (!empty($hEmo['keterangan'])) &mdash; {{ $hEmo['keterangan'] }}@endif</div>
                @php $hFk = $evaluasiAwal['keterbatasanFisikKognitif'] ?? []; @endphp
                <div>&bull; <strong>Keterbatasan fisik / kognitif:</strong> {{ $boolLabel($hFk['ada'] ?? null) }}@if (!empty($hFk['keterangan'])) &mdash; {{ $hFk['keterangan'] }}@endif</div>
                @php $nilaiBudaya = $evaluasiAwal['nilaiKeyakinanBudaya'] ?? []; @endphp
                <div>&bull; <strong>Nilai / keyakinan / budaya:</strong> {{ $boolLabel($nilaiBudaya['ada'] ?? null) }}@if (!empty($nilaiBudaya['deskripsi'])) &mdash; {{ $nilaiBudaya['deskripsi'] }}@endif</div>
                <div>&bull; <strong>Preferensi menerima informasi:</strong>
                    @if (count($prefOpsi) > 0)
                        @php
                            $prefLabels = [];
                            foreach ($prefOpsi as $opsi) {
                                $prefLabels[] = ($mapPref[$opsi] ?? $opsi) . ($opsi === 'lainnya' && !empty($prefLain) ? ' (' . $prefLain . ')' : '');
                            }
                        @endphp
                        {{ implode(', ', $prefLabels) }}
                    @else
                        -
                    @endif
                </div>
            </td>
        </tr>

        {{-- ── 3. KEBUTUHAN EDUKASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">3. Kebutuhan Edukasi</p>
                @if (count($kebutuhanOpsi) > 0)
                    @foreach ($kebutuhanOpsi as $opsi)
                        <div>&#10003; {{ $mapKebutuhan[$opsi] ?? $opsi }}@if ($opsi === 'lainnya' && !empty($kebutuhanLain)): {{ $kebutuhanLain }}@endif</div>
                    @endforeach
                @else
                    <span class="text-gray-500">-</span>
                @endif
            </td>
        </tr>

        {{-- ── 4. METODE & MEDIA ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">4. Metode & Media Edukasi</p>
                @if (count($metodeOpsi) > 0)
                    @foreach ($metodeOpsi as $opsi)
                        <div>&#10003; {{ $mapMetode[$opsi] ?? $opsi }}@if ($opsi === 'lainnya' && !empty($metodeLain)): {{ $metodeLain }}@endif</div>
                    @endforeach
                @else
                    <span class="text-gray-500">-</span>
                @endif
            </td>
        </tr>

        {{-- ── 5. HASIL / EVALUASI ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px]">
                <p class="font-bold mb-1">5. Hasil / Evaluasi Edukasi</p>
                <table class="w-full text-[9px] border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-black px-1 py-0.5 text-left">Indikator</th>
                            <th class="border border-black px-1 py-0.5 w-12 text-center">Hasil</th>
                            <th class="border border-black px-1 py-0.5 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mapHasil as $hasilKey => $hlbl)
                            @php $rowH = $hasil[$hasilKey] ?? []; @endphp
                            <tr>
                                <td class="border border-black px-1 py-0.5">{{ $hlbl }}</td>
                                <td class="border border-black px-1 py-0.5 text-center">{{ $boolLabel($rowH['ya'] ?? null) }}</td>
                                <td class="border border-black px-1 py-0.5">{{ $rowH['keterangan'] ?? '' ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>

        {{-- ── 6. TINDAK LANJUT ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5 text-[10px] leading-relaxed">
                <p class="font-bold mb-1">6. Tindak Lanjut</p>
                @if ($tlSkip)
                    <div>&#10003; Tidak diperlukan tindak lanjut.</div>
                @else
                    <div>&bull; <strong>Tanggal edukasi lanjutan:</strong> {{ $tlTgl ?: '-' }}</div>
                    <div>&bull; <strong>Dirujuk ke:</strong>
                        @if (count($tlRujuk) > 0)
                            @php $rujukLabels = array_map(fn($rujuk) => $mapRujuk[$rujuk] ?? ucfirst($rujuk), $tlRujuk); @endphp
                            {{ implode(', ', $rujukLabels) }}
                        @else
                            -
                        @endif
                    </div>
                @endif
            </td>
        </tr>

        {{-- ── TANDA TANGAN — 2 kolom ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Kolom kiri: Pasien / Keluarga --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Pasien / Keluarga</p>

                            <div class="text-center my-1">
                                @if (!empty($form['ttd']['pasienKeluargaTTD']))
                                    <img src="{{ $form['ttd']['pasienKeluargaTTD'] }}" class="h-16" alt="Tanda Tangan Pasien" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['ttd']['pasienKeluargaNama'] ?? '-') }}</p>
                                @php
                                    $hubunganMap = ['pasien' => 'Pasien Sendiri', 'suami' => 'Suami', 'istri' => 'Istri', 'ayah' => 'Ayah', 'ibu' => 'Ibu', 'anak' => 'Anak', 'saudara' => 'Saudara', 'wali_hukum' => 'Wali Hukum', 'lainnya' => 'Lainnya'];
                                    $hubunganVal = $form['ttd']['pasienKeluargaHubungan'] ?? '';
                                @endphp
                                @if ($hubunganVal)
                                    <p class="text-[9px] text-gray-600">{{ $hubunganMap[$hubunganVal] ?? $hubunganVal }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Garis pemisah --}}
                        <td style="border-left: 1px solid #d1d5db; width: 1px;"></td>

                        {{-- Kolom kanan: Petugas Pemberi Edukasi --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Petugas Pemberi Edukasi</p>

                            <div class="text-center my-1">
                                @if (!empty($data['ttdPetugasPath']))
                                    <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Petugas" />
                                @else
                                    <div class="h-16">&nbsp;</div>
                                @endif
                            </div>

                            <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                                <p class="font-bold">{{ strtoupper($form['pemberiInformasi']['petugasName'] ?? '-') }}</p>
                                <p class="text-[9px] text-gray-500">{{ $data['tglCetak'] ?? '-' }}</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── FOOTER INFO ── --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}, {{ $rsAddress }}
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
