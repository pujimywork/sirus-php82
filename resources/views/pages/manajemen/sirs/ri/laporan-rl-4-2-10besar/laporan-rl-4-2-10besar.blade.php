<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ri\RL42Trait;

new class extends Component {
    use RL42Trait;

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL42($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        return [
            'laki'       => array_sum(array_column($rows, 'laki')),
            'perempuan'  => array_sum(array_column($rows, 'perempuan')),
            'total'      => array_sum(array_column($rows, 'total')),
            'mati_l'     => array_sum(array_column($rows, 'mati_l')),
            'mati_p'     => array_sum(array_column($rows, 'mati_p')),
            'mati_total' => array_sum(array_column($rows, 'mati_total')),
        ];
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Laporan RL 4.2 &mdash; 10 Besar Penyakit Rawat Inap
                </h2>
                <p class="text-base text-gray-700 dark:text-gray-700">
                    Top 10 ICD-10 paling banyak di Rawat Inap per tahun, sesuai format SIRS Online Kemenkes.
                    <span class="text-gray-400">Sorted desc by total (Laki + Perempuan). Pasien tanpa diagnosis
                    di-exclude dari ranking.</span>
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
                        RL 4.2 &mdash; Top 10 ICD-10 di Rawat Inap
                        <span class="ml-2 font-normal text-xs text-gray-500">
                            ({{ count($this->rows) }} ICD top, total {{ number_format($tot['total']) }} pasien, mati {{ number_format($tot['mati_total']) }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-3 text-center w-12 border border-gray-200 dark:border-gray-700">No.</th>
                                <th rowspan="2" class="px-2 py-3 text-center w-24 border border-gray-200 dark:border-gray-700">Kelompok ICD-10</th>
                                <th rowspan="2" class="px-3 py-3 text-left border border-gray-200 dark:border-gray-700">Kelompok Diagnosa Penyakit</th>
                                <th colspan="3" class="px-2 py-3 text-center border border-gray-200 dark:border-gray-700 text-emerald-700 dark:text-emerald-300">Hidup &amp; Mati per Gender</th>
                                <th colspan="3" class="px-2 py-3 text-center border border-gray-200 dark:border-gray-700 text-rose-700 dark:text-rose-300">Pasien Keluar Mati</th>
                            </tr>
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-pink-700 dark:text-pink-300">Perempuan</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Total</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">Perempuan</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $i => $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono font-bold text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-700 dark:text-gray-200">{{ $r['icd'] }}</td>
                                    <td class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100" title="{{ $r['icd_desc'] }}">{{ $r['icd_desc'] }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['laki']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ number_format($r['perempuan']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300 font-semibold">{{ number_format($r['total']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['mati_l']) }}</td>
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['mati_p']) }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums text-rose-700 dark:text-rose-300 font-semibold">{{ number_format($r['mati_total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400 italic">Belum ada data RI exit di tahun {{ $tahun }}</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rows) > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                                <tr class="text-sm text-gray-800 dark:text-gray-100">
                                    <td colspan="3" class="px-3 py-3 border border-gray-200 dark:border-gray-700">TOTAL (top {{ count($this->rows) }})</td>
                                    <td class="px-2 py-3 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['laki']) }}</td>
                                    <td class="px-2 py-3 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ number_format($tot['perempuan']) }}</td>
                                    <td class="px-2 py-3 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['total']) }}</td>
                                    <td class="px-2 py-3 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_l']) }}</td>
                                    <td class="px-2 py-3 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_p']) }}</td>
                                    <td class="px-2 py-3 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_total']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-gray-500 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rihdrs (RI saja, exit_date di tahun, ri_status&ne;F).
                    Diagnosis utama dari JSON <code>datadaftarri_json.diagnosis[0].icdX</code>.
                    <strong>Sorting:</strong> desc by total (Laki + Perempuan). Pasien tanpa diagnosis utama tidak masuk
                    ranking. <strong>"Mati"</strong>: SNOMED 419099009 di JSON.
                    Filter: <code>klaim_id ≠ 'KR'</code>.
                    <strong>Catatan:</strong> Versi simpler dari RL 4.1 — tanpa breakdown 25 kelompok umur, hanya per gender.
                    Total di tfoot adalah jumlah top {{ count($this->rows) }} (bukan total semua pasien RI tahun tsb).
                </div>
            </div>
        </div>
    </div>
</div>
