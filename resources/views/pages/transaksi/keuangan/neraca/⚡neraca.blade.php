<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $tanggal = '';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function updatedTanggal(): void { /* recompute */ }

    /**
     * Saldo per akun per tanggal — generic.
     * D-acc: sa_acc_d + Σ(D−K) | K-acc: sa_acc_k + Σ(K−D)
     */
    private function saldoAkun(string $accId, string $dkStatus, string $tanggal): float
    {
        $tahun = (int) substr($tanggal, 0, 4);

        $sa = DB::table('tktxn_saldoawalakuns')
            ->where('acc_id', $accId)
            ->where('sa_year', (string) $tahun)
            ->first();

        $saldoAwalTahun = $dkStatus === 'K'
            ? (float) ($sa->sa_acc_k ?? 0)
            : (float) ($sa->sa_acc_d ?? 0);

        $expr = $dkStatus === 'K'
            ? 'NVL(txn_k,0) - NVL(txn_d,0)'
            : 'NVL(txn_d,0) - NVL(txn_k,0)';

        $arus = (float) DB::table('tkview_accounts')
            ->where('txn_acc', $accId)
            ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                sprintf('%04d-01-01', $tahun), $tanggal,
            ])
            ->sum(DB::raw($expr));

        return $saldoAwalTahun + $arus;
    }

    /**
     * Mutasi (natural sign) dlm rentang — dipakai utk hitung laba tahun berjalan dari section LR.
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
     * Render section N1 (AKTIVA, HUTANG, EKUITAS) — saldo per akun per tanggal cutoff.
     */
    #[Computed]
    public function sections(): array
    {
        if ($this->tanggal === '') return [];

        $sections = DB::table('tkacc_temlabarugineracadtls')
            ->where('temp_id', 'N1')
            ->orderBy('temp_dtl_seq')
            ->get();

        $result = [];
        foreach ($sections as $sec) {
            $accounts = DB::table('tkacc_temaccountes as t')
                ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 't.acc_id')
                ->where('t.temp_dtl', $sec->temp_dtl)
                ->select('t.acc_id', 'a.acc_name', 'a.acc_dk_status')
                ->orderBy('t.acc_id')
                ->get();

            $items = [];
            $total = 0.0;

            foreach ($accounts as $acc) {
                $dk    = (string) ($acc->acc_dk_status ?? 'D');
                $saldo = $this->saldoAkun((string) $acc->acc_id, $dk, $this->tanggal);

                $items[] = [
                    'acc_id'   => (string) $acc->acc_id,
                    'acc_name' => (string) ($acc->acc_name ?? ''),
                    'dk'       => $dk,
                    'saldo'    => $saldo,
                ];
                $total += $saldo;
            }

            $result[] = [
                'temp_dtl' => (string) $sec->temp_dtl,
                'desc'     => (string) $sec->temp_dtl_desc,
                'gra_id'   => (string) ($sec->gra_id ?? ''),
                'accounts' => $items,
                'total'    => $total,
            ];
        }

        return $result;
    }

    private function totalSection(string $tempDtl): float
    {
        foreach ($this->sections as $sec) {
            if ($sec['temp_dtl'] === $tempDtl) return (float) $sec['total'];
        }
        return 0;
    }

    /**
     * Laba Tahun Berjalan = LR YTD = Penjualan(L1.1) − HPP(L1.2) − Biaya(L1.3)
     * dari Jan 1 sd tanggal cutoff.
     */
    #[Computed]
    public function labaTahunBerjalan(): float
    {
        if ($this->tanggal === '') return 0;

        $tahun     = (int) substr($this->tanggal, 0, 4);
        $ytdStart  = sprintf('%04d-01-01', $tahun);
        $ytdEnd    = $this->tanggal;

        $totalPerSection = [];
        foreach (['1', '2', '3'] as $dtl) {
            $accs = DB::table('tkacc_temaccountes as t')
                ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 't.acc_id')
                ->where('t.temp_dtl', $dtl)
                ->select('t.acc_id', 'a.acc_dk_status')->get();

            $sum = 0.0;
            foreach ($accs as $acc) {
                $sum += $this->arusAkun(
                    (string) $acc->acc_id,
                    (string) ($acc->acc_dk_status ?? 'D'),
                    $ytdStart, $ytdEnd
                );
            }
            $totalPerSection[$dtl] = $sum;
        }

        // Penjualan − HPP − Biaya
        return ($totalPerSection['1'] ?? 0) - ($totalPerSection['2'] ?? 0) - ($totalPerSection['3'] ?? 0);
    }

    #[Computed]
    public function totalAktiva(): float
    {
        return $this->totalSection('4');
    }

    #[Computed]
    public function totalHutang(): float
    {
        return $this->totalSection('5');
    }

    /** Ekuitas dari saldo akun + Laba Tahun Berjalan. */
    #[Computed]
    public function totalEkuitas(): float
    {
        return $this->totalSection('6') + $this->labaTahunBerjalan;
    }

    #[Computed]
    public function totalPasiva(): float
    {
        return $this->totalHutang + $this->totalEkuitas;
    }

    #[Computed]
    public function selisih(): float
    {
        return $this->totalAktiva - $this->totalPasiva;
    }

    #[Computed]
    public function isBalanced(): bool
    {
        return abs($this->selisih) < 0.5; // toleransi pembulatan
    }
};
?>

<div>
    <x-page-title
        title="Laporan Neraca Beta · Masa Pengembangan"
        subtitle="Posisi keuangan per tanggal cutoff. Aktiva harus seimbang dengan Hutang + Ekuitas + Laba Tahun Berjalan. Susunan section mengikuti template N1." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-4 pb-6">
            {{-- Notice masa pengembangan --}}
            <div class="p-4 mb-4 border rounded-lg border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                <div class="flex items-start gap-3">
                    <span class="text-xl leading-none">⚠️</span>
                    <div class="flex-1 text-sm text-amber-900 dark:text-amber-100">
                        <p class="font-semibold">Laporan ini masih dalam masa pengembangan — verifikasi manual sebelum dipakai.</p>
                        <ul class="mt-2 ml-5 space-y-0.5 text-xs list-disc">
                            <li><strong>Saldo awal tahun di <span class="font-mono">tktxn_saldoawalakuns</span> belum lengkap</strong> — banyak akun (Modal, Persediaan, Piutang awal, dll) masih nol. Akibatnya Aktiva ≠ Pasiva. Update via menu <em>Saldo Kas → Edit Saldo</em> (admin) atau jurnal modal awal.</li>
                            <li><strong>Laba Tahun Berjalan</strong> diambil dari Laba-Rugi YTD (Penjualan − HPP − Biaya). Jika HPP otomatis belum akurat (stock opname belum rutin), Laba bisa salah → Ekuitas ikut salah.</li>
                            <li><strong>Persediaan barang (akun 1141, dll)</strong> mengikuti pergerakan stok — masih ada potensi selisih akibat human error input penerimaan / pengeluaran (penamaan produk mirip).</li>
                            <li>Validasi <em>"Selisih ⚠"</em> di kanan atas akan flag kalau tidak balance — pakai itu sebagai pemandu mencari root cause data yang masih kurang.</li>
                            <li>Sumber data: <span class="font-mono">tkview_accounts_neraca</span> + <span class="font-mono">tktxn_saldoawalakuns</span>. Mapping section: template <span class="font-mono">N1</span>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full sm:w-52">
                        <x-input-label for="tanggal" value="Tanggal Cutoff" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                        <x-text-input id="tanggal" type="date" wire:model.live="tanggal" class="block w-full" />
                    </div>

                    @if ($tanggal !== '')
                        <div class="grid grid-cols-3 gap-3 text-right">
                            <div class="px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                                <div class="text-[10px] tracking-wider text-blue-700 uppercase dark:text-blue-300">Total Aktiva</div>
                                <div class="font-mono text-sm font-bold text-blue-800 dark:text-blue-200">
                                    {{ number_format($this->totalAktiva, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-3 py-2 border rounded-lg bg-purple-50 border-purple-200 dark:bg-purple-900/20 dark:border-purple-800">
                                <div class="text-[10px] tracking-wider text-purple-700 uppercase dark:text-purple-300">Total Pasiva</div>
                                <div class="font-mono text-sm font-bold text-purple-800 dark:text-purple-200">
                                    {{ number_format($this->totalPasiva, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-3 py-2 border rounded-lg {{ $this->isBalanced ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : 'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800' }}">
                                <div class="text-[10px] tracking-wider uppercase {{ $this->isBalanced ? 'text-emerald-700 dark:text-emerald-300' : 'text-error dark:text-rose-300' }}">
                                    {{ $this->isBalanced ? 'Balanced' : 'Selisih' }}
                                </div>
                                <div class="font-mono text-sm font-bold {{ $this->isBalanced ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200' }}">
                                    @if ($this->isBalanced)
                                        ✓ {{ number_format(0, 0) }}
                                    @else
                                        {{ number_format($this->selisih, 0, ',', '.') }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">KODE</th>
                                <th class="px-3 py-2 font-semibold">URAIAN</th>
                                <th class="px-3 py-2 font-semibold w-48 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @if ($tanggal === '')
                                <tr><td colspan="3" class="px-4 py-12 text-center text-muted dark:text-gray-400">
                                    Atur tanggal cutoff untuk menampilkan neraca.
                                </td></tr>
                            @else
                                @foreach ($this->sections as $sec)
                                    @php
                                        $isAktiva = $sec['temp_dtl'] === '4';
                                        $isHutang = $sec['temp_dtl'] === '5';
                                        $isEkuitas = $sec['temp_dtl'] === '6';
                                        $secColor = $isAktiva ? 'bg-blue-100 dark:bg-blue-900/30'
                                            : ($isHutang ? 'bg-amber-100 dark:bg-amber-900/30'
                                            : 'bg-purple-100 dark:bg-purple-900/30');
                                    @endphp
                                    <tr wire:key="neraca-sec-{{ $sec['temp_dtl'] ?? $loop->index }}" class="{{ $secColor }}">
                                        <td colspan="2" class="px-3 py-2 text-xs font-bold tracking-wider uppercase">
                                            {{ $sec['desc'] }}
                                        </td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                    @forelse ($sec['accounts'] as $acc)
                                        <tr wire:key="neraca-acc-{{ $sec['temp_dtl'] ?? '' }}-{{ $acc['acc_id'] ?? $loop->index }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-3 py-1.5 font-mono text-xs">{{ $acc['acc_id'] }}</td>
                                            <td class="px-3 py-1.5 text-xs">
                                                {{ $acc['acc_name'] ?: '—' }}
                                                @if ($acc['dk'] === 'D')
                                                    <span class="px-1 ml-1 text-[9px] rounded bg-blue-100 text-blue-700">D</span>
                                                @elseif ($acc['dk'] === 'K')
                                                    <span class="px-1 ml-1 text-[9px] rounded bg-purple-100 text-purple-700">K</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-1.5 font-mono text-sm text-right {{ $acc['saldo'] < 0 ? 'text-red-600' : '' }}">
                                                @if (abs($acc['saldo']) > 0.001)
                                                    {{ number_format($acc['saldo'], 0, ',', '.') }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-3 py-2 text-xs italic text-muted-soft">
                                                (Tidak ada akun di section ini)
                                            </td>
                                        </tr>
                                    @endforelse
                                    <tr class="font-semibold bg-surface-soft dark:bg-gray-800/40">
                                        <td colspan="2" class="px-3 py-1.5 text-xs uppercase">
                                            Subtotal {{ $sec['desc'] }}
                                        </td>
                                        <td class="px-3 py-1.5 font-mono text-sm text-right">
                                            {{ number_format($sec['total'], 0, ',', '.') }}
                                        </td>
                                    </tr>

                                    @if ($isEkuitas)
                                        <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-3 py-1.5 text-xs italic text-muted"></td>
                                            <td class="px-3 py-1.5 text-xs italic text-muted dark:text-gray-300">
                                                Laba Tahun Berjalan
                                                <span class="ml-1 text-[10px] text-muted-soft">(YTD dari Laba Rugi)</span>
                                            </td>
                                            <td class="px-3 py-1.5 font-mono text-sm text-right {{ $this->labaTahunBerjalan < 0 ? 'text-red-600' : '' }}">
                                                {{ number_format($this->labaTahunBerjalan, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                        <tr class="font-bold bg-purple-50 dark:bg-purple-900/20">
                                            <td colspan="2" class="px-3 py-1.5 text-sm uppercase">
                                                Total Ekuitas (incl. Laba Tahun Berjalan)
                                            </td>
                                            <td class="px-3 py-1.5 font-mono text-sm text-right text-purple-800 dark:text-purple-200">
                                                {{ number_format($this->totalEkuitas, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach

                                {{-- Grand totals --}}
                                <tr><td colspan="3" class="h-2"></td></tr>
                                <tr class="font-bold bg-blue-100 dark:bg-blue-900/30">
                                    <td colspan="2" class="px-3 py-2 text-sm uppercase">
                                        Total Aktiva
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right text-blue-800 dark:text-blue-200">
                                        {{ number_format($this->totalAktiva, 0, ',', '.') }}
                                    </td>
                                </tr>
                                <tr class="font-bold bg-purple-100 dark:bg-purple-900/30">
                                    <td colspan="2" class="px-3 py-2 text-sm uppercase">
                                        Total Pasiva (Hutang + Ekuitas)
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right text-purple-800 dark:text-purple-200">
                                        {{ number_format($this->totalPasiva, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @unless ($this->isBalanced)
                                    <tr class="font-bold bg-rose-100 dark:bg-rose-900/30">
                                        <td colspan="2" class="px-3 py-2 text-sm uppercase">
                                            Selisih (Aktiva − Pasiva)
                                            <span class="ml-2 text-[10px] font-normal text-error dark:text-rose-300">
                                                ⚠ Neraca tidak balance — cek jurnal yg belum berimbang.
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-base text-right text-rose-800 dark:text-rose-200">
                                            {{ number_format($this->selisih, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endunless
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
