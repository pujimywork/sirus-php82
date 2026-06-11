<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $tanggal = '';
    public string $searchKeyword = '';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function updatedTanggal(): void { /* recompute saldo */ }
    public function updatedSearchKeyword(): void { /* refilter */ }

    public function openEdit(string $accId): void
    {
        if (!auth()->user()?->hasRole('Admin')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya admin yang bisa mengedit saldo.');
            return;
        }
        $this->dispatch('keuangan.saldo-kas.openEdit', accId: $accId, tanggal: $this->tanggal);
    }

    public function openHistory(string $accId): void
    {
        $this->dispatch('keuangan.saldo-kas.openHistory', accId: $accId, tanggal: $this->tanggal);
    }

    #[On('keuangan.saldo-kas.saved')]
    public function refreshAfterSaved(): void { /* trigger re-render */ }

    public function isAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('Admin');
    }

    /**
     * Saldo per tanggal untuk D-natured account.
     * Logic dari legacy: saldo = saldo_awal_tahun + sum(txn_k - txn_d) dari Jan 1 s/d tanggal,
     * filter txn_acc_k = acc_id (rumus "counter row" di tkview_accounts).
     */
    private function hitungSaldoTanggal(string $accId, string $dkStatus, string $tanggal): float
    {
        $tahun = (int) substr($tanggal, 0, 4);

        $sa = DB::table('tktxn_saldoawalakuns')
            ->where('acc_id', $accId)
            ->where('sa_year', (string) $tahun)
            ->first();

        $saldoAwalTahun = $dkStatus === 'D'
            ? (float) ($sa->sa_acc_d ?? 0)
            : (float) ($sa->sa_acc_k ?? 0);

        // Untuk akun D-natured (kas/bank): filter txn_acc_k = acc, sum (K - D)
        // Untuk akun K-natured: filter txn_acc = acc, sum (D - K) — tidak terjadi di cara-bayar tapi disediakan.
        if ($dkStatus === 'D') {
            $arus = (float) DB::table('tkview_accounts')
                ->where('txn_acc_k', $accId)
                ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                    sprintf('%04d-01-01', $tahun),
                    $tanggal,
                ])
                ->sum(DB::raw('NVL(txn_k,0) - NVL(txn_d,0)'));
        } else {
            $arus = (float) DB::table('tkview_accounts')
                ->where('txn_acc', $accId)
                ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                    sprintf('%04d-01-01', $tahun),
                    $tanggal,
                ])
                ->sum(DB::raw('NVL(txn_d,0) - NVL(txn_k,0)'));
        }

        return $saldoAwalTahun + $arus;
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name', 'a.acc_dk_status', 'a.active_status')
            ->where('a.active_status', '1')
            ->whereIn(
                'a.acc_id',
                fn($sub) => $sub->select('acc_id')->from('user_kas')->where('user_id', auth()->id()),
            );

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($w) use ($kw) {
                $w->whereRaw('UPPER(a.acc_id) LIKE ?', ["%{$kw}%"])
                  ->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->orderBy('a.acc_name')->get()->map(function ($r) {
            $r->saldo = $this->hitungSaldoTanggal(
                (string) $r->acc_id,
                (string) ($r->acc_dk_status ?? 'D'),
                $this->tanggal
            );
            return $r;
        });
    }

    #[Computed]
    public function totalSaldo(): float
    {
        return (float) $this->rows->sum('saldo');
    }
};
?>

<div>
    <x-page-title
        title="Saldo Kas Per Tanggal"
        subtitle="Posisi saldo kas/bank per tanggal yang dipilih (otomatis dari arus jurnal). @if (!$this->isAdmin()) Mode tampilan saja — edit saldo hanya untuk admin. @endif" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-full sm:w-52">
                            <x-input-label for="tanggal" value="Saldo Per Tanggal" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <x-text-input id="tanggal" type="date"
                                wire:model.live="tanggal"
                                class="block w-full" />
                        </div>
                        <div class="w-full sm:w-72">
                            <x-input-label for="searchKeyword" value="Cari" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Kode / nama akun kas..."
                                class="block w-full" />
                        </div>
                    </div>

                    <div class="px-4 py-2 text-right border rounded-lg bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                        <div class="text-[11px] font-medium tracking-wider text-emerald-700 uppercase dark:text-emerald-300">
                            Total Saldo
                        </div>
                        <div class="text-xl font-bold text-emerald-800 dark:text-emerald-200">
                            Rp {{ number_format($this->totalSaldo, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold w-28">ACC ID</th>
                                <th class="px-4 py-3 font-semibold">AKUN KAS</th>
                                <th class="px-4 py-3 font-semibold w-24 text-center">D/K</th>
                                <th class="px-4 py-3 font-semibold w-60 text-right">SALDO PER {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}</th>
                                <th class="px-4 py-3 font-semibold {{ $this->isAdmin() ? 'w-56' : 'w-32' }}">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="saldo-{{ $row->acc_id }}-{{ $tanggal }}"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs align-middle">{{ $row->acc_id }}</td>
                                    <td class="px-4 py-3 align-middle">
                                        <div class="text-sm font-medium text-ink dark:text-gray-100">
                                            {{ $row->acc_name }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center align-middle">
                                        @if ((string) $row->acc_dk_status === 'D')
                                            <span class="px-3 py-1 text-sm font-bold rounded bg-blue-100 text-blue-700">D</span>
                                        @elseif ((string) $row->acc_dk_status === 'K')
                                            <span class="px-3 py-1 text-sm font-bold rounded bg-purple-100 text-purple-700">K</span>
                                        @else
                                            <span class="text-sm text-muted-soft">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-right align-middle">
                                        <span class="text-lg font-bold {{ $row->saldo < 0 ? 'text-red-600' : 'text-ink dark:text-gray-100' }}">
                                            Rp {{ number_format($row->saldo, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 align-middle">
                                        <div class="flex items-center gap-2 flex-nowrap">
                                            <x-secondary-button type="button"
                                                wire:click="openHistory('{{ $row->acc_id }}')"
                                                class="px-3 py-1.5 text-sm whitespace-nowrap">
                                                Riwayat
                                            </x-secondary-button>
                                            @if ($this->isAdmin())
                                                <x-secondary-button type="button"
                                                    wire:click="openEdit('{{ $row->acc_id }}')"
                                                    class="px-3 py-1.5 text-sm whitespace-nowrap">
                                                    Edit Saldo
                                                </x-secondary-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-muted dark:text-gray-400">
                                        Tidak ada akun kas aktif untuk user ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <livewire:pages::transaksi.keuangan.saldo-kas.saldo-kas-history wire:key="saldo-kas-history" />
            @if ($this->isAdmin())
                <livewire:pages::transaksi.keuangan.saldo-kas.saldo-kas-actions wire:key="saldo-kas-actions" />
            @endif
        </div>
    </div>
</div>
