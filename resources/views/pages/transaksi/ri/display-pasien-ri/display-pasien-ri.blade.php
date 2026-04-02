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

            /* ---- TTV dari pemeriksaan ---- */
            $ttv = $ri['pemeriksaan']['tandaVital'] ?? [];
            $nut = $ri['pemeriksaan']['nutrisi'] ?? [];

            /* ---- Klaim ---- */
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

            /* ---- Status RI ---- */
            $statusLabel = ['I' => 'Dirawat', 'L' => 'Pulang', 'P' => 'Pindah Kamar'];
            $statusColor = [
                'I' => 'bg-blue-100   text-blue-700   border-blue-200',
                'L' => 'bg-green-100  text-green-700  border-green-200',
                'P' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            ];
            $riStatus = $ri['riStatus'] ?? 'I';
            $statusText = $statusLabel[$riStatus] ?? $riStatus;
            $statusClass = $statusColor[$riStatus] ?? 'bg-gray-100 text-gray-600';

            /* ---- Cara Masuk ---- */
            $entryLabels = [
                '1' => 'Kiriman Dokter / Puskesmas',
                '2' => 'Kiriman RS Lain',
                '3' => 'Kiriman Polisi',
                '4' => 'Kiriman Dinas Sosial',
                '5' => 'Datang Sendiri',
                '6' => 'Lain-Lain',
            ];
            $entryDesc = $ri['entryDesc'] ?? ($entryLabels[$ri['entryId'] ?? ''] ?? '-');

            /* ---- Leveling Dokter ---- */
            $levelingDokter = $ri['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [];
        @endphp

        {{-- ================================================================
        | GRID UTAMA: LOV Pasien (kiri) + Info Kunjungan (kanan) + TTV (bawah)
        ================================================================= --}}
        <div class="grid grid-cols-5 gap-3">

            {{-- ===== KIRI: LOV + Detail Pasien ===== --}}
            <div class="col-span-3">
                <livewire:lov.pasien.lov-pasien :initialRegNo="$p['regNo'] ?? ''" :disabled="true" :label="''" />
            </div>

            {{-- ===== KANAN: Info Kunjungan RI ===== --}}
            <div class="col-span-2 mt-1">
                <div class="h-full px-4 py-3 space-y-2 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50">

                    {{-- BARIS 1: Klaim + Status | Antrian --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-gray-500">Jenis Klaim:</span>
                            <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-gray-500">Antrian:</span>
                            <span class="ml-1 font-black leading-none text-brand">
                                {{ $ri['noAntrian'] ?? '-' }}
                            </span>
                        </div>
                    </div>

                    {{-- BARIS 2: Bangsal / Ruang / Bed | Dokter DPJP Utama --}}
                    <div class="flex items-start justify-between gap-2 text-base">
                        <div>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">
                                {{ $ri['bangsalDesc'] ?? '-' }}
                            </span>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $ri['roomDesc'] ?? '-' }}
                                / Bed: <span class="font-semibold">{{ $ri['bedNo'] ?? '-' }}</span>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="font-semibold text-brand dark:text-emerald-400">
                                {{ $ri['drDesc'] ?? '-' }}
                            </span>
                            {{-- Leveling dokter --}}
                            @if (!empty($levelingDokter))
                                @foreach ($levelingDokter as $ld)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $ld['drDesc'] ?? '-' }}
                                        <span class="text-gray-400">({{ $ld['levelingDesc'] ?? '-' }})</span>
                                    </p>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- BARIS 3: Tanggal Masuk | Shift --}}
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Tgl Masuk:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $ri['entryDate'] ?? '-' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-gray-500">Shift:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $ri['shift'] ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- BARIS 4: Cara Masuk | No. Booking --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Cara Masuk:</span>
                            <p class="text-gray-700 dark:text-gray-300">{{ $entryDesc }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-gray-500">No. Booking:</span>
                            <p class="text-xs text-gray-700 dark:text-gray-300">{{ $ri['noBooking'] ?? '-' ?: '-' }}
                            </p>
                        </div>
                    </div>

                    {{-- BARIS 5: No. SEP BPJS (jika ada) --}}
                    @if (!empty($ri['sep']['noSep']))
                        <div>
                            <span class="text-gray-500">No. SEP:</span>
                            <span class="ml-1 font-mono text-gray-700 dark:text-gray-300">
                                {{ $ri['sep']['noSep'] }}
                            </span>
                        </div>
                    @endif

                    {{-- BARIS 6: No. SPRI (jika ada) --}}
                    @if (!empty($ri['spri']['noSPRIBPJS']))
                        <div>
                            <span class="text-gray-500">No. SPRI:</span>
                            <span class="ml-1 font-mono text-gray-700 dark:text-gray-300">
                                {{ $ri['spri']['noSPRIBPJS'] }}
                            </span>
                        </div>
                    @endif

                    {{-- Status badge --}}
                    <div
                        class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                        {{ $statusText }}
                    </div>

                </div>
            </div>
            {{-- end KANAN --}}

            {{-- ===== TTV + Alergi: full width di bawah ===== --}}
            <div class="col-span-full flex flex-wrap items-center gap-1.5">

                {{-- Alergi --}}
                @if (!empty($ri['anamnesa']['alergi']['alergi']))
                    <x-badge badgecolor="red">
                        ⚠ Alergi: {{ $ri['anamnesa']['alergi']['alergi'] }}
                    </x-badge>
                @endif

                @php
                    $ttvItems = [
                        ['label' => 'BB', 'val' => $nut['bb'] ?? null, 'unit' => 'Kg'],
                        ['label' => 'TB', 'val' => $nut['tb'] ?? null, 'unit' => 'Cm'],
                        ['label' => 'IMT', 'val' => $nut['imt'] ?? null, 'unit' => 'Kg/M²'],
                        [
                            'label' => 'TD',
                            'val' =>
                                !empty($ttv['sistolik']) && !empty($ttv['distolik'])
                                    ? $ttv['sistolik'] . '/' . $ttv['distolik']
                                    : null,
                            'unit' => 'mmHg',
                        ],
                        ['label' => 'Nadi', 'val' => $ttv['frekuensiNadi'] ?? null, 'unit' => 'x/mnt'],
                        ['label' => 'Nafas', 'val' => $ttv['frekuensiNafas'] ?? null, 'unit' => 'x/mnt'],
                        ['label' => 'Suhu', 'val' => $ttv['suhu'] ?? null, 'unit' => '°C'],
                        ['label' => 'SPO2', 'val' => $ttv['spo2'] ?? null, 'unit' => '%'],
                        ['label' => 'GDA', 'val' => $ttv['gda'] ?? null, 'unit' => 'g/dl'],
                    ];
                    $filledTtv = array_filter($ttvItems, fn($item) => !empty($item['val']));
                @endphp

                @if (!empty($filledTtv))
                    <span class="text-xs font-bold tracking-widest text-gray-400 uppercase">TTV</span>
                    @foreach ($filledTtv as $item)
                        <x-badge badgecolor="default">
                            <span class="text-xs sm:text-sm">
                                {{ $item['label'] }}: {{ $item['val'] }} {{ $item['unit'] }}
                            </span>
                        </x-badge>
                    @endforeach
                @endif

            </div>
            {{-- end TTV --}}

        </div>
        {{-- end grid utama --}}
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-300 dark:text-gray-600">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien Rawat Inap belum dimuat</p>
        </div>
    @endif
</div>
