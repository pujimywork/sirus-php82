<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Tu\PendapatanRsTrait;

new class extends Component {
    use PendapatanRsTrait;

    public int $filterTahun;

    public function mount(): void
    {
        $this->filterTahun = Carbon::now()->year;
    }

    public function resetFilters(): void
    {
        $this->filterTahun = Carbon::now()->year;
    }

    private function periodeRange(): array
    {
        $start = Carbon::create($this->filterTahun, 1, 1)->startOfYear();
        $end = (clone $start)->endOfYear();
        return [$start, $end];
    }

    #[Computed]
    public function rows(): array
    {
        [$start, $end] = $this->periodeRange();
        $agg = $this->buildPendapatanRsAggregate($start, $end, 'MM');

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $row = $agg[$key] ?? ['rj_bpjs'=>0,'rj_umum'=>0,'ugd_bpjs'=>0,'ugd_umum'=>0,'ri_bpjs'=>0,'ri_umum'=>0,'bpjs'=>0,'umum'=>0,'total'=>0];
            $row['label'] = $this->bulanLabelPendapatan($m);
            $result[] = $row;
        }
        return $result;
    }

    #[Computed]
    public function totals(): array
    {
        return $this->totalsPendapatanRs($this->rows);
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->chartDataPendapatanRs($this->rows);
    }

    #[Computed]
    public function dokterRj(): array
    {
        [$start, $end] = $this->periodeRange();
        return $this->dokterBreakdownRj($start, $end);
    }

    #[Computed]
    public function dokterUgd(): array
    {
        [$start, $end] = $this->periodeRange();
        return $this->dokterBreakdownUgd($start, $end);
    }

    #[Computed]
    public function dokterRi(): array
    {
        [$start, $end] = $this->periodeRange();
        return $this->dokterBreakdownRi($start, $end);
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        $chartKey = md5("bulanan-{$filterTahun}");
    @endphp

    {{-- TOOLBAR --}}
    <div class="mt-4 p-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-auto">
                <x-input-label value="Tahun" />
                <x-text-input type="number" wire:model.live.debounce.500ms="filterTahun" min="2000" max="2099" maxlength="4"
                    class="mt-1 block w-full sm:w-32 !font-bold" />
            </div>
            <div class="ml-auto">
                <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">Reset</x-secondary-button>
            </div>
        </div>

        {{-- SUMMARY: 2 baris (BPJS atas, UMUM bawah) + grand total --}}
        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
            <div class="p-2 bg-emerald-50 border border-emerald-200 rounded-lg dark:border-emerald-800 dark:bg-emerald-900/20">
                <div class="text-emerald-700 dark:text-emerald-300 uppercase">RJ BPJS</div>
                <div class="font-mono text-sm font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($tot['rj_bpjs'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-rose-50 border border-rose-200 rounded-lg dark:border-rose-800 dark:bg-rose-900/20">
                <div class="text-rose-700 dark:text-rose-300 uppercase">UGD BPJS</div>
                <div class="font-mono text-sm font-bold text-rose-900 dark:text-rose-100">{{ number_format($tot['ugd_bpjs'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-blue-50 border border-blue-200 rounded-lg dark:border-blue-800 dark:bg-blue-900/20">
                <div class="text-blue-700 dark:text-blue-300 uppercase">RI BPJS</div>
                <div class="font-mono text-sm font-bold text-blue-900 dark:text-blue-100">{{ number_format($tot['ri_bpjs'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-emerald-100 border border-emerald-300 rounded-lg dark:border-emerald-700 dark:bg-emerald-800/30">
                <div class="text-emerald-800 dark:text-emerald-200 uppercase font-semibold">Total BPJS</div>
                <div class="font-mono text-sm font-extrabold text-emerald-900 dark:text-emerald-100">{{ number_format($tot['bpjs'], 0, ',', '.') }}</div>
            </div>

            <div class="p-2 bg-emerald-50/50 border border-emerald-200 rounded-lg dark:border-emerald-800/50 dark:bg-emerald-900/10">
                <div class="text-emerald-600 dark:text-emerald-400 uppercase">RJ UMUM</div>
                <div class="font-mono text-sm font-bold text-emerald-800 dark:text-emerald-200">{{ number_format($tot['rj_umum'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-rose-50/50 border border-rose-200 rounded-lg dark:border-rose-800/50 dark:bg-rose-900/10">
                <div class="text-rose-600 dark:text-rose-400 uppercase">UGD UMUM</div>
                <div class="font-mono text-sm font-bold text-rose-800 dark:text-rose-200">{{ number_format($tot['ugd_umum'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-blue-50/50 border border-blue-200 rounded-lg dark:border-blue-800/50 dark:bg-blue-900/10">
                <div class="text-blue-600 dark:text-blue-400 uppercase">RI UMUM</div>
                <div class="font-mono text-sm font-bold text-blue-800 dark:text-blue-200">{{ number_format($tot['ri_umum'], 0, ',', '.') }}</div>
            </div>
            <div class="p-2 bg-amber-100 border border-amber-300 rounded-lg dark:border-amber-700 dark:bg-amber-800/30">
                <div class="text-amber-800 dark:text-amber-200 uppercase font-semibold">Total UMUM</div>
                <div class="font-mono text-sm font-extrabold text-amber-900 dark:text-amber-100">{{ number_format($tot['umum'], 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="mt-2 p-3 bg-slate-100 border border-slate-300 rounded-lg dark:border-slate-600 dark:bg-slate-800 flex items-baseline justify-between">
            <div class="text-xs text-slate-700 dark:text-slate-300 uppercase font-semibold">Grand Total {{ $filterTahun }}</div>
            <div class="font-mono text-lg font-extrabold text-slate-900 dark:text-slate-100">Rp {{ number_format($tot['total'], 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- CHART --}}
    <div class="mt-4 p-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="text-sm font-semibold text-body dark:text-gray-200 mb-2">
            Tren pendapatan {{ $filterTahun }} &mdash; 2 stack per bulan: <span class="text-emerald-600 dark:text-emerald-400 font-bold">BPJS</span> + <span class="text-amber-600 dark:text-amber-400 font-bold">UMUM</span>
        </div>
        <div class="h-80" wire:ignore wire:key="chart-{{ $chartKey }}"
            x-data="chartPendapatanRs(@js($this->chartData))" x-init="init()" x-on:destroy="destroy()">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="mt-4 overflow-auto bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-xs text-left text-body dark:text-gray-300 table-auto">
            <thead class="text-[10px] text-ink uppercase bg-surface-soft dark:bg-gray-900 dark:text-gray-100">
                <tr>
                    <th rowspan="2" class="px-3 py-2 text-left border-r border-gray-300 dark:border-gray-600">Bulan</th>
                    <th colspan="2" class="px-3 py-1 text-center border-r border-gray-300 dark:border-gray-600 bg-emerald-100 dark:bg-emerald-900/30">RJ</th>
                    <th colspan="2" class="px-3 py-1 text-center border-r border-gray-300 dark:border-gray-600 bg-rose-100 dark:bg-rose-900/30">UGD</th>
                    <th colspan="2" class="px-3 py-1 text-center border-r border-gray-300 dark:border-gray-600 bg-blue-100 dark:bg-blue-900/30">RI</th>
                    <th colspan="2" class="px-3 py-1 text-center border-r border-gray-300 dark:border-gray-600 bg-slate-100 dark:bg-slate-800">Subtotal</th>
                    <th rowspan="2" class="px-3 py-2 text-right">Total</th>
                </tr>
                <tr class="text-[10px]">
                    <th class="px-3 py-1 text-right bg-emerald-50 dark:bg-emerald-900/20">BPJS</th>
                    <th class="px-3 py-1 text-right border-r border-gray-300 dark:border-gray-600 bg-emerald-50/50 dark:bg-emerald-900/10">UMUM</th>
                    <th class="px-3 py-1 text-right bg-rose-50 dark:bg-rose-900/20">BPJS</th>
                    <th class="px-3 py-1 text-right border-r border-gray-300 dark:border-gray-600 bg-rose-50/50 dark:bg-rose-900/10">UMUM</th>
                    <th class="px-3 py-1 text-right bg-blue-50 dark:bg-blue-900/20">BPJS</th>
                    <th class="px-3 py-1 text-right border-r border-gray-300 dark:border-gray-600 bg-blue-50/50 dark:bg-blue-900/10">UMUM</th>
                    <th class="px-3 py-1 text-right bg-emerald-50 dark:bg-emerald-900/20">BPJS</th>
                    <th class="px-3 py-1 text-right border-r border-gray-300 dark:border-gray-600 bg-amber-50 dark:bg-amber-900/20">UMUM</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->rows as $row)
                    <tr class="border-t border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                        <td class="px-3 py-2 font-medium border-r border-hairline dark:border-gray-700">{{ $row['label'] }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($row['rj_bpjs'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-muted border-r border-hairline dark:border-gray-700">{{ number_format($row['rj_umum'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($row['ugd_bpjs'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-muted border-r border-hairline dark:border-gray-700">{{ number_format($row['ugd_umum'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($row['ri_bpjs'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-muted border-r border-hairline dark:border-gray-700">{{ number_format($row['ri_umum'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-300">{{ number_format($row['bpjs'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono font-semibold text-amber-700 dark:text-amber-300 border-r border-hairline dark:border-gray-700">{{ number_format($row['umum'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="font-bold bg-gray-200 dark:bg-gray-700 border-t-2 border-gray-300 dark:border-gray-600">
                    <td class="px-3 py-2 border-r border-gray-300 dark:border-gray-600">Total {{ $filterTahun }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ number_format($tot['rj_bpjs'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-gray-300 dark:border-gray-600">{{ number_format($tot['rj_umum'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ number_format($tot['ugd_bpjs'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-gray-300 dark:border-gray-600">{{ number_format($tot['ugd_umum'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ number_format($tot['ri_bpjs'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-gray-300 dark:border-gray-600">{{ number_format($tot['ri_umum'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono text-emerald-800 dark:text-emerald-200">{{ number_format($tot['bpjs'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono text-amber-800 dark:text-amber-200 border-r border-gray-300 dark:border-gray-600">{{ number_format($tot['umum'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-mono">{{ number_format($tot['total'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @include('pages::manajemen.rs.tu.pendapatan-rs._breakdown-dokter', [
        'dokterRj'  => $this->dokterRj,
        'dokterUgd' => $this->dokterUgd,
        'dokterRi'  => $this->dokterRi,
        'periodeLabel' => (string) $filterTahun,
    ])
</div>
