<?php

/**
 * Pembayaran Hutang PBF (Supplier).
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_angsuran (sirus):
 *   - Pilih supplier
 *   - List nota PBF dengan rcv_status='H' (hutang)
 *   - Centang nota mana yg akan dilunasi via toggle (check_boxstatus='1')
 *   - Klik "Proses Pembayaran" → modal:
 *       * 1x INSERT IMTXN_CASHOUTHDRS (master cashout)
 *       * Per rcv yg dicentang (FIFO oldest first):
 *           - INSERT IMTXN_RECEIVEPAYMENTS
 *           - INSERT IMTXN_CASHOUTDTLS
 *           - UPDATE IMTXN_RECEIVEHDRS rcv_status='L' (kalau lunas penuh)
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?string $suppId = null;
    public ?array $supplier = null;

    /* ── LOV Listener ── */
    #[On('lov.selected.hutang-pbf-supplier')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId   = $payload['supp_id'] ?? null;
        $this->supplier = $payload;
    }

    public function clearSupplier(): void
    {
        $this->reset(['suppId', 'supplier']);
    }

    #[On('hutang-pbf.paid')]
    public function refreshAfterPaid(): void
    {
        // Auto-clear supplier setelah bayar sukses → user balik ke fresh state.
        // Kalau ada cicilan tersisa, user tinggal search supplier yg sama lagi.
        $this->reset(['suppId', 'supplier']);
        unset($this->rcvList, $this->summary);
    }

    /* ── List rcv hutang utk supplier terpilih ── */
    #[Computed]
    public function rcvList()
    {
        if (!$this->suppId) return collect();

        $headers = DB::table('imtxn_receivehdrs')
            ->select([
                'rcv_no',
                'supp_id',
                DB::raw("to_char(rcv_date,'dd/mm/yyyy') as rcv_date_display"),
                'rcv_date',
                DB::raw("to_char(due_date,'dd/mm/yyyy') as due_date_display"),
                'rcv_desc',
                DB::raw('NVL(rcv_diskon,0) as rcv_diskon'),
                DB::raw('NVL(rcv_ppn,0) as rcv_ppn'),
                DB::raw("NVL(rcv_ppn_status,'1') as rcv_ppn_status"),
                DB::raw('NVL(rcv_materai,0) as rcv_materai'),
                'rcv_status',
                'check_boxstatus',
                'vcount',
            ])
            ->where('supp_id', $this->suppId)
            ->where('rcv_status', 'H')
            // urut: yg dipilih dulu (vcount asc), lalu yg belum dipilih (NULL last) urut tanggal beli
            ->orderByRaw('vcount NULLS LAST')
            ->orderBy('rcv_date')
            ->orderBy('rcv_no')
            ->get();

        if ($headers->isEmpty()) return collect();

        $rcvNos = $headers->pluck('rcv_no')->all();

        // Total per rcv (sum detail dgn diskon kaskade)
        $totalDetailMap = DB::table('imtxn_receivedtls')
            ->select('rcv_no', DB::raw("
                NVL(SUM(
                    (NVL(qty,0)*NVL(cost_price,0))
                    - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                    - NVL(dtl_diskon,0)
                    - (((NVL(qty,0)*NVL(cost_price,0))
                        - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                        - NVL(dtl_diskon,0))
                      * NVL(dtl_persen1,0)/100)
                    - NVL(dtl_diskon1,0)
                ),0) as total_detail"))
            ->whereIn('rcv_no', $rcvNos)
            ->groupBy('rcv_no')
            ->pluck('total_detail', 'rcv_no');

        // Total titipan (rcvpayments) per rcv
        $titipanMap = DB::table('imtxn_receivepayments')
            ->select('rcv_no', DB::raw('NVL(SUM(rcvp_value),0) as titipan'))
            ->whereIn('rcv_no', $rcvNos)
            ->groupBy('rcv_no')
            ->pluck('titipan', 'rcv_no');

        $autoFinalize = []; // rcv_no yg sisanya 0 → auto-finalize ke 'L'

        $rows = $headers->map(function ($r) use ($totalDetailMap, $titipanMap, &$autoFinalize) {
            $totalDetail   = (float) ($totalDetailMap[$r->rcv_no] ?? 0);
            $setelahDiskon = $totalDetail - (float) $r->rcv_diskon;
            $ppn           = ((string) $r->rcv_ppn_status) === '1'
                ? ($setelahDiskon * (float) $r->rcv_ppn) / 100
                : 0;
            // Cast ke int rupiah supaya konsisten dgn save logic & cegah sub-1 rupiah nyangkut di 'H'.
            $grandTotal = (int) round($setelahDiskon + $ppn + (float) $r->rcv_materai);
            $titipan    = (int) round((float) ($titipanMap[$r->rcv_no] ?? 0));
            $sisa       = $grandTotal - $titipan;

            if ($sisa <= 0) {
                $autoFinalize[] = $r->rcv_no;
            }

            return (object) [
                'rcv_no'           => $r->rcv_no,
                'rcv_date_display' => $r->rcv_date_display,
                'due_date_display' => $r->due_date_display,
                'rcv_desc'         => $r->rcv_desc,
                'total'            => $grandTotal,
                'titipan'          => $titipan,
                'sisa'             => $sisa,
                'check_boxstatus'  => (string) ($r->check_boxstatus ?? ''),
                'is_checked'       => ((string) ($r->check_boxstatus ?? '')) === '1',
                'vcount'           => $r->vcount !== null ? (int) $r->vcount : null,
            ];
        });

        // Auto-finalize: nota dgn sisa ≤ 0 di-promote ke 'L' & dikeluarkan dari list
        if (!empty($autoFinalize)) {
            DB::table('imtxn_receivehdrs')
                ->whereIn('rcv_no', $autoFinalize)
                ->update([
                    'rcv_status'      => 'L',
                    'check_boxstatus' => '0',
                    'vcount'          => null,
                ]);
            $rows = $rows->reject(fn ($r) => in_array($r->rcv_no, $autoFinalize));
        }

        return $rows->values();
    }

    /* ── Toggle check_boxstatus per row ──
     * Mirror Oracle Forms: ON  → check_boxstatus='1' + vcount=NVL(MAX(vcount)+1,1)
     *                      OFF → check_boxstatus='0' + vcount=NULL
     * vcount = urutan klik user, jadi user bisa pilih NOTA MANA YG DILUNASI DULUAN.
     */
    public function toggleCheckBox(int $rcvNo): void
    {
        $row = DB::table('imtxn_receivehdrs')
            ->where('rcv_no', $rcvNo)
            ->where('supp_id', $this->suppId)
            ->where('rcv_status', 'H')
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Nota tidak valid.');
            return;
        }

        $isCurrentlyChecked = ((string) ($row->check_boxstatus ?? '')) === '1';

        if (!$isCurrentlyChecked) {
            // ON → vcount = max(vcount)+1 di antara yg dichecklist utk supplier ini
            $nextVcount = (int) DB::table('imtxn_receivehdrs')
                ->where('supp_id', $this->suppId)
                ->where('check_boxstatus', '1')
                ->max('vcount');
            $nextVcount = $nextVcount + 1;

            DB::table('imtxn_receivehdrs')
                ->where('rcv_no', $rcvNo)
                ->update(['check_boxstatus' => '1', 'vcount' => $nextVcount]);
        } else {
            // OFF → reset
            DB::table('imtxn_receivehdrs')
                ->where('rcv_no', $rcvNo)
                ->update(['check_boxstatus' => '0', 'vcount' => null]);
        }

        unset($this->rcvList, $this->summary);
    }

    /**
     * Toggle all: kalau semua sudah dipilih → unset semua.
     * Kalau ada yg belum → set semua sesuai urutan tanggal beli (FIFO default),
     * vcount auto-increment 1..N, supaya alokasi tetap jalan saat user nggak peduli urutan.
     */
    public function toggleCheckBoxAll(): void
    {
        if (!$this->suppId) return;

        $allChecked = $this->rcvList->isNotEmpty() && $this->rcvList->every(fn ($r) => $r->is_checked);

        if ($allChecked) {
            // Reset semua
            DB::table('imtxn_receivehdrs')
                ->where('supp_id', $this->suppId)
                ->where('rcv_status', 'H')
                ->update(['check_boxstatus' => '0', 'vcount' => null]);
        } else {
            // Set semua, vcount sesuai urut tanggal beli
            $rcvs = DB::table('imtxn_receivehdrs')
                ->select('rcv_no')
                ->where('supp_id', $this->suppId)
                ->where('rcv_status', 'H')
                ->orderBy('rcv_date')
                ->orderBy('rcv_no')
                ->pluck('rcv_no');

            DB::transaction(function () use ($rcvs) {
                $i = 1;
                foreach ($rcvs as $rcvNo) {
                    DB::table('imtxn_receivehdrs')
                        ->where('rcv_no', $rcvNo)
                        ->update(['check_boxstatus' => '1', 'vcount' => $i++]);
                }
            });
        }

        unset($this->rcvList, $this->summary);
    }

    #[Computed]
    public function summary(): array
    {
        $list = $this->rcvList;
        $checked = $list->where('is_checked', true);
        return [
            'jumlah'         => $list->count(),
            'jumlah_checked' => $checked->count(),
            'tottotal'       => (float) $list->sum('total'),
            'tottitipan'     => (float) $list->sum('titipan'),
            'totsisa'        => (float) $list->sum('sisa'),
            'sisa_checked'   => (float) $checked->sum('sisa'),
        ];
    }

    /* ── Trigger ke modal ── */
    public function openProsesBayar(): void
    {
        if (!$this->suppId) {
            $this->dispatch('toast', type: 'error', message: 'Pilih supplier terlebih dahulu.');
            return;
        }
        if ($this->summary['jumlah'] === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada hutang untuk supplier ini.');
            return;
        }
        if ($this->summary['jumlah_checked'] === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Centang minimal 1 nota yang akan dibayar.');
            return;
        }
        if ($this->summary['sisa_checked'] <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'Sisa hutang nota terpilih sudah Rp 0.');
            return;
        }

        $this->dispatch('hutang-pbf.openProsesBayar',
            suppId: $this->suppId,
            suppName: $this->supplier['supp_name'] ?? '',
            sisaChecked: $this->summary['sisa_checked'],
            jumlah: $this->summary['jumlah_checked'],
        );
    }
};
?>

<div>
    <x-page-title
        title="Pembayaran Hutang PBF"
        subtitle="Pelunasan / angsuran hutang ke PBF / supplier obat" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">

            {{-- 1) PILIH SUPPLIER --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-semibold text-ink dark:text-gray-100">
                            1. Pilih Supplier / PBF
                        </h3>
                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                            Cari berdasarkan kode / nama / telp supplier
                        </p>
                    </div>
                    @if ($suppId)
                        <x-secondary-button type="button" wire:click="clearSupplier"
                            title="Reset pilihan supplier untuk transaksi baru">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Transaksi Baru
                        </x-secondary-button>
                    @endif
                </div>
                <div class="px-5 py-5">
                    <livewire:lov.supplier.lov-supplier
                        target="hutang-pbf-supplier"
                        label="Cari Supplier"
                        placeholder="Ketik kode / nama / telp supplier..."
                        :initialSuppId="$suppId"
                        wire:key="lov-supplier-hutang-pbf-{{ $suppId ?? 'empty' }}" />
                </div>
            </div>

            @if ($suppId)
                {{-- 2) RINGKASAN --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="px-4 py-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-muted dark:text-gray-400">Jumlah Nota</div>
                        <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ $this->summary['jumlah'] }}</div>
                    </div>
                    <div class="px-4 py-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-muted dark:text-gray-400">Total Tagihan</div>
                        <div class="mt-1 font-mono text-xl font-bold text-ink dark:text-gray-100">
                            Rp {{ number_format($this->summary['tottotal']) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-muted dark:text-gray-400">Sudah Dibayar (Titipan)</div>
                        <div class="mt-1 font-mono text-xl font-bold text-success dark:text-success">
                            Rp {{ number_format($this->summary['tottitipan']) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs text-muted dark:text-gray-400">Sisa Hutang (Semua)</div>
                        <div class="mt-1 font-mono text-xl font-bold {{ $this->summary['totsisa'] > 0 ? 'text-error dark:text-rose-400' : 'text-success dark:text-success' }}">
                            Rp {{ number_format($this->summary['totsisa']) }}
                        </div>
                    </div>
                    <div class="px-4 py-4 border-2 rounded-2xl
                        {{ $this->summary['sisa_checked'] > 0 ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/30' : 'border-gray-300 bg-surface-soft dark:bg-gray-800/50' }}">
                        <div class="text-xs {{ $this->summary['sisa_checked'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted' }}">
                            Akan Dibayar ({{ $this->summary['jumlah_checked'] }} dipilih) ✓
                        </div>
                        <div class="mt-1 font-mono text-xl font-bold {{ $this->summary['sisa_checked'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-soft' }}">
                            Rp {{ number_format($this->summary['sisa_checked']) }}
                        </div>
                    </div>
                </div>

                {{-- 3) DETAIL TABEL --}}
                <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-hairline dark:border-gray-700">
                        <div>
                            <h3 class="text-base font-semibold text-ink dark:text-gray-100">
                                2. Daftar Hutang
                            </h3>
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                Centang nota dengan toggle. <strong>Urutan klik = urutan bayar</strong> (badge angka di samping toggle).
                                {{ $this->summary['jumlah_checked'] }} dari {{ $this->summary['jumlah'] }} dipilih.
                            </p>
                        </div>
                        <x-primary-button type="button" wire:click="openProsesBayar"
                            @class(['opacity-50 cursor-not-allowed' => $this->summary['sisa_checked'] <= 0])>
                            Proses Pembayaran →
                        </x-primary-button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    @php
                                        $allChecked = $this->rcvList->isNotEmpty() && $this->rcvList->every(fn ($r) => $r->is_checked);
                                    @endphp
                                    <th class="px-3 py-2 font-semibold text-center w-16">
                                        <x-toggle :current="$allChecked ? '1' : '0'" trueValue="1" falseValue="0"
                                            wireClick="toggleCheckBoxAll" />
                                    </th>
                                    <th class="px-3 py-2 font-semibold">No Nota</th>
                                    <th class="px-3 py-2 font-semibold">Tgl Beli</th>
                                    <th class="px-3 py-2 font-semibold">Jatuh Tempo</th>
                                    <th class="px-3 py-2 font-semibold">Keterangan</th>
                                    <th class="px-3 py-2 font-semibold text-right text-ink dark:text-gray-100">Total</th>
                                    <th class="px-3 py-2 font-semibold text-right text-emerald-700">Titipan</th>
                                    <th class="px-3 py-2 font-semibold text-right text-error">Sisa</th>
                                </tr>
                            </thead>
                            <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @forelse($this->rcvList as $row)
                                    <tr wire:key="hutang-pbf-{{ $row->rcv_no }}"
                                        class="hover:bg-surface-soft dark:hover:bg-gray-800/60
                                            {{ $row->is_checked ? 'bg-amber-50/60 dark:bg-amber-950/20' : '' }}">
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <x-toggle :current="$row->is_checked ? '1' : '0'" trueValue="1" falseValue="0"
                                                    wireClick="toggleCheckBox({{ $row->rcv_no }})" />
                                                @if ($row->is_checked && $row->vcount !== null)
                                                    <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full bg-amber-500 text-white"
                                                        title="Urutan bayar (vcount)">
                                                        {{ $row->vcount }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->rcv_no }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $row->rcv_date_display }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            @if ($row->due_date_display)
                                                <span class="text-amber-700 dark:text-amber-400">{{ $row->due_date_display }}</span>
                                            @else
                                                <span class="text-muted-soft">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">{{ $row->rcv_desc ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono font-semibold text-right">{{ number_format($row->total) }}</td>
                                        <td class="px-3 py-2 font-mono text-right text-emerald-700">{{ number_format($row->titipan) }}</td>
                                        <td class="px-3 py-2 font-mono font-bold text-right text-error">{{ number_format($row->sisa) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-12 text-center text-muted dark:text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                                <p>Tidak ada hutang untuk supplier ini.</p>
                                                <p class="text-xs text-muted-soft">Supplier tidak memiliki nota dengan status Hutang (rcv_status='H').</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($this->rcvList->isNotEmpty())
                                <tfoot class="text-body bg-surface-soft dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="font-semibold">
                                        <td colspan="5" class="px-3 py-3 text-right">TOTAL SEMUA</td>
                                        <td class="px-3 py-3 font-mono text-right">Rp {{ number_format($this->summary['tottotal']) }}</td>
                                        <td class="px-3 py-3 font-mono text-right text-emerald-700">Rp {{ number_format($this->summary['tottitipan']) }}</td>
                                        <td class="px-3 py-3 font-mono text-right text-error">Rp {{ number_format($this->summary['totsisa']) }}</td>
                                    </tr>
                                    @if ($this->summary['jumlah_checked'] > 0)
                                        <tr class="font-semibold bg-amber-50 dark:bg-amber-950/30">
                                            <td colspan="7" class="px-3 py-3 text-right text-amber-800 dark:text-amber-300">
                                                AKAN DIBAYAR ({{ $this->summary['jumlah_checked'] }} dipilih)
                                            </td>
                                            <td class="px-3 py-3 font-mono text-right text-amber-800 dark:text-amber-300">
                                                Rp {{ number_format($this->summary['sisa_checked']) }}
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            @else
                <div class="px-6 py-16 text-center bg-canvas border border-hairline border-dashed rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <svg class="w-16 h-16 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 7h18M3 12h18M3 17h18" />
                    </svg>
                    <p class="mt-3 text-sm text-muted dark:text-gray-400">
                        Pilih supplier terlebih dahulu untuk melihat daftar hutang.
                    </p>
                </div>
            @endif

            {{-- Modal proses bayar --}}
            <livewire:pages::transaksi.keuangan.pembayaran-hutang-pbf.pembayaran-hutang-pbf-actions
                wire:key="pembayaran-hutang-pbf-actions" />
        </div>
    </div>
</div>
