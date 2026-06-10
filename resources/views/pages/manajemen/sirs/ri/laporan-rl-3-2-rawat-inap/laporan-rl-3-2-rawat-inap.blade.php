<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ri\RL32Trait;

new class extends Component {
    use RL32Trait;

    public int $bulan;
    public int $tahun;

    public function mount(): void
    {
        $now         = Carbon::now();
        $this->bulan = $now->month;
        $this->tahun = $now->year;
    }

    public function setBulan(int $b): void
    {
        if ($b >= 1 && $b <= 12) {
            $this->bulan = $b;
        }
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL32($this->bulan, $this->tahun);
    }

    #[Computed]
    public function totalRow(): array
    {
        $rows = $this->rows;
        $sum  = fn(string $k) => array_sum(array_column($rows, $k));

        return [
            'pasien_awal_bulan'     => $sum('pasien_awal_bulan'),
            'pasien_masuk'          => $sum('pasien_masuk'),
            'pasien_pindahan'       => $sum('pasien_pindahan'),
            'pasien_dipindahkan'    => $sum('pasien_dipindahkan'),
            'pasien_keluar_hidup'   => $sum('pasien_keluar_hidup'),
            'pria_mati_lt48'        => $sum('pria_mati_lt48'),
            'pria_mati_ge48'        => $sum('pria_mati_ge48'),
            'wanita_mati_lt48'      => $sum('wanita_mati_lt48'),
            'wanita_mati_ge48'      => $sum('wanita_mati_ge48'),
            'jumlah_lama_dirawat'   => $sum('jumlah_lama_dirawat'),
            'pasien_akhir_bulan'    => $sum('pasien_akhir_bulan'),
            'jumlah_hari_perawatan' => $sum('jumlah_hari_perawatan'),
            'kelas_vvip'            => $sum('kelas_vvip'),
            'kelas_vip'             => $sum('kelas_vip'),
            'kelas_1'               => $sum('kelas_1'),
            'kelas_2'               => $sum('kelas_2'),
            'kelas_3'               => $sum('kelas_3'),
            'kelas_khusus'          => $sum('kelas_khusus'),
            'alokasi_tt_awal'       => $sum('alokasi_tt_awal'),
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
        title="Laporan RL 3.2 — Rawat Inap"
        subtitle="Rekapitulasi rawat inap per jenis pelayanan per bulan, sesuai format SIRS Online Kemenkes. Mapping otomatis: DPJP Utama (JSON levelingDokter) → poli → specialty (keyword match poli_desc). Poli yang tidak match jatuh ke baris &quot;Tidak Ada Data&quot;." />

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
                        RL 3.2 &mdash; Tabel Rekapitulasi Rawat Inap
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis pelayanan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="bg-surface-soft dark:bg-gray-800 text-body dark:text-gray-200">
                            {{-- Header row 1: groups --}}
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left sticky left-12 bg-surface-soft dark:bg-gray-800 z-20 min-w-[14rem]">Jenis Pelayanan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Awal Bulan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Masuk</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Pindahan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Dipindahkan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Keluar Hidup</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Pria Keluar Mati</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Wanita Keluar Mati</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Jumlah Lama Dirawat</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Akhir Bulan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Jumlah Hari Perawatan</th>
                                <th colspan="6" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-purple-700 dark:text-purple-300">Rincian Hari Perawatan Per Kelas</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Jumlah Alokasi TT Awal Bulan</th>
                            </tr>
                            {{-- Header row 2: subcolumns --}}
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&lt; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&ge; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&lt; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&ge; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">VVIP</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">VIP</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">1</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">2</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">3</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Khusus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php
                                    $isCatchAll = $r['id'] === 100;
                                    $rowClass = $isCatchAll
                                        ? 'bg-amber-50/70 dark:bg-amber-900/20 font-medium'
                                        : 'hover:bg-surface-soft dark:hover:bg-gray-800/50';
                                @endphp
                                <tr class="{{ $rowClass }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 {{ $isCatchAll ? 'bg-amber-50/70 dark:bg-amber-900/20' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['id'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-12 z-10 {{ $isCatchAll ? 'bg-amber-50/70 dark:bg-amber-900/20' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_awal_bulan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_masuk']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-muted-soft">{{ number_format($r['pasien_pindahan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-muted-soft">{{ number_format($r['pasien_dipindahkan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['pasien_keluar_hidup']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['pria_mati_lt48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['pria_mati_ge48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['wanita_mati_lt48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['wanita_mati_ge48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['jumlah_lama_dirawat']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_akhir_bulan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums font-semibold">{{ number_format($r['jumlah_hari_perawatan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_vvip']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_vip']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_1']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_2']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_3']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_khusus']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['alokasi_tt_awal']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-[11px] text-ink dark:text-gray-100">
                                <td colspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 sticky left-0 bg-surface-soft dark:bg-gray-800 z-10">TOTAL</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_awal_bulan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_masuk']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['pasien_pindahan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['pasien_dipindahkan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['pasien_keluar_hidup']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['pria_mati_lt48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['pria_mati_ge48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['wanita_mati_lt48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['wanita_mati_ge48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['jumlah_lama_dirawat']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_akhir_bulan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['jumlah_hari_perawatan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_vvip']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_vip']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_1']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_2']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_3']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_khusus']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['alokasi_tt_awal']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800 leading-relaxed">
                    <strong>Mapping specialty:</strong> DPJP Utama dari JSON
                    <code>pengkajianAwalPasienRawatInap.levelingDokter</code> (entry dengan <code>levelDokter='Utama'</code>)
                    &rarr; <code>rsmst_doctors.poli_id</code> &rarr; eksplisit map 25 poli RSI Madinah ke 36 jenis pelayanan SIRS
                    (lihat <code>RL32Trait::POLI_TO_SIRS</code>); fallback keyword match <code>poli_desc</code> untuk poli baru.
                    Fallback drId ke <code>rstxn_rihdrs.dr_id</code> kalau leveling JSON kosong.
                    Admisi tanpa DPJP atau poli yang tidak match &rarr; baris <span class="font-semibold">"Tidak Ada Data"</span>.
                    Kolom <span class="text-muted-soft">Pasien Pindahan / Dipindahkan</span> = 0 (butuh tracking transfer antar specialty, ditangguhkan).
                    <span class="text-muted-soft">Alokasi TT total RS dipin di baris "Tidak Ada Data" (mapping bangsal&rarr;specialty butuh DDL).</span>
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code> &amp; <code>ri_status &lt;&gt; 'F'</code>. Meninggal: SNOMED 419099009.
                </div>
            </div>
        </div>
    </div>
</div>
