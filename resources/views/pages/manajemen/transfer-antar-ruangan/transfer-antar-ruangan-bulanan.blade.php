<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Gudang\TransferAntarRuanganTrait;

new class extends Component {
    use TransferAntarRuanganTrait;

    public int $filterTahun;
    public string $filterKategori = self::KATEGORI_ALL; // all | medis | nonmedis

    public function mount(): void
    {
        $this->filterTahun = Carbon::now()->year;
    }

    public function setKategori(string $k): void
    {
        if (in_array($k, [self::KATEGORI_ALL, self::KATEGORI_MEDIS, self::KATEGORI_NONMEDIS], true)) {
            $this->filterKategori = $k;
        }
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
        $aggregate = $this->buildTrfAggregate($start, $end, "TO_CHAR(h.trf_date,'MM')", $this->filterKategori);

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $result[] = $this->fillTrfRow($aggregate->get($key), $this->bulanLabelTrf($m), $key);
        }
        return $result;
    }

    #[Computed]
    public function totals(): array
    {
        return $this->totalsTrf($this->rows);
    }

    #[Computed]
    public function lokasiBreakdown()
    {
        [$start, $end] = $this->periodeRange();
        return $this->lokasiBreakdownTrf($start, $end, $this->filterKategori);
    }

    #[Computed]
    public function barangBreakdown()
    {
        [$start, $end] = $this->periodeRange();
        return $this->barangBreakdownTrf($start, $end, $this->filterKategori);
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->chartDataTrf($this->rows);
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        $chartKey = md5("trf-bulanan-{$filterTahun}-{$filterKategori}");
    @endphp

    {{-- FILTER + SUMMARY CARDS — collapsible --}}
    <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
        x-data="{ open: false }">

        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                   hover:bg-surface-soft dark:hover:bg-gray-800
                   focus:outline-none focus:ring-1 focus:ring-gray-300">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-body dark:text-gray-200">
                    Ringkasan Transfer {{ $filterTahun }}
                    <span class="ml-2 text-xs font-normal text-muted">
                        ({{ $filterKategori === 'all' ? 'Semua' : ucfirst($filterKategori) }})
                    </span>
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Total <span class="font-medium text-body dark:text-gray-300">{{ number_format($tot['total_trf']) }}</span> transfer
                    · Sudah Diproses <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($tot['posted']) }}</span>
                    · Belum <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($tot['draft']) }}</span>
                    · Dibatalkan <span class="font-medium text-rose-700 dark:text-rose-400">{{ number_format($tot['batal']) }}</span>
                    · Medis <span class="font-medium text-blue-700 dark:text-blue-400">{{ number_format($tot['medis']) }}</span>
                    · Non-Medis <span class="font-medium text-purple-700 dark:text-purple-400">{{ number_format($tot['nonmedis']) }}</span>
                </div>
            </div>
            <span class="hidden sm:inline text-xs text-muted dark:text-gray-400">
                <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
            </span>
            <svg class="w-4 h-4 text-muted-soft transition-transform duration-200 shrink-0"
                :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-cloak x-show="open"
            class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0">

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                {{-- Filter Tahun --}}
                <div class="p-3 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Tahun</div>
                    <x-text-input type="number" wire:model.live.debounce.500ms="filterTahun"
                        min="2000" max="2099" maxlength="4"
                        class="mt-1 block w-full !text-xl !font-bold !py-0.5" />
                    <div class="mt-0.5 text-[10px] text-muted dark:text-gray-400 truncate">
                        Januari&ndash;Desember {{ $filterTahun }}
                    </div>
                </div>

                {{-- Summary --}}
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Total Transfer</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($tot['total_trf']) }}</div>
                </div>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs text-emerald-700 uppercase dark:text-emerald-300">Sudah Diproses</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ number_format($tot['posted']) }}</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400">{{ $tot['total_trf'] > 0 ? round($tot['posted'] / $tot['total_trf'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-700">
                    <div class="text-xs text-amber-700 uppercase dark:text-amber-300">Belum Diproses</div>
                    <div class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-200">{{ number_format($tot['draft']) }}</div>
                    <div class="text-[10px] text-amber-600 dark:text-amber-400">{{ $tot['total_trf'] > 0 ? round($tot['draft'] / $tot['total_trf'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl dark:bg-rose-900/20 dark:border-rose-700">
                    <div class="text-xs text-rose-700 uppercase dark:text-rose-300">Dibatalkan</div>
                    <div class="mt-1 text-2xl font-bold text-rose-800 dark:text-rose-200">{{ number_format($tot['batal']) }}</div>
                    <div class="text-[10px] text-rose-600 dark:text-rose-400">{{ $tot['total_trf'] > 0 ? round($tot['batal'] / $tot['total_trf'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                    <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Total Item</div>
                    <div class="mt-1 text-2xl font-bold text-blue-800 dark:text-blue-200">{{ number_format($tot['total_item']) }}</div>
                </div>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-900/20 dark:border-slate-700">
                    <div class="text-xs text-slate-700 uppercase dark:text-slate-300">Total Qty</div>
                    <div class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-200">{{ number_format($tot['total_qty']) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- KATEGORI SWITCHER --}}
    <div class="mt-3 flex items-center gap-2 text-sm">
        <span class="text-muted dark:text-gray-400">Kategori:</span>
        <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
            @foreach (['all' => 'Semua', 'medis' => 'Medis', 'nonmedis' => 'Non-Medis'] as $k => $label)
                <button type="button" wire:click="setKategori('{{ $k }}')"
                    class="px-3 py-1.5 text-xs font-medium transition-colors
                        {{ $filterKategori === $k
                            ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                            : 'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}
                        {{ !$loop->first ? 'border-l border-gray-300 dark:border-gray-600' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- MAIN TABLE --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto rounded-t-2xl">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left">Bulan</th>
                        <th class="px-3 py-3 text-right">Total Transfer</th>
                        <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">Sudah Diproses</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">Belum Diproses</th>
                        <th class="px-3 py-3 text-right text-rose-700 dark:text-rose-300">Dibatalkan</th>
                        <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300">Medis</th>
                        <th class="px-3 py-3 text-right text-purple-700 dark:text-purple-300">Non-Medis</th>
                        <th class="px-3 py-3 text-right">Total Item</th>
                        <th class="px-3 py-3 text-right">Total Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->rows as $r)
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50 {{ $r['total_trf'] === 0 ? 'opacity-50' : '' }}">
                            <td class="px-4 py-2.5 font-medium text-ink dark:text-gray-100">{{ $r['periode_label'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($r['total_trf']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['posted']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['draft']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['batal']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['medis']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['nonmedis']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['total_item']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['total_qty']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                    <tr class="text-sm font-bold text-ink dark:text-gray-100">
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['total_trf']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['posted']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ number_format($tot['draft']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['batal']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['medis']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['nonmedis']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['total_item']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['total_qty']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- TREN CHART --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Tren Transfer
                <span class="ml-2 font-normal text-xs text-muted">(per bulan, tahun {{ $filterTahun }})</span>
            </h3>
        </div>
        <div class="p-4" wire:ignore wire:key="chart-{{ $chartKey }}">
            <div class="relative h-72" x-data="chartTransferAntarRuangan(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- BREAKDOWN PER PASANGAN LOKASI --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Breakdown per Rute (Dari &rarr; Ke)
                <span class="ml-2 font-normal text-xs text-muted">{{ count($this->lokasiBreakdown) }} rute</span>
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Dari</th>
                        <th class="px-3 py-3 text-left">Ke</th>
                        <th class="px-3 py-3 text-right">Total Transfer</th>
                        <th class="px-3 py-3 text-right">Total Qty</th>
                        <th class="px-3 py-3 text-left w-1/4">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->lokasiBreakdown as $i => $rute)
                        @php $pct = $tot['total_trf'] > 0 ? ($rute->total_trf / $tot['total_trf']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 text-ink dark:text-gray-100">
                                <div class="font-medium">{{ $rute->sl_name_from ?? '-' }}</div>
                                <div class="font-mono text-xs text-muted-soft">{{ $rute->sl_codefrom }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-ink dark:text-gray-100">
                                <div class="font-medium">{{ $rute->sl_name_to ?? '-' }}</div>
                                <div class="font-mono text-xs text-muted-soft">{{ $rute->sl_codeto }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($rute->total_trf) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($rute->total_qty) }}</td>
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
                        <tr><td colspan="6" class="px-6 py-12">
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

    {{-- BREAKDOWN PER BARANG --}}
    @php
        $totalNilaiBarang = $this->barangBreakdown->sum('total_value');
    @endphp
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Breakdown per Barang
                <span class="ml-2 font-normal text-xs text-muted">{{ count($this->barangBreakdown) }} item · sort by nilai (HPP)</span>
            </h3>
            <div class="text-xs text-muted dark:text-gray-400">
                Total Nilai: <span class="font-semibold text-body dark:text-gray-200">Rp {{ number_format($totalNilaiBarang, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Kode</th>
                        <th class="px-3 py-3 text-left">Nama Barang</th>
                        <th class="px-3 py-3 text-left">Kategori</th>
                        <th class="px-3 py-3 text-right">HPP</th>
                        <th class="px-3 py-3 text-right">Total Qty</th>
                        <th class="px-3 py-3 text-right">Total Nilai</th>
                        <th class="px-3 py-3 text-left w-1/5">% Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->barangBreakdown as $i => $b)
                        @php $pct = $totalNilaiBarang > 0 ? ($b->total_value / $totalNilaiBarang) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-muted dark:text-gray-400">{{ $b->product_id }}</td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $b->product_name ?? '-' }}</td>
                            <td class="px-3 py-2.5">
                                @if ($b->kategori === 'medis')
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Medis</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Non-Medis</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $b->cost_price > 0 ? number_format($b->cost_price, 0, ',', '.') : '-' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($b->total_qty) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ $b->total_value > 0 ? 'Rp ' . number_format($b->total_value, 0, ',', '.') : '-' }}</td>
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
                        <tr><td colspan="8" class="px-6 py-12">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data</p>
                                        </div>
                                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
            *) Nilai = qty × HPP (cost_price master). Item tanpa HPP di master akan menampilkan "-" pada kolom nilai.
        </div>
    </div>
</div>
