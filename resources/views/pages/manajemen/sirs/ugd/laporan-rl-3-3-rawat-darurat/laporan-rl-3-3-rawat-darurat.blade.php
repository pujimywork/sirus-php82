<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ugd\RL33Trait;

new class extends Component {
    use RL33Trait;

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
        return $this->computeRL33($this->bulan, $this->tahun);
    }

    #[Computed]
    public function totalRow(): array
    {
        $rows = $this->rows;
        $sum  = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'rujukan'         => $sum('rujukan'),
            'non_rujukan'     => $sum('non_rujukan'),
            'dirawat'         => $sum('dirawat'),
            'dirujuk'         => $sum('dirujuk'),
            'pulang'          => $sum('pulang'),
            'mati_igd_l'      => $sum('mati_igd_l'),
            'mati_igd_p'      => $sum('mati_igd_p'),
            'doa_l'           => $sum('doa_l'),
            'doa_p'           => $sum('doa_p'),
            'luka_l'          => $sum('luka_l'),
            'luka_p'          => $sum('luka_p'),
            'false_emergency' => $sum('false_emergency'),
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
        title="Laporan RL 3.3 — Rawat Darurat"
        subtitle="Rekap UGD/IGD per jenis pelayanan per bulan, sesuai format SIRS Online Kemenkes. Mapping otomatis: poli DPJP & umur pasien → 13 jenis pelayanan (Bayi/Anak/Geriatri/Kebidanan/Psikiatrik/Non bedah lainnya). Kategori Kecelakaan & Kekerasan butuh ICD diagnosis matching, ditangguhkan." />

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
            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
            @php $tot = $this->totalRow; @endphp
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 3.3 &mdash; Tabel Rekap Rawat Darurat
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis pelayanan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="bg-surface-soft dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-12 bg-surface-soft dark:bg-gray-800 z-20">No Pelayanan</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left sticky left-28 bg-surface-soft dark:bg-gray-800 z-20 min-w-[16rem]">Jenis Pelayanan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center">Total Pasien</th>
                                <th colspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300">Tindak Lanjut Pelayanan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Mati di IGD</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">DOA</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-amber-700 dark:text-amber-300">Luka-luka</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">False Emergency</th>
                            </tr>
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Rujukan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Non Rujukan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Dirawat</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Dirujuk</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Pulang</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">P</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50 border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 bg-canvas dark:bg-gray-900">{{ $r['id'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-12 z-10 bg-canvas dark:bg-gray-900">{{ $r['no'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-28 z-10 bg-canvas dark:bg-gray-900">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['rujukan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['non_rujukan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['dirawat']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['dirujuk']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['pulang']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['mati_igd_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['mati_igd_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['doa_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['doa_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300 text-muted-soft">{{ number_format($r['luka_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300 text-muted-soft">{{ number_format($r['luka_p']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['false_emergency']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-[11px] text-ink dark:text-gray-100">
                                <td colspan="3" class="px-3 py-2 border border-hairline dark:border-gray-700 sticky left-0 bg-surface-soft dark:bg-gray-800 z-10">TOTAL</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['rujukan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['non_rujukan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['dirawat']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['dirujuk']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['pulang']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_igd_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_igd_p']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['doa_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['doa_p']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['luka_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['luka_p']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['false_emergency']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800 leading-relaxed">
                    <strong>Mapping jenis pelayanan:</strong> prioritas Psikiatri (poli) &rarr; Kebidanan (POLI OBGIN/KIA)
                    &rarr; Bayi (umur &lt; 1) &rarr; Geriatri (umur &ge; 60) &rarr; Anak (umur 1-17) &rarr; Non bedah lainnya.
                    <strong>Sumber metrik:</strong>
                    <span class="text-muted-soft">Rujukan/Non = </span><code>rsmst_entryugds.rujukan_status</code>;
                    <span class="text-muted-soft">Dirawat = </span><code>rj_status='I'</code>;
                    <span class="text-muted-soft">Dirujuk = </span>JSON <code>perencanaan.tindakLanjut.tindakLanjut='Rujuk'</code>;
                    <span class="text-muted-soft">Mati IGD = </span><code>death_on_igd_status='Y'</code> &times; <code>p.sex</code>;
                    <span class="text-muted-soft">DOA = </span>triase P0 + Mati IGD; <span class="text-muted-soft">False Emergency = </span>triase P4.
                    <strong>Belum diisi:</strong> kategori 1.1-2.3 (butuh ICD diagnosis matching), kolom Luka-luka (butuh trauma flag).
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code> &amp; <code>rj_status &lt;&gt; 'F'</code>.
                </div>
            </div>
        </div>
    </div>
</div>
