{{-- resources/views/pages/components/modul-dokumen/r-j/rekam-medis/cetak-rekam-medis-print.blade.php --}}

<x-pdf.layout-a4 title="REKAM MEDIS RAWAT JALAN">

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
            {{-- <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">NIK</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['identitas']['nik'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Id BPJS</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $data['identitas']['idbpjs'] ?? '-' }}</td>
            </tr> --}}
        </table>
    </x-slot>

    {{-- PENGKAJIAN PERAWAT --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Pengkajian Perawat</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        @php $txn = $data['dataDaftarTxn']; @endphp
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500 align-top">Status Psikologis</td>
            <td class="w-4  py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {{ isset($txn['anamnesa']['statusPsikologis']['tidakAdaKelainan']) ? ($txn['anamnesa']['statusPsikologis']['tidakAdaKelainan'] ? 'Tidak Ada Kelainan' : '-') : '-' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['marah']) ? ($txn['anamnesa']['statusPsikologis']['marah'] ? '/ Marah' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['ccemas']) ? ($txn['anamnesa']['statusPsikologis']['ccemas'] ? '/ Cemas' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['takut']) ? ($txn['anamnesa']['statusPsikologis']['takut'] ? '/ Takut' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['sedih']) ? ($txn['anamnesa']['statusPsikologis']['sedih'] ? '/ Sedih' : '') : '' }}
                {{ isset($txn['anamnesa']['statusPsikologis']['cenderungBunuhDiri']) ? ($txn['anamnesa']['statusPsikologis']['cenderungBunuhDiri'] ? '/ Resiko Bunuh Diri' : '') : '' }}
                @if (!empty($txn['anamnesa']['statusPsikologis']['sebutstatusPsikologis']))
                    &mdash; {{ $txn['anamnesa']['statusPsikologis']['sebutstatusPsikologis'] }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Status Mental</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">
                {{ $txn['anamnesa']['statusMental']['statusMental'] ?? '-' }}
                @if (!empty($txn['anamnesa']['statusMental']['sebutstatusPsikologis']))
                    &mdash; {{ $txn['anamnesa']['statusMental']['sebutstatusPsikologis'] }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Perawat Penerima</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">
                {{ isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) ? strtoupper($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) : '-' }}
            </td>
        </tr>
    </table>

    <hr class="mb-3 border-gray-300">

    {{-- ANAMNESA --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Anamnesa</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500 align-top">Keluhan Utama</td>
            <td class="w-4  py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Riwayat Penyakit Sekarang</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Riwayat Penyakit Dahulu</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Alergi</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['anamnesa']['alergi']['alergi'] ?? '-')) !!}
            </td>
        </tr>
    </table>

    {{-- REKONSILIASI OBAT --}}
    @if (!empty($txn['anamnesa']['rekonsiliasiObat']))
        <p class="mb-1 text-[11px] font-bold uppercase">Rekonsiliasi Obat</p>
        <table class="w-full mb-3 text-[11px]" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th class="text-left" style="border:1px solid #d1d5db;">Nama Obat</th>
                    <th class="text-left" style="border:1px solid #d1d5db;">Dosis</th>
                    <th class="text-left" style="border:1px solid #d1d5db;">Rute</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($txn['anamnesa']['rekonsiliasiObat'] as $obat)
                    <tr>
                        <td style="border:1px solid #d1d5db;">{{ $obat['namaObat'] ?? '-' }}</td>
                        <td style="border:1px solid #d1d5db;">{{ $obat['dosis'] ?? '-' }}</td>
                        <td style="border:1px solid #d1d5db;">{{ $obat['rute'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <hr class="mb-3 border-gray-300">

    {{-- TANDA VITAL & NUTRISI --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Tanda Vital & Nutrisi</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500">Tekanan Darah</td>
            <td class="w-4  py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px] w-36">
                {{ $txn['pemeriksaan']['tandaVital']['sistolik'] ?? '-' }} /
                {{ $txn['pemeriksaan']['tandaVital']['distolik'] ?? '-' }} mmHg
            </td>
            <td class="w-44 py-0.5 text-[11px] text-gray-500">Berat Badan</td>
            <td class="w-4  py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['nutrisi']['bb'] ?? '-' }} Kg</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Nadi</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['frekuensiNadi'] ?? '-' }} x/mnt</td>
            <td class="py-0.5 text-[11px] text-gray-500">Tinggi Badan</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['nutrisi']['tb'] ?? '-' }} cm</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Suhu</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['suhu'] ?? '-' }} °C</td>
            <td class="py-0.5 text-[11px] text-gray-500">IMT</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['nutrisi']['imt'] ?? '-' }} Kg/M²</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Pernafasan</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['frekuensiNafas'] ?? '-' }} x/mnt</td>
            <td class="py-0.5 text-[11px] text-gray-500">Lingkar Kepala</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['nutrisi']['lk'] ?? '-' }} cm</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">SPO2</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['spo2'] ?? '-' }} %</td>
            <td class="py-0.5 text-[11px] text-gray-500">Lingkar Lengan Atas</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['nutrisi']['lila'] ?? '-' }} cm</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">GDA</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['gda'] ?? '-' }} mg/dL</td>
            <td colspan="3"></td>
        </tr>
    </table>

    <hr class="mb-3 border-gray-300">

    {{-- PENILAIAN --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Penilaian</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500">Keadaan Umum</td>
            <td class="w-4  py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Tingkat Kesadaran</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">{{ $txn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Skala Nyeri (VAS)</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">
                {{ $txn['penilaian']['nyeri']['vas']['vas'] ?? '-' }}
                &mdash; Pencetus: {{ $txn['penilaian']['nyeri']['pencetus'] ?? '-' }}
                &mdash; Durasi: {{ $txn['penilaian']['nyeri']['durasi'] ?? '-' }}
                &mdash; Lokasi: {{ $txn['penilaian']['nyeri']['lokasi'] ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500">Resiko Jatuh</td>
            <td class="py-0.5 text-[11px]">:</td>
            <td class="py-0.5 text-[11px]">
                Humpty Dumpty:
                {{ $txn['penilaian']['resikoJatuh']['skalaHumptyDumpty']['skalaHumptyDumptyScore'] ?? '-' }}
                ({{ $txn['penilaian']['resikoJatuh']['skalaHumptyDumpty']['skalaHumptyDumptyDesc'] ?? '-' }})
                &nbsp;|&nbsp;
                Morse: {{ $txn['penilaian']['resikoJatuh']['skalaMorse']['skalaMorseScore'] ?? '-' }}
                ({{ $txn['penilaian']['resikoJatuh']['skalaMorse']['skalaMorseDesc'] ?? '-' }})
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Fungsional</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                Alat Bantu: {{ $txn['pemeriksaan']['fungsional']['alatBantu'] ?? '-' }} &nbsp;|&nbsp;
                Prothesa: {{ $txn['pemeriksaan']['fungsional']['prothesa'] ?? '-' }} &nbsp;|&nbsp;
                Cacat Tubuh: {{ $txn['pemeriksaan']['fungsional']['cacatTubuh'] ?? '-' }}
            </td>
        </tr>
    </table>

    <hr class="mb-3 border-gray-300">

    {{-- PEMERIKSAAN FISIK --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Pemeriksaan Fisik & Uji Fungsi</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500 align-top">Fisik & Uji Fungsi</td>
            <td class="w-4  py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['pemeriksaan']['fisik'] ?? '-')) !!}
                {!! nl2br(e($txn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '')) !!}
            </td>
        </tr>
        @if (!empty($txn['pemeriksaan']['anatomi']))
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 align-top">Anatomi</td>
                <td class="py-0.5 text-[11px] align-top">:</td>
                <td class="py-0.5 text-[11px]">
                    @foreach ($txn['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                        @php $kelainan = $pAnatomi['kelainan'] ?? false; @endphp
                        @if ($kelainan && $kelainan !== 'Tidak Diperiksa')
                            <span class="font-semibold">{{ strtoupper($key) }}</span>: {{ $kelainan }}
                            — {!! nl2br(e($pAnatomi['desc'] ?? '-')) !!}<br>
                        @endif
                    @endforeach
                </td>
            </tr>
        @endif
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Pemeriksaan Penunjang</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['pemeriksaan']['penunjang'] ?? '-')) !!}
            </td>
        </tr>
    </table>

    <hr class="mb-3 border-gray-300">

    {{-- DIAGNOSIS & PROSEDUR --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Diagnosis & Prosedur</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500 align-top">Diagnosis</td>
            <td class="w-4  py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['diagnosisFreeText'] ?? '-')) !!}
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Prosedur</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['procedureFreeText'] ?? '-')) !!}
            </td>
        </tr>
    </table>

    <hr class="mb-3 border-gray-300">

    {{-- PERENCANAAN --}}
    <p class="mb-1 text-[11px] font-bold uppercase">Perencanaan</p>
    <table class="w-full mb-3" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-44 py-0.5 text-[11px] text-gray-500 align-top">Tindak Lanjut</td>
            <td class="w-4  py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {{ $txn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}
                @if (!empty($txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                    &mdash; {{ $txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 align-top">Terapi</td>
            <td class="py-0.5 text-[11px] align-top">:</td>
            <td class="py-0.5 text-[11px]">
                {!! nl2br(e($txn['perencanaan']['terapi']['terapi'] ?? '-')) !!}
            </td>
        </tr>
    </table>

    {{-- TANDA TANGAN --}}
    <table class="w-full mt-6" cellpadding="0" cellspacing="0">
        <tr>
            <td class="w-7/12"></td>
            <td class="text-[12px] text-center">
                Tulungagung, {{ $data['tglCetak'] ?? \Carbon\Carbon::now()->translatedFormat('d F Y') }}
                <br><br><br><br><br>
                <div class="inline-block pt-1 border-t border-black" style="min-width:140px;">
                    <span class="text-[11px]">
                        {{ $data['namaDokter'] ?? 'dr. ............................................' }}
                    </span>
                    <div class="text-[10px] text-gray-500 mt-0.5">Dokter Pemeriksa</div>
                    @if (!empty($data['strDokter']))
                        <div class="text-[10px] text-gray-500">STR: {{ $data['strDokter'] }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4>
