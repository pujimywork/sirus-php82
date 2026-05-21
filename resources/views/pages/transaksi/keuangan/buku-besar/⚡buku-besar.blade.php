<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $accId        = '';
    public string $accDesc      = '';
    public string $accDkStatus  = '';

    /** Format internal: 'YYYY-MM' */
    public string $periode      = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    public function mount(): void
    {
        $this->setPeriode(now()->format('Y-m'));
    }

    #[On('lov.selected.buku-besar-acc')]
    public function onAkunSelected(string $target, ?array $payload): void
    {
        $this->accId       = (string) ($payload['acc_id'] ?? '');
        $this->accDesc     = (string) ($payload['acc_name'] ?? '');
        $this->accDkStatus = (string) ($payload['acc_dk_status'] ?? '');
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
        $this->periode = $ym;
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
    public function dariTanggal(): string
    {
        return $this->periode === '' ? '' : "{$this->periode}-01";
    }

    #[Computed]
    public function sampaiTanggal(): string
    {
        if ($this->periode === '') return '';
        return \Carbon\Carbon::parse("{$this->periode}-01")->endOfMonth()->toDateString();
    }

    /**
     * Saldo akun per tanggal — generic untuk D-acc maupun K-acc.
     * Filter "rows about this account": txn_acc = acc_id.
     * D-acc: saldo bertambah saat didebit  → mutasi = D − K
     * K-acc: saldo bertambah saat dikredit → mutasi = K − D
     */
    private function hitungSaldoTanggal(string $tanggal): float
    {
        if ($this->accId === '' || $tanggal === '') return 0;

        $tahun = (int) substr($tanggal, 0, 4);

        $sa = DB::table('tktxn_saldoawalakuns')
            ->where('acc_id', $this->accId)
            ->where('sa_year', (string) $tahun)
            ->first();

        $saldoAwalTahun = $this->accDkStatus === 'K'
            ? (float) ($sa->sa_acc_k ?? 0)
            : (float) ($sa->sa_acc_d ?? 0);

        $expr = $this->accDkStatus === 'K'
            ? 'NVL(txn_k,0) - NVL(txn_d,0)'
            : 'NVL(txn_d,0) - NVL(txn_k,0)';

        $arus = (float) DB::table('tkview_accounts')
            ->where('txn_acc', $this->accId)
            ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                sprintf('%04d-01-01', $tahun), $tanggal,
            ])
            ->sum(DB::raw($expr));

        return $saldoAwalTahun + $arus;
    }

    #[Computed]
    public function saldoAwalPeriode(): float
    {
        if ($this->dariTanggal === '') return 0;
        $prev = \Carbon\Carbon::parse($this->dariTanggal)->subDay()->toDateString();
        return $this->hitungSaldoTanggal($prev);
    }

    #[Computed]
    public function rows()
    {
        if ($this->accId === '' || $this->periode === '') return collect();

        $rows = DB::table('tkview_accounts as v')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'v.txn_acc_k')
            ->select(
                'v.txn_date', 'v.txn_name',
                'v.txn_acc_k as lawan_acc_id',
                'a.acc_name as lawan_acc_name',
                DB::raw('NVL(v.txn_d,0) AS debit'),
                DB::raw('NVL(v.txn_k,0) AS kredit'),
            )
            ->where('v.txn_acc', $this->accId)
            ->whereBetween(DB::raw("TO_CHAR(v.txn_date,'YYYY-MM-DD')"), [
                $this->dariTanggal, $this->sampaiTanggal,
            ])
            ->orderBy('v.txn_date')
            ->get();

        // Running saldo (sign tergantung D/K nature)
        $saldo = $this->saldoAwalPeriode;
        $isK = $this->accDkStatus === 'K';

        return $rows->map(function ($r) use (&$saldo, $isK) {
            $mutasi = $isK
                ? ((float) $r->kredit - (float) $r->debit)
                : ((float) $r->debit - (float) $r->kredit);
            $saldo += $mutasi;
            $r->saldo_berjalan = $saldo;
            $r->mutasi = $mutasi;
            return $r;
        });
    }

    #[Computed]
    public function totalDebit(): float
    {
        return (float) $this->rows->sum('debit');
    }

    #[Computed]
    public function totalKredit(): float
    {
        return (float) $this->rows->sum('kredit');
    }

    #[Computed]
    public function saldoAkhir(): float
    {
        $isK = $this->accDkStatus === 'K';
        $netMutasi = $isK
            ? ($this->totalKredit - $this->totalDebit)
            : ($this->totalDebit - $this->totalKredit);
        return $this->saldoAwalPeriode + $netMutasi;
    }
};
?>

<div>
    <x-page-title
        title="Buku Besar"
        subtitle="Riwayat mutasi semua transaksi per akun pada periode tertentu, lengkap dengan saldo berjalan. Pilih akun &amp; bulan untuk melihat detailnya." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-full sm:w-80">
                            <livewire:lov.akun.lov-akun
                                target="buku-besar-acc"
                                label="Akun"
                                placeholder="Cari akun (kode/nama)..."
                                :initialAccId="$accId" />
                        </div>

                        <div>
                            <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <div class="flex items-stretch gap-1">
                                <x-secondary-button type="button" wire:click="prevMonth"
                                    class="px-3" title="Bulan sebelumnya">◀</x-secondary-button>
                                <x-text-input id="periodeInput" type="text"
                                    wire:model.live.debounce.500ms="periodeInput"
                                    placeholder="01/2026" maxlength="7"
                                    class="w-28 text-center font-mono" />
                                <x-secondary-button type="button" wire:click="nextMonth"
                                    class="px-3" title="Bulan berikutnya">▶</x-secondary-button>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                @if ($periode !== '')
                                    {{ \Carbon\Carbon::parse($this->dariTanggal)->format('d/m/Y') }}
                                    — {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                @else
                                    <span class="text-rose-600">Format: mm/yyyy (mis. 04/2026)</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($accId !== '' && $periode !== '')
                        <div class="grid grid-cols-3 gap-3 text-right">
                            <div class="px-3 py-2 border rounded-lg bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                                <div class="text-[10px] tracking-wider text-gray-500 uppercase">Saldo Awal</div>
                                <div class="font-mono text-sm font-semibold">
                                    {{ number_format($this->saldoAwalPeriode, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                                <div class="text-[10px] tracking-wider text-blue-700 uppercase dark:text-blue-300">Debit</div>
                                <div class="font-mono text-sm font-semibold text-blue-700 dark:text-blue-300">
                                    {{ number_format($this->totalDebit, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="px-3 py-2 border rounded-lg bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800">
                                <div class="text-[10px] tracking-wider text-rose-700 uppercase dark:text-rose-300">Kredit</div>
                                <div class="font-mono text-sm font-semibold text-rose-700 dark:text-rose-300">
                                    {{ number_format($this->totalKredit, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold w-28">TANGGAL</th>
                                <th class="px-3 py-2 font-semibold">DESKRIPSI</th>
                                <th class="px-3 py-2 font-semibold w-56">LAWAN AKUN</th>
                                <th class="px-3 py-2 font-semibold w-28 text-right">DEBIT</th>
                                <th class="px-3 py-2 font-semibold w-28 text-right">KREDIT</th>
                                <th class="px-3 py-2 font-semibold w-36 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @if ($accId === '' || $periode === '')
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Pilih akun &amp; periode di atas untuk menampilkan buku besar.
                                    </td>
                                </tr>
                            @else
                                <tr class="bg-gray-50 dark:bg-gray-800/40">
                                    <td colspan="5" class="px-3 py-2 text-xs italic text-gray-500">
                                        Saldo per {{ \Carbon\Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-sm font-semibold text-right">
                                        {{ number_format($this->saldoAwalPeriode, 0, ',', '.') }}
                                    </td>
                                </tr>

                                @forelse ($this->rows as $i => $row)
                                    <tr wire:key="bb-{{ $accId }}-{{ $i }}-{{ $row->txn_date }}"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                        <td class="px-3 py-2 font-mono text-xs leading-tight align-top">
                                            <div>{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs align-top">{{ $row->txn_name }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500 align-top dark:text-gray-400">
                                            <div class="font-mono">{{ $row->lawan_acc_id }}</div>
                                            @if (!empty($row->lawan_acc_name))
                                                <div class="text-[10px] truncate">{{ $row->lawan_acc_name }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right align-top text-blue-700 dark:text-blue-300">
                                            @if ((float) $row->debit > 0)
                                                {{ number_format((float) $row->debit, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right align-top text-rose-700 dark:text-rose-300">
                                            @if ((float) $row->kredit > 0)
                                                {{ number_format((float) $row->kredit, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm font-semibold text-right align-top {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format((float) $row->saldo_berjalan, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada transaksi pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse

                                @if ($this->rows->count() > 0)
                                    <tr class="font-semibold bg-emerald-50 dark:bg-emerald-900/20">
                                        <td colspan="3" class="px-3 py-2 text-xs uppercase">
                                            Saldo per {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right text-blue-700 dark:text-blue-300">
                                            {{ number_format($this->totalDebit, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm text-right text-rose-700 dark:text-rose-300">
                                            {{ number_format($this->totalKredit, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-base text-right text-emerald-700 dark:text-emerald-300">
                                            {{ number_format($this->saldoAkhir, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($accId !== '')
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Akun aktif:
                    <span class="font-mono font-semibold">{{ $accId }}</span>
                    @if (!empty($accDesc)) — {{ $accDesc }} @endif
                    @if ($accDkStatus === 'D')
                        <span class="px-1.5 ml-2 text-[10px] rounded bg-blue-100 text-blue-700">D-natural</span>
                    @elseif ($accDkStatus === 'K')
                        <span class="px-1.5 ml-2 text-[10px] rounded bg-purple-100 text-purple-700">K-natural</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
