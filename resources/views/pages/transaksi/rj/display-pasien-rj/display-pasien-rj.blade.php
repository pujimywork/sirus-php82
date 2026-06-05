<?php

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?string $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $dataPasien = [];

    /** Penilaian risiko jatuh terbaru — terisi hanya jika kategori Sedang/Tinggi. */
    public array $resikoJatuhTerakhir = [];

    public function openDisplay(string $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);
        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;
        $this->dataPasien = $this->findDataMasterPasien($dataDaftarPoliRJ['regNo']) ?? [];
        $this->hitungResikoJatuhTerakhir($dataDaftarPoliRJ);
    }

    /**
     * Ambil penilaian risiko jatuh TERAKHIR dari penilaian.resikoJatuh[].
     * "Terakhir" = tglPenilaian paling baru (input manual, bisa diisi mundur —
     * urutan array tidak dijamin kronologis); fallback urutan input.
     * Hasil disimpan hanya jika kategori Sedang/Tinggi — selain itu kosong
     * dan penanda tidak ditampilkan.
     */
    private function hitungResikoJatuhTerakhir(array $dataEmr): void
    {
        $this->resikoJatuhTerakhir = [];

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
            return;
        }

        $this->resikoJatuhTerakhir = [
            'kategori' => $kategori,
            'metode' => $terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '',
            'skor' => (string) ($terakhir['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? ''),
            'tgl' => $terakhir['tglPenilaian'] ?? '',
        ];
    }

    public function mount()
    {
        $this->openDisplay($this->rjNo ?? '');
    }
};
?>

<div>
    @if (!empty($dataDaftarPoliRJ) && !empty($dataPasien))

        @php
            $p = $dataPasien['pasien'] ?? [];
            $rj = $dataDaftarPoliRJ;
            $fun = $rj['pemeriksaan']['fungsional'] ?? [];

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

            $statusLabel = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Transfer UGD'];
            $statusColor = [
                'A' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                'L' => 'bg-green-100 text-green-700 border-green-200',
                'F' => 'bg-red-100 text-red-700 border-red-200',
                'I' => 'bg-blue-100 text-blue-700 border-blue-200',
            ];
            $rjStatus = $rj['rjStatus'] ?? '';
            $statusText = $statusLabel[$rjStatus] ?? $rjStatus;
            $statusClass = $statusColor[$rjStatus] ?? 'bg-gray-100 text-gray-600 ';
        @endphp

        @php
            $alamat = trim($p['identitas']['alamat'] ?? '');
            $rt = trim($p['identitas']['rt'] ?? '');
            $rw = trim($p['identitas']['rw'] ?? '');
            $alamatLine = $alamat;
            if ($rt !== '' || $rw !== '') {
                $alamatLine .= " RT {$rt}/RW {$rw}";
            }

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
        <div class="px-4 py-3 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50">
            <div class="grid grid-cols-5 gap-x-6 gap-y-2">

                {{-- ===== KIRI: Identifikasi Pasien ===== --}}
                <div class="col-span-3 space-y-2 sm:border-r sm:border-gray-200 dark:sm:border-gray-700 sm:pr-4">
                    {{-- Nama + No RM (full width) --}}
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $p['regName'] ?? '-' }}
                        </span>
                        <span class="font-mono text-sm text-gray-600 dark:text-gray-400 shrink-0">
                            {{ $p['regNo'] ?? '-' }}
                        </span>
                    </div>

                    {{-- Detail pasien: 2 kolom (demografi | kontak) --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">

                        {{-- Kolom kiri: Demografi --}}
                        <div class="space-y-1">
                            <div>
                                <span class="text-gray-500">Jenis Kelamin:</span>
                                <span class="ml-1 text-gray-700 dark:text-gray-300">
                                    {{ $p['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500">Umur:</span>
                                <span class="ml-1 text-gray-700 dark:text-gray-300">
                                    {{ $p['thn'] ?? 0 }} Thn {{ $p['bln'] ?? 0 }} Bln {{ $p['hari'] ?? 0 }} Hr
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500">Tgl Lahir:</span>
                                <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $tglLahirLabel }}</span>
                            </div>
                        </div>

                        {{-- Kolom kanan: Kontak & Identitas --}}
                        <div class="space-y-1">
                            @if ($alamatLine !== '')
                                <div class="text-gray-700 dark:text-gray-300">
                                    📍 {{ $alamatLine }}
                                </div>
                            @endif

                            @if (!empty($p['kontak']['nomerTelponSelulerPasien']))
                                <div class="text-gray-700 dark:text-gray-300">
                                    📞 {{ $p['kontak']['nomerTelponSelulerPasien'] }}
                                </div>
                            @endif

                            <div class="text-xs font-mono text-gray-600 dark:text-gray-400">
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
                            <span class="text-gray-500">Jenis Klaim :</span>
                            <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-gray-500">Antrian:</span>
                            <span class="ml-1 font-black leading-none text-brand">
                                {{ $rj['noAntrian'] ?? '-' }}
                            </span>
                        </div>
                    </div>

                    {{-- BARIS 2: Poli | Dokter --}}
                    <div class="flex items-start justify-between gap-2 text-lg">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ $rj['poliDesc'] ?? '-' }}
                        </span>
                        <span class="font-semibold text-right text-brand dark:text-emerald-400">
                            {{ $rj['drDesc'] ?? '-' }}
                        </span>
                    </div>

                    {{-- BARIS 3: Tanggal | Shift --}}
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Tanggal:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $rj['rjDate'] ?? '-' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-gray-500">Shift:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $rj['shift'] ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- BARIS 4: No. SEP (hanya kalau ada) --}}
                    @if (!empty($rj['sep']['noSep']))
                        <div class="flex items-start justify-end gap-2">
                            <div class="text-right">
                                <span class="text-gray-500">No. SEP:</span>
                                <p class="font-mono text-gray-700 dark:text-gray-300">{{ $rj['sep']['noSep'] }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Penanda Risiko Jatuh — hanya muncul jika penilaian terakhir Sedang/Tinggi --}}
                    @if (!empty($resikoJatuhTerakhir))
                        <div class="flex justify-end">
                            <div class="inline-flex items-center gap-1 border rounded-full px-2.5 py-0.5 text-xs font-bold {{ $resikoJatuhTerakhir['kategori'] === 'Tinggi' ? 'bg-red-100 text-red-700 border-red-300' : 'bg-yellow-100 text-yellow-700 border-yellow-300' }}"
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
                </div>

            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-300 dark:text-gray-600">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien belum dimuat</p>
        </div>

    @endif
</div>
