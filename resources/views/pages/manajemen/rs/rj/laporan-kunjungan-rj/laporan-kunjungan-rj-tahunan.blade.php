<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Rj\KunjunganRJTrait;

new class extends Component {
    use KunjunganRJTrait;

    public int $tahunFrom;
    public int $tahunTo;

    public function mount(): void
    {
        $now = Carbon::now()->year;
        $this->tahunFrom = $now - 4;
        $this->tahunTo   = $now;
    }

    private function periodeRange(): array
    {
        $from = min($this->tahunFrom, $this->tahunTo);
        $to   = max($this->tahunFrom, $this->tahunTo);
        $start = Carbon::create($from, 1, 1)->startOfYear();
        $end = Carbon::create($to, 12, 31)->endOfYear();
        return [$start, $end];
    }

    #[Computed]
    public function rows(): array
    {
        [$start, $end] = $this->periodeRange();
        $aggregate = $this->buildKunjunganRJAggregate($start, $end, "to_char(h.rj_date, 'YYYY')");

        $result = [];
        $from = min($this->tahunFrom, $this->tahunTo);
        $to   = max($this->tahunFrom, $this->tahunTo);
        for ($y = $from; $y <= $to; $y++) {
            $key = (string) $y;
            $result[] = $this->fillKunjunganRow($aggregate->get($key), $key, $key);
        }
        return $result;
    }

    #[Computed]
    public function totals(): array
    {
        return $this->totalsKunjungan($this->rows);
    }

    #[Computed]
    public function pasienUnikGlobal(): int
    {
        [$start, $end] = $this->periodeRange();
        return $this->pasienUnikGlobalRJ($start, $end);
    }

    #[Computed]
    public function poliBreakdown()
    {
        [$start, $end] = $this->periodeRange();
        return $this->poliBreakdownRJ($start, $end);
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->chartDataKunjungan($this->rows);
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        $tahunMin = min($tahunFrom, $tahunTo);
        $tahunMax = max($tahunFrom, $tahunTo);
        $chartKey = md5("tahunan-{$tahunFrom}-{$tahunTo}");
    @endphp

    {{-- FILTER + SUMMARY CARDS — collapsible, default closed --}}
    <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
        x-data="{ open: false }">

        <button type="button" @click="open = !open"
            class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                   hover:bg-surface-soft dark:hover:bg-gray-800
                   focus:outline-none focus:ring-1 focus:ring-gray-300">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-body dark:text-gray-200">
                    Ringkasan Kunjungan {{ $tahunMin }}&ndash;{{ $tahunMax }}
                    <span class="font-normal text-xs text-muted">({{ $tahunMax - $tahunMin + 1 }} tahun)</span>
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Total <span class="font-medium text-body dark:text-gray-300">{{ number_format($tot['total']) }}</span>
                    · Unik <span class="font-medium">{{ number_format($this->pasienUnikGlobal) }}</span>
                    · BPJS <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($tot['bpjs']) }}</span> ({{ $tot['total'] > 0 ? round($tot['bpjs'] / $tot['total'] * 100) : 0 }}%)
                    · UMUM <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($tot['umum']) }}</span> ({{ $tot['total'] > 0 ? round($tot['umum'] / $tot['total'] * 100) : 0 }}%)
                    · Baru <span class="font-medium text-blue-700 dark:text-blue-400">{{ number_format($tot['baru']) }}</span>
                    · Lama <span class="font-medium text-slate-700 dark:text-slate-400">{{ number_format($tot['lama']) }}</span>
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
                {{-- Filter card (range tahun) --}}
                <div class="p-3 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Rentang Tahun</div>
                    <div class="mt-1 flex items-center gap-1">
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahunFrom"
                            min="2000" max="2099" maxlength="4"
                            class="block w-full !text-base !font-bold !py-0.5 !px-2" />
                        <span class="text-muted-soft text-sm">&ndash;</span>
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahunTo"
                            min="2000" max="2099" maxlength="4"
                            class="block w-full !text-base !font-bold !py-0.5 !px-2" />
                    </div>
                    <div class="mt-0.5 text-[10px] text-muted dark:text-gray-400 truncate"
                        title="{{ $tahunMin }}–{{ $tahunMax }} ({{ $tahunMax - $tahunMin + 1 }} tahun)">
                        {{ $tahunMin }}&ndash;{{ $tahunMax }} ({{ $tahunMax - $tahunMin + 1 }} thn)
                    </div>
                </div>

                {{-- Summary cards --}}
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Total Kunjungan</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($tot['total']) }}</div>
                </div>
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Pasien Unik</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($this->pasienUnikGlobal) }}</div>
                </div>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs text-emerald-700 uppercase dark:text-emerald-300">BPJS</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ number_format($tot['bpjs']) }}</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400">{{ $tot['total'] > 0 ? round($tot['bpjs'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-700">
                    <div class="text-xs text-amber-700 uppercase dark:text-amber-300">UMUM</div>
                    <div class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-200">{{ number_format($tot['umum']) }}</div>
                    <div class="text-[10px] text-amber-600 dark:text-amber-400">{{ $tot['total'] > 0 ? round($tot['umum'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700">
                    <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Pasien Baru</div>
                    <div class="mt-1 text-2xl font-bold text-blue-800 dark:text-blue-200">{{ number_format($tot['baru']) }}</div>
                    <div class="text-[10px] text-blue-600 dark:text-blue-400">{{ $tot['total'] > 0 ? round($tot['baru'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-900/20 dark:border-slate-700">
                    <div class="text-xs text-slate-700 uppercase dark:text-slate-300">Pasien Lama</div>
                    <div class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-200">{{ number_format($tot['lama']) }}</div>
                    <div class="text-[10px] text-slate-600 dark:text-slate-400">{{ $tot['total'] > 0 ? round($tot['lama'] / $tot['total'] * 100) : 0 }}%</div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN TABLE --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto rounded-t-2xl">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-soft dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left">Tahun</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3 text-right">Pasien Unik</th>
                        <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">BPJS</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">UMUM</th>
                        <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300">Baru</th>
                        <th class="px-3 py-3 text-right text-slate-700 dark:text-slate-300">Lama</th>
                        <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">Selesai</th>
                        <th class="px-3 py-3 text-right text-rose-700 dark:text-rose-300">Batal</th>
                        <th class="px-3 py-3 text-right text-purple-700 dark:text-purple-300" title="Transfer ke UGD (rj_status='I')">Transfer UGD</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">Antrian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->rows as $r)
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50 {{ $r['total'] === 0 ? 'opacity-50' : '' }}">
                            <td class="px-4 py-2.5 font-medium text-ink dark:text-gray-100">{{ $r['periode_label'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ number_format($r['total']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ number_format($r['pasien_unik']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['bpjs']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['umum']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['baru']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($r['lama']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['selesai']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['batal']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['transfer_ugd']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['antrian']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                    <tr class="text-sm font-bold text-ink dark:text-gray-100">
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['total']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-muted" title="Pasien unik global (distinct reg_no di seluruh periode)">{{ number_format($this->pasienUnikGlobal) }}*</td>
                        <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['bpjs']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ number_format($tot['umum']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['baru']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-slate-800 dark:text-slate-200">{{ number_format($tot['lama']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['selesai']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['batal']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['transfer_ugd']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ number_format($tot['antrian']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
            *) Pasien Unik di TOTAL = distinct reg_no untuk seluruh periode (tidak menjumlahkan baris karena pasien yang berkunjung di beberapa periode hanya dihitung 1)
        </div>
    </div>

    {{-- TREN CHART --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Tren Kunjungan
                <span class="ml-2 font-normal text-xs text-muted">(per tahun, {{ $tahunMin }}&ndash;{{ $tahunMax }})</span>
            </h3>
        </div>
        <div class="p-4" wire:ignore wire:key="chart-{{ $chartKey }}">
            <div class="relative h-72" x-data="chartKunjunganRJ(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- BREAKDOWN PER POLI --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Breakdown per Poli (Periode Terpilih)
                <span class="ml-2 font-normal text-xs text-muted">{{ count($this->poliBreakdown) }} poli</span>
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-surface-soft dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Poli</th>
                        <th class="px-3 py-3 text-right">Total Kunjungan</th>
                        <th class="px-3 py-3 text-left w-1/3">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->poliBreakdown as $i => $poli)
                        @php $pct = $tot['total'] > 0 ? ($poli->total / $tot['total']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $poli->poli_desc ?? '(Tanpa Poli)' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($poli->total) }}</td>
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
                        <tr><td colspan="4" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
