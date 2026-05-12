<?php

/**
 * Kartu Stock Obat (Read-only).
 *
 * Equivalent dgn form Oracle Forms TKVIEW_SALDOAWALSTOCKS + tab IOSTOCKWHS:
 *   - Pilih tahun & produk
 *   - Tampilkan saldo awal (TKTXN_SALDOAWALSTOCKS.sa_stockwh) +
 *     mutasi tahun berjalan (TKVIEW_IOSTOCKWHS qty_d - qty_k) = saldo akhir
 *   - List history mutasi: txn_status SLS=OBAT BEBAS, RCV=BELI PBF, RJ=RAWAT JALAN
 *
 * Sumber tabel (sirus):
 *   - IMMST_PRODUCTS              (master barang medis)
 *   - TKTXN_SALDOAWALSTOCKS       (saldo awal per tahun: SA_YEAR+PRODUCT_ID)
 *   - TKVIEW_IOSTOCKWHS           (view mutasi in/out: qty_d / qty_k per txn)
 *   - TKTXN_SOWHS                 (insert mutasi stock opname)
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $year;
    public string $searchProduct = '';      // filter master list (kode/nama)
    public int    $itemsPerPage  = 25;
    public ?string $productId = null;
    public ?array $product = null;

    /* ── Stock Opname (port Oracle Forms edit_saldo flow) ── */
    public string $editSaldo = '0';
    public ?int $stockFisik = null;

    public function mount(): void
    {
        $this->year = (string) Carbon::now()->year;
    }

    public function updatedYear(): void
    {
        unset($this->mutations, $this->saldo, $this->productList);
        $this->resetPage();
    }

    public function updatedSearchProduct(): void
    {
        $this->resetPage();
        unset($this->productList);
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function selectProduct(string $productId): void
    {
        $this->productId = $productId;
        $this->loadProduct();
        $this->reset(['editSaldo', 'stockFisik']);
        unset($this->mutations, $this->mutationsWithBalance, $this->saldo);
    }

    public function clearProduct(): void
    {
        $this->reset(['productId', 'product', 'editSaldo', 'stockFisik']);
    }

    /* ── Master list semua produk dengan saldo per year ── */
    #[Computed]
    public function productList()
    {
        $sub = DB::table('tkview_iostockwhs')
            ->select('product_id',
                DB::raw('NVL(SUM(qty_d),0) as masuk'),
                DB::raw('NVL(SUM(qty_k),0) as keluar'))
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [$this->year])
            ->groupBy('product_id');

        $query = DB::table('immst_products as p')
            ->leftJoinSub($sub, 'io', fn ($j) => $j->on('io.product_id', '=', 'p.product_id'))
            ->leftJoin('tktxn_saldoawalstocks as s', function ($j) {
                $j->on('s.product_id', '=', 'p.product_id')
                  ->where('s.sa_year', '=', $this->year);
            })
            ->select([
                'p.product_id',
                'p.product_name',
                DB::raw("'' as product_type"), // TODO: sirus immst_products tidak punya product_type
                'p.qty_box',
                'p.limit_stock',
                'p.active_status',
                DB::raw('NVL(s.sa_stockwh,0) as saldo_awal'),
                DB::raw('NVL(io.masuk,0) as masuk'),
                DB::raw('NVL(io.keluar,0) as keluar'),
                DB::raw('NVL(s.sa_stockwh,0) + NVL(io.masuk,0) - NVL(io.keluar,0) as saldo_akhir'),
            ])
            ->where('p.active_status', '1');

        if ($this->searchProduct !== '') {
            $kw = mb_strtoupper(trim($this->searchProduct));
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('UPPER(p.product_id) LIKE ?', ["%{$kw}%"])
                  ->orWhereRaw('UPPER(p.product_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $query->orderBy('p.product_name')->paginate($this->itemsPerPage);
    }

    /* ── Stock Opname (port Oracle Forms NEW logic) ──
     * Tidak adjust saldo awal — INSERT mutasi opname ke TKTXN_SOWHS:
     *   updatestock := mutasi + saldo_awal - stock_fisik   (= saldo_akhir_db - stock_fisik)
     *   updatestock > 0 → stock fisik kurang → INSERT (so_d=0, so_k=updatestock)   [keluar]
     *   updatestock < 0 → stock fisik lebih → INSERT (so_d=|updatestock|, so_k=0)  [masuk]
     *   updatestock = 0 → no-op (tidak ada selisih)
     * Hanya boleh opname tahun berjalan.
     */
    public function simpanOpname(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Apoteker'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin & Apoteker yang dapat melakukan stock opname.');
            return;
        }

        if ($this->editSaldo !== '1') {
            $this->dispatch('toast', type: 'error', message: 'Aktifkan toggle "Edit Saldo" terlebih dahulu sebelum menyimpan.');
            return;
        }

        if (!$this->productId) {
            $this->dispatch('toast', type: 'error', message: 'Pilih produk terlebih dahulu.');
            return;
        }

        if ($this->stockFisik === null || $this->stockFisik < 0) {
            $this->dispatch('toast', type: 'error', message: 'Stock fisik wajib diisi (≥ 0).');
            return;
        }

        // Hanya boleh opname tahun berjalan
        $currentYear = (string) Carbon::now()->year;
        if ($this->year !== $currentYear) {
            $this->dispatch('toast', type: 'error',
                message: "Data terkunci. Opname hanya boleh dilakukan untuk tahun berjalan ({$currentYear}).");
            return;
        }

        // Resolve emp_id (sirus pakai emp_id di tktxn_sowhs, bukan kasir_id)
        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil pegawai Anda belum di-mapping (emp_id). Hubungi admin.');
            return;
        }

        $selisih = $this->saldo['akhir'] - (int) $this->stockFisik;

        if ($selisih === 0) {
            $this->dispatch('toast', type: 'info', message: 'Stock fisik sama dengan catatan — tidak ada selisih untuk dicatat.');
            $this->editSaldo = '0';
            $this->stockFisik = null;
            return;
        }

        try {
            DB::transaction(function () use ($selisih, $empId) {
                // Generate so_no = NVL(MAX(so_no),0)+1
                $soNo = (int) (DB::table('tktxn_sowhs')->max('so_no') ?? 0) + 1;

                $payload = [
                    'product_id' => $this->productId,
                    'so_no'      => $soNo,
                    'so_date'    => DB::raw('SYSDATE'),
                    'emp_id'     => $empId,
                    'so_desc'    => 'SO',
                ];

                if ($selisih > 0) {
                    // Stock fisik KURANG dari catatan → keluar (so_k)
                    $payload['so_d'] = 0;
                    $payload['so_k'] = $selisih;
                } else {
                    // Stock fisik LEBIH dari catatan → masuk (so_d)
                    $payload['so_d'] = abs($selisih);
                    $payload['so_k'] = 0;
                }

                DB::table('tktxn_sowhs')->insert($payload);
            });

            $arah = $selisih > 0 ? 'kurang ' . number_format($selisih) : 'lebih ' . number_format(abs($selisih));
            $this->dispatch('toast', type: 'success',
                message: "Opname disimpan. Stock fisik {$arah} dari catatan — selisih dicatat sebagai mutasi SO.");

            $this->stockFisik = null;
            $this->editSaldo = '0';
            unset($this->saldo, $this->mutations, $this->mutationsWithBalance);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan opname: ' . $e->getMessage());
        }
    }

    protected function loadProduct(): void
    {
        if (!$this->productId) return;

        $row = DB::table('immst_products as p')
            ->leftJoin('immst_uoms as u', 'p.uom_id', '=', 'u.uom_id')
            ->select([
                'p.product_id', 'p.product_name',
                DB::raw("'' as product_type"),  // TODO: tidak ada di immst_products
                DB::raw("'' as product_rak"),   // TODO: tidak ada di immst_products
                'p.sales_price', 'p.cost_price', 'p.qty_box', 'p.limit_stock',
                'p.cat_id', 'p.uom_id', 'u.uom_desc',
            ])
            ->where('p.product_id', $this->productId)
            ->first();

        $this->product = $row ? (array) $row : null;
    }

    /* ── Saldo summary utk product+year terpilih ── */
    #[Computed]
    public function saldo(): array
    {
        if (!$this->productId) {
            return ['awal' => 0, 'masuk' => 0, 'keluar' => 0, 'akhir' => 0];
        }

        $awal = (int) (DB::table('tktxn_saldoawalstocks')
            ->where('product_id', $this->productId)
            ->where('sa_year', $this->year)
            ->sum('sa_stockwh') ?? 0);

        $mut = DB::table('tkview_iostockwhs')
            ->where('product_id', $this->productId)
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [$this->year])
            ->selectRaw('NVL(SUM(qty_d),0) as masuk, NVL(SUM(qty_k),0) as keluar')
            ->first();

        $masuk  = (int) ($mut->masuk  ?? 0);
        $keluar = (int) ($mut->keluar ?? 0);

        return [
            'awal'   => $awal,
            'masuk'  => $masuk,
            'keluar' => $keluar,
            'akhir'  => $awal + $masuk - $keluar,
        ];
    }

    /* ── List mutasi (history) per product+year ──
     * Query ASC dulu (utk hitung running balance dari saldo awal),
     * di mutationsWithBalance dibalik jadi DESC supaya tampil terbaru di atas.
     */
    #[Computed]
    public function mutations()
    {
        if (!$this->productId) return collect();

        return DB::table('tkview_iostockwhs')
            ->select([
                DB::raw("TO_CHAR(txn_date,'dd/mm/yyyy hh24:mi:ss') as txn_date_display"),
                'txn_date',
                'txn_no',
                'txn_status',
                DB::raw('NVL(qty_d,0) as qty_d'),
                DB::raw('NVL(qty_k,0) as qty_k'),
            ])
            ->where('product_id', $this->productId)
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [$this->year])
            ->orderBy('txn_date')
            ->orderBy('txn_no')
            ->get();
    }

    /**
     * Mapping txn_status → keterangan (port Oracle Forms).
     */
    public static function statusLabel(string $status): array
    {
        return match ($status) {
            'SLS' => ['Obat Bebas', 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'],
            'RCV' => ['Beli PBF', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'],
            'RJ'  => ['Rawat Jalan', 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'],
            'SO'  => ['Stock Opname', 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'],
            default => [$status ?: '—', 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
        };
    }

    /* ── Saldo berjalan per row (running balance) ──
     * Compute pakai urut ASC (oldest first) supaya saldo benar,
     * lalu reverse jadi DESC utk display (terbaru di atas).
     */
    #[Computed]
    public function mutationsWithBalance()
    {
        $running = $this->saldo['awal'];
        $rows = $this->mutations->map(function ($r) use (&$running) {
            $running += (int) $r->qty_d - (int) $r->qty_k;
            return (object) [
                ...((array) $r),
                'saldo' => $running,
            ];
        });

        return $rows->reverse()->values();
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Kartu Stock Gudang
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Riwayat mutasi stok obat di lokasi gudang/warehouse (saldo awal + masuk − keluar = saldo akhir)
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4">

            {{-- TOOLBAR: tahun + search produk + per page --}}
            <div class="sticky z-30 px-4 py-3 bg-white border border-gray-200 shadow-sm rounded-2xl top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-28">
                        <x-input-label value="Tahun" :required="true" />
                        <x-text-input type="number" wire:model.live.debounce.500ms="year"
                            class="mt-1" min="2020" max="2099" />
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <x-input-label value="Cari Produk" />
                        <x-text-input wire:model.live.debounce.300ms="searchProduct"
                            placeholder="Ketik kode / nama produk..." class="w-full mt-1" />
                    </div>
                    <div class="w-24">
                        <x-input-label value="Per hal." />
                        <x-select-input wire:model.live="itemsPerPage" class="mt-1">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>
                </div>
            </div>

            {{-- MAIN GRID: KIRI = list produk (col-4) | KANAN = detail (col-8) --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

                {{-- KIRI: LIST PRODUK --}}
                <div class="lg:col-span-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Daftar Obat
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Klik baris untuk lihat detail kartu stock {{ $year }}
                        </p>
                    </div>

                    <div class="overflow-x-auto max-h-[calc(100dvh-300px)] overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    <th class="px-3 py-2 font-semibold">Produk</th>
                                    <th class="px-3 py-2 font-semibold text-right">Saldo {{ $year }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                @forelse($this->productList as $row)
                                    @php
                                        $isActive = $productId === $row->product_id;
                                        $belowMin = ($row->limit_stock ?? 0) > 0 && $row->saldo_akhir < $row->limit_stock;
                                    @endphp
                                    <tr wire:key="prod-{{ $row->product_id }}"
                                        wire:click="selectProduct('{{ $row->product_id }}')"
                                        class="cursor-pointer transition-colors
                                            {{ $isActive ? 'bg-brand/10 dark:bg-brand-lime/15' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-900 dark:text-gray-100 line-clamp-1">{{ $row->product_name }}</div>
                                            <div class="font-mono text-xs text-gray-400">{{ $row->product_id }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            <div class="font-mono font-semibold {{ $belowMin ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-gray-100' }}">
                                                {{ number_format($row->saldo_akhir) }}
                                            </div>
                                            @if ($belowMin)
                                                <div class="text-[10px] text-rose-600 dark:text-rose-400">⚠ &lt; {{ number_format($row->limit_stock) }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada produk yang cocok.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="sticky bottom-0 z-10 px-3 py-2 text-xs bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                        {{ $this->productList->links() }}
                    </div>
                </div>

                {{-- KANAN: DETAIL --}}
                <div class="lg:col-span-8">
                    @if ($productId && $product)
                        {{-- Sub-grid dalam kanan: history (col-7) + info+opname (col-5) --}}
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

                    {{-- HISTORY MUTASI (kiri dlm panel kanan) --}}
                    <div class="lg:col-span-7 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                History Mutasi {{ $year }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Saldo Awal: <span class="font-mono text-gray-700 dark:text-gray-300">{{ number_format($this->saldo['awal']) }}</span>
                                &middot; Total Mutasi: {{ $this->mutations->count() }} transaksi
                            </p>
                        </div>

                        <div class="overflow-x-auto max-h-[calc(100dvh-380px)] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-semibold">Tanggal</th>
                                        <th class="px-3 py-2 font-semibold">Status</th>
                                        <th class="px-3 py-2 font-semibold">Keterangan</th>
                                        <th class="px-3 py-2 font-semibold text-right text-emerald-700">Masuk</th>
                                        <th class="px-3 py-2 font-semibold text-right text-rose-700">Keluar</th>
                                        <th class="px-3 py-2 font-semibold text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                                    @forelse($this->mutationsWithBalance as $row)
                                        @php
                                            [$label, $badgeClass] = $this::statusLabel($row->txn_status);
                                            $ket = match ($row->txn_status) {
                                                'SLS' => 'OBAT BEBAS ' . $row->txn_no,
                                                'RCV' => 'BELI PBF ' . $row->txn_no,
                                                'RJ'  => 'RAWAT JALAN ' . $row->txn_no,
                                                'SO'  => 'OPNAME #' . $row->txn_no,
                                                default => $row->txn_status . ' ' . $row->txn_no,
                                            };
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->txn_date_display }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 text-xs rounded-full {{ $badgeClass }}">{{ $label }}</span>
                                            </td>
                                            <td class="px-3 py-2">{{ $ket }}</td>
                                            <td class="px-3 py-2 font-mono text-right text-emerald-700">
                                                {{ $row->qty_d > 0 ? number_format($row->qty_d) : '-' }}
                                            </td>
                                            <td class="px-3 py-2 font-mono text-right text-rose-700">
                                                {{ $row->qty_k > 0 ? number_format($row->qty_k) : '-' }}
                                            </td>
                                            <td class="px-3 py-2 font-mono font-semibold text-right">{{ number_format($row->saldo) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                                Tidak ada mutasi di tahun {{ $year }}.
                                            </td>
                                        </tr>
                                    @endforelse

                                    {{-- Row penutup: saldo awal (paling bawah karena urut DESC) --}}
                                    <tr class="bg-gray-50/50 dark:bg-gray-800/30">
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">01/01/{{ $year }} 00:00:00</td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200">SALDO AWAL</span>
                                        </td>
                                        <td class="px-3 py-2 italic text-gray-500">Saldo awal tahun {{ $year }}</td>
                                        <td class="px-3 py-2 font-mono text-right text-gray-400">-</td>
                                        <td class="px-3 py-2 font-mono text-right text-gray-400">-</td>
                                        <td class="px-3 py-2 font-mono font-semibold text-right">{{ number_format($this->saldo['awal']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- DATA OBAT + SALDO + OPNAME (kanan dlm panel kanan) --}}
                    <div class="lg:col-span-5 space-y-4">
                        {{-- Card: Data Obat --}}
                        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Data Obat</h3>
                            </div>
                            <div class="px-5 py-4 space-y-3 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Kode Produk</div>
                                    <div class="font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $product['product_id'] ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Nama Produk</div>
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $product['product_name'] ?? '-' }}</div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Tipe</div>
                                        <div class="text-gray-700 dark:text-gray-200">{{ $product['product_type'] ?? '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Satuan</div>
                                        <div class="text-gray-700 dark:text-gray-200">{{ $product['uom_desc'] ?? ($product['uom_id'] ?? '-') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Qty per Box</div>
                                        <div class="font-mono text-gray-700 dark:text-gray-200">{{ number_format($product['qty_box'] ?? 0) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Min Stock</div>
                                        <div class="font-mono text-gray-700 dark:text-gray-200">{{ number_format($product['limit_stock'] ?? 0) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Rak</div>
                                        <div class="font-mono text-gray-700 dark:text-gray-200">{{ $product['product_rak'] ?? '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Harga Jual</div>
                                        <div class="font-mono text-gray-700 dark:text-gray-200">Rp {{ number_format($product['sales_price'] ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Card: Saldo Stock --}}
                        <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Saldo Stock {{ $year }}</h3>
                            </div>
                            <div class="px-5 py-4 space-y-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Saldo Awal</span>
                                    <span class="font-mono font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->saldo['awal']) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Total Masuk</span>
                                    <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">+ {{ number_format($this->saldo['masuk']) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Total Keluar</span>
                                    <span class="font-mono font-semibold text-rose-600 dark:text-rose-400">− {{ number_format($this->saldo['keluar']) }}</span>
                                </div>
                                <hr class="border-gray-200 dark:border-gray-700" />
                                <div class="flex items-center justify-between p-3 -mx-2 rounded-xl bg-brand/5 dark:bg-brand-lime/10">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">Saldo Akhir</span>
                                    <span class="font-mono text-2xl font-bold {{ $this->saldo['akhir'] < ($product['limit_stock'] ?? 0) ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                        {{ number_format($this->saldo['akhir']) }}
                                    </span>
                                </div>
                                @if (($product['limit_stock'] ?? 0) > 0 && $this->saldo['akhir'] < $product['limit_stock'])
                                    <div class="px-3 py-2 text-xs border rounded-lg border-rose-200 bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:border-rose-700 dark:text-rose-400">
                                        ⚠ Stock di bawah minimum ({{ number_format($product['limit_stock']) }})
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card: Stock Opname --}}
                        @hasanyrole('Admin|Apotek')
                            @php $isCurrentYear = $year === (string) now()->year; @endphp
                            <div class="bg-white border-2 border-amber-200 shadow-sm rounded-2xl dark:border-amber-700 dark:bg-gray-900">
                                <div class="px-5 py-4 border-b border-amber-200 dark:border-amber-700 bg-amber-50/50 dark:bg-amber-950/20">
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 class="text-base font-semibold text-amber-900 dark:text-amber-200">
                                            Stock Opname
                                        </h3>
                                        <x-toggle wire:model.live="editSaldo" trueValue="1" falseValue="0"
                                            :disabled="!$isCurrentYear">
                                            {{ $editSaldo === '1' ? 'Edit Aktif' : 'Edit Terkunci' }}
                                        </x-toggle>
                                    </div>
                                    @if (!$isCurrentYear)
                                        <p class="mt-1 text-xs text-rose-700 dark:text-rose-400">
                                            ⛔ Data terkunci. Opname hanya boleh tahun berjalan ({{ now()->year }}).
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-300/80">
                                            Selisih akan dicatat sebagai mutasi <strong>SO</strong> di history. Saldo awal tidak diubah.
                                        </p>
                                    @endif
                                </div>
                                <div class="px-5 py-4 space-y-3">
                                    <div>
                                        <x-input-label value="Stock Fisik (hasil hitung)" />
                                        <x-text-input-number wire:model="stockFisik"
                                            :disabled="$editSaldo !== '1'" class="mt-1" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Masukkan jumlah stock fisik hasil opname.
                                        </p>
                                    </div>

                                    @if ($editSaldo === '1' && $stockFisik !== null)
                                        @php
                                            // delta: stock fisik - saldo akhir saat ini
                                            //   delta > 0 → fisik LEBIH → mutasi MASUK (so_d)
                                            //   delta < 0 → fisik KURANG → mutasi KELUAR (so_k)
                                            $delta = (int) $stockFisik - $this->saldo['akhir'];
                                        @endphp
                                        <div class="p-3 space-y-1 text-xs border rounded-lg bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-500">Saldo akhir saat ini</span>
                                                <span class="font-mono">{{ number_format($this->saldo['akhir']) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-500">Stock fisik (input)</span>
                                                <span class="font-mono">{{ number_format($stockFisik) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between font-semibold">
                                                <span class="{{ $delta > 0 ? 'text-emerald-700' : ($delta < 0 ? 'text-rose-700' : 'text-gray-500') }}">
                                                    Selisih
                                                </span>
                                                <span class="font-mono {{ $delta > 0 ? 'text-emerald-700' : ($delta < 0 ? 'text-rose-700' : 'text-gray-500') }}">
                                                    {{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}
                                                </span>
                                            </div>
                                            <hr class="my-1 border-gray-200 dark:border-gray-700" />
                                            @if ($delta !== 0)
                                                <div class="flex items-center justify-between text-purple-800 dark:text-purple-300">
                                                    <span>Akan dicatat sebagai mutasi</span>
                                                    <span class="font-semibold">
                                                        SO {{ $delta > 0 ? '(MASUK)' : '(KELUAR)' }}
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Saldo awal tetap. Mutasi opname ditambahkan ke history.
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-500">Tidak ada selisih — tidak akan ditulis ke history.</div>
                                            @endif
                                        </div>
                                    @endif

                                    <x-confirm-button variant="primary"
                                        action="simpanOpname()"
                                        title="Simpan Stock Opname"
                                        message="Yakin menyimpan? Selisih akan ditulis ke history sebagai mutasi SO."
                                        confirmText="Ya, Simpan"
                                        cancelText="Batal"
                                        :disabled="!$isCurrentYear || $editSaldo !== '1' || $stockFisik === null"
                                        class="w-full justify-center">
                                        Simpan Opname
                                    </x-confirm-button>
                                </div>
                            </div>
                        @endhasanyrole
                    </div>
                </div>
            @else
                <div class="px-6 py-16 text-center bg-white border border-gray-200 border-dashed rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <svg class="w-16 h-16 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Klik salah satu obat di tabel kiri untuk melihat detail kartu stock.
                    </p>
                </div>
            @endif
                </div> {{-- close: KANAN col-span-8 wrapper --}}
            </div> {{-- close: MAIN GRID lg:grid-cols-12 --}}
        </div>
    </div>
</div>
