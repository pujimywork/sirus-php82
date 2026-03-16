<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?string $rjNo = null;
    public array $dataDaftarUGD = [];
    public array $dataPasien = [];

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
    }

    public function mount(): void
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
            $ttv = $rj['pemeriksaan']['tandaVital'] ?? [];
            $nut = $rj['pemeriksaan']['nutrisi'] ?? [];

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

            $statusLabel = ['A' => 'Antrian', 'L' => 'Selesai', 'I' => 'Transfer / Inap'];
            $statusColor = [
                'A' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                'L' => 'bg-green-100  text-green-700  border-green-200',
                'I' => 'bg-blue-100   text-blue-700   border-blue-200',
            ];
            $rjStatus = $rj['rjStatus'] ?? '';
            $statusText = $statusLabel[$rjStatus] ?? $rjStatus;
            $statusClass = $statusColor[$rjStatus] ?? 'bg-gray-100 text-gray-600';

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
        @endphp

        {{-- ================================================================
        | GRID UTAMA: LOV Pasien (kiri) + Info Kunjungan (kanan) + TTV (bawah)
        ================================================================= --}}
        <div class="grid grid-cols-5 gap-3">

            {{-- ===== KIRI: LOV + Detail Pasien ===== --}}
            <div class="col-span-3">
                <livewire:lov.pasien.lov-pasien :initialRegNo="$p['regNo'] ?? ''" :disabled="true" :label="''" />
            </div>

            {{-- ===== KANAN: Info Kunjungan ===== --}}
            <div class="col-span-2 mt-1">
                <div class="h-full px-4 py-3 space-y-2 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50">

                    {{-- BARIS 1: Klaim + Status | Antrian --}}
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

                    {{-- BARIS 2: UGD / IGD | Dokter --}}
                    <div class="flex items-start justify-between gap-2 text-lg">
                        <div>
                            <span class="ml-1 font-semibold text-red-600 dark:text-red-400">
                                UGD / IGD
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="ml-1 font-semibold text-brand dark:text-emerald-400">
                                {{ $rj['drDesc'] ?? '-' }}
                            </span>
                        </div>
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

                    {{-- BARIS 4: Cara Masuk | Status Lanjutan --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Cara Masuk:</span>
                            <p class="text-gray-700 dark:text-gray-300">{{ $entryDesc }}</p>
                        </div>
                        @if (!empty($rj['statusLanjutan']))
                            <div class="text-right">
                                <span class="text-gray-500">Lanjutan:</span>
                                <p class="text-gray-700 dark:text-gray-300">{{ $rj['statusLanjutan'] }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- BARIS 5: No. Booking | No. SEP --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-gray-500">No. Booking:</span>
                            <p class="text-gray-700 dark:text-gray-300">{{ $rj['noBooking'] ?? '-' ?: '-' }}</p>
                        </div>
                        @if (!empty($rj['sep']['noSep']))
                            <div class="text-right">
                                <span class="text-gray-500">No. SEP:</span>
                                <p class="font-mono text-gray-700 dark:text-gray-300">{{ $rj['sep']['noSep'] }}</p>
                            </div>
                        @endif
                    </div>

                    <div
                        class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                        {{ $statusText }}
                    </div>

                </div>
            </div>
            {{-- end KANAN --}}

            {{-- ===== TTV + Alergi: full width di bawah ===== --}}
            <div class="col-span-full flex flex-wrap items-center gap-1.5 border-brand/20 dark:border-brand/30">

                @if (!empty($rj['anamnesa']['alergi']['alergi']))
                    <x-badge badgecolor="red">
                        ⚠ Alergi: {{ $rj['anamnesa']['alergi']['alergi'] }}
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
                    <span class="text-sm font-bold tracking-widest text-gray-400 uppercase sm:text-xs">TTV</span>
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
            <p class="text-sm font-medium">Data pasien UGD belum dimuat</p>
        </div>
    @endif
</div>
