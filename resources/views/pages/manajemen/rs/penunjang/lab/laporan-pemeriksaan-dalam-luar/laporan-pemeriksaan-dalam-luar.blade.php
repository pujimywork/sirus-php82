<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Penunjang\Lab\PemeriksaanDalamLuarLabTrait;

new class extends Component {
    use WithPagination, PemeriksaanDalamLuarLabTrait;

    /** Periode fleksibel: "MM/YYYY" (bulanan, mis. 06/2026) atau "YYYY" (tahunan, mis. 2026). */
    public string $periode = '';
    public int $perPage = 25;
    public string $tab = 'detail'; // 'detail' | 'rekap'

    // Filter tab Detail
    public string $filterPemeriksaan = '';
    public string $filterJenis = ''; // '' | 'DALAM' | 'LUAR'
    public string $filterUnit = '';  // '' | 'RJ' | 'UGD' | 'RI'

    public function mount(): void
    {
        $this->periode = Carbon::now()->format('m/Y');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['detail', 'rekap'], true)) {
            $this->tab = $tab;
        }
    }

    public function updatedPeriode(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPemeriksaan(): void
    {
        $this->resetPage();
    }

    public function updatedFilterJenis(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUnit(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->periode = Carbon::now()->format('m/Y');
        $this->perPage = 25;
        $this->reset(['filterPemeriksaan', 'filterJenis', 'filterUnit']);
        $this->resetPage();
    }

    /**
     * Label status transaksi induk (RJ/UGD/RI) — arti kode beda per unit.
     * RJ : A=Antrian, L=Selesai, F=Batal, I=Transfer UGD
     * UGD: A=Antrian, L=Selesai, I=Transfer Inap, F=Batal
     * RI : I=Dirawat, P=Pulang
     */
    public function trxStatusLabel(?string $unit, ?string $code): string
    {
        $code = $code ?: '';
        if ($code === '') {
            return '-';
        }
        if ($unit === 'RI') {
            return ['I' => 'Dirawat', 'P' => 'Pulang'][$code] ?? $code;
        }
        return [
            'A' => 'Antrian',
            'L' => 'Selesai',
            'F' => 'Batal',
            'I' => $unit === 'UGD' ? 'Transfer Inap' : 'Transfer UGD',
        ][$code] ?? $code;
    }

    /** Kelas badge (Tailwind) per kode status transaksi. */
    public function trxStatusBadge(?string $code): string
    {
        return match ($code) {
            'L', 'P' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'A'      => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            'I'      => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'F'      => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            default  => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
        };
    }

    private function bulanId(int $m): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
    }

    /**
     * Parse $periode → [start, end, tipe('Bulanan'|'Tahunan'), label] atau null bila tak valid.
     */
    private function periodeRange(): ?array
    {
        $p = trim($this->periode);

        if (preg_match('#^(\d{1,2})/(\d{4})$#', $p, $m)) {
            $bln = (int) $m[1];
            $thn = (int) $m[2];
            if ($bln < 1 || $bln > 12) {
                return null;
            }
            $start = Carbon::create($thn, $bln, 1)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $label = $this->bulanId($bln) . ' ' . $thn;
            return [$start, $end, 'Bulanan', $label];
        }

        if (preg_match('#^(\d{4})$#', $p, $m)) {
            $thn = (int) $m[1];
            $start = Carbon::create($thn, 1, 1)->startOfYear();
            $end = (clone $start)->endOfYear();
            return [$start, $end, 'Tahunan', "Tahun {$thn}"];
        }

        return null;
    }

    #[Computed]
    public function valid(): bool
    {
        return $this->periodeRange() !== null;
    }

    #[Computed]
    public function periodeLabel(): string
    {
        return $this->periodeRange()[3] ?? '—';
    }

    #[Computed]
    public function periodeTipe(): string
    {
        return $this->periodeRange()[2] ?? '—';
    }

    #[Computed]
    public function detail()
    {
        $range = $this->periodeRange();
        if ($range === null) {
            return null;
        }
        [$start, $end] = $range;
        return $this->detailDalamLuarQuery($start, $end, [
            'jenis' => $this->filterJenis,
            'unit' => $this->filterUnit,
            'pemeriksaan' => $this->filterPemeriksaan,
        ])->paginate($this->perPage);
    }

    #[Computed]
    public function recap(): array
    {
        $range = $this->periodeRange();
        if ($range === null) {
            return [
                'dalam' => ['jml' => 0, 'revenue' => 0],
                'luar'  => ['jml' => 0, 'revenue' => 0],
                'total' => ['jml' => 0, 'revenue' => 0],
            ];
        }
        [$start, $end] = $range;
        return $this->recapDalamLuar($start, $end);
    }

    #[Computed]
    public function perDalam()
    {
        $range = $this->periodeRange();
        return $range === null ? collect() : $this->perJenisDalam($range[0], $range[1]);
    }

    #[Computed]
    public function perLuar()
    {
        $range = $this->periodeRange();
        return $range === null ? collect() : $this->perJenisLuar($range[0], $range[1]);
    }
};
?>

<div>
    <x-page-title
        title="Laporan Pemeriksaan Lab — Dalam &amp; Luar"
        subtitle="Detail pemeriksaan per pasien (tanggal, no. reg, nama, pemeriksaan, harga) + rekap jumlah &amp; nilai, dipisah Lab RS Sendiri vs Lab Luar. Filter per bulan (06/2026) atau per tahun (2026)." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-surface-soft dark:bg-gray-800">
        <div class="px-6 pt-0 pb-6">

            {{-- TOOLBAR (pola pelayanan-rj) --}}
            <div class="sticky z-30 px-4 pt-1 pb-2 bg-surface-soft border-b border-hairline top-16 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- FILTER PERIODE --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.500ms="periode"
                                class="block w-full pl-10 sm:w-44" placeholder="06/2026 atau 2026" />
                        </div>
                    </div>

                    {{-- PERIODE AKTIF (badge) --}}
                    @if ($this->valid)
                        <div class="w-full pb-2 sm:w-auto">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full bg-brand-green/10 text-brand-green text-xs font-medium dark:bg-brand-lime/15 dark:text-brand-lime">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                {{ $this->periodeTipe }} · {{ $this->periodeLabel }}
                            </span>
                        </div>
                    @endif

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-toolbar-refresh-reset :label="null" />
                        <div class="w-20">
                            <x-select-input wire:model.live="perPage" class="text-sm" title="Per halaman">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>

                </div>
            </div>

            @if (! $this->valid)
                <div class="mt-4 flex items-center gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-200">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
                    <div class="text-sm">
                        Format periode belum valid. Ketik <span class="font-semibold">MM/YYYY</span> (mis. 06/2026) untuk bulanan
                        atau <span class="font-semibold">YYYY</span> (mis. 2026) untuk tahunan.
                    </div>
                </div>
            @else
                {{-- TABS (pola x-tabs underline) --}}
                <div class="mt-4">
                    <x-tabs variant="underline">
                        <x-tab :active="$tab === 'detail'" color="brand" wire:click="setTab('detail')">Detail Pemeriksaan</x-tab>
                        <x-tab :active="$tab === 'rekap'" color="blue" wire:click="setTab('rekap')">Rekap Pemeriksaan</x-tab>
                    </x-tabs>
                </div>

                @if ($tab === 'detail')
                    {{-- FILTER DETAIL: Pemeriksaan · Jenis · Unit --}}
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Pemeriksaan" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.400ms="filterPemeriksaan"
                                    class="block w-full pl-10 sm:w-64" placeholder="Cari nama pemeriksaan..." />
                            </div>
                        </div>
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Jenis" />
                            <x-select-input wire:model.live="filterJenis" class="mt-1 w-full sm:w-44">
                                <option value="">Semua</option>
                                <option value="DALAM">RS Sendiri</option>
                                <option value="LUAR">Lab Luar</option>
                            </x-select-input>
                        </div>
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Unit" />
                            <x-select-input wire:model.live="filterUnit" class="mt-1 w-full sm:w-32">
                                <option value="">Semua</option>
                                <option value="RJ">RJ</option>
                                <option value="UGD">UGD</option>
                                <option value="RI">RI</option>
                            </x-select-input>
                        </div>
                    </div>

                    {{-- DETAIL: judul di LUAR table (pola daftar-rj) --}}
                    <div class="mt-4 flex items-center justify-between px-1">
                        <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                            Detail Pemeriksaan
                            <span class="ml-1 font-normal text-xs text-muted">({{ $this->periodeLabel }})</span>
                        </h3>
                        <span class="text-xs text-muted dark:text-gray-400 tabular-nums">
                            {{ number_format($this->detail->total()) }} baris
                        </span>
                    </div>

                    {{-- DETAIL TABLE (card bersih ala daftar-rj) --}}
                    <div class="mt-2 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto rounded-t-2xl">
                            <table class="min-w-full text-sm">
                                <thead class="bg-surface-card dark:bg-gray-800">
                                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                        <th class="px-3 py-3 text-left w-12">#</th>
                                        <th class="px-3 py-3 text-left">Tanggal</th>
                                        <th class="px-3 py-3 text-left">No. Reg</th>
                                        <th class="px-3 py-3 text-left">Nama Pasien</th>
                                        <th class="px-3 py-3 text-left">Pemeriksaan</th>
                                        <th class="px-3 py-3 text-center">Jenis</th>
                                        <th class="px-3 py-3 text-left">Unit</th>
                                        <th class="px-3 py-3 text-center">Status</th>
                                        <th class="px-3 py-3 text-right">Harga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->detail as $i => $r)
                                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-2.5 text-muted-soft tabular-nums">{{ $this->detail->firstItem() + $i }}</td>
                                            <td class="px-3 py-2.5 whitespace-nowrap tabular-nums text-ink dark:text-gray-100">{{ $r->tgl }}</td>
                                            <td class="px-3 py-2.5 whitespace-nowrap font-medium tabular-nums text-ink dark:text-gray-100">{{ $r->reg_no }}</td>
                                            <td class="px-3 py-2.5 text-ink dark:text-gray-100">
                                                {{ $r->nama ?: '—' }}
                                                <span class="text-xs text-muted-soft">({{ $r->sex === 'L' ? 'L' : ($r->sex === 'P' ? 'P' : '-') }})</span>
                                            </td>
                                            <td class="px-3 py-2.5 text-body dark:text-gray-200">{{ $r->pemeriksaan ?: '—' }}</td>
                                            <td class="px-3 py-2.5 text-center">
                                                @if ($r->jenis === 'DALAM')
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300">RS Sendiri</span>
                                                @else
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Lab Luar</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-muted dark:text-gray-400">{{ $r->unit }}</td>
                                            <td class="px-3 py-2.5 text-center">
                                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $this->trxStatusBadge($r->trx_status) }}">{{ $this->trxStatusLabel($r->unit, $r->trx_status) }}</span>
                                            </td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-ink dark:text-gray-100">Rp {{ number_format($r->harga, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="px-6 py-12">
                                            <div class="flex flex-col items-center justify-center gap-3">
                                                <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                                <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada pemeriksaan pada periode ini</p>
                                            </div>
                                        </td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($this->detail->hasPages())
                            <div class="px-4 py-3 border-t border-hairline dark:border-gray-700">
                                {{ $this->detail->links() }}
                            </div>
                        @endif
                    </div>
                @else
                    {{-- REKAP TAB: ringkasan (info-bar) + per jenis pemeriksaan (kanan/kiri) --}}
                    @php
                        $rc = $this->recap;
                        $pctDalam = $rc['total']['jml'] > 0 ? round($rc['dalam']['jml'] / $rc['total']['jml'] * 100) : 0;
                        $pctLuar = $rc['total']['jml'] > 0 ? round($rc['luar']['jml'] / $rc['total']['jml'] * 100) : 0;
                    @endphp

                    {{-- RINGKASAN (teks biasa, 1 baris) --}}
                    <div class="mt-4 px-4 py-3 bg-canvas border border-hairline rounded-2xl text-sm text-body dark:text-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <span class="font-semibold text-body dark:text-gray-200">Ringkasan {{ $this->periodeLabel }}:</span>
                        Total <span class="font-semibold">{{ number_format($rc['total']['jml']) }}</span> pemeriksaan
                        · Revenue <span class="font-semibold text-blue-700 dark:text-blue-400">Rp {{ number_format($rc['total']['revenue'], 0, ',', '.') }}</span>
                        · Lab RS Sendiri <span class="font-medium text-cyan-700 dark:text-cyan-400">{{ number_format($rc['dalam']['jml']) }}</span> ({{ $pctDalam }}%) Rp {{ number_format($rc['dalam']['revenue'], 0, ',', '.') }}
                        · Lab Luar <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($rc['luar']['jml']) }}</span> ({{ $pctLuar }}%) Rp {{ number_format($rc['luar']['revenue'], 0, ',', '.') }}
                    </div>

                    {{-- PER JENIS — kanan (Lab RS Sendiri) / kiri (Lab Luar) --}}
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">

                        {{-- KIRI: Lab RS Sendiri --}}
                        <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                                    Pemeriksaan Lab RS Sendiri
                                    <span class="ml-1 font-normal text-xs text-muted">({{ $this->periodeLabel }})</span>
                                </h3>
                                <span class="text-xs text-muted dark:text-gray-400 tabular-nums">{{ number_format(count($this->perDalam)) }} jenis</span>
                            </div>
                            <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-surface-card dark:bg-gray-800">
                                            <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                                <th class="px-3 py-3 text-left w-10">#</th>
                                                <th class="px-3 py-3 text-left">Jenis Pemeriksaan</th>
                                                <th class="px-3 py-3 text-right">Jml</th>
                                                <th class="px-3 py-3 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($this->perDalam as $i => $it)
                                                <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                                    <td class="px-3 py-2.5 text-muted-soft tabular-nums">{{ $i + 1 }}</td>
                                                    <td class="px-3 py-2.5 text-ink dark:text-gray-100">{{ $it->nama ?: '—' }}</td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-ink dark:text-gray-100">{{ number_format($it->jml) }}</td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums text-cyan-700 dark:text-cyan-300">Rp {{ number_format($it->total, 0, ',', '.') }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-6 py-10 text-center text-muted dark:text-gray-400">Tidak ada pemeriksaan lab RS sendiri</td></tr>
                                            @endforelse
                                        </tbody>
                                        @if (count($this->perDalam))
                                            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                                <tr class="text-sm font-bold text-ink dark:text-gray-100">
                                                    <td class="px-3 py-3" colspan="2">TOTAL</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ number_format($rc['dalam']['jml']) }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums text-cyan-800 dark:text-cyan-200">Rp {{ number_format($rc['dalam']['revenue'], 0, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                        {{-- KANAN: Lab Luar --}}
                        <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                    Pemeriksaan Lab Luar (Rujukan)
                                    <span class="ml-1 font-normal text-xs text-muted">({{ $this->periodeLabel }})</span>
                                </h3>
                                <span class="text-xs text-muted dark:text-gray-400 tabular-nums">{{ number_format(count($this->perLuar)) }} jenis</span>
                            </div>
                            <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-surface-card dark:bg-gray-800">
                                            <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                                <th class="px-3 py-3 text-left w-10">#</th>
                                                <th class="px-3 py-3 text-left">Jenis Pemeriksaan</th>
                                                <th class="px-3 py-3 text-right">Jml</th>
                                                <th class="px-3 py-3 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($this->perLuar as $i => $it)
                                                <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                                    <td class="px-3 py-2.5 text-muted-soft tabular-nums">{{ $i + 1 }}</td>
                                                    <td class="px-3 py-2.5 text-ink dark:text-gray-100">{{ $it->nama ?: '—' }}</td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-ink dark:text-gray-100">{{ number_format($it->jml) }}</td>
                                                    <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">Rp {{ number_format($it->total, 0, ',', '.') }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-6 py-10 text-center text-muted dark:text-gray-400">Tidak ada pemeriksaan lab luar</td></tr>
                                            @endforelse
                                        </tbody>
                                        @if (count($this->perLuar))
                                            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                                <tr class="text-sm font-bold text-ink dark:text-gray-100">
                                                    <td class="px-3 py-3" colspan="2">TOTAL</td>
                                                    <td class="px-3 py-3 text-right tabular-nums">{{ number_format($rc['luar']['jml']) }}</td>
                                                    <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">Rp {{ number_format($rc['luar']['revenue'], 0, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
