<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Rj\RL35Trait;

new class extends Component {
    use RL35Trait;

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
        return $this->computeRL35($this->bulan, $this->tahun);
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
        title="Laporan RL 3.5 — Kunjungan"
        subtitle="Rekap kunjungan rawat jalan + UGD per bulan, sesuai format SIRS Online Kemenkes. &quot;Kunjungan&quot; = setiap visit (bukan distinct pasien). Dalam Kota = pasien dengan kab_id Tulungagung (3504 BPS atau 1 legacy); selainnya Luar Kota." />

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
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 3.5 &mdash; Tabel Rekap Kunjungan
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis kegiatan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="bg-surface-soft dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left sticky left-12 bg-surface-soft dark:bg-gray-800 z-20 min-w-[16rem]">Jenis Kegiatan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-emerald-700 dark:text-emerald-300">Kunjungan Pasien Dalam Kota</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-amber-700 dark:text-amber-300">Kunjungan Pasien Luar Kota</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Total Kunjungan</th>
                            </tr>
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Perempuan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">Perempuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php
                                    $isSpecial = in_array($r['id'], [66, 77, 99, 100], true);
                                    $isTotal   = $r['id'] === 99;
                                    $rowCls = match (true) {
                                        $isTotal      => 'bg-gray-200 dark:bg-gray-700/60 font-bold',
                                        $r['id']===66 => 'bg-blue-50/40 dark:bg-blue-900/10',
                                        $r['id']===77 => 'bg-blue-50/40 dark:bg-blue-900/10 italic',
                                        $r['id']===100=> 'bg-amber-50/40 dark:bg-amber-900/10',
                                        default       => 'hover:bg-surface-soft dark:hover:bg-gray-800/50',
                                    };
                                @endphp
                                <tr class="{{ $rowCls }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 {{ $isSpecial ? 'bg-inherit' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['no'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-12 z-10 {{ $isSpecial ? 'bg-inherit' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['dalam_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['dalam_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['luar_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['luar_p']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold">{{ number_format($r['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800 leading-relaxed">
                    <strong>Source:</strong> RJ poliklinik (rstxn_rjhdrs) + UGD (rstxn_ugdhdrs untuk kategori 24).
                    <strong>Mapping:</strong> 25 poli aktual RSI Madinah ke 34 jenis kegiatan SIRS via
                    <code>POLI_TO_SIRS_RL35</code>. Poli yang tidak ke-map → row 100 "Tidak Ada Data".
                    <strong>Dalam Kota:</strong> <code>kab_id IN ('3504','1')</code> &mdash; '3504' BPS resmi,
                    '1' legacy _TULUNGAGUNG (data lama). <strong>Luar:</strong> selainnya.
                    <strong>Sub-kategori:</strong> Anak Neonatal (id 3), Ibu Hamil (id 5), Stroke (id 30/32) butuh
                    umur/ICD diagnosis matching &mdash; semua admisi default ke "Lainnya" sub-category.
                    <strong>Kategori 9, 10, 11, 13, 15, 20-22, 28-29, 30-32:</strong> tidak ada poli yang map ke sini di RSI Madinah → 0.
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code>, status &lt;&gt; <code>A</code>/<code>F</code>.
                </div>
            </div>
        </div>
    </div>
</div>
