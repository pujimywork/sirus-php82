<?php

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?string $rjNo = null;
    public array $dataDaftarUGD = [];
    public array $dataPasien = [];

    /** Penilaian risiko jatuh terbaru — terisi hanya jika kategori Sedang/Tinggi. */
    public array $resikoJatuhTerakhir = [];

    public function openDisplay(string $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $dataDaftarUGD = $this->findDataUGD($rjNo);
        if (!$dataDaftarUGD) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $dataDaftarUGD;
        $this->dataPasien = $this->findDataMasterPasien($dataDaftarUGD['regNo']) ?? [];
        $this->resikoJatuhTerakhir = $this->hitungResikoJatuhTerakhir($dataDaftarUGD);
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
        $this->openDisplay($this->rjNo ?? '');
    }

    /**
     * Reload data setelah ada simpan di EMR UGD (penilaian, diagnosa, dll.)
     * supaya info card — termasuk penanda risiko jatuh — langsung ter-update.
     */
    #[On('refresh-after-ugd.saved')]
    public function refreshDisplay(): void
    {
        $this->openDisplay($this->rjNo ?? '');
    }
};
?>

<div>
    @if (!empty($dataDaftarUGD) && !empty($dataPasien))

        @php
            $p = $dataPasien['pasien'] ?? [];
            $rj = $dataDaftarUGD;

            $klaim = DB::table('rsmst_klaimtypes')
                ->where('klaim_id', $rj['klaimId'] ?? null)
                ->select('klaim_desc')
                ->first();
            $klaimDesc = $klaim->klaim_desc ?? 'Asuransi Lain';
            $badgeKlaim = match ($rj['klaimId'] ?? '') {
                'UM' => 'green',
                'JM' => 'default',
                'KR' => 'yellow',
                default => 'red',
            };

            $statusLabel = ['A' => 'Antrian', 'L' => 'Selesai', 'I' => 'Transfer / Inap', 'F' => 'Batal'];
            $statusColor = [
                'A' => 'bg-warning/10 text-warning border-warning/30',
                'L' => 'bg-success/10 text-success border-success/30',
                'I' => 'bg-blue-100 text-blue-700 border-blue-200',
                'F' => 'bg-error/10 text-error border-error/30',
            ];
            $rjStatus = $rj['rjStatus'] ?? '';
            $statusText = $statusLabel[$rjStatus] ?? $rjStatus;
            $statusClass = $statusColor[$rjStatus] ?? 'bg-surface-soft text-muted';

            // Entry cara masuk UGD
            $entryLabels = [
                '1' => 'Kiriman Dokter / Puskesmas',
                '2' => 'Kiriman RS Lain',
                '3' => 'Kiriman Polisi',
                '4' => 'Kiriman Dinas Sosial',
                '5' => 'Datang Sendiri',
                '6' => 'Lain - lain',
            ];
            $entryDesc = $rj['entryDesc'] ?? ($entryLabels[$rj['entryId'] ?? ''] ?? '-');

            // Alamat + RT/RW
            $alamat = trim($p['identitas']['alamat'] ?? '');
            $rt = trim($p['identitas']['rt'] ?? '');
            $rw = trim($p['identitas']['rw'] ?? '');
            $alamatLine = $alamat;
            if ($rt !== '' || $rw !== '') {
                $alamatLine .= " RT {$rt}/RW {$rw}";
            }

            // Tgl lahir
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
        | CARD UTAMA: Pasien (kiri) + Info Kunjungan (kanan) dalam 1 card
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

                {{-- ===== KANAN: Info Kunjungan ===== --}}
                <div class="col-span-2 space-y-2">
                    {{-- BARIS 1: Klaim | Antrian --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-muted">Jenis Klaim :</span>
                            <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-muted">Antrian:</span>
                            <span class="ml-1 font-black leading-none text-brand">
                                {{ $rj['noAntrian'] ?? '-' }}
                            </span>
                        </div>
                    </div>

                    {{-- BARIS 2: UGD/IGD | Dokter --}}
                    <div class="flex items-start justify-between gap-2 text-lg">
                        <span class="font-semibold text-red-600 dark:text-red-400">UGD / IGD</span>
                        <span class="font-semibold text-right text-brand dark:text-brand-lime">
                            {{ $rj['drDesc'] ?? '-' }}
                        </span>
                    </div>

                    {{-- BARIS 3: Tanggal | Shift --}}
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-muted">Tanggal:</span>
                            <span class="ml-1 text-body dark:text-gray-300">{{ $rj['rjDate'] ?? '-' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-muted">Shift:</span>
                            <span class="ml-1 text-body dark:text-gray-300">{{ $rj['shift'] ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- BARIS 4: Cara Masuk --}}
                    <div>
                        <span class="text-muted">Cara Masuk:</span>
                        <span class="ml-1 text-body dark:text-gray-300">{{ $entryDesc }}</span>
                    </div>

                    {{-- BARIS 5: No. SEP (kalau ada) — inline, samakan dengan RI --}}
                    @if (!empty($rj['sep']['noSep']))
                        <div class="whitespace-nowrap">
                            <span class="text-muted">No. SEP:</span>
                            <span class="ml-1 font-mono text-xs tracking-tight text-body dark:text-gray-300">{{ $rj['sep']['noSep'] }}</span>
                        </div>
                    @endif

                    {{-- Penanda Risiko Jatuh — hanya muncul jika penilaian terakhir Sedang/Tinggi --}}
                    @if (!empty($resikoJatuhTerakhir))
                        <div class="flex justify-end">
                            <x-badge :variant="$resikoJatuhTerakhir['kategori'] === 'Tinggi' ? 'danger' : 'warning'" class="gap-1"
                                title="Penilaian terakhir{{ $resikoJatuhTerakhir['tgl'] ? ' ' . $resikoJatuhTerakhir['tgl'] : '' }}{{ $resikoJatuhTerakhir['metode'] ? ' — ' . $resikoJatuhTerakhir['metode'] : '' }}{{ $resikoJatuhTerakhir['skor'] !== '' ? ' (skor ' . $resikoJatuhTerakhir['skor'] . ')' : '' }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                Risiko Jatuh {{ $resikoJatuhTerakhir['kategori'] }}
                            </x-badge>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-300 dark:text-gray-600">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien UGD belum dimuat</p>
        </div>
    @endif
</div>
