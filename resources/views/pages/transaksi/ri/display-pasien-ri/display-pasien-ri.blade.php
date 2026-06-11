<?php
// resources/views/pages/transaksi/ri/emr-ri/display-pasien/display-pasien-ri.blade.php

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];
    public array $dataPasien = [];

    /** Penilaian risiko jatuh terbaru — terisi hanya jika kategori Sedang/Tinggi. */
    public array $resikoJatuhTerakhir = [];

    public function openDisplay(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;

        $dataDaftarRi = $this->findDataRI($riHdrNo);
        if (!$dataDaftarRi) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $dataDaftarRi;
        $this->dataPasien = $this->findDataMasterPasien($dataDaftarRi['regNo']) ?? [];
        $this->resikoJatuhTerakhir = $this->hitungResikoJatuhTerakhir($dataDaftarRi);
    }

    /**
     * Ambil penilaian risiko jatuh TERAKHIR dari penilaian.resikoJatuh[].
     * "Terakhir" = tglPenilaian paling baru (input manual, bisa diisi mundur —
     * urutan array tidak dijamin kronologis); fallback urutan input.
     * Return kosong jika kategori bukan Sedang/Tinggi → penanda tidak tampil.
     * Sengaja inline per komponen (bukan helper bersama) — struktur JSON tiap
     * modul bisa berubah sendiri-sendiri tanpa saling merusak.
     */
    private function hitungResikoJatuhTerakhir(array $dataEmr): array
    {
        $list = $dataEmr['penilaian']['resikoJatuh'] ?? [];
        $terakhir = null;
        $maxTimestamp = null;
        foreach (is_array($list) ? $list : [] as $entri) {
            try {
                $timestamp = Carbon::createFromFormat('d/m/Y H:i:s', trim($entri['tglPenilaian'] ?? ''))->getTimestamp();
            } catch (\Throwable) {
                $timestamp = null;
            }
            // >= : tanggal sama/tak terparse → entri yang diinput belakangan menang
            if ($terakhir === null || $timestamp === null || $maxTimestamp === null || $timestamp >= $maxTimestamp) {
                $terakhir = $entri;
                $maxTimestamp = $timestamp ?? $maxTimestamp;
            }
        }

        $kategori = $terakhir['resikoJatuh']['kategoriResiko'] ?? '';
        if (!in_array($kategori, ['Sedang', 'Tinggi'], true)) {
            return [];
        }

        return [
            'kategori' => $kategori,
            'metode' => $terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '',
            'skor' => (string) ($terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? ''),
            'tgl' => $terakhir['tglPenilaian'] ?? '',
        ];
    }

    public function mount(): void
    {
        $this->openDisplay($this->riHdrNo ?? '');
    }

    /**
     * Reload data setelah ada simpan di EMR RI (penilaian, diagnosa, dll.)
     * supaya info card — termasuk penanda risiko jatuh — langsung ter-update.
     */
    #[On('refresh-after-ri.saved')]
    public function refreshDisplay(): void
    {
        $this->openDisplay($this->riHdrNo ?? '');
    }
};
?>

<div>
    @if (!empty($dataDaftarRi) && !empty($dataPasien))

        @php
            $p = $dataPasien['pasien'] ?? [];
            $ri = $dataDaftarRi;

            /* ── Klaim ── */
            $klaim = DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $ri['klaimId'] ?? null)
                ->select('klaim_desc')
                ->first();
            $klaimDesc = $klaim->klaim_desc ?? 'Asuransi Lain';
            $badgeKlaim = match ($ri['klaimId'] ?? '') {
                'UM' => 'green',
                'JM' => 'default',
                'KR' => 'yellow',
                default => 'red',
            };

            /* ── Status RI ── */
            $statusLabel = [
                'I' => 'Dirawat',
                'L' => 'Pulang',
                'P' => 'Pindah Kamar',
            ];
            $statusColor = [
                'I' => 'bg-brand/10 text-brand border-brand/30',
                'L' => 'bg-surface-soft text-muted border-hairline',
                'P' => 'bg-amber-100 text-amber-700 border-amber-200',
            ];
            $riStatus = $ri['riStatus'] ?? 'I';
            $statusText = $statusLabel[$riStatus] ?? $riStatus;
            $statusClass = $statusColor[$riStatus] ?? 'bg-surface-soft text-muted';

            /* ── Cara Masuk ── */
            $entryLabels = [
                '1' => 'Kiriman Dokter / Puskesmas',
                '2' => 'Kiriman RS Lain',
                '3' => 'Kiriman Polisi',
                '4' => 'Kiriman Dinas Sosial',
                '5' => 'Datang Sendiri',
                '6' => 'Lain-Lain',
            ];
            $entryDesc = $ri['entryDesc'] ?? ($entryLabels[$ri['entryId'] ?? ''] ?? '-');

            /* ── Leveling Dokter ── */
            $levelingDokter = $ri['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];

            /* ── Alamat + RT/RW ── */
            $alamat = trim($p['identitas']['alamat'] ?? '');
            $rt = trim($p['identitas']['rt'] ?? '');
            $rw = trim($p['identitas']['rw'] ?? '');
            $alamatLine = $alamat;
            if ($rt !== '' || $rw !== '') {
                $alamatLine .= " RT {$rt}/RW {$rw}";
            }

            /* ── Tgl lahir ── */
            $tglLahirRaw = $p['tglLahir'] ?? '';
            $tglLahirFmt = '-';
            if (!empty($tglLahirRaw)) {
                try {
                    $tglLahirFmt = \Carbon\Carbon::parse($tglLahirRaw)->format('d/m/Y');
                } catch (\Exception) {
                    $tglLahirFmt = $tglLahirRaw;
                }
            }
            $tempatLahir = trim($p['tempatLahir'] ?? '');
            $tglLahirLabel = $tempatLahir !== '' ? "{$tempatLahir}, {$tglLahirFmt}" : $tglLahirFmt;
        @endphp

        {{-- ================================================================
        | CARD UTAMA: Pasien (kiri) + Info Rawat Inap (kanan) dalam 1 card
        ================================================================= --}}
        <div class="px-4 py-3 text-base leading-relaxed border border-hairline rounded-lg bg-canvas dark:bg-gray-900">
            <div class="grid grid-cols-5 gap-x-6 gap-y-2.5">

                {{-- ===== KIRI: Identifikasi Pasien ===== --}}
                <div class="col-span-3 space-y-2 sm:border-r sm:border-hairline dark:sm:border-gray-700 sm:pr-4">
                    {{-- Nama + No RM --}}
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xl font-bold text-ink dark:text-white">
                            {{ $p['regName'] ?? '-' }}
                        </span>
                        <span class="font-mono text-base text-muted dark:text-gray-400 shrink-0">
                            {{ $p['regNo'] ?? '-' }}
                        </span>
                    </div>

                    {{-- Detail pasien: 2 kolom (demografi | kontak) --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                        {{-- Kolom kiri: Demografi --}}
                        <div class="space-y-1">
                            <div>
                                <span class="text-muted">Jenis Kelamin:</span>
                                <span class="ml-1 text-body dark:text-gray-300">
                                    {{ $p['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Umur:</span>
                                <span class="ml-1 text-body dark:text-gray-300">
                                    {{ $p['thn'] ?? 0 }} Thn {{ $p['bln'] ?? 0 }} Bln {{ $p['hari'] ?? 0 }} Hr
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Tgl Lahir:</span>
                                <span class="ml-1 text-body dark:text-gray-300">{{ $tglLahirLabel }}</span>
                            </div>
                        </div>

                        {{-- Kolom kanan: Kontak & Identitas --}}
                        <div class="space-y-1">
                            @if ($alamatLine !== '')
                                <div class="text-body dark:text-gray-300">📍 {{ $alamatLine }}</div>
                            @endif

                            @if (!empty($p['kontak']['nomerTelponSelulerPasien']))
                                <div class="text-body dark:text-gray-300">
                                    📞 {{ $p['kontak']['nomerTelponSelulerPasien'] }}
                                </div>
                            @endif

                            <div class="text-xs font-mono text-muted dark:text-gray-400">
                                🆔
                                NIK: {{ $p['identitas']['nik'] ?? '-' }}
                                @if (!empty($p['identitas']['idbpjs']))
                                    • BPJS: {{ $p['identitas']['idbpjs'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== KANAN: Info Rawat Inap (split 2 sub-kolom) ===== --}}
                <div class="col-span-2 grid grid-cols-2 gap-x-4">

                    {{-- ── Sub-kolom KIRI: data RI ── --}}
                    <div class="space-y-2">
                        {{-- Klaim --}}
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-muted">Jenis Klaim:</span>
                            <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                        </div>

                        {{-- Bangsal / Ruang / Bed --}}
                        <div>
                            <p class="font-semibold text-brand">{{ $ri['bangsalDesc'] ?? '-' }}</p>
                            <p class="text-body dark:text-gray-300">
                                {{ $ri['roomDesc'] ?? '-' }}
                            </p>
                        </div>

                        {{-- Tgl Masuk + Cara Masuk --}}
                        <div>
                            <span class="text-muted">Tgl Masuk:</span>
                            <span class="ml-1 text-body dark:text-gray-300">{{ $ri['entryDate'] ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted">Cara Masuk:</span>
                            <span class="ml-1 text-body dark:text-gray-300">{{ $entryDesc }}</span>
                        </div>

                        {{-- No. SEP (kalau ada) --}}
                        @if (!empty($ri['sep']['noSep']))
                            <div>
                                <span class="text-muted">No. SEP:</span>
                                <span class="ml-1 font-mono text-body dark:text-gray-300">{{ $ri['sep']['noSep'] }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- ── Sub-kolom KANAN: Status badge + Leveling Dokter ── --}}
                    <div class="space-y-2">
                        {{-- Status (Dirawat / Pulang / dll.) --}}
                        <div class="flex justify-end">
                            <div class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                {{ $statusText }}
                            </div>
                        </div>

                        {{-- Penanda Risiko Jatuh — hanya muncul jika penilaian terakhir Sedang/Tinggi --}}
                        @if (!empty($resikoJatuhTerakhir))
                            <div class="flex justify-end">
                                <div class="inline-flex items-center gap-1 border rounded-full px-2.5 py-0.5 text-xs font-bold {{ $resikoJatuhTerakhir['kategori'] === 'Tinggi' ? 'bg-error/10 text-error border-error/30' : 'bg-warning/10 text-warning border-warning/30' }}"
                                    title="Penilaian terakhir{{ $resikoJatuhTerakhir['tgl'] ? ' ' . $resikoJatuhTerakhir['tgl'] : '' }}{{ $resikoJatuhTerakhir['metode'] ? ' — ' . $resikoJatuhTerakhir['metode'] : '' }}{{ $resikoJatuhTerakhir['skor'] !== '' ? ' (skor ' . $resikoJatuhTerakhir['skor'] . ')' : '' }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Risiko Jatuh {{ $resikoJatuhTerakhir['kategori'] }}
                                </div>
                            </div>
                        @endif

                        {{-- Leveling Dokter — sejajar di bawah badge status --}}
                        @if (!empty($levelingDokter))
                            <table class="text-xs w-full">
                                <thead>
                                    <tr class="text-muted-soft border-b border-hairline dark:border-gray-700">
                                        <th class="pb-0.5 pr-2 font-medium text-left">Dokter</th>
                                        <th class="pb-0.5 font-medium text-left">Leveling</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($levelingDokter as $ld)
                                        @if (!empty($ld['drName']))
                                            <tr wire:key="display-ri-ld-{{ $ld['drId'] ?? $loop->index }}">
                                                <td class="py-0.5 pr-2 font-semibold text-brand">{{ $ld['drName'] }}</td>
                                                <td class="py-0.5 text-muted">
                                                    {{ ($ld['levelDokter'] ?? '') === 'RawatGabung' ? 'Rawat Gabung' : ($ld['levelDokter'] ?? '-') }}
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                </div>

            </div>
        </div>
    @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-12 text-gray-300 dark:text-gray-600">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien Rawat Inap belum dimuat</p>
        </div>
    @endif
</div>
