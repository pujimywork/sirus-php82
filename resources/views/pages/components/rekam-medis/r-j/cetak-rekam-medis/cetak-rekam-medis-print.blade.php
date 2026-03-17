{{-- resources/views/pages/components/modul-dokumen/r-j/rekam-medis/cetak-rekam-medis-print.blade.php --}}

<x-pdf.layout-a4 title="ASSESMENT AWAL RAWAT JALAN">

    {{-- IDENTITAS PASIEN — sejajar dengan logo --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $data['regNo'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['dataDaftarTxn']['rjDate'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ strtoupper($data['regName'] ?? '-') }}</td>
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
                        $alamat = $data['identitas']['alamat'] ?? '-';
                        $rt = $data['identitas']['rt'] ?? '';
                        $rw = $data['identitas']['rw'] ?? '';
                        $desa = $data['identitas']['desaName'] ?? '';
                        $kec = $data['identitas']['kecamatanName'] ?? '';
                        $full = trim(
                            $alamat .
                                ($rt ? ' RT ' . $rt : '') .
                                ($rw ? '/RW ' . $rw : '') .
                                ($desa ? ', ' . $desa : '') .
                                ($kec ? ', ' . $kec : ''),
                        );
                    @endphp
                    {{ $full }}
                </td>
            </tr>
        </table>
    </x-slot>

    {{-- ================================================================ --}}
    {{-- ASSESMENT AWAL RAWAT JALAN                                        --}}
    {{-- ================================================================ --}}
    @php
        $txn = $data['dataDaftarTxn'];
        $lastNyeri = !empty($txn['penilaian']['nyeri']) ? end($txn['penilaian']['nyeri']) : null;
        $lastResikoJatuh = !empty($txn['penilaian']['resikoJatuh']) ? end($txn['penilaian']['resikoJatuh']) : null;
        $lastDekubitus = !empty($txn['penilaian']['dekubitus']) ? end($txn['penilaian']['dekubitus']) : null;
        $lastGizi = !empty($txn['penilaian']['gizi']) ? end($txn['penilaian']['gizi']) : null;
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ──  PERAWAT (full width) ────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top w-28">
                PERAWAT
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                <span class="font-bold">Status Psikologis :</span>
                {{ isset($txn['anamnesa']['statusPsikologis']['tidakAdaKelainan']) ? ($txn['anamnesa']['statusPsikologis']['tidakAdaKelainan'] ? 'Tidak Ada Kelainan' : '-') : '-' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['marah']) ? ($txn['anamnesa']['statusPsikologis']['marah'] ? '/ Marah' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['ccemas']) ? ($txn['anamnesa']['statusPsikologis']['ccemas'] ? '/ Cemas' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['takut']) ? ($txn['anamnesa']['statusPsikologis']['takut'] ? '/ Takut' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['sedih']) ? ($txn['anamnesa']['statusPsikologis']['sedih'] ? '/ Sedih' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['cenderungBunuhDiri']) ? ($txn['anamnesa']['statusPsikologis']['cenderungBunuhDiri'] ? '/ Resiko Bunuh Diri' : '') : '' }}
                @if (!empty($txn['anamnesa']['statusPsikologis']['sebutstatusPsikologis']))
                    &mdash; {{ $txn['anamnesa']['statusPsikologis']['sebutstatusPsikologis'] }}
                @endif
                &nbsp;&nbsp;
                <span class="font-bold">Keterangan Status Psikologis</span>
                <br>
                <span class="font-bold">Status Mental :</span>
                {{ $txn['anamnesa']['statusMental']['statusMental'] ?? '-' }}
                @if (!empty($txn['anamnesa']['statusMental']['sebutstatusPsikologis']))
                    &mdash; {{ $txn['anamnesa']['statusMental']['sebutstatusPsikologis'] }}
                @endif
                &nbsp;&nbsp;
                <span class="font-bold">Keterangan Status Mental :</span>
                {{ $txn['anamnesa']['statusMental']['keteranganStatusMental'] ?? '-' }}
            </td>
        </tr>

        {{-- ── ANAMNESA + panel kanan (Perawat, Tanda Vital, Nutrisi) ──── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                ANAMNESA
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Keluhan Utama :</span>
                {!! nl2br(e($txn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-')) !!}
                <br>
                <span class="font-bold">Screening Batuk :</span>
                {{ $txn['anamnesa']['screeningBatuk'] ?? '-' }}
                <br>

                {{-- ── SKALA NYERI ── --}}
                <span class="font-bold">Skala Nyeri :</span>
                Metode : {{ $lastNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }} /
                Skor : {{ $lastNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }} /
                {{ $lastNyeri['nyeri']['nyeriKet'] ?? '-' }} /
                Pencetus : {{ $lastNyeri['nyeri']['pencetus'] ?? '-' }} /
                Durasi : {{ $lastNyeri['nyeri']['durasi'] ?? '-' }} /
                Lokasi : {{ $lastNyeri['nyeri']['lokasi'] ?? '-' }}
                <br>

                {{-- ── RESIKO JATUH ── --}}
                <span class="font-bold">Resiko Jatuh :</span>
                Metode : {{ $lastResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }} /
                Skor : {{ $lastResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }} /
                {{ $lastResikoJatuh['resikoJatuh']['kategoriResiko'] ?? '-' }}
                <br>

                {{-- ── DEKUBITUS (tampil hanya jika ada data) ── --}}
                @if ($lastDekubitus)
                    <span class="font-bold">Dekubitus :</span>
                    {{ $lastDekubitus['dekubitus']['dekubitus'] ?? '-' }} /
                    Skor Braden : {{ $lastDekubitus['dekubitus']['bradenScore'] ?? '-' }} /
                    {{ $lastDekubitus['dekubitus']['kategoriResiko'] ?? '-' }}
                    @if (!empty($lastDekubitus['dekubitus']['rekomendasi']))
                        / {{ $lastDekubitus['dekubitus']['rekomendasi'] }}
                    @endif
                    <br>
                @endif

                {{-- ── GIZI (tampil hanya jika ada data) ── --}}
                @if ($lastGizi)
                    <span class="font-bold">Gizi :</span>
                    BB : {{ $lastGizi['gizi']['beratBadan'] ?? '-' }} kg /
                    TB : {{ $lastGizi['gizi']['tinggiBadan'] ?? '-' }} cm /
                    IMT : {{ $lastGizi['gizi']['imt'] ?? '-' }} /
                    Skor Skrining : {{ $lastGizi['gizi']['skorSkrining'] ?? '-' }} /
                    {{ $lastGizi['gizi']['kategoriGizi'] ?? '-' }}
                    @if (!empty($lastGizi['gizi']['catatan']))
                        / {{ $lastGizi['gizi']['catatan'] }}
                    @endif
                    <br>
                @endif

                <span class="font-bold">Riwayat Penyakit Sekarang :</span>
                {!! nl2br(e($txn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-')) !!}
                <br>
                <span class="font-bold">Riwayat Penyakit Dahulu :</span>
                {!! nl2br(e($txn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-')) !!}
                <br>
                <span class="font-bold">Alergi :</span>
                {!! nl2br(e($txn['anamnesa']['alergi']['alergi'] ?? '-')) !!}
                <br>
                <span class="font-bold">Rekonsiliasi Obat :</span>
                <table class="w-full border-collapse mt-0.5 text-[10px]">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-500 px-1 py-0.5 text-left">Nama Obat</th>
                            <th class="border border-gray-500 px-1 py-0.5 text-left">Dosis</th>
                            <th class="border border-gray-500 px-1 py-0.5 text-left">Rute</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($txn['anamnesa']['rekonsiliasiObat'] ?? [] as $obat)
                            <tr>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['namaObat'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['dosis'] ?? '-' }}</td>
                                <td class="border border-gray-500 px-1 py-0.5">{{ $obat['rute'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                                <td class="border border-gray-500 px-1 py-0.5">&nbsp;</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            {{-- ── PANEL KANAN: Perawat + Tanda Vital + Nutrisi ────────── --}}
            <td class="border border-black px-1.5 py-0.5 align-top w-44">
                <p class="font-bold mb-0.5">PERAWAT / TERAPIS :</p>

                {{-- TTD Perawat --}}
                @isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                    @if ($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                        @isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                            @if ($txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                @php
                                    $ttdPerawat = App\Models\User::where(
                                        'myuser_code',
                                        $txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'],
                                    )->value('myuser_ttd_image');
                                @endphp
                                @if (!empty($ttdPerawat))
                                    <img class="h-12 mx-auto" src="{{ 'storage/' . $ttdPerawat }}" alt="">
                                @endif
                            @endif
                        @endisset
                    @endif
                @endisset

                <p class="mb-1.5">
                    {{ isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                        ? strtoupper($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                        : '-' }}
                </p>

                <p class="font-bold mb-0.5">TANDA VITAL :</p>
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-20">TD</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['tandaVital']['sistolik'] ?? '-' }} /
                            {{ $txn['pemeriksaan']['tandaVital']['distolik'] ?? '-' }} mmhg</td>
                    </tr>
                    <tr>
                        <td>Nadi</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['tandaVital']['frekuensiNadi'] ?? '-' }} x/mnt</td>
                    </tr>
                    <tr>
                        <td>Suhu</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['tandaVital']['suhu'] ?? '-' }} °C</td>
                    </tr>
                    <tr>
                        <td>Pernafasan</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['tandaVital']['frekuensiNafas'] ?? '-' }} x/mnt</td>
                    </tr>
                    <tr>
                        <td>SPO2</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['tandaVital']['spo2'] ?? '-' }} %</td>
                    </tr>
                    <tr>
                        <td>GDA</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['tandaVital']['gda'] ?? '-' }} mg/dL</td>
                    </tr>
                </table>

                <p class="font-bold mt-1.5 mb-0.5">NUTRISI :</p>
                <table cellpadding="0" cellspacing="0" class="w-full text-[10px]">
                    <tr>
                        <td class="w-20">Berat Badan</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['nutrisi']['bb'] ?? '-' }} Kg</td>
                    </tr>
                    <tr>
                        <td>Tinggi Badan</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['nutrisi']['tb'] ?? '-' }} cm</td>
                    </tr>
                    <tr>
                        <td>Index Masa Tubuh</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['nutrisi']['imt'] ?? '-' }} Kg/M2</td>
                    </tr>
                    <tr>
                        <td>Lingkar Kepala</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['nutrisi']['lk'] ?? '-' }} cm</td>
                    </tr>
                    <tr>
                        <td>Lingkar Lengan Atas</td>
                        <td>:&nbsp;{{ $txn['pemeriksaan']['nutrisi']['lila'] ?? '-' }} cm</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── KEADAAN UMUM ─────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">
                KEADAAN UMUM
            </td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                {{ $txn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? 'BAIK' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Tingkat Kesadaran :</span>
                {{ $txn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}
            </td>
        </tr>

        {{-- ── FUNGSIONAL ───────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">
                FUNGSIONAL
            </td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                <span class="font-bold">Alat Bantu :</span> {{ $txn['pemeriksaan']['fungsional']['alatBantu'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Prothesa :</span> {{ $txn['pemeriksaan']['fungsional']['prothesa'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Cacat Tubuh :</span>
                {{ $txn['pemeriksaan']['fungsional']['cacatTubuh'] ?? '-' }}
                &nbsp;/&nbsp;
                <span class="font-bold">Suspek Akibat Kecelakaan Kerja :</span>
                {{ $txn['pemeriksaan']['fungsional']['suspekKecelakaanKerja'] ?? '-' }}
            </td>
        </tr>

        {{-- ── PEMERIKSAAN ────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                PEMERIKSAAN
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Fisik dan Uji Fungsi:</span><br>
                {!! nl2br(e($txn['pemeriksaan']['fisik'] ?? '-')) !!}
                {!! nl2br(e($txn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '')) !!}
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                <span class="font-bold">Anatomi :</span><br>
                @if (!empty($txn['pemeriksaan']['anatomi']))
                    @foreach ($txn['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                        @php $kelainan = $pAnatomi['kelainan'] ?? false; @endphp
                        @if ($kelainan && $kelainan !== 'Tidak Diperiksa')
                            <span class="font-semibold">{{ strtoupper($key) }}</span>:
                            {{ $kelainan }} &mdash; {!! nl2br(e($pAnatomi['desc'] ?? '-')) !!}<br>
                        @endif
                    @endforeach
                @endif
            </td>
        </tr>

        {{-- ── PENUNJANG ────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                PENUNJANG
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                <span class="font-bold">Pemeriksaan Penunjang Lab / Foto / EKG / Lan-lain :</span><br>
                {!! nl2br(e($txn['pemeriksaan']['penunjang'] ?? '-')) !!}
            </td>
        </tr>

        {{-- ── DIAGNOSIS ────────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                DIAGNOSIS
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                {!! nl2br(e($txn['diagnosisFreeText'] ?? '-')) !!}
            </td>
        </tr>

        {{-- ── PROSEDUR ─────────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                PROSEDUR
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top" colspan="2">
                {!! nl2br(e($txn['procedureFreeText'] ?? '-')) !!}
            </td>
        </tr>

        {{-- ── TINDAK LANJUT ────────────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-middle">
                TINDAK LANJUT
            </td>
            <td class="border border-black px-1.5 py-0.5 align-middle" colspan="2">
                {{ $txn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}
                @if (!empty($txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                    / {{ $txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                @endif
            </td>
        </tr>

        {{-- ── TERAPI + TTD DOKTER ──────────────────────────────────────── --}}
        <tr>
            <td class="border border-black px-1.5 py-0.5 font-bold align-top">
                TERAPI
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top">
                {!! nl2br(e($txn['perencanaan']['terapi']['terapi'] ?? '-')) !!}
            </td>
            <td class="border border-black px-1.5 py-0.5 align-top text-center">
                Tulungagung, {{ $data['tglCetak'] ?? \Carbon\Carbon::now()->format('d/m/Y') }}
                <br>

                {{-- TTD Dokter --}}
                @isset($txn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                    @if ($txn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                        @php
                            $ttdDokter = App\Models\User::where('myuser_code', $txn['drId'] ?? '')->value(
                                'myuser_ttd_image',
                            );
                        @endphp
                        @if (!empty($ttdDokter))
                            <img class="h-16 mx-auto" src="{{ 'storage/' . $ttdDokter }}" alt="">
                        @else
                            <br><br><br>
                        @endif
                    @else
                        <br><br><br>
                    @endif
                @else
                    <br><br><br>
                @endisset

                <div class="inline-block min-w-[130px] border-t border-black pt-0.5">
                    <span class="text-[10px]">
                        {{ $data['namaDokter'] ?? 'dr. ............................................' }}
                    </span>
                    @if (!empty($data['strDokter']))
                        <div class="text-[9px] text-gray-500">STR: {{ $data['strDokter'] }}</div>
                    @endif
                </div>
            </td>
        </tr>

    </table>

</x-pdf.layout-a4>
