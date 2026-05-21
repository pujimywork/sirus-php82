<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Penunjang\RL39Trait;

new class extends Component {
    use RL39Trait;

    public int $bulan;
    public int $tahun;

    public function mount(): void
    {
        $now         = Carbon::now();
        $this->bulan = $now->month;
        $this->tahun = $now->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL39($this->bulan, $this->tahun);
    }

    #[Computed]
    public function totalJumlah(): int
    {
        return array_sum(array_column($this->rows, 'jumlah'));
    }

    public function bulanLabel(int $m): string
    {
        return [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',     4 => 'April',
            5 => 'Mei',      6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September',10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
    }
};
?>

<div>
    <x-page-title
        title="Laporan RL 3.9 — Radiologi"
        subtitle="Rekap pemeriksaan radiologi per bulan, sesuai format SIRS Online Kemenkes (19 jenis kegiatan + 1 fallback). Mapping otomatis lewat keyword rad_desc: Foto tanpa/dengan kontras, CT Scan, USG, MRI, Radioterapi (Linac/Cobalt/Brakhiterapi)." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">
            <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
                <a href="{{ route('manajemen.indikator-pelayanan') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali
                </a>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <select wire:model.live="bulan"
                            class="block w-full mt-1 sm:w-40 rounded-lg border-gray-300 shadow-sm focus:border-brand-green focus:ring-brand-green text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            @for ($m = 1; $m <= 12; $m++)<option value="{{ $m }}">{{ $this->bulanLabel($m) }}</option>@endfor
                        </select>
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tahun" />
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahun"
                            min="2000" max="2099" maxlength="4" class="block w-full mt-1 sm:w-32" />
                    </div>
                    <div class="ml-auto text-xs text-gray-500 dark:text-gray-400 leading-snug">
                        Periode: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->bulanLabel($bulan) }} {{ $tahun }}</span>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $currentGrup = null; @endphp
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        RL 3.9 &mdash; Tabel Pemeriksaan Radiologi
                        <span class="ml-2 font-normal text-xs text-gray-500">
                            ({{ count($this->rows) }} jenis kegiatan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th class="px-2 py-3 text-center w-16 border border-gray-200 dark:border-gray-700">No.</th>
                                <th class="px-2 py-3 text-center w-20 border border-gray-200 dark:border-gray-700">No Kegiatan</th>
                                <th class="px-3 py-3 text-left border border-gray-200 dark:border-gray-700">Jenis Kegiatan</th>
                                <th class="px-3 py-3 text-right border border-gray-200 dark:border-gray-700">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $i => $r)
                                @php
                                    $isCatchAll = $r['id'] === '0';
                                    $showGroupHeader = !$isCatchAll && $r['grup'] !== $currentGrup;
                                    if (!$isCatchAll) $currentGrup = $r['grup'];
                                @endphp

                                @if ($showGroupHeader)
                                    <tr class="bg-gray-50 dark:bg-gray-800/40">
                                        <td colspan="4" class="px-3 py-1.5 text-[11px] font-semibold tracking-wider uppercase text-gray-600 dark:text-gray-300 border-y border-gray-200 dark:border-gray-700">
                                            {{ $r['grup'] }}
                                        </td>
                                    </tr>
                                @endif

                                <tr class="{{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15 font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }} border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-500">{{ $r['id'] }}</td>
                                    <td class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100">{{ $r['nama'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ number_format($r['jumlah']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-sm text-gray-800 dark:text-gray-100">
                                <td colspan="3" class="px-3 py-3 border border-gray-200 dark:border-gray-700">TOTAL</td>
                                <td class="px-3 py-3 text-right tabular-nums border border-gray-200 dark:border-gray-700">{{ number_format($this->totalJumlah) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-gray-500 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rjrads (RJ filter rj_date), rstxn_ugdrads (UGD filter rj_date),
                    rstxn_riradiologs (RI filter exit_date sesuai konvensi laporan RI). JOIN ke
                    <code>rsmst_radiologis</code> via rad_id untuk klasifikasi.
                    <strong>Mapping</strong> otomatis lewat keyword <code>rad_desc</code> (THORAX/BOF/CERVICAL/dll →
                    1.1; IVP/HSG/COLON IN LOOP/dll → 1.2; USG → 4.1; MRI → 4.2; CT SCAN → 1.6).
                    <strong>Item "KIRIM KE LUAR" / "USG KE LUAR"</strong> = referral, tidak count sebagai kegiatan
                    radiologi internal → row "0 Tidak Ada Data".
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code>.
                </div>
            </div>
        </div>
    </div>
</div>
