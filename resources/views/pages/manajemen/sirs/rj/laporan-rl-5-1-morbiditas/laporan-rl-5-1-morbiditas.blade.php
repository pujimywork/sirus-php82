<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Rj\RL51Trait;

new class extends Component {
    use RL51Trait;

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL51($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        $totalCells = array_fill(0, 25, ['L' => 0, 'P' => 0]);
        $kL = 0; $kP = 0; $juL = 0; $juP = 0;
        foreach ($rows as $r) {
            foreach ($r['cells'] as $i => $cell) {
                $totalCells[$i]['L'] += $cell['L'];
                $totalCells[$i]['P'] += $cell['P'];
            }
            $kL += $r['kasus_l'];
            $kP += $r['kasus_p'];
            $juL += $r['kunj_l'];
            $juP += $r['kunj_p'];
        }
        return [
            'cells'       => $totalCells,
            'kasus_l'     => $kL,
            'kasus_p'     => $kP,
            'kasus_total' => $kL + $kP,
            'kunj_l'      => $juL,
            'kunj_p'      => $juP,
            'kunj_total'  => $juL + $juP,
        ];
    }
};
?>

<div>
    <x-page-title
        title="Laporan RL 5.1 — Morbiditas Pasien Rawat Jalan"
        subtitle="Rekap morbiditas RJ poliklinik per ICD-10 × umur × gender per tahun. &quot;Kasus Baru&quot; = pasien unik per ICD per tahun (DISTINCT reg_no). &quot;Jumlah Kunjungan&quot; = total visits. 1 pasien dengan multiple visit ICD-yang-sama → tetap 1 kasus baru." />

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
                        RL 5.1 &mdash; Morbiditas RJ per ICD-10 × Umur × Gender
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} ICD, {{ number_format($tot['kasus_total']) }} kasus baru, {{ number_format($tot['kunj_total']) }} kunjungan)
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                    <table class="text-[10px] border-collapse">
                        <thead class="sticky top-0 z-30 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="font-semibold tracking-wider uppercase">
                                <th rowspan="3" class="px-1 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20 w-10">No.</th>
                                <th rowspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-10 bg-surface-soft dark:bg-gray-800 z-20 w-20">ICD-10</th>
                                <th rowspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 text-left sticky left-30 bg-surface-soft dark:bg-gray-800 z-20 min-w-[14rem]">Diagnosis</th>
                                <th colspan="50" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300">Kasus Baru per Umur × Gender</th>
                                <th colspan="3" rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-emerald-700 dark:text-emerald-300">Total Kasus Baru</th>
                                <th colspan="3" rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-purple-700 dark:text-purple-300">Total Kunjungan</th>
                            </tr>
                            <tr>
                                @foreach (App\Http\Traits\Manajemen\Sirs\Rj\RL51Trait::AGE_GROUPS_RL51 as $ag)
                                    <th colspan="2" class="px-1 py-1 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300 whitespace-nowrap" style="font-size:9px;">{{ $ag['label'] }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach (App\Http\Traits\Manajemen\Sirs\Rj\RL51Trait::AGE_GROUPS_RL51 as $ag)
                                    <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300" style="font-size:9px;">L</th>
                                    <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-pink-700 dark:text-pink-300" style="font-size:9px;">P</th>
                                @endforeach
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">L</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">P</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">Total</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300" style="font-size:9px;">L</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300" style="font-size:9px;">P</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300" style="font-size:9px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $i => $r)
                                @php $isCatchAll = $r['icd'] === '0'; @endphp
                                <tr class="{{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15 font-semibold' : 'hover:bg-surface-soft dark:hover:bg-gray-800/50' }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-canvas dark:bg-gray-900' }}">{{ $i + 1 }}</td>
                                    <td class="px-2 py-1 border-r border-hairline dark:border-gray-700 font-mono text-xs text-body dark:text-gray-200 sticky left-10 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['icd'] }}</td>
                                    <td class="px-2 py-1 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-30 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-canvas dark:bg-gray-900' }}" title="{{ $r['icd_desc'] }}">{{ \Illuminate\Support\Str::limit($r['icd_desc'], 60) }}</td>
                                    @for ($g = 0; $g < 25; $g++)
                                        <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $r['cells'][$g]['L'] ?: '' }}</td>
                                        <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ $r['cells'][$g]['P'] ?: '' }}</td>
                                    @endfor
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $r['kasus_l'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $r['kasus_p'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300 font-semibold">{{ $r['kasus_total'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $r['kunj_l'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ $r['kunj_p'] }}</td>
                                    <td class="px-1 py-1 text-right tabular-nums text-purple-700 dark:text-purple-300 font-semibold">{{ $r['kunj_total'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="59" class="px-6 py-10 text-center text-muted dark:text-gray-400 italic">Belum ada data RJ di tahun {{ $tahun }}</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rows) > 0)
                            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold sticky bottom-0 z-10">
                                <tr class="text-[10px] text-ink dark:text-gray-100">
                                    <td colspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">TOTAL</td>
                                    @for ($g = 0; $g < 25; $g++)
                                        <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $tot['cells'][$g]['L'] ?: '' }}</td>
                                        <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ $tot['cells'][$g]['P'] ?: '' }}</td>
                                    @endfor
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['kasus_l'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['kasus_p'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['kasus_total'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['kunj_l'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['kunj_p'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $tot['kunj_total'] }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rjhdrs (RJ saja, rj_date di tahun, rj_status NOT IN ('A','F')).
                    Diagnosis utama dari JSON <code>datadaftarpolirj_json.diagnosis[0].icdX</code>. Pasien tanpa
                    diagnosis → row "0 Tidak Ada Data".
                    <strong>"Kasus Baru":</strong> DISTINCT reg_no per (icd, age_group, gender). 1 pasien dengan
                    multiple visit ICD-yang-sama → tetap 1 kasus baru.
                    <strong>"Kunjungan":</strong> COUNT semua visit (1 visit = 1 kunjungan).
                    <strong>Klasifikasi umur:</strong> birth_date pasien dengan rj_date sebagai reference. Perinatal
                    (&lt;28 hari) pakai detik untuk akurasi jam; ≥28 hari pakai Carbon::diffInYears (calendar-aware).
                    Filter: <code>klaim_id ≠ 'KR'</code>.
                </div>
            </div>
        </div>
    </div>
</div>
