<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Penunjang\RL38Trait;

new class extends Component {
    use RL38Trait;

    public int $bulan;
    public int $tahun;

    public function mount(): void
    {
        $now         = Carbon::now();
        $this->bulan = $now->month;
        $this->tahun = $now->year;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL38($this->bulan, $this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        return [
            'jumlah_l' => array_sum(array_column($rows, 'jumlah_l')),
            'jumlah_p' => array_sum(array_column($rows, 'jumlah_p')),
        ];
    }

    public function bulanLabel(int $m): string
    {
        return [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',     4 => 'April',
            5 => 'Mei',      6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September',10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
    }
};
?>

<div>
    <x-page-title
        title="Laporan RL 3.8 — Laboratorium"
        subtitle="Rekap pemeriksaan lab per bulan sesuai format SIRS Online Kemenkes (138 jenis). Dihitung dari tes individual (komponen panel), jumlah = pemeriksaan berbeda per pasien, rata-rata/hari = jumlah ÷ hari buka lab." />

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
                        <x-input-label value="Bulan" />
                        <select wire:model.live="bulan"
                            class="block w-full mt-1 sm:w-40 rounded-lg border-gray-300 shadow-sm focus:border-brand-green focus:ring-brand-green text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            @for ($m = 1; $m <= 12; $m++)<option value="{{ $m }}">{{ $this->bulanLabel($m) }}</option>@endfor
                        </select>
                    </div>
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tahun" />
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahun"
                            min="2000" max="2099" maxlength="4" class="block w-full mt-1 sm:w-32" />
                    </div>
                    <div class="ml-auto text-xs text-muted dark:text-gray-400 leading-snug">
                        Periode: <span class="font-semibold text-body dark:text-gray-200">{{ $this->bulanLabel($bulan) }} {{ $tahun }}</span>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php
                $tot = $this->totals;
                $currentGrup = null;
            @endphp
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 3.8 &mdash; Tabel Pemeriksaan Laboratorium
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis pemeriksaan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="sticky top-0 z-20 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center w-16">No.</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left min-w-[24rem]">Jenis Pemeriksaan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300">Jumlah Pemeriksaan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-purple-700 dark:text-purple-300">Rata-Rata Pemeriksaan/Hari</th>
                            </tr>
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Perempuan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Perempuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php
                                    $isCatchAll = $r['id'] === '0';
                                    $showGroupHeader = !$isCatchAll && $r['grup'] !== $currentGrup;
                                    if (!$isCatchAll) $currentGrup = $r['grup'];
                                @endphp

                                @if ($showGroupHeader)
                                    <tr class="bg-surface-soft dark:bg-gray-800/40">
                                        <td colspan="6" class="px-3 py-1.5 text-[11px] font-semibold tracking-wider uppercase text-muted dark:text-gray-300 border-y border-hairline dark:border-gray-700">
                                            {{ $r['grup'] }}
                                        </td>
                                    </tr>
                                @endif

                                <tr class="{{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15 font-semibold' : 'hover:bg-surface-soft dark:hover:bg-gray-800/50' }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted">{{ $r['id'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['jumlah_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['jumlah_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['rata_l'], 1) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['rata_p'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold sticky bottom-0 z-10">
                            <tr class="text-[11px] text-ink dark:text-gray-100">
                                <td colspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700">TOTAL (semua grup)</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['jumlah_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['jumlah_p']) }}</td>
                                <td colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted italic">&mdash;</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> rstxn_rjlabs (RJ filter rj_date), rstxn_ugdlabs (UGD filter rj_date),
                    rstxn_rilabs (RI filter exit_date sesuai konvensi laporan RI). JOIN ke rsmst_pasiens untuk gender.
                    <strong>Hari buka lab</strong> = COUNT DISTINCT TRUNC(rj_date) lintas RJ + UGD lab dalam periode.
                    <strong>Rata-rata</strong> = total / hari_buka per gender.
                    <strong>Mapping clabitem &rarr; SIRS:</strong> belum ada di master &mdash; semua lab valid jatuh
                    ke baris <code>"0 - Tidak Ada Data"</code>. 138 row resmi diisi 0. Aktivasi mapping butuh
                    DDL kolom <code>sirs_rl38_id</code> di <code>lbmst_clabitems</code> atau master mapping terpisah.
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code>.
                </div>
            </div>
        </div>
    </div>
</div>
