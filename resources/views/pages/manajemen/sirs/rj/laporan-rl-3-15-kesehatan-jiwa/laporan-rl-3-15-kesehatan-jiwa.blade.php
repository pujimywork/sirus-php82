<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Rj\RL315Trait;

new class extends Component {
    use RL315Trait;

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL315($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        return [
            'laki'      => array_sum(array_column($rows, 'laki')),
            'perempuan' => array_sum(array_column($rows, 'perempuan')),
            'jumlah'    => array_sum(array_column($rows, 'jumlah')),
        ];
    }
};
?>

<div>
    <x-page-title
        title="Laporan RL 3.15 — Kesehatan Jiwa"
        subtitle="Rekap pelayanan kesehatan jiwa rawat jalan per tahun, sesuai format SIRS Online Kemenkes (8 jenis kegiatan + 1 fallback). Source: kunjungan POLI PSIKIATRI di RJ. UGD/RI tidak termasuk (RL 3.15 spesifik untuk pelayanan rawat jalan)." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">
            <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
                <a href="{{ route('manajemen.indikator-pelayanan') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali
                </a>
            </div>

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
                        RL 3.15 &mdash; Tabel Pelayanan Kesehatan Jiwa
                        <span class="ml-2 font-normal text-xs text-gray-500">
                            ({{ count($this->rows) }} jenis kegiatan, tahun {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th class="px-2 py-3 text-center w-16 border border-gray-200 dark:border-gray-700">No.</th>
                                <th class="px-3 py-3 text-left border border-gray-200 dark:border-gray-700">Jenis Kegiatan</th>
                                <th class="px-3 py-3 text-right border border-gray-200 dark:border-gray-700 text-blue-700 dark:text-blue-300">Laki-Laki</th>
                                <th class="px-3 py-3 text-right border border-gray-200 dark:border-gray-700 text-pink-700 dark:text-pink-300">Perempuan</th>
                                <th class="px-3 py-3 text-right border border-gray-200 dark:border-gray-700">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php $isCatchAll = $r['id'] === 0; @endphp
                                <tr class="{{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15 font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }} border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-500">{{ $r['no'] }}</td>
                                    <td class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100">{{ $r['nama'] }}</td>
                                    <td class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['laki']) }}</td>
                                    <td class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ number_format($r['perempuan']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ number_format($r['jumlah']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-sm text-gray-800 dark:text-gray-100">
                                <td colspan="2" class="px-3 py-3 border border-gray-200 dark:border-gray-700">TOTAL</td>
                                <td class="px-3 py-3 text-right tabular-nums border border-gray-200 dark:border-gray-700 text-blue-800 dark:text-blue-200">{{ number_format($tot['laki']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums border border-gray-200 dark:border-gray-700 text-pink-800 dark:text-pink-200">{{ number_format($tot['perempuan']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums border border-gray-200 dark:border-gray-700">{{ number_format($tot['jumlah']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-gray-500 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rjhdrs (RJ saja) JOIN rsmst_doctors di mana <code>poli_id='14'</code>
                    (POLI PSIKIATRI). UGD/RI tidak termasuk &mdash; RL 3.15 spesifik untuk rawat jalan.
                    <strong>Mapping kegiatan:</strong> sistem belum tracking per-aktivitas psikiatri (Pemeriksaan vs
                    Psikoterapi vs Konseling vs Medikamentosa, dll). MVP: SEMUA kunjungan poli psikiatri jatuh ke
                    row <strong>1 "Pemeriksaan Psikiatri"</strong> (default minimal yang pasti dilakukan tiap visit).
                    Row 2-8 di-render 0; aktivasi butuh tracking aktivitas di EMR atau master tindakan psikiatri.
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code>, <code>rj_status NOT IN ('A','F')</code>.
                </div>
            </div>
        </div>
    </div>
</div>
