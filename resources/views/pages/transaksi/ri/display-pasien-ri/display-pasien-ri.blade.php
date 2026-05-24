<?php
// resources/views/pages/transaksi/ri/emr-ri/display-pasien/display-pasien-ri.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];
    public array $dataPasien = [];

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
    }

    public function mount(): void
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
                'L' => 'bg-gray-100 text-gray-600 border-gray-200',
                'P' => 'bg-amber-100 text-amber-700 border-amber-200',
            ];
            $riStatus = $ri['riStatus'] ?? 'I';
            $statusText = $statusLabel[$riStatus] ?? $riStatus;
            $statusClass = $statusColor[$riStatus] ?? 'bg-gray-100 text-gray-600';

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
        <div class="px-4 py-3 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-3">

                {{-- ===== KIRI: Identifikasi Pasien ===== --}}
                <div class="md:col-span-5 space-y-2 md:border-r md:border-gray-200 dark:md:border-gray-700 md:pr-4">
                    {{-- Nama + No RM --}}
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
                                <div class="text-gray-700 dark:text-gray-300">📍 {{ $alamatLine }}</div>
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

                {{-- ===== TENGAH: Info Rawat Inap inti ===== --}}
                <div class="md:col-span-3 space-y-2">
                    {{-- Klaim + Status --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-gray-500">Jenis Klaim:</span>
                            <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                        </div>
                        <div class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                            {{ $statusText }}
                        </div>
                    </div>

                    {{-- Bangsal / Ruang / Bed --}}
                    <div>
                        <p class="font-semibold text-brand">{{ $ri['bangsalDesc'] ?? '-' }}</p>
                        <p class="text-gray-700 dark:text-gray-300">
                            {{ $ri['roomDesc'] ?? '-' }}
                            / Bed: <span class="font-semibold">{{ $ri['bedNo'] ?? '-' }}</span>
                        </p>
                    </div>

                    {{-- Tgl Masuk + Cara Masuk --}}
                    <div>
                        <span class="text-gray-500">Tgl Masuk:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $ri['entryDate'] ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Cara Masuk:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $entryDesc }}</span>
                    </div>

                    {{-- No. SEP (kalau ada) --}}
                    @if (!empty($ri['sep']['noSep']))
                        <div>
                            <span class="text-gray-500">No. SEP:</span>
                            <span class="ml-1 font-mono text-gray-700 dark:text-gray-300">{{ $ri['sep']['noSep'] }}</span>
                        </div>
                    @endif
                </div>

                {{-- ===== KANAN: Leveling Dokter (di samping Info RI) ===== --}}
                <div class="md:col-span-4 md:border-l md:border-gray-200 dark:md:border-gray-700 md:pl-4">
                    @if (!empty($levelingDokter))
                        <table class="text-xs w-full">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="pb-0.5 pr-2 font-medium text-left">Dokter</th>
                                    <th class="pb-0.5 font-medium text-left">Leveling</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($levelingDokter as $ld)
                                    @if (!empty($ld['drName']))
                                        <tr wire:key="display-ri-ld-{{ $ld['drId'] ?? $loop->index }}">
                                            <td class="py-0.5 pr-2 font-semibold text-brand">{{ $ld['drName'] }}</td>
                                            <td class="py-0.5 text-gray-500">
                                                {{ ($ld['levelDokter'] ?? '') === 'RawatGabung' ? 'Rawat Gabung' : ($ld['levelDokter'] ?? '-') }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-xs italic text-gray-400">Leveling dokter belum diisi</div>
                    @endif
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
