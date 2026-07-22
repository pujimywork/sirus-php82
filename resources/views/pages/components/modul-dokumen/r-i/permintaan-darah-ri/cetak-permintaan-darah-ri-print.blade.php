{{-- resources/views/pages/components/modul-dokumen/r-i/permintaan-darah-ri/cetak-permintaan-darah-ri-print.blade.php --}}
{{-- Formulir Permintaan Darah (transfusi) — Bagian 1 (RS) terisi + Bagian 2 (PMI) kosong utk diisi manual --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PERMINTAAN DARAH">

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
        $identitasRs = $data['identitasRs'] ?? null;

        // Tampilkan nilai apa adanya; jika kosong ganti tanda strip.
        $isiAtauStrip = fn($nilai) => filled($nilai) ? $nilai : '-';

        // Border eksplisit: preflight Tailwind (di-inject layout) me-reset border-width:0,
        // sehingga atribut HTML border="1" tak tampak → wajib inline style.
        $borderSel = 'border:1px solid #000; padding:4px;';

        // Golongan + rhesus (mis. "A +"); strip bila keduanya kosong.
        $labelGolongan = function ($baris) {
            $golongan = trim(($baris['golongan'] ?? '') . (($baris['rhesus'] ?? '') !== '' ? ' ' . $baris['rhesus'] : ''));
            return $golongan !== '' ? $golongan : '-';
        };
        // Jumlah + satuan (mis. "2 Unit"); strip bila jumlah kosong.
        $labelJumlah = function ($baris) {
            $jumlah = trim((string) ($baris['jumlah'] ?? ''));
            return $jumlah !== '' ? $jumlah . ' ' . ($baris['satuan'] ?? '') : '-';
        };
    @endphp

    <div style="font-size:11px; color:#111;">

        {{-- ══════════ DIISI OLEH PETUGAS RUMAH SAKIT ══════════ --}}
        <div style="font-weight:bold; margin:6px 0 4px;">DIISI OLEH PETUGAS RUMAH SAKIT</div>

        <table style="width:100%; border-collapse:collapse; margin-bottom:6px;">
            <tr>
                <td style="width:22%;">Transfusi sebelumnya</td>
                <td style="width:2%;">:</td>
                <td>{{ $label['transfusi'][$form['transfusiSebelumnya'] ?? ''] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Diagnosa Sementara</td>
                <td>:</td>
                <td>{{ $isiAtauStrip($form['diagnosaSementara'] ?? '') }}</td>
            </tr>
            <tr>
                <td>Indikasi Transfusi</td>
                <td>:</td>
                <td>{{ $isiAtauStrip($form['indikasiTransfusi'] ?? '') }}</td>
            </tr>
        </table>

        <div style="font-weight:bold; margin:6px 0 4px;">JENIS DARAH YANG DIPERLUKAN</div>
        @php
            // Hanya jenis yang DIPILIH (pilih=true) yang ditampilkan.
            $barisDarahTampil = [];
            foreach (['wb', 'prc', 'ffp'] as $kode) {
                $baris = $form['jenisDarah'][$kode] ?? [];
                if (!empty($baris['pilih'])) {
                    $barisDarahTampil[] = ['nama' => $label['jenis'][$kode] ?? $kode, 'baris' => $baris];
                }
            }
            $barisLainnya = $form['jenisDarah']['lainnya'] ?? [];
            if (!empty($barisLainnya['pilih'])) {
                $keterangan = trim(($barisLainnya['ket1'] ?? '') . (filled($barisLainnya['ket2'] ?? '') ? ', ' . $barisLainnya['ket2'] : ''));
                $barisDarahTampil[] = ['nama' => 'Lainnya' . (filled($keterangan) ? ': ' . $keterangan : ''), 'baris' => $barisLainnya];
            }
        @endphp
        <table style="width:100%; border-collapse:collapse; border:1px solid #000;">
            <thead>
                <tr style="background:#f2f2f2; font-weight:bold; text-align:center;">
                    <td style="{{ $borderSel }} text-align:left;">Jenis Permintaan</td>
                    <td style="{{ $borderSel }}">Golongan Darah</td>
                    <td style="{{ $borderSel }}">Jumlah Unit/CC</td>
                    <td style="{{ $borderSel }}">Diperlukan (Tgl &amp; Jam)</td>
                </tr>
            </thead>
            <tbody>
                @forelse ($barisDarahTampil as $barisDarah)
                    <tr>
                        <td style="{{ $borderSel }}">{{ $barisDarah['nama'] }}</td>
                        <td style="{{ $borderSel }} text-align:center;">{{ $labelGolongan($barisDarah['baris']) }}</td>
                        <td style="{{ $borderSel }} text-align:center;">{{ $labelJumlah($barisDarah['baris']) }}</td>
                        <td style="{{ $borderSel }} text-align:center;">{{ $isiAtauStrip($barisDarah['baris']['diperlukan'] ?? '') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="{{ $borderSel }} text-align:center;">-</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Tempat/tanggal + TTD Dokter peminta --}}
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <tr>
                <td style="width:55%;">&nbsp;</td>
                <td style="text-align:center;">
                    {{ $identitasRs->int_city ?? 'Tasikmalaya' }}, {{ $data['tglCetak'] ?? '' }}<br>
                    Dokter yang meminta,
                    <div style="height:56px; text-align:center;">
                        @if (!empty($data['ttdDokterPath']))
                            <img src="{{ $data['ttdDokterPath'] }}" style="height:52px;" alt="TTD Dokter">
                        @else
                            &nbsp;
                        @endif
                    </div>
                    ( {{ $isiAtauStrip($form['ttd']['dokterNama'] ?? '') }} )
                </td>
            </tr>
        </table>

        {{-- ══════════ DIISI OLEH PETUGAS PMI (KOSONG — diisi manual) ══════════ --}}
        <div style="font-weight:bold; margin:14px 0 4px;">DIISI OLEH PETUGAS PMI</div>
        <div style="margin-bottom:4px;">Telah kami kirimkan</div>
        <table style="width:100%; border-collapse:collapse; border:1px solid #000;">
            <thead>
                <tr style="background:#f2f2f2; font-weight:bold; text-align:center;">
                    <td style="{{ $borderSel }} width:6%;">No</td>
                    <td style="{{ $borderSel }}">Jenis Darah</td>
                    <td style="{{ $borderSel }}">Nomor Labu</td>
                    <td style="{{ $borderSel }}">Gol. Darah</td>
                    <td style="{{ $borderSel }}">Jumlah unit/cc</td>
                </tr>
            </thead>
            <tbody>
                @for ($i = 1; $i <= 3; $i++)
                    <tr>
                        <td style="{{ $borderSel }} text-align:center;">{{ $i }}</td>
                        <td style="{{ $borderSel }}">&nbsp;</td>
                        <td style="{{ $borderSel }}">&nbsp;</td>
                        <td style="{{ $borderSel }}">&nbsp;</td>
                        <td style="{{ $borderSel }}">&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <tr>
                <td style="width:14%;">Untuk pasien</td><td style="width:2%;"></td><td style="width:34%;"></td>
                <td style="width:16%;"></td><td style="width:2%;"></td><td></td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;Nama</td><td>:</td><td>{{ $isiAtauStrip($data['regName'] ?? '') }}</td>
                <td>Bagian</td><td>:</td><td>.................................</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;&nbsp;Dirawat di</td><td>:</td><td>{{ $identitasRs->int_name ?? 'RS' }}</td>
                <td>Ruangan</td><td>:</td><td>.................................</td>
            </tr>
            <tr><td>&nbsp;&nbsp;&nbsp;Hasil IMLTD</td><td>:</td><td colspan="4">.......................................................................</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;Hasil Crossmatch</td><td>:</td><td colspan="4">.......................................................................</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;Tanggal Pengiriman</td><td>:</td><td colspan="4">.......................................................................</td></tr>
        </table>

        <table style="width:100%; border-collapse:collapse; margin-top:14px; text-align:center;">
            <tr>
                <td style="width:50%;">
                    Pengirim,<br>Petugas PMI
                    <div style="height:52px;">&nbsp;</div>
                    ( ................................................... )
                </td>
                <td style="width:50%;">
                    Penerima,
                    <div style="height:52px;">&nbsp;</div>
                    ( ................................................... )
                </td>
            </tr>
        </table>

    </div>
</x-pdf.layout-a4-with-out-background>
