<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Penunjang\Lab\PermintaanLabTrait;

new class extends Component {
    use PermintaanLabTrait;

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
        $aggregate = $this->buildPermintaanLabAggregate(
            $start, $end,
            "to_char(h.rj_date, 'MM')",
            "to_char(h.exit_date, 'MM')"
        );
        $byPeriode = $this->pivotPermintaanByPeriode($aggregate);

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $result[] = $this->fillPermintaanRow($byPeriode, $key, $this->bulanLabel($m));
        }
        return $result;
    }

    #[Computed]
    public function totals(): array
    {
        return $this->totalsPermintaan($this->rows);
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->chartDataPermintaan($this->rows);
    }

    #[Computed]
    public function topDokter()
    {
        [$start, $end] = $this->periodeRange();
        return $this->topDokterLab($start, $end, 10);
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        $chartKey = md5("lab-bulanan-{$filterTahun}");
    @endphp

    {{-- FILTER + SUMMARY CARDS --}}
    <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
        x-data="{ open: false }">

        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl hover:bg-surface-soft dark:hover:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-300">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-body dark:text-gray-200">
                    Ringkasan Permintaan Lab {{ $filterTahun }}
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Total <span class="font-medium text-body dark:text-gray-300">{{ number_format($tot['total']) }}</span>
                    · RJ <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($tot['rj']) }}</span>
                    · UGD <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($tot['ugd']) }}</span>
                    · RI <span class="font-medium text-purple-700 dark:text-purple-400">{{ number_format($tot['ri']) }}</span>
                    · BPJS <span class="font-medium">{{ number_format($tot['bpjs']) }}</span>
                    · UMUM <span class="font-medium">{{ number_format($tot['umum']) }}</span>
                    · Revenue <span class="font-medium text-blue-700 dark:text-blue-400">Rp {{ number_format($tot['revenue']) }}</span>
                </div>
            </div>
            <span class="hidden sm:inline text-xs text-muted dark:text-gray-400"><span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span></span>
            <svg class="w-4 h-4 text-muted-soft transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-cloak x-show="open" class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="p-3 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Tahun</div>
                    <x-text-input type="number" wire:model.live.debounce.500ms="filterTahun" min="2000" max="2099" maxlength="4" class="mt-1 block w-full !text-xl !font-bold !py-0.5" />
                    <div class="mt-0.5 text-[10px] text-muted dark:text-gray-400 truncate" title="Januari–Desember {{ $filterTahun }}">Januari&ndash;Desember {{ $filterTahun }}</div>
                </div>
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Total Permintaan</div>
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
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700" title="Total revenue dari sum lab_price">
                    <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Revenue</div>
                    <div class="mt-1 text-xl font-bold text-blue-800 dark:text-blue-200">Rp {{ number_format($tot['revenue'], 0, ',', '.') }}</div>
                    <div class="text-[10px] text-blue-600 dark:text-blue-400">total nilai permintaan</div>
                </div>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-900/20 dark:border-slate-700">
                    <div class="text-xs text-slate-700 uppercase dark:text-slate-300">BPJS / UMUM</div>
                    <div class="mt-1 text-base font-bold">
                        <span class="text-emerald-700 dark:text-emerald-300">{{ number_format($tot['bpjs']) }}</span>
                        <span class="text-muted-soft">/</span>
                        <span class="text-amber-700 dark:text-amber-300">{{ number_format($tot['umum']) }}</span>
                    </div>
                    <div class="text-[10px] text-slate-600 dark:text-slate-400">
                        {{ $tot['total'] > 0 ? round($tot['bpjs'] / $tot['total'] * 100) : 0 }}% / {{ $tot['total'] > 0 ? round($tot['umum'] / $tot['total'] * 100) : 0 }}%
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN TABLE --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto rounded-t-2xl">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left">Bulan</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">RJ</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">UGD</th>
                        <th class="px-3 py-3 text-right text-purple-700 dark:text-purple-300">RI</th>
                        <th class="px-3 py-3 text-right">BPJS</th>
                        <th class="px-3 py-3 text-right">UMUM</th>
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
                            <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ number_format($r['bpjs']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ number_format($r['umum']) }}</td>
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
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['bpjs']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['umum']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">Rp {{ number_format($tot['revenue'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- TREN CHART --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Tren Permintaan Lab
                <span class="ml-2 font-normal text-xs text-muted">(per bulan, tahun {{ $filterTahun }})</span>
            </h3>
        </div>
        <div class="p-4" wire:ignore wire:key="chart-{{ $chartKey }}">
            <div class="relative h-72" x-data="chartPermintaan(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- TOP 10 DOKTER PENGIRIM --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Top 10 Dokter Pengirim Lab
                <span class="ml-2 font-normal text-xs text-muted">(berdasarkan dokter penanggung jawab kasus, periode terpilih)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Dokter</th>
                        <th class="px-3 py-3 text-right">Total Permintaan</th>
                        <th class="px-3 py-3 text-left w-1/3">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->topDokter as $i => $dr)
                        @php $pct = $tot['total'] > 0 ? ($dr->total / $tot['total']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $dr->dr_name ?? '(Tanpa Dokter / ' . ($dr->dr_id ?? '-') . ')' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($dr->total) }}</td>
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
                        <tr><td colspan="4" class="px-6 py-12">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data</p>
                                        </div>
                                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
