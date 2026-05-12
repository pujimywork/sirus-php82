{{-- resources/views/pages/components/modul-dokumen/u-g-d/form-penjaminan/cetak-form-penjaminan-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORM PERNYATAAN KEPEMILIKAN KARTU PENJAMINAN BIAYA DAN ORIENTASI KAMAR">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $data['regNo'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">
                    {{ strtoupper(
                        trim(($data['gelarDepan'] ?? '') . ' ' . ($data['regName'] ?? '-') . ' ' . ($data['gelarBelakang'] ?? '')),
                    ) }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Kelamin</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tempat, Tgl. Lahir</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">
                    {{ $data['tempatLahir'] ?? '-' }}, {{ $data['tglLahir'] ?? '-' }}
                    ({{ $data['thn'] ?? '-' }})
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">
                    @php
                        $id = $data['identitas'] ?? [];
                        echo trim(
                            ($id['alamat'] ?? '-') .
                                (!empty($id['rt']) ? ' RT ' . $id['rt'] : '') .
                                (!empty($id['rw']) ? '/RW ' . $id['rw'] : '') .
                                (!empty($id['desaName']) ? ', ' . $id['desaName'] : '') .
                                (!empty($id['kecamatanName']) ? ', ' . $id['kecamatanName'] : ''),
                        );
                    @endphp
                </td>
            </tr>
        </table>
    </x-slot>

    @php
        $form = $data['form'] ?? [];

        // ── Mapping label ──
        $jenisPenjaminMap = [
            'BPJS_KESEHATAN' => 'BPJS Kesehatan',
            'BPJS_KETENAGAKERJAAN' => 'BPJS Ketenagakerjaan',
            'ASABRI_TASPEN' => 'ASABRI / TASPEN',
            'JASA_RAHARJA' => 'Jasa Raharja',
            'ASURANSI_LAIN' => 'Asuransi Lain',
            'TANPA_KARTU' => 'Tidak memiliki Kartu Penjaminan',
        ];
        $jenisPenjaminKey = $form['jenisPenjamin'] ?? '';
        $jenisPenjaminLabel = $jenisPenjaminMap[$jenisPenjaminKey] ?? $jenisPenjaminKey;
        $asuransiLain = $form['asuransiLain'] ?? '';
        // ── Fasilitas kamar ──
        $kelasKamarOptions = [
            'VIP' => [
                'label' => 'VIP',
                'tarif' => 'Rp 700.000 / hari',
                'fasilitas' => [
                    '1 tempat tidur pasien',
                    'AC',
                    'Kamar mandi di dalam',
                    'Sofa bed penunggu',
                    'Kulkas',
                    'Televisi LED',
                    'Almari',
                    'Overbed table',
                    'Dispenser air minum',
                    'Makan siang 1 penunggu',
                ],
            ],
            'KELAS_I' => [
                'label' => 'Kelas I',
                'tarif' => 'Rp 275.000 / hari',
                'fasilitas' => [
                    '1 tempat tidur pasien',
                    'Kamar mandi di dalam',
                    'Sofa bed penunggu',
                    'Kulkas',
                    'Televisi LED',
                    'Almari',
                    'Kipas angin',
                    'Makan siang 1 penunggu',
                ],
            ],
            'KELAS_II' => [
                'label' => 'Kelas II',
                'tarif' => 'Rp 175.000 / hari',
                'fasilitas' => [
                    '2 tempat tidur pasien',
                    'Kamar mandi di dalam',
                    'Kursi penunggu',
                    'Televisi',
                    'Almari',
                    'Kipas angin',
                    'Makan siang 1 penunggu',
                ],
            ],
            'KELAS_III' => [
                'label' => 'Kelas III',
                'tarif' => 'Rp 175.000 / hari',
                'fasilitas' => [
                    '4 tempat tidur pasien',
                    'Kamar mandi di dalam',
                    'Televisi di luar ruangan',
                    'Kursi',
                    'Almari',
                    'Kipas angin',
                ],
            ],
        ];
        $kelasKey = $form['kelasKamar'] ?? '';
        $kelasInfo = $kelasKamarOptions[$kelasKey] ?? null;
        $kelasLabel = $kelasInfo['label'] ?? $kelasKey ?: '-';
        $kelasTarif = $kelasInfo['tarif'] ?? '-';
        $fasilitas = $kelasInfo['fasilitas'] ?? [];
        $fasChunks = array_chunk($fasilitas, ceil(count($fasilitas) / 2));

        // ── TTD Pembuat ──
        $sigPembuatRaw = (string) ($form['signaturePembuat'] ?? '');
        $signaturePembuat = \Illuminate\Support\Str::startsWith($sigPembuatRaw, '<svg')
            ? 'data:image/svg+xml;base64,' . base64_encode($sigPembuatRaw)
            : $sigPembuatRaw;

        // ── TTD Saksi ──
        $sigSaksiRaw = (string) ($form['signatureSaksiKeluarga'] ?? '');
        $signatureSaksi = \Illuminate\Support\Str::startsWith($sigSaksiRaw, '<svg')
            ? 'data:image/svg+xml;base64,' . base64_encode($sigSaksiRaw)
            : $sigSaksiRaw;

        // ── TTD Petugas (dari file storage) ──
        $ttdPetugasBase64 = null;
        if (!empty($form['kodePetugas'])) {
            $user = App\Models\User::where('myuser_code', $form['kodePetugas'])->first();
            if ($user && $user->myuser_ttd_image) {
                $path = public_path('storage/' . $user->myuser_ttd_image);
                if (file_exists($path)) {
                    $mime = mime_content_type($path);
                    $ttdPetugasBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';
        $rsAddress = $identitasRs->int_address ?? '';
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── Pembuat Pernyataan ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1 font-bold bg-gray-100">
                DATA PEMBUAT PERNYATAAN
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1 w-[35%]">Nama</td>
            <td class="border border-black px-2 py-1">{{ $form['pembuatNama'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Hubungan dengan Pasien</td>
            <td class="border border-black px-2 py-1">{{ $form['hubunganDenganPasien'] ?? '-' }}</td>
        </tr>

        {{-- ── Penjaminan Biaya ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1 font-bold bg-gray-100">
                PENJAMINAN BIAYA
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Jenis Kartu Penjaminan</td>
            <td class="border border-black px-2 py-1">
                {{ $jenisPenjaminLabel }}
                @if ($jenisPenjaminKey === 'ASURANSI_LAIN' && !empty($asuransiLain))
                    <span class="text-gray-600">({{ $asuransiLain }})</span>
                @endif
            </td>
        </tr>

        {{-- ── Klausul BPJS (hanya tampil jika BPJS Kesehatan) ── --}}
        @if ($jenisPenjaminKey === 'BPJS_KESEHATAN')
            <tr>
                <td class="border border-black px-2 py-1">Persetujuan Ketentuan BPJS</td>
                <td class="border border-black px-2 py-1">
                    {{ !empty($form['bpjsKlausulDisetujui']) ? 'Disetujui' : '-' }}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="border border-black px-2 py-1.5">
                    <p class="font-bold mb-1">Ketentuan Penjaminan BPJS Kesehatan</p>
                    <p class="mb-1 text-justify leading-snug">
                        BPJS Kesehatan hanya menjamin pelayanan kesehatan peserta JKN yang sesuai dengan ketentuan
                        yang berlaku. Pelayanan yang tidak sesuai tidak menjadi tanggungan BPJS Kesehatan, antara lain:
                    </p>
                    <ol class="ml-3 list-decimal leading-snug space-y-0.5">
                        <li>Pelayanan di luar ketentuan/prosedur yang diatur dalam Program JKN.</li>
                        <li>
                            Pelayanan yang tidak sesuai ketentuan:
                            <div class="ml-3 mt-0.5">
                                <div>a. Rawat jalan/rawat inap atas permintaan sendiri (APS).</div>
                                <div>b. Penolakan/tidak mematuhi rencana terapi yang direkomendasikan (pulang APS)
                                    dan menerima segala konsekuensi atas keputusan pribadinya.</div>
                            </div>
                        </li>
                        <li>Pelayanan di luar lingkup penjaminan dalam Perjanjian Kerja Sama.</li>
                        <li>Pelayanan homecare di rumah (tidak dijamin dalam PKS FKRTL).</li>
                        <li>Kecelakaan lalu lintas tidak sesuai ketentuan (tidak urus LP/damai, intoksikasi miras).</li>
                        <li>Pelayanan atas instruksi dari fasilitas kesehatan yang tidak bekerja sama dengan BPJS
                            Kesehatan.</li>
                        <li>Apabila peserta memilih pelayanan di luar ketentuan di atas, biaya menjadi tanggungan
                            pribadi/keluarga.</li>
                    </ol>
                </td>
            </tr>
        @endif

        {{-- ── Orientasi Kamar ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1 font-bold bg-gray-100">
                ORIENTASI KAMAR RAWAT INAP
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Kelas Kamar yang Dipilih</td>
            <td class="border border-black px-2 py-1">
                {{ $kelasLabel }}
                @if (!empty($kelasTarif))
                    <span class="text-gray-500">({{ $kelasTarif }})</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1 align-top">Fasilitas Kamar</td>
            <td class="border border-black px-2 py-1">
                @if (!empty($fasilitas))
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="align-top pr-6">
                                <ul class="list-disc pl-3 space-y-0">
                                    @foreach ($fasChunks[0] ?? [] as $fas)
                                        <li class="leading-snug">{{ $fas }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="align-top">
                                <ul class="list-disc pl-3 space-y-0">
                                    @foreach ($fasChunks[1] ?? [] as $fas)
                                        <li class="leading-snug">{{ $fas }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    </table>
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Penjelasan Fasilitas & Tarif</td>
            <td class="border border-black px-2 py-1">
                {{ !empty($form['orientasiKamarDijelaskan']) ? 'Telah dijelaskan kepada pasien/keluarga' : 'Belum dijelaskan' }}
            </td>
        </tr>

        {{-- ── Pernyataan Penutup ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-2 text-[10px] leading-relaxed text-justify">
                Demikian pernyataan ini saya buat dengan sebenar-benarnya, tanpa paksaan dari pihak manapun, dan
                bersedia menerima konsekuensi atas kebenaran data yang saya sampaikan. Saya juga menyatakan telah
                memahami fasilitas dan tarif kamar yang dipilih, serta menyetujui untuk ditempatkan di ruang rawat
                inap sesuai pilihan tersebut di atas.
            </td>
        </tr>

        {{-- ── Tanda Tangan ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>

                        {{-- Pembuat Pernyataan --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Pembuat Pernyataan</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['signaturePembuatDate'] ?? '-' }}</p>
                            <div style="height:60px; display:flex; align-items:center; justify-content:center;">
                                @if (!empty($signaturePembuat))
                                    <img src="{{ $signaturePembuat }}"
                                        style="max-height:55px; max-width:160px; object-fit:contain;"
                                        alt="TTD Pembuat" />
                                @endif
                            </div>
                            <div
                                style="border-top:1px solid #000; padding-top:3px; margin-top:4px; min-width:120px; display:inline-block;">
                                <p class="font-bold">{{ strtoupper($form['pembuatNama'] ?? '-') }}</p>
                            </div>
                        </td>

                        <td style="border-left:1px solid #d1d5db; width:1px;"></td>

                        {{-- Saksi Keluarga --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Saksi Keluarga</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['signatureSaksiKeluargaDate'] ?? '-' }}
                            </p>
                            <div style="height:60px; display:flex; align-items:center; justify-content:center;">
                                @if (!empty($signatureSaksi))
                                    <img src="{{ $signatureSaksi }}"
                                        style="max-height:55px; max-width:160px; object-fit:contain;" alt="TTD Saksi" />
                                @endif
                            </div>
                            <div
                                style="border-top:1px solid #000; padding-top:3px; margin-top:4px; min-width:120px; display:inline-block;">
                                <p>{{ strtoupper($form['namaSaksiKeluarga'] ?? '-') }}</p>
                            </div>
                        </td>

                        <td style="border-left:1px solid #d1d5db; width:1px;"></td>

                        {{-- Petugas RS --}}
                        <td class="w-1/3 align-top text-center px-2 py-2">
                            <p class="font-bold mb-1">Petugas Rumah Sakit</p>
                            <p class="text-[9px] text-gray-500 mb-2">{{ $form['petugasDate'] ?? '-' }}</p>
                            <div style="height:60px; display:flex; align-items:center; justify-content:center;">
                                @if (!empty($ttdPetugasBase64))
                                    <img src="{{ $ttdPetugasBase64 }}"
                                        style="max-height:55px; max-width:160px; object-fit:contain;"
                                        alt="TTD Petugas" />
                                @endif
                            </div>
                            <div
                                style="border-top:1px solid #000; padding-top:3px; margin-top:4px; min-width:120px; display:inline-block;">
                                <p>{{ strtoupper($form['namaPetugas'] ?? '-') }}</p>
                                @if (!empty($form['kodePetugas']))
                                    <p class="text-[9px] text-gray-500">Kode: {{ $form['kodePetugas'] }}</p>
                                @endif
                            </div>
                        </td>

                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── Footer ── --}}
        <tr>
            <td colspan="2" class="px-1.5 py-1 text-[9px] text-gray-500 text-center border-t border-gray-300">
                Dicetak: {{ $data['tglCetak'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                No. RM: {{ $data['regNo'] ?? '-' }}
                &nbsp;&bull;&nbsp;
                {{ $rsName }}@if (!empty($rsAddress))
                    , {{ $rsAddress }}
                @endif
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
