<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ri\RL41Trait;

new class extends Component {
    use RL41Trait;

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL41($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        $totalCells = array_fill(0, 25, ['L' => 0, 'P' => 0]);
        $totL = 0; $totP = 0; $matiL = 0; $matiP = 0;
        foreach ($rows as $r) {
            foreach ($r['cells'] as $i => $cell) {
                $totalCells[$i]['L'] += $cell['L'];
                $totalCells[$i]['P'] += $cell['P'];
            }
            $totL += $r['total_l'];
            $totP += $r['total_p'];
            $matiL += $r['mati_l'];
            $matiP += $r['mati_p'];
        }
        return [
            'cells' => $totalCells,
            'total_l' => $totL,
            'total_p' => $totP,
            'total' => $totL + $totP,
            'mati_l' => $matiL,
            'mati_p' => $matiP,
            'mati_total' => $matiL + $matiP,
        ];
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Laporan RL 4.1 &mdash; Morbiditas Pasien Rawat Inap
                </h2>
                <p class="text-base text-gray-700 dark:text-gray-700">
                    Rekap morbiditas RI per ICD-10 × umur × gender × hidup/mati per tahun, sesuai format SIRS Online Kemenkes.
                    <span class="text-gray-400">25 kelompok umur (&lt;1 jam s.d. &ge;85 tahun) × 2 gender = 50 cell + total + meninggal.
                    Diagnosis utama dari JSON <code>diagnosis[0].icdX</code>.</span>
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
                        RL 4.1 &mdash; Morbiditas RI per ICD-10 × Umur × Gender
                        <span class="ml-2 font-normal text-xs text-gray-500">
                            ({{ count($this->rows) }} ICD, total {{ number_format($tot['total']) }} pasien, mati {{ number_format($tot['mati_total']) }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                    <table class="text-[10px] border-collapse">
                        <thead class="sticky top-0 z-30 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr class="font-semibold tracking-wider uppercase">
                                <th rowspan="3" class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-center sticky left-0 bg-gray-100 dark:bg-gray-800 z-20 w-10">No.</th>
                                <th rowspan="3" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center sticky left-10 bg-gray-100 dark:bg-gray-800 z-20 w-20">ICD-10</th>
                                <th rowspan="3" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-left sticky left-30 bg-gray-100 dark:bg-gray-800 z-20 min-w-[14rem]">Diagnosis</th>
                                <th colspan="50" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-blue-700 dark:text-blue-300">Jumlah Hidup &amp; Mati Menurut Kelompok Umur &amp; Gender</th>
                                <th colspan="3" rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-emerald-700 dark:text-emerald-300">Total Hidup &amp; Mati per Gender</th>
                                <th colspan="3" rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Total Pasien Keluar Mati</th>
                            </tr>
                            <tr>
                                @foreach (App\Http\Traits\Manajemen\Sirs\Ri\RL41Trait::AGE_GROUPS_RL41 as $ag)
                                    <th colspan="2" class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-center text-blue-700 dark:text-blue-300 whitespace-nowrap" style="font-size:9px;">{{ $ag['label'] }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach (App\Http\Traits\Manajemen\Sirs\Ri\RL41Trait::AGE_GROUPS_RL41 as $ag)
                                    <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-blue-700 dark:text-blue-300" style="font-size:9px;">L</th>
                                    <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-pink-700 dark:text-pink-300" style="font-size:9px;">P</th>
                                @endforeach
                                <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">L</th>
                                <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">P</th>
                                <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">Total</th>
                                <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300" style="font-size:9px;">L</th>
                                <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300" style="font-size:9px;">P</th>
                                <th class="px-1 py-1 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300" style="font-size:9px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $i => $r)
                                @php $isCatchAll = $r['icd'] === '0'; @endphp
                                <tr class="{{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15 font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }} border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-500 sticky left-0 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-white dark:bg-gray-900' }}">{{ $i + 1 }}</td>
                                    <td class="px-2 py-1 border-r border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-700 dark:text-gray-200 sticky left-10 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-white dark:bg-gray-900' }}">{{ $r['icd'] }}</td>
                                    <td class="px-2 py-1 border-r border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100 sticky left-30 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-white dark:bg-gray-900' }}" title="{{ $r['icd_desc'] }}">{{ \Illuminate\Support\Str::limit($r['icd_desc'], 60) }}</td>
                                    @for ($g = 0; $g < 25; $g++)
                                        <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $r['cells'][$g]['L'] ?: '' }}</td>
                                        <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ $r['cells'][$g]['P'] ?: '' }}</td>
                                    @endfor
                                    <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $r['total_l'] }}</td>
                                    <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $r['total_p'] }}</td>
                                    <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300 font-semibold">{{ $r['total'] }}</td>
                                    <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ $r['mati_l'] ?: '' }}</td>
                                    <td class="px-1 py-1 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ $r['mati_p'] ?: '' }}</td>
                                    <td class="px-1 py-1 text-right tabular-nums text-rose-700 dark:text-rose-300 font-semibold">{{ $r['mati_total'] ?: '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="59" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400 italic">Belum ada data RI exit di tahun {{ $tahun }}</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rows) > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold sticky bottom-0 z-10">
                                <tr class="text-[10px] text-gray-800 dark:text-gray-100">
                                    <td colspan="3" class="px-2 py-2 border border-gray-200 dark:border-gray-700 sticky left-0 bg-gray-100 dark:bg-gray-800 z-20">TOTAL</td>
                                    @for ($g = 0; $g < 25; $g++)
                                        <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $tot['cells'][$g]['L'] ?: '' }}</td>
                                        <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ $tot['cells'][$g]['P'] ?: '' }}</td>
                                    @endfor
                                    <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['total_l'] }}</td>
                                    <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['total_p'] }}</td>
                                    <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['total'] }}</td>
                                    <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $tot['mati_l'] }}</td>
                                    <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $tot['mati_p'] }}</td>
                                    <td class="px-1 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $tot['mati_total'] }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-gray-500 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rihdrs (RI saja, exit_date di tahun, ri_status≠F).
                    Diagnosis utama dari JSON <code>datadaftarri_json.diagnosis[0].icdX</code>. Pasien tanpa diagnosis → row "0 Tidak Ada Data".
                    <strong>Klasifikasi umur:</strong> perinatal (&lt;28 hari) pakai LOS (exit−entry) untuk akurasi jam/hari;
                    umur ≥28 hari pakai birth_date (Carbon::diffInYears, calendar-aware).
                    <strong>"Mati"</strong> dideteksi dari JSON tindakLanjutKode='419099009' (SNOMED Meninggal).
                    <strong>Catatan:</strong> 1 admisi = 1 baris data (diagnosis utama saja). Diagnosis sekunder tidak dihitung
                    di RL 4.1 (sesuai konvensi SIRS — count per pasien, bukan per diagnosis).
                    Filter: <code>klaim_id ≠ 'KR'</code>.
                </div>
            </div>
        </div>
    </div>
</div>
