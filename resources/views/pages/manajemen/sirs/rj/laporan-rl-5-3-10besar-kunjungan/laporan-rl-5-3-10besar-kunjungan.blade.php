<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Rj\RL53Trait;

new class extends Component {
    use RL53Trait;

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL53($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        return [
            'kasus_l'     => array_sum(array_column($rows, 'kasus_l')),
            'kasus_p'     => array_sum(array_column($rows, 'kasus_p')),
            'kasus_total' => array_sum(array_column($rows, 'kasus_total')),
            'kunj_l'      => array_sum(array_column($rows, 'kunj_l')),
            'kunj_p'      => array_sum(array_column($rows, 'kunj_p')),
            'kunj_total'  => array_sum(array_column($rows, 'kunj_total')),
        ];
    }
};
?>

<div>
    <x-page-title
        title="Laporan RL 5.3 — 10 Besar Kunjungan Penyakit Rawat Jalan"
        subtitle="Top 10 ICD-10 dengan jumlah kunjungan terbanyak di RJ poliklinik per tahun. Sorted desc by total kunjungan (L + P). &quot;Kasus Baru&quot; = pasien unik (DISTINCT reg_no), &quot;Kunjungan&quot; = total visits." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">
            <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
                <a href="{{ route('manajemen.indikator-pelayanan') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-body bg-canvas border border-gray-300 rounded-lg hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali
                </a>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tahun" />
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahun"
                            min="2000" max="2099" maxlength="4" class="block w-full mt-1 sm:w-32" />
                    </div>
                    <div class="ml-auto text-xs text-muted dark:text-gray-400 leading-snug">
                        Periode: <span class="font-semibold text-body dark:text-gray-200">Januari &ndash; Desember {{ $tahun }}</span>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totals; @endphp
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 5.3 &mdash; Top 10 ICD-10 Kunjungan di Rawat Jalan
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} ICD top, {{ number_format($tot['kasus_total']) }} kasus baru, {{ number_format($tot['kunj_total']) }} kunjungan)
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border-collapse">
                        <thead class="bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-3 text-center w-12 border border-hairline dark:border-gray-700">No.</th>
                                <th rowspan="2" class="px-2 py-3 text-center w-24 border border-hairline dark:border-gray-700">Kelompok ICD-10</th>
                                <th rowspan="2" class="px-3 py-3 text-left border border-hairline dark:border-gray-700">Kelompok Diagnosa Penyakit</th>
                                <th colspan="3" class="px-2 py-3 text-center border border-hairline dark:border-gray-700 text-emerald-700 dark:text-emerald-300">Jumlah Kasus Baru</th>
                                <th colspan="3" class="px-2 py-3 text-center border border-hairline dark:border-gray-700 text-purple-700 dark:text-purple-300">Jumlah Kunjungan (sort key)</th>
                            </tr>
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-pink-700 dark:text-pink-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Total</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-pink-700 dark:text-pink-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $i => $r)
                                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50 border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-center font-mono font-bold text-muted">{{ $i + 1 }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-center font-mono text-body dark:text-gray-200">{{ $r['icd'] }}</td>
                                    <td class="px-3 py-2 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100" title="{{ $r['icd_desc'] }}">{{ $r['icd_desc'] }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['kasus_l']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ number_format($r['kasus_p']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300 font-semibold">{{ number_format($r['kasus_total']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['kunj_l']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ number_format($r['kunj_p']) }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums text-purple-700 dark:text-purple-300 font-bold text-base">{{ number_format($r['kunj_total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-6 py-10 text-center text-muted dark:text-gray-400 italic">Belum ada data RJ di tahun {{ $tahun }}</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rows) > 0)
                            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                                <tr class="text-sm text-ink dark:text-gray-100">
                                    <td colspan="3" class="px-3 py-3 border border-hairline dark:border-gray-700">TOTAL (top {{ count($this->rows) }})</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['kasus_l']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ number_format($tot['kasus_p']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['kasus_total']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['kunj_l']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ number_format($tot['kunj_p']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kunj_total']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rjhdrs (RJ saja, rj_date di tahun, rj_status NOT IN ('A','F')).
                    Diagnosis utama dari JSON <code>datadaftarpolirj_json.diagnosis[0].icdX</code>.
                    <strong>Sorting:</strong> desc by total kunjungan (L + P).
                    <strong>"Kasus Baru":</strong> DISTINCT reg_no per (icd, gender). 1 pasien dgn multiple visit ICD-sama → 1 kasus baru.
                    <strong>"Kunjungan":</strong> COUNT semua visits.
                    Pasien tanpa diagnosis utama dikecualikan dari ranking.
                    Filter: <code>klaim_id ≠ 'KR'</code>.
                    <strong>Catatan:</strong> Versi simpler dari RL 5.1 — tanpa breakdown 25 kelompok umur, hanya total per gender.
                </div>
            </div>
        </div>
    </div>
</div>
