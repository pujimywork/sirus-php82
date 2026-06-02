{{-- cetak-riwayat-pengobatan-ri-print.blade.php --}}

@php
    use Carbon\Carbon;

    /* ========= 1) Sumber data dasar ========= */
    $pasien = data_get($dataPasien, 'pasien', []);
    $ri = $dataDaftarRi ?? [];

    /* ========= 2) Identitas Pasien & Perawatan ========= */
    $rm = (string) data_get($pasien, 'regNo', '');
    $nama = (string) data_get($pasien, 'regName', '');
    $tglLahir = (string) data_get($pasien, 'tglLahir', '');
    $ruang = (string) data_get($ri, 'bangsalDesc', '');
    $kamar = trim(
        (string) data_get($ri, 'roomDesc', '') . (data_get($ri, 'bedNo') ? ' / ' . data_get($ri, 'bedNo') : ''),
    );
    $tglMasuk = (string) data_get($ri, 'entryDate', '');
    $tglKeluar = (string) data_get($ri, 'exitDate', '');

    /* DPJP (levelingDokter = Utama) */
    $dokterUtama = collect(data_get($ri, 'pengkajianAwalPasienRawatInap.levelingDokter', []))->first(
        fn($r) => strcasecmp((string) data_get($r, 'levelDokter', ''), 'Utama') === 0,
    );
    $dpjp = (string) data_get($dokterUtama, 'drName', data_get($ri, 'drDesc', 'DPJP'));

    /* ========= 3) Ringkasan Masuk & Anamnesis ========= */
    $diagnosaMasuk = (string) data_get($ri, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', '');
    $keluhanUtama = (string) data_get($ri, 'pengkajianDokter.anamnesa.keluhanUtama', '');
    $keluhanTambahanIndikasiInap = (string) data_get($ri, 'pengkajianDokter.anamnesa.keluhanTambahan', '');
    $riwayatPenyakit =
        'Riwayat Penyakit Sekarang: ' .
        (string) data_get($ri, 'pengkajianDokter.anamnesa.riwayatPenyakit.sekarang', '-') .
        "\n" .
        'Riwayat Penyakit Dahulu: ' .
        (string) data_get($ri, 'pengkajianDokter.anamnesa.riwayatPenyakit.dahulu', '-') .
        "\n" .
        'Riwayat Penyakit Keluarga: ' .
        (string) data_get($ri, 'pengkajianDokter.anamnesa.riwayatPenyakit.keluarga', '-');

    /* ========= 4) Pemeriksaan Fisik Awal ========= */
    $tv = (array) data_get($ri, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital', []);
    $td = trim((string) data_get($tv, 'sistolik', '') . '/' . (string) data_get($tv, 'distolik', ''));
    $suhu = (string) data_get($tv, 'suhu', '');
    $nadi = (string) data_get($tv, 'frekuensiNadi', '');
    $rr = (string) data_get($tv, 'frekuensiNafas', '');
    $gcsAwal = (string) data_get(
        $ri,
        'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.pemeriksaanSistemOrgan.neurologi.gcs',
        '',
    );
    $pemeriksaanFisik = (string) data_get($ri, 'pengkajianDokter.fisik', '');

    /* ========= 5) Penunjang ========= */
    $tandaObs = collect(data_get($ri, 'observasi.observasiLanjutan.tandaVital', []));
    $gdaAwal = (string) data_get($tv, 'gda', '');
    $gdaAkhir = $tandaObs->pluck('gda')->filter(fn($v) => $v !== '' && $v !== '-' && $v !== '0')->last();
    $gdaText = 'GDA awal: ' . ($gdaAwal ?: '-') . '; GDA terakhir: ' . ($gdaAkhir ?: '-');

    $labText = trim((string) data_get($ri, 'pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium', ''));
    $radText =
        'Hasil radiologi: ' .
        trim((string) data_get($ri, 'pengkajianDokter.hasilPemeriksaanPenunjang.radiologi', '-'));
    $lainText =
        'Pemeriksaan penunjang lain: ' .
        trim((string) data_get($ri, 'pengkajianDokter.hasilPemeriksaanPenunjang.penunjangLain', '-'));

    /* ========= 6) Diagnosis (ICD + Free Text) ========= */
    $dxList = collect(data_get($ri, 'diagnosis', []));
    $dxFree = trim((string) data_get($ri, 'diagnosisFreeText', ''));
    $scDxFree = trim((string) data_get($ri, 'secondaryDiagnosisFreeText', ''));

    $dxUtamaRow =
        $dxList->first(function ($d) {
            $k = strtolower($d['kategoriDiagnosa'] ?? '');
            return in_array($k, ['utama', 'primer', 'primary', 'utama/primer'], true);
        }) ?:
        $dxList->first();

    $dxUtama = (string) data_get($dxUtamaRow, 'diagDesc', '');
    $dxUtamaICD = (string) data_get($dxUtamaRow, 'icdX', '');
    $dxSekunderRows = $dxList
        ->reject(fn($d) => $dxUtamaRow && data_get($d, 'diagId') === data_get($dxUtamaRow, 'diagId'))
        ->values();
    $dxSekunder = $dxSekunderRows->pluck('diagDesc')->filter()->values()->all();
    $dxSekunderICD = $dxSekunderRows->pluck('icdX')->filter()->values()->all();

    if ($dxFree !== '') {
        $freeDxItems = collect(preg_split('/\r\n|\r|\n|;|\|/', $dxFree))->map('trim')->filter()->values();
        if ($dxUtama === '' && $freeDxItems->isNotEmpty()) {
            $dxUtama = $freeDxItems->shift();
        }
        foreach ($freeDxItems as $item) {
            $dxSekunder[] = $item;
            $dxSekunderICD[] = '';
        }
    }

    /* ========= 7) Tindakan/Prosedur ========= */
    $procList = collect(data_get($ri, 'procedure', []))
        ->map(fn($p) => [
            'desc' => trim((string) data_get($p, 'procedureDesc', '')),
            'code' => trim((string) data_get($p, 'procedureId', '')),
        ])
        ->filter(fn($x) => $x['desc'] !== '');

    $procFree = trim((string) data_get($ri, 'procedureFreeText', ''));
    if ($procFree !== '') {
        foreach (collect(preg_split('/\r\n|\r|\n|;|\|/', $procFree))->map('trim')->filter() as $fp) {
            $procList->push(['desc' => $fp, 'code' => '']);
        }
    }
    $tindakanDesc = $procList->pluck('desc')->values();
    $tindakanCode = $procList->pluck('code')->values();

    /* ========= 8) Diet ========= */
    $diet = trim((string) data_get($ri, 'pengkajianDokter.rencana.diet', '-'));

    /* ========= 9) Terapi di RS ========= */
    $terapiRS = (string) data_get($ri, 'pengkajianDokter.rencana.terapi', '');

    /* ========= 10) Tindak Lanjut / Cara Keluar RS ========= */
    $tindakLanjutOptions = [
        ['tindakLanjut' => 'Pulang Sehat', 'tindakLanjutKode' => '371827001', 'tindakLanjutKodeBpjs' => 1],
        ['tindakLanjut' => 'Pulang dengan Permintaan Sendiri', 'tindakLanjutKode' => '266707007', 'tindakLanjutKodeBpjs' => 3],
        ['tindakLanjut' => 'Pulang Pindah / Rujuk', 'tindakLanjutKode' => '306206005', 'tindakLanjutKodeBpjs' => 5],
        ['tindakLanjut' => 'Pulang Tanpa Perbaikan', 'tindakLanjutKode' => '371828006', 'tindakLanjutKodeBpjs' => 5],
        ['tindakLanjut' => 'Meninggal', 'tindakLanjutKode' => '419099009', 'tindakLanjutKodeBpjs' => 4],
        ['tindakLanjut' => 'Lain-lain', 'tindakLanjutKode' => '74964007', 'tindakLanjutKodeBpjs' => 5],
    ];
    $tindakLanjutLookup = collect($tindakLanjutOptions)->keyBy('tindakLanjutKode');

    $modelTindakLanjut = (array) data_get($ri, 'perencanaan.tindakLanjut', []);
    $selectedKodeTL = (string) (data_get($modelTindakLanjut, 'tindakLanjutKode') ?: data_get($modelTindakLanjut, 'tindakLanjut'));
    $selectedTindakLanjut = $selectedKodeTL ? $tindakLanjutLookup->get($selectedKodeTL) : null;

    $labelTerpilihTindakLanjut = (string) data_get($selectedTindakLanjut, 'tindakLanjut', '');
    $labelTerpilihTindakLanjutKode = (string) data_get($selectedTindakLanjut, 'tindakLanjutKode', '');
    $kodeBpjsTerpilihTindakLanjut = data_get($selectedTindakLanjut, 'tindakLanjutKodeBpjs');
    $keteranganTindakLanjut = trim((string) data_get($modelTindakLanjut, 'keteranganTindakLanjut', ''));

    $statusPulang = $labelTerpilihTindakLanjut ?: '-';
    $tglKontrol = (string) data_get($ri, 'kontrol.tglKontrol', '');
    $isKontrol = !empty($tglKontrol);
    $isMeninggal = stripos((string) $statusPulang, 'meninggal') !== false || (string) $kodeBpjsTerpilihTindakLanjut === '4';

    /* ========= 11) Kondisi Saat Pulang ========= */
    $terapiPulang = (string) data_get($ri, 'pengkajianDokter.rencana.terapiPulang', '');

    $cppt = collect(data_get($ri, 'cppt', []));
    $exitDateOnly = !empty($tglKeluar) ? Carbon::createFromFormat('d/m/Y H:i:s', $tglKeluar) : null;

    if ($exitDateOnly) {
        $lastCppt = $cppt
            ->filter(fn($row) => !empty($row['tglCPPT']) && Carbon::createFromFormat('d/m/Y H:i:s', $row['tglCPPT'])->isSameDay($exitDateOnly))
            ->sortByDesc(fn($row) => Carbon::createFromFormat('d/m/Y H:i:s', $row['tglCPPT']))
            ->first();
    } else {
        $lastCppt = $cppt->sortByDesc(fn($row) => Carbon::createFromFormat('d/m/Y H:i:s', $row['tglCPPT']))->first();
    }
    $lastSubjective = (string) data_get($lastCppt, 'soap.subjective', '');

    /* Observasi terakhir di hari pulang */
    $lastObsExit = $exitDateOnly
        ? $tandaObs->filter(function ($r) use ($exitDateOnly) {
            $w = data_get($r, 'waktuPemeriksaan');
            if (!$w) return false;
            try { return Carbon::createFromFormat('d/m/Y H:i:s', $w)->isSameDay($exitDateOnly); }
            catch (\Throwable $e) { return false; }
        })->sortBy(function ($r) {
            try { return Carbon::createFromFormat('d/m/Y H:i:s', data_get($r, 'waktuPemeriksaan'))->timestamp; }
            catch (\Throwable $e) { return 0; }
        })->last()
        : null;

    $obs = $lastObsExit ?: $tandaObs->sortBy('waktuPemeriksaan')->last() ?: [];
    $sis = trim((string) data_get($obs, 'sistolik', ''));
    $dis = trim((string) data_get($obs, 'distolik', ''));
    $tdPulang = $sis !== '' && $dis !== '' ? "{$sis}/{$dis}" : ($sis !== '' ? $sis : ($dis !== '' ? "/{$dis}" : '-'));
    $suhuPulang = ($tmp = trim((string) data_get($obs, 'suhu', ''))) !== '' ? $tmp : '-';
    $nadiPulang = ($tmp = trim((string) data_get($obs, 'frekuensiNadi', ''))) !== '' ? $tmp : '-';
    $rrPulang = ($tmp = trim((string) data_get($obs, 'frekuensiNafas', ''))) !== '' ? $tmp : '-';
    $gcsPulang = ($tmp = trim((string) data_get($obs, 'gcs', ''))) !== '' ? $tmp : ($isMeninggal ? '0' : '-');
@endphp

<x-pdf.layout-a4-with-out-background title="RIWAYAT PENGOBATAN" :showGaris="false">
    <x-slot:patientData>
        <x-pdf.identitas-pasien :rm="$rm" :nama="$nama" :tglLahir="$tglLahir">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Ruang / Kamar</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $ruang }} / {{ $kamar }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Masuk</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $tglMasuk }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tgl. Keluar</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">@if ($isMeninggal) Meninggal, @endif {{ $tglKeluar }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">DPJP</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $dpjp }}</td>
            </tr>
        </x-pdf.identitas-pasien>
    </x-slot:patientData>

    {{-- ======================= IDENTITAS RINGKAS ======================= --}}
    <table class="w-full mt-1 border border-collapse border-black table-auto">
        <tr>
            <th class="w-40 px-2 py-1 text-left border border-black">Diagnosis masuk</th>
            <td class="px-2 py-1 border border-black" colspan="3">{{ $diagnosaMasuk }}</td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left border border-black">Indikasi Rawat Inap</th>
            <td class="px-2 py-1 border border-black" colspan="3">{{ $keluhanTambahanIndikasiInap }}</td>
        </tr>
    </table>

    {{-- ======================= ANAMNESIS ======================= --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="6" class="px-2 py-1 text-left">ANAMNESIS</th>
        </tr>
        <tr>
            <th class="w-48 px-2 py-1 text-left border border-black">Keluhan utama</th>
            <td class="px-2 py-1 border border-black" colspan="5">{{ $keluhanUtama }}</td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left align-top border border-black">Riwayat penyakit</th>
            <td class="px-2 py-1 whitespace-pre-line border border-black" colspan="5">{{ $riwayatPenyakit }}</td>
        </tr>
    </table>

    {{-- ======================= PEMERIKSAAN FISIK ======================= --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="6" class="px-2 py-1 text-left">PEMERIKSAAN FISIK</th>
        </tr>
        <tr>
            <th class="w-48 px-2 py-1 text-left border border-black">Keadaan umum</th>
            <td class="px-2 py-1 border border-black" colspan="5">
                {{ data_get($ri, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.keluhanUtama', '') ?: '-' }}
            </td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left border border-black">Tanda vital</th>
            <td class="px-2 py-1 border border-black" colspan="5">
                TD: {{ $td }} &nbsp; Suhu: {{ $suhu }} &nbsp; Nadi: {{ $nadi }} &nbsp;
                RR: {{ $rr }} &nbsp; {{ $gdaText }} &nbsp; GCS: {{ $gcsAwal }}
            </td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left align-top border border-black">Pemeriksaan Fisik</th>
            <td class="px-2 py-1 whitespace-pre-line border border-black" colspan="5">{{ $pemeriksaanFisik }}</td>
        </tr>
    </table>

    {{-- ======================= PEMERIKSAAN PENUNJANG ======================= --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="6" class="px-2 py-1 text-left">PEMERIKSAAN PENUNJANG</th>
        </tr>
        <tr>
            <th class="w-48 px-2 py-1 text-left border border-black">1. LABORATORIUM</th>
            <td class="px-2 py-1 border border-black" colspan="5">{{ $labText }}</td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left border border-black">2. RADIOLOGI</th>
            <td class="px-2 py-1 border border-black" colspan="5">{{ $radText }}</td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left border border-black">3. LAIN-LAIN</th>
            <td class="px-2 py-1 border border-black" colspan="5">{{ $lainText }}</td>
        </tr>
    </table>

    {{-- ======================= TERAPI DI RS ======================= --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="4" class="px-2 py-1 text-left">TERAPI / TINDAKAN MEDIS SELAMA DI RUMAH SAKIT</th>
        </tr>
        <tr>
            <td class="px-3 py-0.5 text-sm break-words whitespace-pre-line border border-black" colspan="4">
                {{ $terapiRS }}
            </td>
        </tr>
    </table>

    {{-- ======================= DIAGNOSIS & TINDAKAN + ICD ======================= --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr>
            <th class="w-48 px-2 py-1 text-left border border-black">DIAGNOSIS UTAMA</th>
            <td class="px-2 py-1 border border-black">{{ $dxFree ?: $dxUtama }}</td>
            <th class="w-24 px-2 py-1 text-left border border-black">ICD-10</th>
            <td class="w-32 px-2 py-1 border border-black">{{ $dxUtamaICD }}</td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left align-top border border-black">DIAGNOSIS SEKUNDER :</th>
            <td class="px-2 py-1 align-top border border-black">
                <ol class="pl-6 leading-6 list-none">
                    @if ($scDxFree) <li>{{ $scDxFree }}</li> @endif
                    @forelse($dxSekunder as $dx)
                        <li>{{ $dx }}</li>
                    @empty
                        <li>&nbsp;</li>
                    @endforelse
                </ol>
            </td>
            <th class="px-2 py-1 text-left align-top border border-black">ICD-10</th>
            <td class="px-2 py-1 align-top border border-black">
                <ol class="pl-6 leading-6 list-none">
                    @forelse($dxSekunderICD as $code)
                        <li>{{ $code }}</li>
                    @empty
                        <li>&nbsp;</li>
                    @endforelse
                </ol>
            </td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left align-top border border-black">TINDAKAN/PROSEDUR :</th>
            <td class="px-2 py-1 align-top border border-black">
                <ol class="pl-6 leading-6 list-none">
                    @if ($procFree) <li>{{ $procFree }}</li> @endif
                    @forelse($tindakanDesc as $t)
                        <li>{{ $t }}</li>
                    @empty
                        <li>&nbsp;</li>
                    @endforelse
                </ol>
            </td>
            <th class="px-2 py-1 text-left align-top border border-black">ICD-9-CM</th>
            <td class="px-2 py-1 align-top border border-black">
                <ol class="pl-6 leading-6 list-none">
                    @forelse($tindakanCode as $c)
                        <li>{{ $c }}</li>
                    @empty
                        <li>&nbsp;</li>
                    @endforelse
                </ol>
            </td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left border border-black">DIET</th>
            <td class="px-2 py-1 border border-black" colspan="3">{{ $diet }}</td>
        </tr>
    </table>

    <div class="mt-1 text-xs italic text-right">Bersambung ke hal 2</div>
    <div style="page-break-after: always;"></div>

    {{-- ======================= HALAMAN 2 ======================= --}}
    <div class="font-semibold">Sambungan <span class="uppercase">RINGKASAN PULANG</span></div>

    <table class="w-full mt-1 border border-collapse border-black table-auto">
        <tr>
            <th class="w-40 px-2 py-1 text-left border border-black">Nama pasien :</th>
            <td class="px-2 py-1 border border-black">{{ $nama }}</td>
            <th class="w-40 px-2 py-1 text-left border border-black">No. Rekam Medis :</th>
            <td class="px-2 py-1 border border-black">{{ $rm }}</td>
        </tr>
    </table>

    {{-- KONDISI SAAT PULANG --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="4" class="px-2 py-1 text-left">KONDISI SAAT PULANG</th>
        </tr>
        <tr>
            <th class="w-48 px-2 py-1 text-left align-top border border-black">Keadaan umum</th>
            <td class="px-2 py-1 border border-black">{{ $lastSubjective }}</td>
            <th class="w-24 px-2 py-1 text-left align-top border border-black">GCS</th>
            <td class="px-2 py-1 border border-black">{{ $gcsPulang }}</td>
        </tr>
        <tr>
            <th class="px-2 py-1 text-left border border-black">Tanda vital</th>
            <td class="px-2 py-1 border border-black" colspan="3">
                TD: {{ $tdPulang }} &nbsp; Suhu: {{ $suhuPulang }} &nbsp;
                Nadi: {{ $nadiPulang }} &nbsp; RR: {{ $rrPulang }}
            </td>
        </tr>
    </table>

    {{-- CARA KELUAR RS --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="6" class="px-2 py-1 text-left">CARA KELUAR RS</th>
        </tr>
        <tr>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '371827001') bg-gray-900 @endif">&nbsp;</span> Pulang Sehat
            </td>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '266707007') bg-gray-900 @endif">&nbsp;</span> Pulang Atas Permintaan Sendiri
            </td>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '306206005') bg-gray-900 @endif">&nbsp;</span> Dirujuk
            </td>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '371828006') bg-gray-900 @endif">&nbsp;</span> Pulang Tanpa Perbaikan
            </td>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '419099009') bg-gray-900 @endif">&nbsp;</span> Meninggal
            </td>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '74964007') bg-gray-900 @endif">&nbsp;</span> Lain-lain
            </td>
        </tr>
    </table>

    {{-- TINDAK LANJUT --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="6" class="px-2 py-1 text-left">TINDAK LANJUT</th>
        </tr>
        <tr>
            <td class="w-1/2 px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($isKontrol) bg-gray-900 @endif">&nbsp;</span>
                Kontrol rawat jalan, tanggal
                <span class="inline-block w-56 align-middle border-b border-black border-dotted">&nbsp;{{ $tglKontrol }}&nbsp;</span>
            </td>
            <td class="w-1/2 px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black">&nbsp;</span>
                <span class="inline-block w-56 border-b border-black border-dotted">&nbsp;&nbsp;</span>
            </td>
        </tr>
        <tr>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black @if ($labelTerpilihTindakLanjutKode === '306206005') bg-gray-900 @endif">&nbsp;</span>
                Dirujuk ke
                <span class="inline-block w-64 border-b border-black border-dotted">&nbsp;{{ $keteranganTindakLanjut }}</span>
            </td>
            <td class="px-2 py-1 border border-black">
                <span class="inline-block w-3 h-3 mr-2 align-middle border border-black">&nbsp;</span>
                <span class="inline-block w-56 border-b border-black border-dotted">&nbsp;&nbsp;</span>
            </td>
        </tr>
    </table>

    {{-- TERAPI PULANG --}}
    <table class="w-full mt-2 border border-collapse border-black table-auto">
        <tr class="font-semibold bg-gray-100">
            <th colspan="4" class="px-2 py-1 text-left">TERAPI PULANG</th>
        </tr>
        <tr>
            <td class="px-3 py-0.5 text-sm break-words whitespace-pre-line border border-black" colspan="4">
                {{ $terapiPulang }}
            </td>
        </tr>
    </table>

    {{-- TTD --}}
    <table class="w-full mt-3 border border-collapse border-black table-auto">
        <tr>
            <td class="border border-black px-1.5 py-2 align-top text-center">
                <div class="text-center mb-0.5">&nbsp;</div>
                <div class="text-center">
                    <div class="h-16">&nbsp;</div>
                </div>
                <div class="text-center">
                    <span class="inline-block min-w-[150px] border-t border-black pt-0.5">
                        Tanda tangan pasien/keluarga
                    </span>
                </div>
            </td>
            <td class="border border-black px-1.5 py-2 align-top text-center">
                <div class="text-center mb-0.5">Tulungagung, {{ $tglKeluar ?: '-' }}</div>
                <div class="text-center">
                    <div class="h-16">&nbsp;</div>
                </div>
                <div class="text-center">
                    <span class="inline-block min-w-[150px] border-t border-black pt-0.5 font-bold">
                        {{ $dpjp ?: 'DPJP' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- FOOTER --}}
    <div class="text-center text-[10px] mt-2 font-semibold">
        MOHON UNTUK TIDAK MENGGUNAKAN SINGKATAN DALAM PENULISAN DIAGNOSIS DAN TINDAKAN<br />
        SERTA DITULIS DENGAN RAPI
    </div>
    <div class="text-right text-[10px]">2/2</div>

</x-pdf.layout-a4-with-out-background>
