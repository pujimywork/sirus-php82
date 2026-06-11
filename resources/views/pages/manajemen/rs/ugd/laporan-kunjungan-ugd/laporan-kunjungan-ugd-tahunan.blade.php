<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Ugd\KunjunganUGDTrait;

new class extends Component {
    use KunjunganUGDTrait;

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
        $aggregate = $this->buildKunjunganUGDAggregate($start, $end, "to_char(h.rj_date, 'YYYY')");

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
        return $this->pasienUnikGlobalUGD($start, $end);
    }

    #[Computed]
    public function dokterBreakdown()
    {
        [$start, $end] = $this->periodeRange();
        return $this->dokterBreakdownUGD($start, $end);
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
        $chartKey = md5("ugd-tahunan-{$tahunFrom}-{$tahunTo}");
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
                    Ringkasan Kunjungan UGD {{ $tahunMin }}&ndash;{{ $tahunMax }}
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
                <thead class="bg-surface-card dark:bg-gray-800">
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
                        <th class="px-3 py-3 text-right text-purple-700 dark:text-purple-300" title="Transfer ke Rawat Inap (rj_status='I')">Transfer RI</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">Antrian</th>
                        <th class="px-2 py-3 text-right text-red-700 dark:text-red-300" title="Triase P1 — Kritis (Merah)">P1</th>
                        <th class="px-2 py-3 text-right text-yellow-700 dark:text-yellow-400" title="Triase P2 — Urgent (Kuning)">P2</th>
                        <th class="px-2 py-3 text-right text-green-700 dark:text-green-400" title="Triase P3 — Minor (Hijau)">P3</th>
                        <th class="px-2 py-3 text-right text-muted dark:text-gray-400" title="Triase P4 — Non-darurat">P4</th>
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
                            <td class="px-3 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['transfer_ri']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['antrian']) }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-red-700 dark:text-red-300">{{ number_format($r['p1']) }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-yellow-700 dark:text-yellow-400">{{ number_format($r['p2']) }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-green-700 dark:text-green-400">{{ number_format($r['p3']) }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ number_format($r['p4']) }}</td>
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
                        <td class="px-3 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['transfer_ri']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ number_format($tot['antrian']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-red-800 dark:text-red-200">{{ number_format($tot['p1']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-yellow-800 dark:text-yellow-300">{{ number_format($tot['p2']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-green-800 dark:text-green-300">{{ number_format($tot['p3']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-body dark:text-gray-300">{{ number_format($tot['p4']) }}</td>
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
            <div class="relative h-72" x-data="chartKunjunganUGD(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- BREAKDOWN TRIASE --}}
    @php
        $totalTriase = $tot['p1'] + $tot['p2'] + $tot['p3'] + $tot['p4'];
        $triaseRows = [
            ['code' => 'P1', 'label' => 'Kritis (Merah)',  'count' => $tot['p1'], 'cls' => 'bg-red-500 dark:bg-red-400',         'text' => 'text-red-700 dark:text-red-300'],
            ['code' => 'P2', 'label' => 'Urgent (Kuning)', 'count' => $tot['p2'], 'cls' => 'bg-yellow-400 dark:bg-yellow-300',   'text' => 'text-yellow-700 dark:text-yellow-300'],
            ['code' => 'P3', 'label' => 'Minor (Hijau)',   'count' => $tot['p3'], 'cls' => 'bg-green-500 dark:bg-green-400',     'text' => 'text-green-700 dark:text-green-300'],
            ['code' => 'P4', 'label' => 'Non-darurat',     'count' => $tot['p4'], 'cls' => 'bg-gray-400 dark:bg-gray-500',       'text' => 'text-body dark:text-gray-300'],
        ];
    @endphp
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Breakdown Triase (Periode Terpilih)
                <span class="ml-2 font-normal text-xs text-muted">
                    {{ number_format($totalTriase) }} dari {{ number_format($tot['total']) }} pasien terlabel triase
                    @if ($tot['triase_kosong'] > 0)
                        · {{ number_format($tot['triase_kosong']) }} tanpa triase
                    @endif
                </span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-16">Kode</th>
                        <th class="px-3 py-3 text-left">Kategori</th>
                        <th class="px-3 py-3 text-right">Jumlah</th>
                        <th class="px-3 py-3 text-left w-1/3">Persentase (dari total)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($triaseRows as $tr)
                        @php $pct = $tot['total'] > 0 ? ($tr['count'] / $tot['total']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $tr['text'] }}">
                                    <span class="w-2 h-2 rounded-full mr-1.5 {{ $tr['cls'] }}"></span>
                                    {{ $tr['code'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $tr['label'] }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold {{ $tr['text'] }}">{{ number_format($tr['count']) }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                        <div class="h-2 rounded-full {{ $tr['cls'] }}" style="width: {{ min(100, round($pct, 1)) }}%"></div>
                                    </div>
                                    <span class="text-xs text-muted dark:text-gray-400 tabular-nums w-12 text-right">{{ round($pct, 1) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if ($tot['triase_kosong'] > 0)
                        @php $pctKosong = $tot['total'] > 0 ? ($tot['triase_kosong'] / $tot['total']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 bg-surface-soft/50 dark:bg-gray-800/30">
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-muted">
                                    &mdash;
                                </span>
                            </td>
                            <td class="px-3 py-2.5 italic text-muted dark:text-gray-400">Tanpa Triase</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-muted">{{ number_format($tot['triase_kosong']) }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                        <div class="h-2 rounded-full bg-gray-300 dark:bg-gray-600" style="width: {{ min(100, round($pctKosong, 1)) }}%"></div>
                                    </div>
                                    <span class="text-xs text-muted dark:text-gray-400 tabular-nums w-12 text-right">{{ round($pctKosong, 1) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- BREAKDOWN PER DOKTER --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Breakdown per Dokter UGD (Periode Terpilih)
                <span class="ml-2 font-normal text-xs text-muted">{{ count($this->dokterBreakdown) }} dokter</span>
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Dokter</th>
                        <th class="px-3 py-3 text-right">Total Kunjungan</th>
                        <th class="px-3 py-3 text-left w-1/3">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->dokterBreakdown as $i => $dokter)
                        @php $pct = $tot['total'] > 0 ? ($dokter->total / $tot['total']) * 100 : 0; @endphp
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $dokter->dr_name ?? '(Tanpa Dokter)' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($dokter->total) }}</td>
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
