<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ri\RL319Trait;

new class extends Component {
    use RL319Trait;

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL319($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        return [
            'ranap_keluar'    => array_sum(array_column($rows, 'ranap_keluar')),
            'ranap_lama'      => array_sum(array_column($rows, 'ranap_lama')),
            'rajal_pasien'    => array_sum(array_column($rows, 'rajal_pasien')),
            'rajal_lab'       => array_sum(array_column($rows, 'rajal_lab')),
            'rajal_radiologi' => array_sum(array_column($rows, 'rajal_radiologi')),
            'rajal_lain'      => array_sum(array_column($rows, 'rajal_lain')),
        ];
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Laporan RL 3.19 &mdash; Cara Bayar
                </h2>
                <p class="text-base text-gray-700 dark:text-gray-700">
                    Rekap pasien per cara pembayaran per tahun, sesuai format SIRS Online Kemenkes
                    (9 cara bayar).
                    <span class="text-gray-400">Mapping otomatis: <code>klaim_id</code> + <code>klaim_status</code> +
                    keyword <code>klaim_desc</code> dari master <code>rsmst_klaimtypes</code>.</span>
                </p>
            </div>
            <a href="{{ route('manajemen.indikator-pelayanan') }}" wire:navigate
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali
            </a>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">
            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tahun" />
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahun"
                            min="2000" max="2099" maxlength="4" class="block w-full mt-1 sm:w-32" />
                    </div>
                    <div class="ml-auto text-xs text-gray-500 dark:text-gray-400 leading-snug">
                        Periode: <span class="font-semibold text-gray-700 dark:text-gray-200">Januari &ndash; Desember {{ $tahun }}</span>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totals; @endphp
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        RL 3.19 &mdash; Tabel Cara Pembayaran
                        <span class="ml-2 font-normal text-xs text-gray-500">
                            ({{ count($this->rows) }} cara bayar, tahun {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center w-12">No.</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center w-16">No. Cara Bayar</th>
                                <th rowspan="2" class="px-3 py-2 border border-gray-200 dark:border-gray-700 text-left min-w-[16rem]">Cara Pembayaran</th>
                                <th colspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-purple-700 dark:text-purple-300">Pasien Rawat Inap</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Jumlah Pasien Rawat Jalan</th>
                                <th colspan="3" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-cyan-700 dark:text-cyan-300">Pasien Rawat Jalan</th>
                            </tr>
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Pasien Keluar</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Lama Dirawat</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-cyan-700 dark:text-cyan-300">Laboratorium</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-cyan-700 dark:text-cyan-300">Radiologi</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-cyan-700 dark:text-cyan-300">Lain-lain</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $i => $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-600">{{ $r['no'] }}</td>
                                    <td class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['ranap_keluar']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['ranap_lama']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300 font-semibold">{{ number_format($r['rajal_pasien']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-cyan-700 dark:text-cyan-300">{{ number_format($r['rajal_lab']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-cyan-700 dark:text-cyan-300">{{ number_format($r['rajal_radiologi']) }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums text-cyan-700 dark:text-cyan-300">{{ number_format($r['rajal_lain']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-[11px] text-gray-800 dark:text-gray-100">
                                <td colspan="3" class="px-3 py-2 border border-gray-200 dark:border-gray-700">TOTAL</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['ranap_keluar']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['ranap_lama']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['rajal_pasien']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-cyan-800 dark:text-cyan-200">{{ number_format($tot['rajal_lab']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-cyan-800 dark:text-cyan-200">{{ number_format($tot['rajal_radiologi']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-cyan-800 dark:text-cyan-200">{{ number_format($tot['rajal_lain']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-gray-500 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800 leading-relaxed">
                    <strong>Mapping cara bayar</strong> eksplisit per <code>klaim_id</code> dari
                    <code>rsmst_klaimtypes</code> (13 klaim RSI Madinah):
                    PB/JM/HI → 2.1 BPJS Kesehatan; JR/JS/TP → 2.3 Asuransi Pemerintah Lainnya;
                    JML → 2.4 Asuransi Swasta; JK → 4.1 Kartu Sehat (Jamkesmas);
                    UM/KW/HC/KR → 1 Membayar Sendiri (KR Kronis = bayar sendiri per konvensi RSI Madinah);
                    DK → 4.3 Lain-Lain. Fallback keyword match untuk klaim_id baru.
                    <strong>Sumber metrik:</strong>
                    Pasien RI Keluar = COUNT <code>rstxn_rihdrs</code> (exit_date di tahun, ri_status&ne;F);
                    Lama Dirawat = SUM(exit-entry) hari;
                    Pasien Rajal = COUNT visit di RJ + UGD (rj_date di tahun);
                    Lab/Rad = COUNT DISTINCT rj_no yang punya order di rstxn_*labs / *rads;
                    Lain-lain = max(0, Pasien Rajal &minus; Lab &minus; Rad) &mdash; approximation, lab/rad bisa overlap.
                    Filter: status valid (RJ/UGD <code>rj_status NOT IN ('A','F')</code>, RI <code>ri_status &ne; 'F'</code>).
                    <strong>Catatan:</strong> Kronis (KR) di RL 3.19 tetap di-count sebagai BPJS (rujuk balik), beda dari laporan lain.
                </div>
            </div>
        </div>
    </div>
</div>
