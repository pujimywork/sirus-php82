<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** Format internal: 'YYYY-MM' */
    public string $periode      = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    /** Override HPP manual — kalau enabled, bypass hitungan auto dari section 2 */
    public bool   $hppManualEnabled = false;
    public string $hppManualBulan   = '';
    public string $hppManualYtd     = '';

    public function mount(): void
    {
        $this->setPeriode(now()->format('Y-m'));
    }

    public function updatedPeriodeInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $value, $m)) {
            $this->periode = '';
            return;
        }
        [$_, $bulan, $tahun] = $m;
        $this->periode = "{$tahun}-{$bulan}";
    }

    private function setPeriode(string $ym): void
    {
        $this->periode      = $ym;
        $this->periodeInput = \Carbon\Carbon::parse("{$ym}-01")->format('m/Y');
    }

    public function prevMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(\Carbon\Carbon::parse("{$this->periode}-01")->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(\Carbon\Carbon::parse("{$this->periode}-01")->addMonth()->format('Y-m'));
    }

    #[Computed]
    public function bulanStart(): string
    {
        return $this->periode === '' ? '' : "{$this->periode}-01";
    }

    #[Computed]
    public function bulanEnd(): string
    {
        if ($this->periode === '') return '';
        return \Carbon\Carbon::parse("{$this->periode}-01")->endOfMonth()->toDateString();
    }

    #[Computed]
    public function ytdStart(): string
    {
        if ($this->periode === '') return '';
        return substr($this->periode, 0, 4) . '-01-01';
    }

    /**
     * Hitung mutasi (natural sign per account) dlm rentang tanggal.
     * D-acc → return D−K (positif kalau didebit dominan)
     * K-acc → return K−D (positif kalau dikredit dominan)
     */
    private function arusAkun(string $accId, string $dkStatus, string $dari, string $sampai): float
    {
        $expr = $dkStatus === 'K'
            ? 'NVL(txn_k,0) - NVL(txn_d,0)'
            : 'NVL(txn_d,0) - NVL(txn_k,0)';

        return (float) DB::table('tkview_accounts')
            ->where('txn_acc', $accId)
            ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [$dari, $sampai])
            ->sum(DB::raw($expr));
    }

    /**
     * Susun report L1: tiap section + akun-akun di dalamnya, dgn nilai bulan & YTD.
     * Format:
     *   [
     *     ['temp_dtl' => 1, 'desc' => 'PENJUALAN', 'gra_id' => '4',
     *      'accounts' => [['acc_id'=>..., 'acc_name'=>..., 'dk'=>'K', 'bulan'=>..., 'ytd'=>...]],
     *      'total_bulan' => ..., 'total_ytd' => ...,
     *     ], ...
     *   ]
     */
    #[Computed]
    public function sections(): array
    {
        if ($this->periode === '') return [];

        $sections = DB::table('tkacc_temlabarugineracadtls')
            ->where('temp_id', 'L1')
            ->orderBy('temp_dtl_seq')
            ->get()
            ->map(fn($s) => [
                'temp_dtl'      => (string) $s->temp_dtl,
                'temp_dtl_seq'  => (int) ($s->temp_dtl_seq ?? 0),
                'desc'          => (string) $s->temp_dtl_desc,
                'gra_id'        => (string) ($s->gra_id ?? ''),
            ])->all();

        $result = [];
        foreach ($sections as $sec) {
            // Ambil akun-akun di section
            $accounts = DB::table('tkacc_temaccountes as t')
                ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 't.acc_id')
                ->where('t.temp_dtl', $sec['temp_dtl'])
                ->select('t.acc_id', 'a.acc_name', 'a.acc_dk_status')
                ->orderBy('t.acc_id')
                ->get();

            $items = [];
            $totalBulan = 0.0;
            $totalYtd   = 0.0;

            foreach ($accounts as $acc) {
                $dk    = (string) ($acc->acc_dk_status ?? 'D');
                $bulan = $this->arusAkun($acc->acc_id, $dk, $this->bulanStart, $this->bulanEnd);
                $ytd   = $this->arusAkun($acc->acc_id, $dk, $this->ytdStart,   $this->bulanEnd);

                $items[] = [
                    'acc_id'    => (string) $acc->acc_id,
                    'acc_name'  => (string) ($acc->acc_name ?? ''),
                    'dk'        => $dk,
                    'bulan'     => $bulan,
                    'ytd'       => $ytd,
                ];

                $totalBulan += $bulan;
                $totalYtd   += $ytd;
            }

            $sec['accounts']    = $items;
            $sec['total_bulan'] = $totalBulan;
            $sec['total_ytd']   = $totalYtd;
            $result[] = $sec;
        }

        return $result;
    }

    /**
     * Helper: ambil total section by temp_dtl id.
     */
    private function totalSection(string $tempDtl, string $kolom): float
    {
        foreach ($this->sections as $sec) {
            if ($sec['temp_dtl'] === $tempDtl) return (float) $sec[$kolom];
        }
        return 0;
    }

    #[Computed]
    public function hppBulan(): float
    {
        return $this->hppManualEnabled
            ? (float) ($this->hppManualBulan === '' ? 0 : $this->hppManualBulan)
            : $this->totalSection('2', 'total_bulan');
    }

    #[Computed]
    public function hppYtd(): float
    {
        return $this->hppManualEnabled
            ? (float) ($this->hppManualYtd === '' ? 0 : $this->hppManualYtd)
            : $this->totalSection('2', 'total_ytd');
    }

    #[Computed]
    public function labaKotorBulan(): float
    {
        // Penjualan (1) - HPP (2 atau manual)
        return $this->totalSection('1', 'total_bulan') - $this->hppBulan;
    }

    #[Computed]
    public function labaKotorYtd(): float
    {
        return $this->totalSection('1', 'total_ytd') - $this->hppYtd;
    }

    #[Computed]
    public function labaBersihBulan(): float
    {
        // Laba Kotor - Biaya (3)
        return $this->labaKotorBulan - $this->totalSection('3', 'total_bulan');
    }

    #[Computed]
    public function labaBersihYtd(): float
    {
        return $this->labaKotorYtd - $this->totalSection('3', 'total_ytd');
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Laporan Laba Rugi
                <span class="px-2 py-0.5 ml-2 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 align-middle">
                    Beta · Masa Pengembangan
                </span>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Penjualan dikurangi HPP &amp; Biaya per bulan terpilih, plus akumulasi tahun berjalan (YTD).
                Susunan section mengikuti template <span class="font-mono">L1</span>.
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6">
            {{-- Notice masa pengembangan --}}
            <div class="p-4 mb-4 border rounded-lg border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                <div class="flex items-start gap-3">
                    <span class="text-xl leading-none">⚠️</span>
                    <div class="flex-1 text-sm text-amber-900 dark:text-amber-100">
                        <p class="font-semibold">Laporan ini masih dalam masa pengembangan — verifikasi manual sebelum dipakai.</p>
                        <ul class="mt-2 ml-5 space-y-0.5 text-xs list-disc">
                            <li><strong>HPP otomatis dari pergerakan stok belum reliabel</strong> — stock opname belum rutin & ada potensi selisih input barang masuk/keluar (penamaan produk mirip). Pakai <em>toggle "Override HPP Manual"</em> di bawah kalau tahu HPP fisik yang benar.</li>
                            <li><strong>Saldo awal akun 2026</strong> di <span class="font-mono">tktxn_saldoawalakuns</span> belum lengkap — belum mempengaruhi LR (LR cuma pakai arus periode), tapi mempengaruhi Neraca.</li>
                            <li>Penjualan &amp; Biaya non-stok (gaji, listrik, dll) sudah benar; yang perlu kehati-hatian adalah HPP dan akun yang berasal dari movement persediaan.</li>
                            <li>Sumber data: <span class="font-mono">tkview_accounts_labarugi</span>. Section &amp; mapping akun: template <span class="font-mono">L1</span> di <span class="font-mono">tkacc_temaccountes</span>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                        <div class="flex items-stretch gap-1">
                            <x-secondary-button type="button" wire:click="prevMonth" class="px-3" title="Bulan sebelumnya">◀</x-secondary-button>
                            <x-text-input id="periodeInput" type="text"
                                wire:model.live.debounce.500ms="periodeInput"
                                placeholder="01/2026" maxlength="7"
                                class="w-28 text-center font-mono" />
                            <x-secondary-button type="button" wire:click="nextMonth" class="px-3" title="Bulan berikutnya">▶</x-secondary-button>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                            @if ($periode !== '')
                                Bulan: {{ \Carbon\Carbon::parse($this->bulanStart)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($this->bulanEnd)->format('d/m/Y') }}
                                · YTD: {{ \Carbon\Carbon::parse($this->ytdStart)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($this->bulanEnd)->format('d/m/Y') }}
                            @else
                                <span class="text-rose-600">Format: mm/yyyy</span>
                            @endif
                        </p>
                    </div>

                    @if ($periode !== '')
                        <div class="grid grid-cols-2 gap-3 text-right">
                            <div class="px-4 py-2 border rounded-lg bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                                <div class="text-[10px] tracking-wider text-gray-500 uppercase">Laba Bersih · Bulan</div>
                                <div class="font-mono text-lg font-bold {{ $this->labaBersihBulan < 0 ? 'text-red-600' : 'text-emerald-700 dark:text-emerald-300' }}">
                                    Rp {{ number_format($this->labaBersihBulan, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-4 py-2 border rounded-lg bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                                <div class="text-[10px] tracking-wider text-emerald-700 uppercase dark:text-emerald-300">Laba Bersih · YTD</div>
                                <div class="font-mono text-lg font-bold {{ $this->labaBersihYtd < 0 ? 'text-red-600' : 'text-emerald-800 dark:text-emerald-200' }}">
                                    Rp {{ number_format($this->labaBersihYtd, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Override HPP Manual --}}
                @if ($periode !== '')
                    <div class="mt-3 pt-3 border-t border-dashed border-gray-300 dark:border-gray-600">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div class="flex items-start gap-3">
                                <x-toggle wire:model.live="hppManualEnabled" trueValue="1" falseValue="0" />
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Override HPP Manual
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 max-w-md">
                                        Aktifkan kalau HPP otomatis dari stok belum reliabel
                                        (stock-opname belum rutin / ada selisih barang masuk-keluar).
                                        Nilai di bawah akan menggantikan total section HPP.
                                    </p>
                                </div>
                            </div>

                            @if ($hppManualEnabled)
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="hppManualBulan" value="HPP Bulan Ini" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                                        <x-text-input id="hppManualBulan" type="number" step="0.01" min="0"
                                            wire:model.live.debounce.500ms="hppManualBulan"
                                            placeholder="0"
                                            class="w-40 text-right font-mono" />
                                    </div>
                                    <div>
                                        <x-input-label for="hppManualYtd" value="HPP YTD" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                                        <x-text-input id="hppManualYtd" type="number" step="0.01" min="0"
                                            wire:model.live.debounce.500ms="hppManualYtd"
                                            placeholder="0"
                                            class="w-40 text-right font-mono" />
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-340px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">KODE</th>
                                <th class="px-3 py-2 font-semibold">URAIAN</th>
                                <th class="px-3 py-2 font-semibold w-40 text-right">BULAN INI</th>
                                <th class="px-3 py-2 font-semibold w-40 text-right">YTD</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @if ($periode === '')
                                <tr><td colspan="4" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    Atur periode untuk menampilkan laporan.
                                </td></tr>
                            @else
                                @foreach ($this->sections as $sec)
                                    @php
                                        $isHpp = $sec['temp_dtl'] === '2';
                                        $hppOverridden = $isHpp && $hppManualEnabled;
                                    @endphp
                                    <tr wire:key="laba-rugi-sec-{{ $sec['temp_dtl'] ?? $loop->index }}" class="bg-gray-100 dark:bg-gray-800">
                                        <td colspan="2" class="px-3 py-2 text-xs font-bold tracking-wider uppercase">
                                            {{ $sec['desc'] }}
                                            @if ($hppOverridden)
                                                <span class="ml-2 px-1.5 text-[9px] rounded bg-amber-200 text-amber-800 normal-case">manual override</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                    @if ($hppOverridden)
                                        <tr class="italic bg-amber-50/50 dark:bg-amber-900/10">
                                            <td colspan="4" class="px-3 py-1.5 text-[11px] text-amber-700 dark:text-amber-300">
                                                ⚠ Akun-akun di section ini di-bypass; pakai nilai HPP manual yang di-input di atas.
                                            </td>
                                        </tr>
                                    @else
                                        @forelse ($sec['accounts'] as $acc)
                                            <tr wire:key="laba-rugi-acc-{{ $sec['temp_dtl'] ?? '' }}-{{ $acc['acc_id'] ?? $loop->index }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                                <td class="px-3 py-1.5 font-mono text-xs">{{ $acc['acc_id'] }}</td>
                                                <td class="px-3 py-1.5 text-xs">
                                                    {{ $acc['acc_name'] ?: '—' }}
                                                    @if ($acc['dk'] === 'D')
                                                        <span class="px-1 ml-1 text-[9px] rounded bg-blue-100 text-blue-700">D</span>
                                                    @elseif ($acc['dk'] === 'K')
                                                        <span class="px-1 ml-1 text-[9px] rounded bg-purple-100 text-purple-700">K</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-1.5 font-mono text-sm text-right">
                                                    @if (abs($acc['bulan']) > 0.001)
                                                        {{ number_format($acc['bulan'], 0, ',', '.') }}
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-1.5 font-mono text-sm text-right">
                                                    @if (abs($acc['ytd']) > 0.001)
                                                        {{ number_format($acc['ytd'], 0, ',', '.') }}
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-3 py-2 text-xs italic text-gray-400">
                                                    (Tidak ada akun di section ini)
                                                </td>
                                            </tr>
                                        @endforelse
                                    @endif
                                    <tr class="font-semibold {{ $hppOverridden ? 'bg-amber-100 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-800/40' }}">
                                        <td colspan="2" class="px-3 py-1.5 text-xs uppercase">
                                            Subtotal {{ $sec['desc'] }}
                                            @if ($hppOverridden)
                                                <span class="text-[10px] text-amber-700 dark:text-amber-300 normal-case ml-1">(manual)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right {{ $hppOverridden ? 'text-amber-800 dark:text-amber-200' : '' }}">
                                            {{ number_format($isHpp ? $this->hppBulan : $sec['total_bulan'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right {{ $hppOverridden ? 'text-amber-800 dark:text-amber-200' : '' }}">
                                            {{ number_format($isHpp ? $this->hppYtd : $sec['total_ytd'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Laba Kotor (sec1 - sec2) --}}
                                <tr class="font-bold bg-blue-50 dark:bg-blue-900/20">
                                    <td colspan="2" class="px-3 py-2 text-sm uppercase">
                                        Laba Kotor (Penjualan − HPP)
                                    </td>
                                    <td class="px-3 py-2 font-mono text-sm text-right text-blue-800 dark:text-blue-200">
                                        {{ number_format($this->labaKotorBulan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-sm text-right text-blue-800 dark:text-blue-200">
                                        {{ number_format($this->labaKotorYtd, 0, ',', '.') }}
                                    </td>
                                </tr>

                                {{-- Laba Bersih --}}
                                <tr class="font-bold {{ $this->labaBersihBulan < 0 ? 'bg-rose-50 dark:bg-rose-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20' }}">
                                    <td colspan="2" class="px-3 py-2 text-sm uppercase">
                                        Laba Bersih (Laba Kotor − Biaya)
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->labaBersihBulan < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                        {{ number_format($this->labaBersihBulan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->labaBersihYtd < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                        {{ number_format($this->labaBersihYtd, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
