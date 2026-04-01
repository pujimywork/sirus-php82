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

            /* ── TTV ── */
            $ttv = $ri['pemeriksaan']['tandaVital'] ?? [];
            $nut = $ri['pemeriksaan']['nutrisi'] ?? [];

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
                'I' => 'bg-brand/10  text-brand       border-brand/30',
                'L' => 'bg-gray-100  text-gray-600    border-gray-200',
                'P' => 'bg-amber-100 text-amber-700   border-amber-200',
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

            /* ── TTV items ── */
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

        {{-- ================================================================
        | GRID UTAMA: LOV Pasien (kiri 3/5) + Info Kunjungan (kanan 2/5)
        |             TTV + Alergi (full width bawah)
        ================================================================= --}}
        <div class="grid grid-cols-2 gap-3 items-stretch">

            {{-- ===== KIRI: LOV + Detail Pasien ===== --}}
            <div>
                <livewire:lov.pasien.lov-pasien :initialRegNo="$p['regNo'] ?? ''" :disabled="true" :label="''" />
            </div>

            {{-- ===== KANAN: Info Kunjungan RI ===== --}}
            <div class="h-full">
                <div
                    class="h-full px-4 py-3 text-sm border rounded-lg bg-gray-50 dark:bg-gray-800/50
                            flex gap-4 items-start">

                    {{-- Kiri dalam: Klaim, Bangsal, Tgl Masuk, Cara Masuk --}}
                    <div class="flex-1 space-y-2">

                        {{-- Klaim --}}
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-gray-500">Jenis Klaim:</span>
                            <x-badge :badgecolor="$badgeKlaim">{{ $klaimDesc }}</x-badge>
                        </div>

                        {{-- Bangsal / Ruang / Bed --}}
                        <div>
                            <p class="font-semibold text-brand">
                                {{ $ri['bangsalDesc'] ?? '-' }}
                            </p>
                            <p class="text-gray-700 dark:text-gray-300">
                                {{ $ri['roomDesc'] ?? '-' }}
                                / Bed: <span class="font-semibold">{{ $ri['bedNo'] ?? '-' }}</span>
                            </p>
                        </div>

                        {{-- Tgl Masuk --}}
                        <div>
                            <span class="text-gray-500">Tgl Masuk:</span>
                            <span class="ml-1 text-gray-700 dark:text-gray-300">{{ $ri['entryDate'] ?? '-' }}</span>
                        </div>

                        {{-- Cara Masuk --}}
                        <div>
                            <span class="text-gray-500">Cara Masuk:</span>
                            <p class="text-gray-700 dark:text-gray-300">{{ $entryDesc }}</p>
                        </div>

                    </div>

                    {{-- Kanan dalam: Leveling + SEP + Status --}}
                    <div class="shrink-0 space-y-1 text-right">

                        {{-- Tabel Leveling Dokter --}}
                        <table class="text-xs ml-auto">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="pb-0.5 pr-2 font-medium text-left">Dokter</th>
                                    <th class="pb-0.5 font-medium text-left">Leveling</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($levelingDokter as $ld)
                                    @if (!empty($ld['drDesc']))
                                        <tr>
                                            <td class="py-0.5 pr-2 font-semibold text-brand">
                                                {{ $ld['drDesc'] }}
                                            </td>
                                            <td class="py-0.5 text-gray-500">
                                                {{ $ld['levelingDesc'] ?? '-' }}
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-1 text-gray-400 italic">Belum ada leveling</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        {{-- Status --}}
                        <div
                            class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                            {{ $statusText }}
                        </div>

                    </div>

                </div>
            </div>
            {{-- end KANAN --}}

            {{-- ===== TTV + Alergi: full width di bawah ===== --}}
            <div class="col-span-2 flex flex-wrap items-center gap-1.5">

                {{-- Alergi --}}
                @if (!empty($ri['anamnesa']['alergi']['alergi']))
                    <x-badge badgecolor="red">
                        ⚠ Alergi: {{ $ri['anamnesa']['alergi']['alergi'] }}
                    </x-badge>
                @endif

                {{-- TTV --}}
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
