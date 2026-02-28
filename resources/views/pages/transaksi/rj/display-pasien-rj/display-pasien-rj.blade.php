<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?string $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $dataPasien = [];

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
            $ttv = $rj['pemeriksaan']['tandaVital'] ?? [];
            $nut = $rj['pemeriksaan']['nutrisi'] ?? [];
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

            $statusLabel = ['A' => 'Antrian', 'L' => 'Selesai', 'F' => 'Batal', 'I' => 'Rujuk'];
            $statusColor = [
                'A' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                'L' => 'bg-green-100 text-green-700 border-green-200',
                'F' => 'bg-red-100 text-red-700 border-red-200',
                'I' => 'bg-blue-100 text-blue-700 border-blue-200',
            ];
            $rjStatus = $rj['rjStatus'] ?? '';
            $statusText = $statusLabel[$rjStatus] ?? $rjStatus;
            $statusClass = $statusColor[$rjStatus] ?? 'bg-gray-100 text-gray-600 border-gray-200';
        @endphp

        {{-- ================================================================
        | GRID UTAMA: LOV Pasien (kiri) + Info Kunjungan (kanan) + TTV (bawah)
        ================================================================= --}}
        <div class="grid grid-cols-5 gap-3">

            {{-- LOV Pasien read-only --}}
            <div class="col-span-3">
                <livewire:lov.pasien.lov-pasien :initialRegNo="$p['regNo'] ?? ''" :disabled="true" :label="'Data Pasien'" />
            </div>

            {{-- KANAN: Info Kunjungan --}}
            <div class="col-span-2 space-y-1 text-right">

                <p class="text-5xl font-black leading-none text-brand sm:text-6xl">
                    {{ $rj['noAntrian'] ?? '-' }}
                </p>
                <p class="text-sm font-semibold tracking-widest text-gray-700 uppercase sm:text-xs">
                    Antrian
                </p>

                <p class="text-xs font-bold text-gray-700 dark:text-gray-200 sm:text-sm">
                    {{ $rj['poliDesc'] ?? '-' }}
                </p>

                <div class="flex flex-wrap items-center justify-end gap-1.5">
                    <span class="text-xs font-semibold text-brand sm:text-sm">
                        {{ $rj['drDesc'] ?? '-' }}
                    </span>
                    <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                </div>

                <div class="flex justify-end">
                    <span
                        class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                        {{ $statusText }}
                    </span>
                </div>

                @if (!empty($rj['sep']['noSep']))
                    <p class="font-mono text-sm text-gray-400 sm:text-xs">
                        SEP: {{ $rj['sep']['noSep'] }}
                    </p>
                @endif

                <p class="text-sm text-gray-700 sm:text-xs">
                    Tgl: {{ $rj['rjDate'] ?? '-' }} &bull; Shift: {{ $rj['shift'] ?? '-' }}
                </p>

                <p class="text-xs text-gray-500 sm:text-sm">
                    No. Booking: {{ $rj['noBooking'] ?? '-' }}
                </p>

            </div>
            {{-- end KANAN --}}

            {{-- TTV: full width di bawah kedua kolom --}}
            <div
                class="col-span-full flex flex-wrap items-center gap-1.5 pt-1.5 border-t border-brand/20 dark:border-brand/30">

                @if (!empty($rj['anamnesa']['alergi']['alergi']))
                    <x-badge badgecolor="red">
                        ⚠ Alergi: {{ $rj['anamnesa']['alergi']['alergi'] }}
                    </x-badge>
                @endif

                <span class="text-sm font-bold tracking-widest text-gray-700 uppercase sm:text-xs">
                    TTV
                </span>

                @php
                    $ttvItems = [
                        ['label' => 'BB', 'val' => ($nut['bb'] ?? '--') . ' Kg'],
                        ['label' => 'TB', 'val' => ($nut['tb'] ?? '--') . ' Cm'],
                        ['label' => 'IMT', 'val' => ($nut['imt'] ?? '--') . ' Kg/M²'],
                        [
                            'label' => 'TD',
                            'val' => ($ttv['sistolik'] ?? '--') . '/' . ($ttv['distolik'] ?? '--') . ' mmHg',
                        ],
                        ['label' => 'Nadi', 'val' => ($ttv['frekuensiNadi'] ?? '--') . ' x/mnt'],
                        ['label' => 'Nafas', 'val' => ($ttv['frekuensiNafas'] ?? '--') . ' x/mnt'],
                        ['label' => 'Suhu', 'val' => ($ttv['suhu'] ?? '--') . ' °C'],
                        ['label' => 'SPO2', 'val' => ($ttv['spo2'] ?? '--') . ' %'],
                        ['label' => 'GDA', 'val' => ($ttv['gda'] ?? '--') . ' g/dl'],
                    ];
                @endphp

                @foreach ($ttvItems as $item)
                    <x-badge badgecolor="default">
                        <span class="text-xs sm:text-sm">{{ $item['label'] }}: {{ $item['val'] }}</span>
                    </x-badge>
                @endforeach

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
            <p class="text-sm font-medium">Data pasien belum dimuat</p>
        </div>

    @endif
</div>
