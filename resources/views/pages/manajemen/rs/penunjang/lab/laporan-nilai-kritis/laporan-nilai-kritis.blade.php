<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Rs\Penunjang\Lab\NilaiKritisLabTrait;

new class extends Component {
    use WithPagination, NilaiKritisLabTrait;

    public string $filterMode = 'bulanan';
    public string $filterBulan = '';
    public string $filterTahun = '';
    public int $perPage = 25;
    public string $tab = 'detail';

    public string $filterPemeriksaan = '';
    public string $filterUnit = '';   // '' | 'RJ' | 'UGD' | 'RI'
    public string $filterStatus = ''; // '' | 'H' | 'L'

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTahun = Carbon::now()->format('Y');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['detail', 'rekap'], true)) {
            $this->tab = $tab;
        }
    }

    public function updatedFilterMode(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }
    public function updatedFilterTahun(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }
    public function updatedFilterPemeriksaan(): void { $this->resetPage(); }
    public function updatedFilterUnit(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->filterMode = 'bulanan';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTahun = Carbon::now()->format('Y');
        $this->perPage = 25;
        $this->reset(['filterPemeriksaan', 'filterUnit', 'filterStatus']);
        $this->resetPage();
    }

    public function unitLabel(?string $code): string
    {
        return ['RJ' => 'Rawat Jalan', 'UGD' => 'Gawat Darurat', 'RI' => 'Rawat Inap'][$code] ?? ($code ?: '-');
    }

    /** Label + apakah Tinggi (H) atau Rendah (L) dari flag status hasil. */
    public function isTinggi(?string $flag): bool
    {
        return in_array((string) $flag, ['H', 'HH', 'HIGH'], true);
    }

    private function bulanId(int $bulan): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$bulan] ?? (string) $bulan;
    }

    private function periodeRange(): ?array
    {
        if ($this->filterMode === 'tahunan') {
            if (!preg_match('#^(\d{4})$#', trim($this->filterTahun), $matches)) {
                return null;
            }
            $tahun = (int) $matches[1];
            $start = Carbon::create($tahun, 1, 1)->startOfYear();
            $end = (clone $start)->endOfYear();
            return [$start, $end, 'Tahunan', "Tahun {$tahun}"];
        }

        if (!preg_match('#^(\d{1,2})/(\d{4})$#', trim($this->filterBulan), $matches)) {
            return null;
        }
        $bulan = (int) $matches[1];
        $tahun = (int) $matches[2];
        if ($bulan < 1 || $bulan > 12) {
            return null;
        }
        $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        return [$start, $end, 'Bulanan', $this->bulanId($bulan) . ' ' . $tahun];
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
    public function detail()
    {
        $range = $this->periodeRange();
        if ($range === null) {
            return null;
        }
        [$start, $end] = $range;
        return $this->detailKritisQuery($start, $end, [
            'unit' => $this->filterUnit,
            'status' => $this->filterStatus,
            'pemeriksaan' => $this->filterPemeriksaan,
        ])->paginate($this->perPage);
    }

    #[Computed]
    public function recap(): array
    {
        $range = $this->periodeRange();
        if ($range === null) {
            return ['total' => 0, 'tinggi' => 0, 'rendah' => 0, 'rj' => 0, 'ugd' => 0, 'ri' => 0];
        }
        [$start, $end] = $range;
        return $this->recapKritis($start, $end);
    }

    #[Computed]
    public function perJenis()
    {
        $range = $this->periodeRange();
        return $range === null ? collect() : $this->perJenisKritis($range[0], $range[1]);
    }
};
?>

<div>
    <x-page-title
        title="Laporan Nilai Kritis Laboratorium"
        subtitle="Hasil lab yang melewati ambang (low/high limit) pada item auto-alert aktif — perlu perhatian dokter segera." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-surface-soft dark:bg-gray-800">
        <div class="px-6 pt-0 pb-6">

            {{-- TOP BAR: tab kiri + kontrol kanan --}}
            <div class="sticky z-30 pt-1 bg-surface-soft border-b border-hairline top-16 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                    <x-tabs variant="underline" class="-mb-px border-b-0">
                        <x-tab :active="$tab === 'detail'" color="brand" wire:click="setTab('detail')">Detail Nilai Kritis</x-tab>
                        <x-tab :active="$tab === 'rekap'" color="blue" wire:click="setTab('rekap')">Rekap</x-tab>
                    </x-tabs>

                    <div class="flex flex-wrap items-end gap-2.5 pb-2">
                        <div>
                            <x-input-label value="Mode" />
                            <div class="inline-flex mt-1 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                                <button type="button" wire:click="$set('filterMode', 'bulanan')"
                                    class="px-3 py-2 text-xs font-medium transition-colors {{ $filterMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                    Bulanan
                                </button>
                                <button type="button" wire:click="$set('filterMode', 'tahunan')"
                                    class="px-3 py-2 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600 {{ $filterMode === 'tahunan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                    Tahunan
                                </button>
                            </div>
                        </div>

                        @if ($filterMode === 'bulanan')
                            <div>
                                <x-input-label value="Bulan" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                    <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan" class="block w-32 pl-10" placeholder="mm/yyyy" maxlength="7" />
                                </div>
                            </div>
                        @else
                            <div>
                                <x-input-label value="Tahun" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                    <x-text-input type="text" wire:model.live.debounce.500ms="filterTahun" class="block w-28 pl-10" placeholder="yyyy" maxlength="4" />
                                </div>
                            </div>
                        @endif

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
                        @if ($filterMode === 'tahunan')
                            Format tahun belum valid. Ketik <span class="font-semibold">YYYY</span> (mis. 2026).
                        @else
                            Format bulan belum valid. Ketik <span class="font-semibold">MM/YYYY</span> (mis. 06/2026).
                        @endif
                    </div>
                </div>
            @else
                @if ($tab === 'detail')
                    {{-- FILTER DETAIL --}}
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Pemeriksaan" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.400ms="filterPemeriksaan" class="block w-full pl-10 sm:w-64" placeholder="Cari nama pemeriksaan..." />
                            </div>
                        </div>
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Status" />
                            <x-select-input wire:model.live="filterStatus" class="mt-1 w-full sm:w-36">
                                <option value="">Semua</option>
                                <option value="H">Tinggi</option>
                                <option value="L">Rendah</option>
                            </x-select-input>
                        </div>
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Unit" />
                            <x-select-input wire:model.live="filterUnit" class="mt-1 w-full sm:w-44">
                                <option value="">Semua</option>
                                <option value="RJ">Rawat Jalan</option>
                                <option value="UGD">Gawat Darurat</option>
                                <option value="RI">Rawat Inap</option>
                            </x-select-input>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between px-1">
                        <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                            Detail Nilai Kritis
                            <span class="ml-1 font-normal text-xs text-muted">({{ $this->periodeLabel }})</span>
                        </h3>
                        <span class="text-xs text-muted dark:text-gray-400 tabular-nums">{{ number_format($this->detail->total()) }} hasil</span>
                    </div>

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
                                        <th class="px-3 py-3 text-right">Hasil</th>
                                        <th class="px-3 py-3 text-left">Satuan</th>
                                        <th class="px-3 py-3 text-center">Nilai Rujukan</th>
                                        <th class="px-3 py-3 text-center">Status</th>
                                        <th class="px-3 py-3 text-left">Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->detail as $i => $row)
                                        @php
                                            $tinggi = $this->isTinggi($row->flag);
                                            // Konversi satuan seperti tampilan hasil lab: lowhigh_status='Y' & unit_convert numerik>0 → dikali.
                                            $unitConvertRaw = trim((string) ($row->unit_convert ?? ''));
                                            $factor = (($row->lowhigh_status ?? '') === 'Y' && is_numeric($unitConvertRaw) && (float) $unitConvertRaw > 0) ? (float) $unitConvertRaw : 1.0;
                                            $konversi = function ($nilai) use ($factor) {
                                                $teks = trim((string) ($nilai ?? ''));
                                                if ($teks === '' || !is_numeric($teks)) {
                                                    return $teks;
                                                }
                                                $nilaiKonversi = (float) $teks * $factor;
                                                return fmod($nilaiKonversi, 1) !== 0.0 ? (string) round($nilaiKonversi, 2) : number_format($nilaiKonversi);
                                            };
                                            $hasilFmt = $konversi($row->hasil);
                                            $lowFmt = $konversi($row->low_limit);
                                            $highFmt = $konversi($row->high_limit);
                                            $rujukanFmt = ($lowFmt !== '' || $highFmt !== '') ? trim($lowFmt . ' – ' . $highFmt) : '-';
                                        @endphp
                                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-2.5 text-muted-soft tabular-nums">{{ $this->detail->firstItem() + $i }}</td>
                                            <td class="px-3 py-2.5 whitespace-nowrap tabular-nums text-ink dark:text-gray-100">{{ $row->tgl }}</td>
                                            <td class="px-3 py-2.5 whitespace-nowrap font-medium tabular-nums text-ink dark:text-gray-100">{{ $row->reg_no }}</td>
                                            <td class="px-3 py-2.5 text-ink dark:text-gray-100">
                                                {{ $row->nama ?: '—' }}
                                                <span class="text-xs text-muted-soft">({{ $row->sex === 'L' ? 'L' : ($row->sex === 'P' ? 'P' : '-') }})</span>
                                            </td>
                                            <td class="px-3 py-2.5 text-body dark:text-gray-200">{{ $row->pemeriksaan ?: '—' }}</td>
                                            <td class="px-3 py-2.5 text-right font-bold tabular-nums {{ $tinggi ? 'text-red-700 dark:text-red-400' : 'text-blue-700 dark:text-blue-400' }}">{{ $hasilFmt !== '' ? $hasilFmt : '-' }}</td>
                                            <td class="px-3 py-2.5 text-muted dark:text-gray-400">{{ $row->satuan ?: '-' }}</td>
                                            <td class="px-3 py-2.5 text-center whitespace-nowrap text-muted dark:text-gray-400 tabular-nums">
                                                {{ $rujukanFmt }}
                                            </td>
                                            <td class="px-3 py-2.5 text-center">
                                                @if ($tinggi)
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Tinggi</span>
                                                @else
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Rendah</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 whitespace-nowrap text-muted dark:text-gray-400">{{ $this->unitLabel($row->unit) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="10" class="px-6 py-12">
                                            <div class="flex flex-col items-center justify-center gap-3">
                                                <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada nilai kritis pada periode ini</p>
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
                    {{-- REKAP --}}
                    @php $rekap = $this->recap; @endphp
                    <div class="mt-4 px-4 py-3 bg-canvas border border-hairline rounded-2xl text-sm text-body dark:text-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <span class="font-semibold text-body dark:text-gray-200">Ringkasan {{ $this->periodeLabel }}:</span>
                        Total <span class="font-semibold">{{ number_format($rekap['total']) }}</span> nilai kritis
                        · Tinggi <span class="font-medium text-red-700 dark:text-red-400">{{ number_format($rekap['tinggi']) }}</span>
                        · Rendah <span class="font-medium text-blue-700 dark:text-blue-400">{{ number_format($rekap['rendah']) }}</span>
                        · Rawat Jalan <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ number_format($rekap['rj']) }}</span>
                        · Gawat Darurat <span class="font-medium text-amber-700 dark:text-amber-400">{{ number_format($rekap['ugd']) }}</span>
                        · Rawat Inap <span class="font-medium text-purple-700 dark:text-purple-400">{{ number_format($rekap['ri']) }}</span>
                    </div>

                    <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                                Nilai Kritis per Jenis Pemeriksaan
                                <span class="ml-1 font-normal text-xs text-muted">({{ $this->periodeLabel }})</span>
                            </h3>
                            <span class="text-xs text-muted dark:text-gray-400 tabular-nums">{{ number_format(count($this->perJenis)) }} jenis</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-surface-card dark:bg-gray-800">
                                    <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                        <th class="px-3 py-3 text-left w-12">#</th>
                                        <th class="px-3 py-3 text-left">Jenis Pemeriksaan</th>
                                        <th class="px-3 py-3 text-right">Total Kritis</th>
                                        <th class="px-3 py-3 text-right text-red-700 dark:text-red-300">Tinggi</th>
                                        <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300">Rendah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->perJenis as $i => $jenis)
                                        <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-2.5 text-muted-soft tabular-nums">{{ $i + 1 }}</td>
                                            <td class="px-3 py-2.5 text-ink dark:text-gray-100">{{ $jenis->nama ?: '—' }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-ink dark:text-gray-100">{{ number_format($jenis->jml) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-red-700 dark:text-red-300">{{ number_format($jenis->tinggi) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($jenis->rendah) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-6 py-10 text-center text-muted dark:text-gray-400">Tidak ada nilai kritis pada periode ini</td></tr>
                                    @endforelse
                                </tbody>
                                @if (count($this->perJenis))
                                    <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                        <tr class="text-sm font-bold text-ink dark:text-gray-100">
                                            <td class="px-3 py-3" colspan="2">TOTAL</td>
                                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($rekap['total']) }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums text-red-800 dark:text-red-200">{{ number_format($rekap['tinggi']) }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($rekap['rendah']) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
