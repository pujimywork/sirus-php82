<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\RL32Trait;

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
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Laporan RL 3.2 &mdash; Rawat Inap
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rekapitulasi rawat inap per <span class="font-medium">jenis pelayanan</span> per bulan,
                    sesuai format SIRS Online Kemenkes.
                    <span class="text-gray-400">Mapping otomatis: DPJP Utama (JSON <code>levelingDokter</code>) &rarr; poli &rarr; specialty (keyword match poli_desc).
                    Poli yang tidak match jatuh ke baris "Tidak Ada Data".</span>
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

            {{-- PROFILE FASYANKES --}}
            <div class="bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                x-data="{ open: false }">
                <button type="button" @click="open = !open"
                    class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                           hover:bg-gray-50 dark:hover:bg-gray-800
                           focus:outline-none focus:ring-1 focus:ring-gray-300">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Profile Fasyankes
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Rumah Sakit Islam Madinah &middot; Tulungagung, Jawa Timur
                        </div>
                    </div>
                    <span class="hidden sm:inline text-xs text-gray-500 dark:text-gray-400">
                        <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 shrink-0"
                        :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-cloak x-show="open"
                    class="px-4 pb-4 border-t border-gray-200 dark:border-gray-700"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div>
                            <div class="text-[10px] uppercase font-semibold text-gray-500 dark:text-gray-400">Nama</div>
                            <div class="font-medium text-gray-800 dark:text-gray-100">Rumah Sakit Islam Madinah</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-semibold text-gray-500 dark:text-gray-400">Alamat</div>
                            <div class="font-medium text-gray-800 dark:text-gray-100">
                                Jl. Jati Wayang, Lk. 2, Ngunut, Kec. Ngunut
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-semibold text-gray-500 dark:text-gray-400">Provinsi</div>
                            <div class="font-medium text-gray-800 dark:text-gray-100">Jawa Timur</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-semibold text-gray-500 dark:text-gray-400">Kab/Kota</div>
                            <div class="font-medium text-gray-800 dark:text-gray-100">Kabupaten Tulungagung</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FILTER PERIODE --}}
            <div class="bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900 p-4">
                <div class="text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400 mb-2">
                    Periode Laporan
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <x-input-label value="Bulan" />
                        <select wire:model.live="bulan"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-green focus:ring-brand-green text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ $this->bulanLabel($m) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Tahun" />
                        <x-text-input type="number" wire:model.live.debounce.500ms="tahun"
                            min="2000" max="2099" maxlength="4" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-end">
                        <div class="text-xs text-gray-500 dark:text-gray-400 leading-snug">
                            Periode aktif:
                            <span class="font-semibold text-gray-700 dark:text-gray-200">
                                {{ $this->bulanLabel($bulan) }} {{ $tahun }}
                            </span>
                            <br>
                            <span class="text-[10px] text-gray-400">Filter pakai entry_date / exit_date kalender bulan tsb.</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totalRow; @endphp
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        RL 3.2 &mdash; Tabel Rekapitulasi Rawat Inap
                        <span class="ml-2 font-normal text-xs text-gray-500">
                            ({{ count($this->rows) }} jenis pelayanan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            {{-- Header row 1: groups --}}
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center sticky left-0 bg-gray-100 dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-3 py-2 border border-gray-200 dark:border-gray-700 text-left sticky left-12 bg-gray-100 dark:bg-gray-800 z-20 min-w-[14rem]">Jenis Pelayanan</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Pasien Awal Bulan</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Pasien Masuk</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Pasien Pindahan</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Pasien Dipindahkan</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Pasien Keluar Hidup</th>
                                <th colspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Pria Keluar Mati</th>
                                <th colspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Wanita Keluar Mati</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Jumlah Lama Dirawat</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Pasien Akhir Bulan</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Jumlah Hari Perawatan</th>
                                <th colspan="6" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-center text-purple-700 dark:text-purple-300">Rincian Hari Perawatan Per Kelas</th>
                                <th rowspan="2" class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right">Jumlah Alokasi TT Awal Bulan</th>
                            </tr>
                            {{-- Header row 2: subcolumns --}}
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&lt; 48 jam</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&ge; 48 jam</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&lt; 48 jam</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&ge; 48 jam</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">VVIP</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">VIP</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">1</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">2</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">3</th>
                                <th class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Khusus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php
                                    $isCatchAll = $r['id'] === 100;
                                    $rowClass = $isCatchAll
                                        ? 'bg-amber-50/70 dark:bg-amber-900/20 font-medium'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/50';
                                @endphp
                                <tr class="{{ $rowClass }} border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-center font-mono text-gray-500 sticky left-0 z-10 {{ $isCatchAll ? 'bg-amber-50/70 dark:bg-amber-900/20' : 'bg-white dark:bg-gray-900' }}">{{ $r['id'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100 sticky left-12 z-10 {{ $isCatchAll ? 'bg-amber-50/70 dark:bg-amber-900/20' : 'bg-white dark:bg-gray-900' }}">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_awal_bulan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_masuk']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-gray-400">{{ number_format($r['pasien_pindahan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-gray-400">{{ number_format($r['pasien_dipindahkan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['pasien_keluar_hidup']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['pria_mati_lt48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['pria_mati_ge48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['wanita_mati_lt48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['wanita_mati_ge48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($r['jumlah_lama_dirawat']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_akhir_bulan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums font-semibold">{{ number_format($r['jumlah_hari_perawatan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_vvip']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_vip']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_1']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_2']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_3']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_khusus']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['alokasi_tt_awal']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-[11px] text-gray-800 dark:text-gray-100">
                                <td colspan="2" class="px-3 py-2 border border-gray-200 dark:border-gray-700 sticky left-0 bg-gray-100 dark:bg-gray-800 z-10">TOTAL</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_awal_bulan']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_masuk']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-gray-500">{{ number_format($tot['pasien_pindahan']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-gray-500">{{ number_format($tot['pasien_dipindahkan']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['pasien_keluar_hidup']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['pria_mati_lt48']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['pria_mati_ge48']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['wanita_mati_lt48']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['wanita_mati_ge48']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['jumlah_lama_dirawat']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_akhir_bulan']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['jumlah_hari_perawatan']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_vvip']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_vip']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_1']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_2']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_3']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_khusus']) }}</td>
                                <td class="px-2 py-2 border border-gray-200 dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['alokasi_tt_awal']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-4 py-2 text-[10px] text-gray-500 dark:text-gray-500 border-t border-gray-100 dark:border-gray-800 leading-relaxed">
                    <strong>Mapping specialty:</strong> DPJP Utama dari JSON
                    <code>pengkajianAwalPasienRawatInap.levelingDokter</code> (entry dengan <code>levelDokter='Utama'</code>)
                    &rarr; <code>rsmst_doctors.poli_id</code> &rarr; eksplisit map 25 poli RSI Madinah ke 36 jenis pelayanan SIRS
                    (lihat <code>RL32Trait::POLI_TO_SIRS</code>); fallback keyword match <code>poli_desc</code> untuk poli baru.
                    Fallback drId ke <code>rstxn_rihdrs.dr_id</code> kalau leveling JSON kosong.
                    Admisi tanpa DPJP atau poli yang tidak match &rarr; baris <span class="font-semibold">"Tidak Ada Data"</span>.
                    Kolom <span class="text-gray-400">Pasien Pindahan / Dipindahkan</span> = 0 (butuh tracking transfer antar specialty, ditangguhkan).
                    <span class="text-gray-400">Alokasi TT total RS dipin di baris "Tidak Ada Data" (mapping bangsal&rarr;specialty butuh DDL).</span>
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code> &amp; <code>ri_status &lt;&gt; 'F'</code>. Meninggal: SNOMED 419099009.
                </div>
            </div>
        </div>
    </div>
</div>
