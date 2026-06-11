<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Penunjang\Rad\PemeriksaanRadTrait;

new class extends Component {
    use PemeriksaanRadTrait;

    public int $filterTahun;

    public function mount(): void
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
        $aggregate = $this->buildPemeriksaanRadAggregate($start, $end, "to_char(h.rj_date, 'MM')", "to_char(h.exit_date, 'MM')");
        $byPeriode = $this->pivotPemeriksaanByPeriode($aggregate);
        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $result[] = $this->fillPemeriksaanRow($byPeriode, $key, $this->bulanLabel($m));
        }
        return $result;
    }

    #[Computed] public function totals(): array { return $this->totalsPemeriksaan($this->rows); }
    #[Computed] public function chartData(): array { return $this->chartDataPemeriksaan($this->rows); }
    #[Computed] public function topItem() { [$start, $end] = $this->periodeRange(); return $this->topItemRad($start, $end, 20); }
};
?>

<div>
    @php
        $tot = $this->totals;
        $chartKey = md5("pemeriksaan-rad-bulanan-{$filterTahun}");
    @endphp

    <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900" x-data="{ open: false }">
        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-surface-soft dark:hover:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-300">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-body dark:text-gray-200">Ringkasan Pemeriksaan Radiologi {{ $filterTahun }}</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Total <span class="font-medium text-body dark:text-gray-300">{{ number_format($tot['total']) }}</span> pemeriksaan
                    · RJ <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($tot['rj']) }}</span>
                    · UGD <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($tot['ugd']) }}</span>
                    · RI <span class="font-medium text-purple-700 dark:text-purple-400">{{ number_format($tot['ri']) }}</span>
                    · Revenue <span class="font-medium text-blue-700 dark:text-blue-400">Rp {{ number_format($tot['revenue']) }}</span>
                </div>
            </div>
            <span class="hidden sm:inline text-xs text-muted dark:text-gray-400"><span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span></span>
            <svg class="w-4 h-4 text-muted-soft transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </button>

        <div x-cloak x-show="open" class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                <div class="p-3 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Tahun</div>
                    <x-text-input type="number" wire:model.live.debounce.500ms="filterTahun" min="2000" max="2099" maxlength="4" class="mt-1 block w-full !text-xl !font-bold !py-0.5" />
                    <div class="mt-0.5 text-[10px] text-muted dark:text-gray-400 truncate">Januari&ndash;Desember {{ $filterTahun }}</div>
                </div>
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Total Pemeriksaan</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($tot['total']) }}</div>
                </div>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs text-emerald-700 uppercase dark:text-emerald-300">RJ</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ number_format($tot['rj']) }}</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400">{{ $tot['total'] > 0 ? round($tot['rj'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-700">
                    <div class="text-xs text-amber-700 uppercase dark:text-amber-300">UGD</div>
                    <div class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-200">{{ number_format($tot['ugd']) }}</div>
                    <div class="text-[10px] text-amber-600 dark:text-amber-400">{{ $tot['total'] > 0 ? round($tot['ugd'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                    <div class="text-xs text-purple-700 uppercase dark:text-purple-300">RI</div>
                    <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ number_format($tot['ri']) }}</div>
                    <div class="text-[10px] text-purple-600 dark:text-purple-400">{{ $tot['total'] > 0 ? round($tot['ri'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                    <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Revenue</div>
                    <div class="mt-1 text-xl font-bold text-blue-800 dark:text-blue-200">Rp {{ number_format($tot['revenue'], 0, ',', '.') }}</div>
                    <div class="text-[10px] text-blue-600 dark:text-blue-400">total nilai pemeriksaan</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto rounded-t-2xl">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-soft dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left">Bulan</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">RJ</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">UGD</th>
                        <th class="px-3 py-3 text-right text-purple-700 dark:text-purple-300">RI</th>
                        <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->rows as $r)
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50 {{ $r['total'] === 0 ? 'opacity-50' : '' }}">
                            <td class="px-4 py-2.5 font-medium text-ink dark:text-gray-100">{{ $r['periode_label'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($r['total']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['rj']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['ugd']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['ri']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">Rp {{ number_format($r['revenue'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                    <tr class="text-sm font-bold text-ink dark:text-gray-100">
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['total']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['rj']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ number_format($tot['ugd']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['ri']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">Rp {{ number_format($tot['revenue'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">Tren Pemeriksaan Radiologi <span class="ml-2 font-normal text-xs text-muted">(per bulan, tahun {{ $filterTahun }})</span></h3>
        </div>
        <div class="p-4" wire:ignore wire:key="chart-{{ $chartKey }}">
            <div class="relative h-72" x-data="chartPermintaan(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Top 20 Item Radiologi Terbanyak
                <span class="ml-2 font-normal text-xs text-muted">(periode terpilih, gabungan RJ/UGD/RI)</span>
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-surface-soft dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Item Radiologi</th>
                        <th class="px-3 py-3 text-right">Jumlah</th>
                        <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300">Revenue</th>
                        <th class="px-3 py-3 text-left w-1/4">% dari Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->topItem as $i => $it)
                        @php $pct = $tot['total'] > 0 ? ($it->total / $tot['total']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $it->item_name ?? "(ID: {$it->rad_id})" }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($it->total) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">Rp {{ number_format($it->revenue, 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                        <div class="h-2 rounded-full bg-brand-green dark:bg-brand-lime" style="width: {{ min(100, round($pct, 1)) }}%"></div>
                                    </div>
                                    <span class="text-xs text-muted dark:text-gray-400 tabular-nums w-12 text-right">{{ round($pct, 1) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
