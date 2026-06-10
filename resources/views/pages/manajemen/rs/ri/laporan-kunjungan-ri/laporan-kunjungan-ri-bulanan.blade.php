<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Ri\KunjunganRITrait;

new class extends Component {
    use KunjunganRITrait;

    public int $filterTahun;
    public int $kapasitasTT;       // editable — default dari DB, user bisa override (mis. exclude ICU/IGD)
    public int $defaultKapasitasTT;

    public function mount(): void
    {
        $this->filterTahun = Carbon::now()->year;
        $this->defaultKapasitasTT = $this->kapasitasTTGlobal();
        $this->kapasitasTT = $this->defaultKapasitasTT;
    }

    public function resetKapasitasTT(): void
    {
        $this->kapasitasTT = $this->defaultKapasitasTT;
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
        $aggregate = $this->buildKunjunganRIAggregate($start, $end, "to_char(h.exit_date, 'MM')");

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $result[] = $this->fillKunjunganRow($aggregate->get($key), $this->bulanLabel($m), $key);
        }

        // Enrich dengan BOR/BTO/TOI berdasarkan jumlah hari di tiap bulan
        $tahun = $this->filterTahun;
        return $this->enrichWithBORTOIBTO($result, $this->kapasitasTT, function ($r) use ($tahun) {
            $month = (int) $r['periode_short'];
            return Carbon::create($tahun, $month, 1)->daysInMonth;
        });
    }

    #[Computed]
    public function totals(): array
    {
        $base = $this->totalsKunjungan($this->rows);
        $extra = $this->totalBORTOIBTO($this->rows, $this->kapasitasTT);
        return array_merge($base, $extra);
    }

    #[Computed]
    public function pasienUnikGlobal(): int
    {
        [$start, $end] = $this->periodeRange();
        return $this->pasienUnikGlobalRI($start, $end);
    }

    #[Computed]
    public function bangsalBreakdown(): array
    {
        [$start, $end] = $this->periodeRange();
        $rows = $this->bangsalBreakdownRI($start, $end);
        $totalDays = Carbon::create($this->filterTahun, 1, 1)->isLeapYear() ? 366 : 365;
        return $this->enrichBangsalIndicators($rows, $totalDays);
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
        $chartKey = md5("ri-bulanan-{$filterTahun}");
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
                    Ringkasan Kunjungan RI {{ $filterTahun }}
                </div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Pulang <span class="font-medium text-body dark:text-gray-300">{{ number_format($tot['total']) }}</span>
                    · BPJS <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($tot['bpjs']) }}</span>
                    · UMUM <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($tot['umum']) }}</span>
                    · BOR <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['bor'] }}%</span>
                    · ALOS <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['alos'] }}h</span>
                    · TOI <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['toi'] !== null ? $tot['toi'] . 'h' : '—' }}</span>
                    · BTO <span class="font-medium text-purple-700 dark:text-purple-400">{{ $tot['bto'] }}x</span>
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

            {{-- Counts row --}}
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                <div class="p-3 bg-brand-green/5 border border-brand-green/30 rounded-xl dark:border-brand-lime/30 dark:bg-brand-lime/5">
                    <div class="text-xs text-brand-green uppercase dark:text-brand-lime">Tahun</div>
                    <x-text-input type="number" wire:model.live.debounce.500ms="filterTahun"
                        min="2000" max="2099" maxlength="4"
                        class="mt-1 block w-full !text-xl !font-bold !py-0.5" />
                    <div class="mt-0.5 text-[10px] text-muted dark:text-gray-400 truncate" title="Januari–Desember {{ $filterTahun }}">
                        Januari&ndash;Desember {{ $filterTahun }}
                    </div>
                </div>
                <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs text-muted uppercase">Total Pulang</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($tot['total']) }}</div>
                    <div class="text-[10px] text-muted">{{ number_format($this->pasienUnikGlobal) }} pasien unik</div>
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
                    <div class="flex items-baseline justify-between">
                        <div class="text-xs text-blue-700 uppercase dark:text-blue-300">Kapasitas TT</div>
                        @if ($kapasitasTT !== $defaultKapasitasTT)
                            <button type="button" wire:click="resetKapasitasTT"
                                class="text-[9px] text-blue-600 hover:underline dark:text-blue-400" title="Reset ke default DB">
                                reset
                            </button>
                        @endif
                    </div>
                    <x-text-input type="number" wire:model.live.debounce.500ms="kapasitasTT"
                        min="1" max="9999"
                        class="mt-1 block w-full !text-xl !font-bold !py-0.5 !text-blue-800 dark:!text-blue-200" />
                    <div class="mt-0.5 text-[10px] text-blue-600 dark:text-blue-400 truncate"
                        title="Default {{ $defaultKapasitasTT }} bed (rsmst_beds). Override untuk exclude ICU/IGD/perinatologi yang tidak dihitung BOR resmi.">
                        default: {{ $defaultKapasitasTT }} bed (DB)
                    </div>
                </div>
            </div>

            {{-- Indikator RI (Kemenkes) row --}}
            <div class="mt-3">
                <div class="mb-2 text-[11px] font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                    Indikator Pelayanan RI (standar Kemenkes)
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- BOR --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">BOR</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 60&ndash;85%</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $tot['bor'] }}<span class="text-sm font-medium">%</span></div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Bed Occupancy Rate</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Persentase pemakaian TT dalam periode. <strong>Rendah</strong> = bed banyak kosong (potensi kerugian), <strong>tinggi</strong> = sering penuh (perlu tambah TT).
                            </div>
                        </div>
                    </div>

                    {{-- ALOS --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">ALOS</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 6&ndash;9 hari</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $tot['alos'] }}<span class="text-sm font-medium"> hari</span></div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Average Length of Stay</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Rata-rata hari rawat per pasien. <strong>Pendek</strong> bisa berarti pulih cepat atau pulang terlalu dini, <strong>panjang</strong> bisa indikasi mutu pelayanan kurang.
                            </div>
                        </div>
                    </div>

                    {{-- TOI --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">TOI</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 1&ndash;3 hari</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">
                            {{ $tot['toi'] !== null ? $tot['toi'] : '—' }}<span class="text-sm font-medium"> hari</span>
                        </div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Turn Over Interval</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Interval rata-rata bed kosong antar pasien. <strong>Tinggi</strong> = bed sering tidak dipakai, <strong>rendah</strong> = bed cepat diisi pasien baru (efisien).
                            </div>
                        </div>
                    </div>

                    {{-- BTO --}}
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="flex items-baseline justify-between">
                            <div class="text-xs text-purple-700 uppercase dark:text-purple-300 font-bold">BTO</div>
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal 40&ndash;50/tahun</div>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $tot['bto'] }}<span class="text-sm font-medium">x</span></div>
                        <div class="mt-2 pt-2 border-t border-purple-200 dark:border-purple-700/50">
                            <div class="text-[11px] font-semibold text-purple-700 dark:text-purple-300">Bed Turn Over</div>
                            <div class="text-[10px] text-muted dark:text-gray-400 leading-snug mt-0.5">
                                Frekuensi pemakaian 1 bed dalam periode. <strong>Tinggi</strong> = bed produktif (banyak pasien), <strong>rendah</strong> = bed jarang dipakai.
                            </div>
                        </div>
                    </div>
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
                        <th class="px-4 py-3 text-left">Bulan</th>
                        <th class="px-3 py-3 text-right">Total Pulang</th>
                        <th class="px-3 py-3 text-right">Pasien Unik</th>
                        <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">BPJS</th>
                        <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-300">UMUM</th>
                        <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300">Selesai</th>
                        <th class="px-3 py-3 text-right text-rose-700 dark:text-rose-300">Batal</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Bed Occupancy Rate (%)">BOR</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Average Length of Stay (hari)">ALOS</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Turn Over Interval (hari)">TOI</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Bed Turn Over (kali)">BTO</th>
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
                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['selesai']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['batal']) }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $r['bor'] }}%</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $r['alos'] }}h</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $r['toi'] !== null ? $r['toi'] . 'h' : '—' }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $r['bto'] }}x</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                    <tr class="text-sm font-bold text-ink dark:text-gray-100">
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($tot['total']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-muted" title="Pasien unik global">{{ number_format($this->pasienUnikGlobal) }}*</td>
                        <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['bpjs']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ number_format($tot['umum']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['selesai']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['batal']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['bor'] }}%</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['alos'] }}h</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['toi'] !== null ? $tot['toi'] . 'h' : '—' }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['bto'] }}x</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
            *) Pasien Unik global. <strong>BOR/TOI/BTO</strong> dihitung pakai kapasitas TT = {{ $kapasitasTT }} bed
            @if ($kapasitasTT !== $defaultKapasitasTT)
                <span class="text-amber-600 dark:text-amber-400">(override dari default DB {{ $defaultKapasitasTT }})</span>
            @endif. ALOS & BOR weighted (total hari rawat ÷ total pasien × hari periode).
        </div>
    </div>

    {{-- TREN CHART --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Tren Kunjungan
                <span class="ml-2 font-normal text-xs text-muted">(per bulan, tahun {{ $filterTahun }})</span>
            </h3>
        </div>
        <div class="p-4" wire:ignore wire:key="chart-{{ $chartKey }}">
            <div class="relative h-72" x-data="chartKunjunganRI(@js($this->chartData))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </div>

    {{-- BREAKDOWN PER BANGSAL --}}
    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                Indikator Pelayanan per Bangsal (Periode Terpilih)
                <span class="ml-2 font-normal text-xs text-muted">{{ count($this->bangsalBreakdown) }} bangsal</span>
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-surface-soft dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-3 py-3 text-left">Jenis Pelayanan (Bangsal)</th>
                        <th class="px-3 py-3 text-right" title="Jumlah TT (rsmst_beds via room.bangsal_id)">TT</th>
                        <th class="px-3 py-3 text-right">Total Pulang</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Bed Occupancy Rate (%)">BOR</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Average Length of Stay (hari)">ALOS</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Bed Turn Over (kali)">BTO</th>
                        <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Turn Over Interval (hari)">TOI</th>
                        <th class="px-2 py-3 text-right text-rose-700 dark:text-rose-300" title="Net Death Rate (per 1000) — meninggal LOS ≥ 48 jam">NDR</th>
                        <th class="px-2 py-3 text-right text-rose-700 dark:text-rose-300" title="Gross Death Rate (per 1000) — semua meninggal">GDR</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->bangsalBreakdown as $i => $b)
                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $i + 1 }}</td>
                            <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $b['bangsal_name'] ?? '(Tanpa Bangsal)' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $b['tt'] > 0 ? number_format($b['tt']) : '—' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($b['total']) }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $b['bor'] !== null ? $b['bor'] . '%' : '—' }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $b['alos'] }}h</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $b['bto'] !== null ? $b['bto'] . 'x' : '—' }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $b['toi'] !== null ? $b['toi'] . 'h' : '—' }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300" title="{{ $b['meninggal48'] }} pasien meninggal ≥48h dari {{ $b['total'] }} pulang">{{ $b['ndr'] }}‰</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300" title="{{ $b['meninggal'] }} pasien meninggal dari {{ $b['total'] }} pulang">{{ $b['gdr'] }}‰</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
            <strong>BOR/BTO/TOI</strong> per bangsal pakai TT real per bangsal (rsmst_beds × room.bangsal_id), bukan override global.
            <strong>NDR/GDR</strong> per 1000 pasien pulang. Meninggal terdeteksi dari EMR Perencanaan RI (kode SNOMED 419099009).
            Hari periode = {{ Carbon::create($filterTahun, 1, 1)->isLeapYear() ? 366 : 365 }} hari (tahun {{ $filterTahun }}).
        </div>
    </div>
</div>
