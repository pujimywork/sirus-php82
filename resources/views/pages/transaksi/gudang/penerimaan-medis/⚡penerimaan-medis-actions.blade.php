<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    public string $formMode = 'create';

    // ── Header ──
    public ?int $rcvNo = null;
    public ?string $rcvDate = null;
    public ?string $suppId = null;
    public ?string $suppName = null;
    public ?string $rcvDesc = null;
    public ?int $spNo = null;
    public ?string $rcvStatus = null;

    // ── Detail entry (input barang) ──
    public ?string $entryProductId = null;
    public ?string $entryProductName = null;
    public ?int $entryQty = 1;
    public ?int $entryCostPrice = 0;
    public ?string $entryDiscount1 = null; // "10%" atau "50000"
    public ?string $entryDiscount2 = null;
    public ?string $entryRcvBath = null;
    public ?string $entryRcvEd = null;

    // ── Keranjang (in-memory array) ──
    public array $details = [];
    private int $detailCounter = 0;
    public array $pendingPriceUpdate = [];

    // ── Summary ──
    public int $totalBarang = 0;
    public int $totalQty = 0;
    public ?int $rcvDiskon = 0;
    public int $totalSetelahDiskon = 0;
    public ?float $rcvPpn = 0;
    public string $rcvPpnStatus = '1';
    public int $ppnNominal = 0;
    public ?int $rcvMaterai = 0;
    public int $grandTotal = 0;
    public ?int $bayar = 0;
    public int $sisa = 0;

    // ── Cara Bayar (akun kas keluar) ──
    public ?string $accId = null;
    public ?string $accName = null;

    public function mount(): void
    {
        $this->registerAreas(['modal', 'entry', 'bayar']);
    }

    /* ══════════════════════════════
     | OPEN CREATE
     ══════════════════════════════ */
    #[On('penerimaan-medis.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->rcvDate = Carbon::now()->format('d/m/Y H:i:s');
        $this->details = [];
        $this->detailCounter = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'penerimaan-medis-actions');
        $this->dispatch('focus-rcv-date');
    }

    /* ══════════════════════════════
     | OPEN EDIT
     ══════════════════════════════ */
    #[On('penerimaan-medis.openEdit')]
    public function openEdit(string $rcvNo): void
    {
        $this->resetFormFields();
        $this->formMode = 'edit';

        $hdr = DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->first();
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->rcvNo = (int) $hdr->rcv_no;
        $this->rcvDate = $hdr->rcv_date ? Carbon::parse($hdr->rcv_date)->format('d/m/Y H:i:s') : null;
        $this->suppId = $hdr->supp_id;
        $this->rcvDesc = $hdr->rcv_desc;
        $this->spNo = $hdr->sp_no ? (int) $hdr->sp_no : null;
        $this->rcvDiskon = (int) ($hdr->rcv_diskon ?? 0);
        $this->rcvPpn = (float) ($hdr->rcv_ppn ?? 0);
        $this->rcvPpnStatus = (string) ($hdr->rcv_ppn_status ?? '1');
        $this->rcvMaterai = (int) ($hdr->rcv_materai ?? 0);
        $this->bayar = (int) ($hdr->rcv_bayar ?? 0);
        $this->accId = $hdr->acc_id ?? null;
        $this->rcvStatus = (string) ($hdr->rcv_status ?? '');

        $this->loadDetailsFromDb();
        $this->hitungSemua();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'penerimaan-medis-actions');
        $this->dispatch('focus-rcv-desc');
    }

    /* ══════════════════════════════
     | LOV LISTENERS
     ══════════════════════════════ */
    #[On('lov.selected.supplier-rcv')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId = $payload['supp_id'] ?? null;
        $this->suppName = $payload['supp_name'] ?? null;
        $this->dispatch('focus-rcv-desc');
    }

    #[On('lov.selected.product-rcv')]
    public function onProductSelected(string $target, ?array $payload): void
    {
        $this->entryProductId = $payload['product_id'] ?? null;
        $this->entryProductName = $payload['product_name'] ?? null;
        $this->entryCostPrice = (int) ($payload['cost_price'] ?? 0);
        $this->entryQty = 1;
        $this->dispatch('focus-entry-qty');
    }

    #[On('lov.selected.akun-co-rcv')]
    public function onAkunCoSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
    }

    /* ══════════════════════════════
     | BARANG KE KERANJANG (IN-MEMORY)
     ══════════════════════════════ */
    protected function entryRules(): array
    {
        return [
            'entryProductId' => 'required|string',
            'entryQty' => 'required|integer|min:1',
            'entryCostPrice' => 'required|integer|min:0',
            'entryDiscount1' => 'nullable|string',
            'entryDiscount2' => 'nullable|string',
            'entryRcvBath' => 'required|string|max:25',
            'entryRcvEd' => 'required|date_format:d/m/Y',
        ];
    }

    protected function entryMessages(): array
    {
        return [
            'entryProductId.required' => 'Barang wajib dipilih.',
            'entryQty.required' => 'Qty wajib diisi.',
            'entryQty.min' => 'Qty minimal 1.',
            'entryCostPrice.required' => 'Harga wajib diisi.',
            'entryCostPrice.min' => 'Harga tidak boleh negatif.',
            'entryRcvBath.required' => 'Batch wajib diisi.',
            'entryRcvBath.max' => 'Batch maksimal 25 karakter.',
            'entryRcvEd.required' => 'Exp. Date wajib diisi.',
            'entryRcvEd.date_format' => 'Format Exp. Date harus dd/mm/yyyy.',
        ];
    }

    public function tambahBarang(): void
    {
        $this->validate($this->entryRules(), $this->entryMessages());

        // Cek duplikat product_id di keranjang
        $existingIdx = null;
        foreach ($this->details as $idx => $dtl) {
            if ($dtl['product_id'] === $this->entryProductId) {
                $existingIdx = $idx;
                break;
            }
        }

        // Parse diskon: "10%" → persen, "50000" → rupiah
        $qty = $this->entryQty ?? 0;
        $costPrice = $this->entryCostPrice ?? 0;
        $total = $qty * $costPrice;

        [$dtlPersen, $dtlDiskon] = $this->parseDiscount($this->entryDiscount1, $total);
        $afterDisc1 = $total - ($total * $dtlPersen) / 100 - $dtlDiskon;
        [$dtlPersen1, $dtlDiskon1] = $this->parseDiscount($this->entryDiscount2, $afterDisc1);

        $item = [
            'product_id' => $this->entryProductId,
            'product_name' => $this->entryProductName,
            'qty' => $qty,
            'cost_price' => $costPrice,
            'dtl_persen' => $dtlPersen,
            'dtl_diskon' => $dtlDiskon,
            'dtl_persen1' => $dtlPersen1,
            'dtl_diskon1' => $dtlDiskon1,
            'dsp_discount' => $this->entryDiscount1,
            'dsp_discount1' => $this->entryDiscount2,
            'rcv_bath' => $this->entryRcvBath,
            'rcv_ed' => $this->entryRcvEd,
        ];

        // Hitung vtotal
        $item['vtotal'] = $this->hitungVtotal($item);

        if ($existingIdx !== null) {
            // Update existing
            $item['_key'] = $this->details[$existingIdx]['_key'];
            $item['rcv_dtl'] = $this->details[$existingIdx]['rcv_dtl'] ?? null;
            $this->details[$existingIdx] = $item;
        } else {
            // New item
            $this->detailCounter++;
            $item['_key'] = $this->detailCounter;
            $item['rcv_dtl'] = null; // belum ada di DB
            $this->details[] = $item;
        }

        $this->resetEntry();
        $this->incrementVersion('entry');
        $this->hitungSemua();
        $this->dispatch('toast', type: 'success', message: 'Barang ditambahkan ke keranjang.');
        $this->dispatch('focus-entry-product');
    }

    /* ══════════════════════════════
     | CEK HARGA BELI vs MASTER
     ══════════════════════════════ */
    public function cekHargaBeliEntry(): void
    {
        if (!$this->entryProductId || !$this->entryCostPrice) {
            return;
        }

        $productName = $this->entryProductName ?? $this->entryProductId;
        $this->cekHargaBeli($this->entryProductId, $productName, (int) $this->entryCostPrice);
    }

    private function cekHargaBeli(string $productId, string $productName, int $newPrice): void
    {
        $masterPrice = (int) (DB::table('immst_products')->where('product_id', $productId)->value('cost_price') ?? 0);

        if ($newPrice === $masterPrice) {
            return;
        }

        $fmtOld = number_format($masterPrice);
        $fmtNew = number_format($newPrice);

        if ($newPrice > $masterPrice) {
            $this->dispatch('toast', type: 'warning', message: "Harga {$productName} NAIK, dari Rp {$fmtOld} menjadi Rp {$fmtNew}");
        } else {
            $this->dispatch('toast', type: 'info', message: "Harga {$productName} TURUN, dari Rp {$fmtOld} menjadi Rp {$fmtNew}");
        }

        // Cek setting auto update
        $autoUpdate = DB::table('rsmst_identitases')->value('rcvupdate_cost_price') ?? '0';

        if ($autoUpdate === '1') {
            // Auto update harga di master
            DB::table('immst_products')
                ->where('product_id', $productId)
                ->update(['cost_price' => $newPrice]);
            $this->dispatch('toast', type: 'success', message: "Harga master {$productName} otomatis diupdate.");
        } else {
            // Simpan pending untuk konfirmasi manual
            $this->pendingPriceUpdate = [
                'product_id' => $productId,
                'product_name' => $productName,
                'old_price' => $masterPrice,
                'new_price' => $newPrice,
            ];
        }
    }

    public function confirmUpdateHarga(): void
    {
        if (empty($this->pendingPriceUpdate)) {
            return;
        }

        DB::table('immst_products')
            ->where('product_id', $this->pendingPriceUpdate['product_id'])
            ->update(['cost_price' => $this->pendingPriceUpdate['new_price']]);

        $this->dispatch('toast', type: 'success', message: "Harga master {$this->pendingPriceUpdate['product_name']} berhasil diupdate.");
        $this->pendingPriceUpdate = [];
    }

    public function skipUpdateHarga(): void
    {
        $this->pendingPriceUpdate = [];
    }

    public function hapusBarang(int $key): void
    {
        $this->details = array_values(array_filter($this->details, fn($d) => $d['_key'] !== $key));
        $this->hitungSemua();
    }

    /* ══════════════════════════════
     | LOAD DETAILS FROM DB (EDIT)
     ══════════════════════════════ */
    private function loadDetailsFromDb(): void
    {
        $rows = DB::table('imtxn_receivedtls as a')
            ->leftJoin('immst_products as b', 'a.product_id', '=', 'b.product_id')
            ->where('a.rcv_no', $this->rcvNo)
            ->select(['a.rcv_dtl', 'a.product_id', 'b.product_name', 'a.qty', 'a.cost_price', 'a.dtl_persen', 'a.dtl_diskon', 'a.dtl_persen1', 'a.dtl_diskon1', 'a.rcv_bath', 'a.rcv_ed'])
            ->orderBy('a.rcv_dtl')
            ->get();

        $this->details = [];
        $this->detailCounter = 0;

        foreach ($rows as $row) {
            $this->detailCounter++;
            $sub = ($row->qty ?? 0) * ($row->cost_price ?? 0);
            $disc1 = ($sub * ($row->dtl_persen ?? 0)) / 100 + ($row->dtl_diskon ?? 0);
            $afterDisc1 = $sub - $disc1;
            $disc2 = ($afterDisc1 * ($row->dtl_persen1 ?? 0)) / 100 + ($row->dtl_diskon1 ?? 0);

            // Reconstruct display discount
            $dspDiscount = ($row->dtl_persen ?? 0) > 0 ? "{$row->dtl_persen}%" : (($row->dtl_diskon ?? 0) > 0 ? (string) (int) $row->dtl_diskon : null);
            $dspDiscount1 = ($row->dtl_persen1 ?? 0) > 0 ? "{$row->dtl_persen1}%" : (($row->dtl_diskon1 ?? 0) > 0 ? (string) (int) $row->dtl_diskon1 : null);

            $this->details[] = [
                '_key' => $this->detailCounter,
                'rcv_dtl' => $row->rcv_dtl,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'qty' => $row->qty,
                'cost_price' => $row->cost_price,
                'dtl_persen' => $row->dtl_persen,
                'dtl_diskon' => $row->dtl_diskon,
                'dtl_persen1' => $row->dtl_persen1,
                'dtl_diskon1' => $row->dtl_diskon1,
                'dsp_discount' => $dspDiscount,
                'dsp_discount1' => $dspDiscount1,
                'rcv_bath' => $row->rcv_bath,
                'rcv_ed' => $row->rcv_ed,
                'vtotal' => $afterDisc1 - $disc2,
            ];
        }
    }

    /* ══════════════════════════════
     | PARSE DISCOUNT ("10%" → persen, "50000" → rupiah)
     ══════════════════════════════ */
    private function parseDiscount(?string $input, float $maxTotal): array
    {
        if (!$input || trim($input) === '') {
            return [0, 0]; // [persen, rupiah]
        }

        $clean = str_replace(',', '', trim($input));

        if (str_ends_with($clean, '%')) {
            $persen = min(100, (float) str_replace('%', '', $clean));
            return [$persen, 0];
        }

        $rupiah = (float) $clean;
        if ($rupiah > $maxTotal) {
            $this->dispatch('toast', type: 'error', message: 'Diskon melebihi total.');
            return [0, 0];
        }

        return [0, $rupiah];
    }

    private function hitungVtotal(array $item): float
    {
        $total = ($item['qty'] ?? 0) * ($item['cost_price'] ?? 0);
        $persen1 = ($total * ($item['dtl_persen'] ?? 0)) / 100;
        $afterDisc1 = $total - $persen1 - ($item['dtl_diskon'] ?? 0);
        $persen2 = ($afterDisc1 * ($item['dtl_persen1'] ?? 0)) / 100;
        return $afterDisc1 - $persen2 - ($item['dtl_diskon1'] ?? 0);
    }

    /* ══════════════════════════════
     | HITUNG SEMUA (sesuai Oracle hitung_semua)
     ══════════════════════════════ */
    public function hitungSemua(): void
    {
        // total = SUM vtotal dari detail
        $this->totalBarang = (int) array_sum(array_column($this->details, 'vtotal'));
        $this->totalQty = (int) array_sum(array_column($this->details, 'qty'));

        // diskon header
        $diskon = $this->rcvDiskon ?? 0;

        // total setelah diskon
        $this->totalSetelahDiskon = $this->totalBarang - $diskon;

        // ppn — di Oracle dihitung di kedua kondisi (status '1' dan '0')
        $this->ppnNominal = (int) round(($this->totalSetelahDiskon * ($this->rcvPpn ?? 0)) / 100);

        // materai
        $materai = $this->rcvMaterai ?? 0;

        // grand total = setelah diskon + ppn + materai
        $this->grandTotal = $this->totalSetelahDiskon + $this->ppnNominal + $materai;

        // sisa = bayar - grand total
        $this->sisa = ($this->bayar ?? 0) - $this->grandTotal;
    }

    public function updatedRcvDiskon(): void
    {
        $this->hitungSemua();
    }
    public function updatedRcvPpn(): void
    {
        $this->hitungSemua();
    }
    public function updatedRcvPpnStatus(): void
    {
        $this->hitungSemua();
    }
    public function updatedRcvMaterai(): void
    {
        $this->hitungSemua();
    }
    public function updatedBayar(): void
    {
        $this->hitungSemua();
    }

    /* ══════════════════════════════
     | SIMPAN (INSERT HEADER + DETAILS)
     ══════════════════════════════ */
    public function simpan(): void
    {
        // Guard edit mode: status selain 'A' (Daftar Tunggu) sudah final — tidak boleh disimpan ulang.
        if ($this->formMode === 'edit' && $this->rcvNo) {
            $currentStatus = (string) (DB::table('imtxn_receivehdrs')->where('rcv_no', $this->rcvNo)->value('rcv_status') ?? '');
            if ($currentStatus !== 'A') {
                $this->dispatch('toast', type: 'error', message: "Status '{$currentStatus}' — transaksi sudah final, tidak bisa diubah. Batalkan dulu untuk mengembalikan ke Daftar Tunggu.");
                return;
            }
        }

        if (!$this->suppId) {
            $this->dispatch('toast', type: 'error', message: 'Supplier wajib dipilih.');
            return;
        }

        if (count($this->details) === 0) {
            $this->dispatch('toast', type: 'error', message: 'Keranjang masih kosong, tambahkan barang.');
            return;
        }

        if (!$this->accId) {
            $this->addError('accId', 'Cara bayar wajib dipilih.');
            $this->dispatch('toast', type: 'error', message: 'Cara bayar wajib dipilih.');
            return;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user.');
            return;
        }

        // Refresh summary supaya rcvStatus akurat
        $this->hitungSemua();
        $bayar = (int) ($this->bayar ?? 0);
        $grandTotal = (int) $this->grandTotal;
        $rcvStatus = $bayar >= $grandTotal && $grandTotal > 0 ? 'L' : 'H';
        $payDate = $rcvStatus === 'L' ? $this->rcvDate : null;
        $cashoutValue = min($bayar, $grandTotal);

        // Supplier name untuk keterangan cashout
        $suppName = DB::table('immst_suppliers')->where('supp_id', $this->suppId)->value('supp_name') ?? ($this->suppName ?? '');

        $now = Carbon::now();
        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();
        $shift = (string) ($findShift?->shift ?? 1);

        try {
            DB::transaction(function () use ($empId, $shift, $bayar, $grandTotal, $rcvStatus, $payDate, $cashoutValue, $suppName) {
                $payDateRaw = $payDate ? DB::raw("to_date('{$payDate}','dd/mm/yyyy hh24:mi:ss')") : null;

                if ($this->formMode === 'create') {
                    // Generate rcv_no
                    $rcvNo = (int) DB::selectOne('SELECT NVL(MAX(rcv_no),0)+1 AS val FROM imtxn_receivehdrs')->val;
                    $this->rcvNo = $rcvNo;

                    DB::table('imtxn_receivehdrs')->insert([
                        'rcv_no' => $rcvNo,
                        'rcv_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'supp_id' => $this->suppId,
                        'emp_id' => $empId,
                        'shift' => $shift,
                        'rcv_desc' => $this->rcvDesc,
                        'sp_no' => $this->spNo,
                        'rcv_diskon' => $this->rcvDiskon ?? 0,
                        'rcv_ppn' => $this->rcvPpn ?? 0,
                        'rcv_ppn_status' => $this->rcvPpnStatus,
                        'rcv_materai' => $this->rcvMaterai ?? 0,
                        'rcv_bayar' => $bayar,
                        'rcv_status' => $rcvStatus,
                        'pay_date' => $payDateRaw,
                        'acc_id' => $this->accId,
                    ]);

                    // Insert all details
                    foreach ($this->details as $dtl) {
                        DB::table('imtxn_receivedtls')->insert([
                            'rcv_no' => $rcvNo,
                            'rcv_dtl' => DB::raw('rcvdtl_seq.nextval'),
                            'product_id' => $dtl['product_id'],
                            'qty' => $dtl['qty'] ?? 0,
                            'cost_price' => $dtl['cost_price'] ?? 0,
                            'dtl_persen' => $dtl['dtl_persen'] ?? 0,
                            'dtl_diskon' => $dtl['dtl_diskon'] ?? 0,
                            'dtl_persen1' => $dtl['dtl_persen1'] ?? 0,
                            'dtl_diskon1' => $dtl['dtl_diskon1'] ?? 0,
                            'rcv_bath' => $dtl['rcv_bath'],
                            'rcv_ed' => $dtl['rcv_ed'],
                        ]);
                    }
                } else {
                    // Update header
                    DB::table('imtxn_receivehdrs')
                        ->where('rcv_no', $this->rcvNo)
                        ->update([
                            'rcv_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                            'supp_id' => $this->suppId,
                            'emp_id' => $empId,
                            'shift' => $shift,
                            'rcv_desc' => $this->rcvDesc,
                            'sp_no' => $this->spNo,
                            'rcv_diskon' => $this->rcvDiskon ?? 0,
                            'rcv_ppn' => $this->rcvPpn ?? 0,
                            'rcv_ppn_status' => $this->rcvPpnStatus,
                            'rcv_materai' => $this->rcvMaterai ?? 0,
                            'rcv_bayar' => $bayar,
                            'rcv_status' => $rcvStatus,
                            'pay_date' => $payDateRaw,
                            'acc_id' => $this->accId,
                        ]);

                    // Delete old details & re-insert
                    DB::table('imtxn_receivedtls')->where('rcv_no', $this->rcvNo)->delete();

                    foreach ($this->details as $dtl) {
                        DB::table('imtxn_receivedtls')->insert([
                            'rcv_no' => $this->rcvNo,
                            'rcv_dtl' => DB::raw('rcvdtl_seq.nextval'),
                            'product_id' => $dtl['product_id'],
                            'qty' => $dtl['qty'] ?? 0,
                            'cost_price' => $dtl['cost_price'] ?? 0,
                            'dtl_persen' => $dtl['dtl_persen'] ?? 0,
                            'dtl_diskon' => $dtl['dtl_diskon'] ?? 0,
                            'dtl_persen1' => $dtl['dtl_persen1'] ?? 0,
                            'dtl_diskon1' => $dtl['dtl_diskon1'] ?? 0,
                            'rcv_bath' => $dtl['rcv_bath'],
                            'rcv_ed' => $dtl['rcv_ed'],
                        ]);
                    }

                    // Hapus pembayaran lama (edit mode) supaya tidak double insert
                    DB::table('imtxn_receivepayments')->where('rcv_no', $this->rcvNo)->delete();
                    $oldCashoutNos = DB::table('imtxn_cashoutdtls')->where('rcv_no', $this->rcvNo)->pluck('cashout_no')->all();
                    DB::table('imtxn_cashoutdtls')->where('rcv_no', $this->rcvNo)->delete();
                    if (!empty($oldCashoutNos)) {
                        DB::table('imtxn_cashouthdrs')->whereIn('cashout_no', $oldCashoutNos)->delete();
                    }
                }

                // Insert pembayaran (cashout + receive payment) kalau bayar > 0
                if ($bayar > 0) {
                    $cashoutNo = (int) DB::selectOne('SELECT cashout_seq.nextval AS val FROM dual')->val;
                    $desc = 'Angsuran Awal, Atas Nama :"' . $suppName . '" Nota No "' . $this->rcvNo . '".';

                    DB::table('imtxn_cashouthdrs')->insert([
                        'shift' => $shift,
                        'acc_id' => $this->accId,
                        'cashout_no' => $cashoutNo,
                        'cashout_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'cashout_desc' => $desc,
                        'cashout_value' => $cashoutValue,
                        'emp_id' => $empId,
                        'supp_id' => $this->suppId,
                    ]);

                    DB::table('imtxn_cashoutdtls')->insert([
                        'cashout_no' => $cashoutNo,
                        'rcv_no' => $this->rcvNo,
                        'cashout_dtl' => DB::raw('codtl_seq.nextval'),
                    ]);

                    DB::table('imtxn_receivepayments')->insert([
                        'rcvp_no' => DB::raw('rcvp_seq.nextval'),
                        'rcv_no' => $this->rcvNo,
                        'rcvp_date' => DB::raw("to_date('{$this->rcvDate}','dd/mm/yyyy hh24:mi:ss')"),
                        'rcvp_value' => $cashoutValue,
                        'emp_id' => $empId,
                    ]);
                }
            });

            $msg = $rcvStatus === 'L'
                ? 'Obat dari PBF — lunas, disimpan & diposting.'
                : 'Obat dari PBF — status Hutang, disimpan & diposting.';
            $this->dispatch('toast', type: 'success', message: $msg);
            $this->closeBayar();
            $this->closeModal();
            $this->dispatch('penerimaan-medis.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ══════════════════════════════
     | DELETE
     ══════════════════════════════ */
    #[On('penerimaan-medis.requestDelete')]
    public function deleteFromGrid(string $rcvNo): void
    {
        if (
            !auth()
                ->user()
                ->hasAnyRole(['Admin', 'Tu'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($rcvNo) {
                DB::table('imtxn_receivedtls')->where('rcv_no', $rcvNo)->delete();
                DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->delete();
            });

            $this->dispatch('toast', type: 'success', message: 'Data penerimaan berhasil dihapus.');
            $this->dispatch('penerimaan-medis.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ══════════════════════════════
     | BATAL TRANSAKSI
     | Port dari Oracle Forms:
     |   - status 'A'      → set 'F'
     |   - status 'H'/'L'  → rollback cashout+payment + set status kembali 'A',
     |                       kecuali jumlah cashoutdtls untuk rcv_no > 1 (terkunci nota lain).
     ══════════════════════════════ */
    public function batalCurrentRcv(): void
    {
        if (!$this->rcvNo) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi belum disimpan.');
            return;
        }
        $this->batalFromGrid((string) $this->rcvNo);
        $this->closeModal();
    }

    #[On('penerimaan-medis.requestBatal')]
    public function batalFromGrid(string $rcvNo): void
    {
        if (
            !auth()
                ->user()
                ->hasAnyRole(['Admin', 'Tu'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan.');
            return;
        }

        try {
            $hdr = DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->first();
            if (!$hdr) {
                $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
                return;
            }

            $status = (string) ($hdr->rcv_status ?? '');

            if ($status === 'F') {
                $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah dibatalkan sebelumnya.');
                return;
            }

            if ($status === 'A') {
                DB::transaction(function () use ($rcvNo) {
                    DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->update(['rcv_status' => 'F']);
                });
                $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
                $this->dispatch('penerimaan-medis.saved');
                return;
            }

            if (in_array($status, ['H', 'L'], true)) {
                $cekPembayaran = (int) DB::table('imtxn_cashoutdtls')->where('rcv_no', $rcvNo)->count();
                if ($cekPembayaran > 1) {
                    $this->dispatch('toast', type: 'error', message: 'Tidak bisa membatalkan — status pembayaran terkunci dengan nota lain.');
                    return;
                }

                DB::transaction(function () use ($rcvNo) {
                    DB::table('imtxn_receivepayments')->where('rcv_no', $rcvNo)->delete();

                    $cashoutNos = DB::table('imtxn_cashoutdtls')->where('rcv_no', $rcvNo)->pluck('cashout_no')->all();
                    DB::table('imtxn_cashoutdtls')->where('rcv_no', $rcvNo)->delete();
                    if (!empty($cashoutNos)) {
                        DB::table('imtxn_cashouthdrs')->whereIn('cashout_no', $cashoutNos)->delete();
                    }

                    DB::table('imtxn_receivehdrs')->where('rcv_no', $rcvNo)->update([
                        'rcv_status' => 'A',
                        'rcv_bayar' => 0,
                        'pay_date' => null,
                    ]);
                });

                $this->dispatch('toast', type: 'success', message: 'Pembayaran di-rollback. Status kembali ke Daftar Tunggu (A).');
                $this->dispatch('penerimaan-medis.saved');
                return;
            }

            $this->dispatch('toast', type: 'warning', message: "Status '{$status}' tidak dikenal, pembatalan dilewati.");
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    /* ══════════════════════════════
     | MODAL PEMBAYARAN
     ══════════════════════════════ */
    public function openBayar(): void
    {
        if (empty($this->details)) {
            $this->dispatch('toast', type: 'error', message: 'Keranjang masih kosong.');
            return;
        }
        // Reset input pembayaran — biar saat buka modal bayar selalu mulai dari 0
        // (mis. kasus edit transaksi yang punya rcv_bayar lama tidak auto-fill ke field).
        $this->reset(['rcvDiskon', 'rcvPpn', 'rcvMaterai', 'bayar', 'accId', 'accName']);
        $this->resetErrorBag('accId');

        // Auto-fill PPN dari RSMST_IDENTITASES (port Oracle Forms):
        //   AUTO_PPN_STATUS = '0' → PPN 0%, selain itu → ambil PPN_VALUE master.
        $idn = DB::table('rsmst_identitases')->select('auto_ppn_status', 'ppn_value')->first();
        if ($idn) {
            $this->rcvPpnStatus = (string) ($idn->auto_ppn_status ?? '1');
            $this->rcvPpn = $this->rcvPpnStatus === '0' ? 0 : (float) ($idn->ppn_value ?? 0);
        }

        $this->hitungSemua();
        $this->incrementVersion('bayar');
        $this->dispatch('open-modal', name: 'penerimaan-medis-bayar');
    }

    public function closeBayar(): void
    {
        // Reset semua input pembayaran ke default 0 / null — biar tampilan bersih saat reopen.
        $this->reset(['rcvDiskon', 'rcvPpn', 'rcvMaterai', 'bayar', 'accId', 'accName']);
        $this->resetErrorBag('accId');
        $this->hitungSemua();
        // Bump versi area 'bayar' supaya LOV akun-co remount (state bersih) tanpa
        // ganggu LOV lain di main modal.
        $this->incrementVersion('bayar');
        $this->dispatch('close-modal', name: 'penerimaan-medis-bayar');
    }

    /* ══════════════════════════════
     | CLOSE & RESET
     ══════════════════════════════ */
    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'penerimaan-medis-actions');
        // JANGAN resetVersion — open berikutnya tinggal incrementVersion dapat angka baru.
        // Kalau di-reset ke 0, open kedua balik ke v1 → wire:key LOV sama → state persist.
        $this->incrementVersion('modal');
        $this->incrementVersion('entry');
    }

    protected function resetFormFields(): void
    {
        $this->reset(['rcvNo', 'rcvDate', 'suppId', 'suppName', 'rcvDesc', 'spNo', 'rcvStatus', 'entryProductId', 'entryProductName', 'entryQty', 'entryCostPrice', 'entryDiscount1', 'entryDiscount2', 'entryRcvBath', 'entryRcvEd', 'details', 'totalBarang', 'totalQty', 'rcvDiskon', 'totalSetelahDiskon', 'rcvPpn', 'rcvPpnStatus', 'ppnNominal', 'rcvMaterai', 'grandTotal', 'bayar', 'sisa', 'pendingPriceUpdate', 'accId', 'accName']);
        $this->detailCounter = 0;
        $this->resetValidation();
    }

    private function resetEntry(): void
    {
        $this->reset(['entryProductId', 'entryProductName', 'entryQty', 'entryCostPrice', 'entryDiscount1', 'entryDiscount2', 'entryRcvBath', 'entryRcvEd']);
    }
};
?>

<div>
    <x-modal name="penerimaan-medis-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rcvNo]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            @php
                                [$modalTitle, $modalSubtitle, $modeBadgeLabel, $modeBadgeVariant] = match (true) {
                                    $formMode === 'create' => ['Tambah Obat dari PBF', 'Catat penerimaan obat baru dari PBF / Supplier.', 'Tambah Baru', 'success'],
                                    in_array($rcvStatus, ['H', 'L'], true) => ["Lihat Obat dari PBF #{$rcvNo}", 'Transaksi sudah diposting — data hanya bisa dilihat. Gunakan "Batalkan Transaksi" kalau perlu revisi.', 'Lihat (Final)', 'alternative'],
                                    $rcvStatus === 'F' => ["Lihat Obat dari PBF #{$rcvNo}", 'Transaksi sudah dibatalkan — hanya untuk riwayat.', 'Batal', 'danger'],
                                    default => ["Edit Obat dari PBF #{$rcvNo}", 'Ubah data penerimaan obat (Daftar Tunggu).', 'Edit', 'warning'],
                                };
                            @endphp
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $modalTitle }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $modalSubtitle }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$modeBadgeVariant">{{ $modeBadgeLabel }}</x-badge>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-rcv-date.window="$nextTick(() => setTimeout(() => $refs.inputRcvDate?.focus(), 150))"
                x-on:focus-rcv-supplier.window="$nextTick(() => setTimeout(() => $refs.lovSupplierWrapper?.querySelector('input:not([disabled])')?.focus(), 150))"
                x-on:focus-rcv-desc.window="$nextTick(() => setTimeout(() => $refs.inputRcvDesc?.focus(), 150))"
                x-on:focus-entry-product.window="$nextTick(() => setTimeout(() => $refs.entryProductWrapper?.querySelector('input:not([disabled])')?.focus(), 150))"
                x-on:focus-entry-qty.window="$nextTick(() => setTimeout(() => $refs.inputEntryQty?.focus(), 150))"
                x-on:focus-entry-cost.window="$nextTick(() => setTimeout(() => $refs.inputEntryCost?.focus(), 150))"
                x-on:focus-btn-save-rcv.window="$nextTick(() => setTimeout(() => $refs.btnSaveRcv?.focus(), 150))">

                <div class="grid grid-cols-7 gap-4">
                    @php $isReadOnly = in_array($rcvStatus, ['H', 'L', 'F'], true); @endphp

                    {{-- ═══ SECTION 1: DATA PENERIMAAN ═══ --}}
                    <x-border-form title="Data Penerimaan" class="col-span-2">
                        <div class="grid grid-cols-1 gap-4 ">
                            <div>
                                <x-input-label value="Tanggal" :required="true" />
                                <x-text-input type="text" wire:model="rcvDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                    class="w-full mt-1" x-ref="inputRcvDate" @disabled($isReadOnly)
                                    x-on:keydown.enter.prevent="$refs.lovSupplierWrapper?.querySelector('input:not([disabled])')?.focus()" />
                            </div>
                            <div x-ref="lovSupplierWrapper">
                                <livewire:lov.supplier.lov-supplier target="supplier-rcv" label="Supplier"
                                    jenisSupplier="medis" :initialSuppId="$suppId" :readonly="$isReadOnly"
                                    wire:key="lov-supp-{{ $rcvNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan" />
                                <x-text-input type="text" wire:model="rcvDesc" placeholder="Keterangan penerimaan"
                                    class="w-full mt-1" x-ref="inputRcvDesc" @disabled($isReadOnly)
                                    x-on:keydown.enter.prevent="$refs.entryProductWrapper?.querySelector('input:not([disabled])')?.focus()" />
                            </div>
                        </div>
                    </x-border-form>

                    {{-- ═══ SECTION 2: TAMBAH BARANG + KERANJANG — tampil kalau supplier sudah dipilih ═══ --}}
                    @if (empty($suppId))
                        <div class="flex flex-col items-center justify-center gap-2 py-12 text-center border border-dashed col-span-5 rounded-2xl border-gray-300 bg-gray-50/60 dark:bg-gray-800/30 dark:border-gray-700">
                            <svg class="w-10 h-10 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Lengkapi <span class="font-semibold text-brand dark:text-brand-lime">Data
                                    Penerimaan</span> (Supplier) di atas untuk mulai menambahkan barang.
                            </p>
                        </div>
                    @else
                    <x-border-form :title="($isReadOnly ? 'Keranjang (' : 'Tambah Barang / Keranjang (') . count($details) . ' item)'" padding="p-0"
                        class="overflow-hidden col-span-5"
                        wire:key="entry-section-{{ $renderVersions['entry'] ?? 0 }}">
                        {{-- Form entry (sembunyikan saat status non-editable) --}}
                        @if (!$isReadOnly)
                        <div class="p-4 ">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-[2.5fr_0.7fr_repeat(5,1fr)_auto] items-start">
                                {{-- Barang --}}
                                <div class="col-span-2 sm:col-span-1" x-ref="entryProductWrapper">
                                    <div>
                                        <livewire:lov.product.lov-product target="product-rcv" label="Barang"
                                            :initialProductId="$entryProductId"
                                            wire:key="lov-prod-{{ $rcvNo ?? 'new' }}-{{ $renderVersions['entry'] ?? 0 }}" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryProductId')" />
                                    </div>
                                </div>
                                {{-- Qty --}}
                                <div>
                                    <div>
                                        <x-input-label value="Qty" />
                                        <x-text-input-number wire:model="entryQty" x-ref="inputEntryQty"
                                            :error="$errors->has('entryQty')"
                                            x-on:keydown.enter.prevent="$el.blur(); $nextTick(() => $refs.inputEntryCost?.focus())" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryQty')" />
                                    </div>
                                </div>
                                {{-- Harga --}}
                                <div>
                                    <div>
                                        <x-input-label value="Harga" />
                                        <x-text-input-number wire:model="entryCostPrice" x-ref="inputEntryCost"
                                            :error="$errors->has('entryCostPrice')"
                                            x-on:keydown.enter.prevent="$el.blur(); $nextTick(() => { $wire.cekHargaBeliEntry(); $refs.inputEntryDisc1?.focus() })" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryCostPrice')" />
                                    </div>
                                </div>
                                {{-- Disc 1 --}}
                                <div>
                                    <div>
                                        <x-input-label value="Disc 1" />
                                        <x-text-input type="text" wire:model="entryDiscount1" class="w-full mt-1"
                                            placeholder="10% / 5000" :error="$errors->has('entryDiscount1')" x-ref="inputEntryDisc1"
                                            x-on:keydown.enter.prevent="$refs.inputEntryDisc2?.focus()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryDiscount1')" />
                                    </div>
                                </div>
                                {{-- Disc 2 --}}
                                <div>
                                    <div>
                                        <x-input-label value="Disc 2" />
                                        <x-text-input type="text" wire:model="entryDiscount2" class="w-full mt-1"
                                            placeholder="5% / 1000" :error="$errors->has('entryDiscount2')" x-ref="inputEntryDisc2"
                                            x-on:keydown.enter.prevent="$refs.inputEntryBatch?.focus()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryDiscount2')" />
                                    </div>
                                </div>
                                {{-- Batch --}}
                                <div>
                                    <div>
                                        <x-input-label value="Batch" />
                                        <x-text-input type="text" wire:model="entryRcvBath" class="w-full mt-1"
                                            placeholder="Batch" :error="$errors->has('entryRcvBath')" x-ref="inputEntryBatch"
                                            x-on:keydown.enter.prevent="$refs.inputEntryEd?.focus()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryRcvBath')" />
                                    </div>
                                </div>
                                {{-- ED --}}
                                <div>
                                    <div>
                                        <x-input-label value="ED" />
                                        <x-text-input type="text" wire:model="entryRcvEd" class="w-full mt-1"
                                            placeholder="dd/mm/yyyy" :error="$errors->has('entryRcvEd')" x-ref="inputEntryEd"
                                            x-on:keydown.enter.prevent="$wire.tambahBarang()" />
                                    </div>
                                    <div class="mt-1 min-h-[1.25rem]">
                                        <x-input-error :messages="$errors->get('entryRcvEd')" />
                                    </div>
                                </div>
                                {{-- Button (label spacer + button + error spacer biar align dengan field lain) --}}
                                <div class="col-span-2 sm:col-span-1">
                                    <div class="invisible mb-1 text-xs select-none">&nbsp;</div>
                                    <x-primary-button type="button" wire:click="tambahBarang"
                                        class="justify-center w-full !px-2" title="Tambah ke Keranjang">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span class="sr-only">Tambah ke Keranjang</span>
                                    </x-primary-button>
                                    <div class="mt-1 min-h-[1.25rem]"></div>
                                </div>
                            </div>
                        </div>

                        {{-- ═══ KONFIRMASI UPDATE HARGA (amber = warning) ═══ --}}
                        @if (!empty($pendingPriceUpdate))
                            <div
                                class="mx-4 mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 border border-amber-300 rounded-xl bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                                <div class="flex-1 min-w-0 text-sm text-amber-800 dark:text-amber-200">
                                    Harga <strong>{{ $pendingPriceUpdate['product_name'] }}</strong> berubah
                                    (Rp {{ number_format($pendingPriceUpdate['old_price']) }} &rarr; Rp
                                    {{ number_format($pendingPriceUpdate['new_price']) }}).
                                    Update harga di master barang?
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <x-primary-button type="button" wire:click="confirmUpdateHarga"
                                        class="!py-1 !px-3 text-sm">
                                        Ya, Update
                                    </x-primary-button>
                                    <x-secondary-button type="button" wire:click="skipUpdateHarga"
                                        class="!py-1 !px-3 text-sm">
                                        Tidak
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endif
                        @endif {{-- /!$isReadOnly: tutup form entry --}}

                        {{-- ═══ TABEL KERANJANG — lanjutan section yang sama ═══ --}}
                        <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-700">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="text-xs tracking-wider text-gray-600 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-semibold">#</th>
                                        <th class="px-3 py-2 font-semibold">Barang</th>
                                        <th class="px-3 py-2 font-semibold text-right">Qty</th>
                                        <th class="px-3 py-2 font-semibold text-right">Harga</th>
                                        <th class="px-3 py-2 font-semibold text-right">Diskon</th>
                                        <th class="px-3 py-2 font-semibold text-right">Total</th>
                                        <th class="px-3 py-2 font-semibold">Batch</th>
                                        <th class="px-3 py-2 font-semibold">ED</th>
                                        @if (!$isReadOnly)
                                            <th class="px-3 py-2 font-semibold"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($details as $i => $dtl)
                                        <tr wire:key="dtl-{{ $dtl['_key'] }}"
                                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-2 text-gray-400">{{ $i + 1 }}</td>
                                            <td class="px-3 py-2">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $dtl['product_name'] ?? '-' }}</div>
                                                <div class="text-xs text-gray-400">{{ $dtl['product_id'] }}</div>
                                            </td>
                                            <td class="px-3 py-2 font-mono text-right">
                                                {{ number_format($dtl['qty'] ?? 0) }}</td>
                                            <td class="px-3 py-2 font-mono text-right">
                                                {{ number_format($dtl['cost_price'] ?? 0) }}</td>
                                            <td class="px-3 py-2 text-right">
                                                <div>{{ $dtl['dsp_discount'] ?? '-' }}</div>
                                                @if (!empty($dtl['dsp_discount1']))
                                                    <div class="text-xs text-gray-400">{{ $dtl['dsp_discount1'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td
                                                class="px-3 py-2 font-mono font-semibold text-right text-brand dark:text-brand-lime">
                                                Rp {{ number_format($dtl['vtotal'] ?? 0) }}</td>
                                            <td class="px-3 py-2">{{ $dtl['rcv_bath'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $dtl['rcv_ed'] ?? '-' }}</td>
                                            @if (!$isReadOnly)
                                                <td class="px-3 py-2">
                                                    <x-confirm-button variant="danger" :action="'hapusBarang(' . $dtl['_key'] . ')'"
                                                        title="Hapus Barang"
                                                        message="Hapus {{ $dtl['product_name'] ?? '' }} dari keranjang?"
                                                        confirmText="Ya" cancelText="Batal" class="!py-1 !px-2 text-xs">
                                                        X
                                                    </x-confirm-button>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $isReadOnly ? 8 : 9 }}"
                                                class="px-4 py-8 text-sm text-center text-gray-400">
                                                Keranjang kosong{{ $isReadOnly ? '.' : '. Tambahkan barang di atas.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-border-form>
                    @endif
                </div>

                {{-- ═══ RINCIAN PEMBAYARAN — hanya tampil kalau transaksi sudah dibayar (H/L) ═══ --}}
                @if (in_array($rcvStatus, ['H', 'L'], true))
                    <x-border-form :title="'Rincian Pembayaran — ' . ($rcvStatus === 'L' ? 'Lunas' : 'Hutang')">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div
                                class="p-4 space-y-2 text-sm border bg-gradient-to-br from-gray-50 to-white border-brand/20 rounded-xl dark:from-gray-800 dark:to-gray-900 dark:border-brand-lime/25">
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Total Qty</span>
                                    <span class="font-mono font-semibold">{{ number_format($totalQty) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Total Barang</span>
                                    <span class="font-mono">Rp {{ number_format($totalBarang) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Diskon</span>
                                    <span class="font-mono text-rose-600 dark:text-rose-400">- Rp
                                        {{ number_format($rcvDiskon ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Setelah Diskon</span>
                                    <span class="font-mono">Rp {{ number_format($totalSetelahDiskon) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>PPN ({{ $rcvPpn ?? 0 }}%)</span>
                                    <span class="font-mono">Rp {{ number_format($ppnNominal) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Materai</span>
                                    <span class="font-mono">Rp {{ number_format($rcvMaterai ?? 0) }}</span>
                                </div>
                                <hr class="border-gray-300 dark:border-gray-700">
                                <div class="flex items-center justify-between text-base font-bold">
                                    <span class="tracking-wide uppercase text-brand dark:text-brand-lime">Grand
                                        Total</span>
                                    <span class="font-mono text-brand dark:text-brand-lime">Rp
                                        {{ number_format($grandTotal) }}</span>
                                </div>
                            </div>

                            <div
                                class="p-4 space-y-2 text-sm border bg-gradient-to-br from-gray-50 to-white rounded-xl dark:from-gray-800 dark:to-gray-900 {{ $rcvStatus === 'L' ? 'border-emerald-300 dark:border-emerald-800' : 'border-amber-300 dark:border-amber-800' }}">
                                <div
                                    class="flex items-center gap-2 pb-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $rcvStatus === 'L' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                        {{ $rcvStatus === 'L' ? 'LUNAS' : 'HUTANG' }}
                                    </span>
                                    @if ($accName || $accId)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            via <strong
                                                class="text-gray-700 dark:text-gray-200">{{ $accName ?: $accId }}</strong>
                                        </span>
                                    @endif
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Dibayar</span>
                                    <span class="font-mono font-semibold">Rp {{ number_format($bayar ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                    <span>Grand Total</span>
                                    <span class="font-mono">Rp {{ number_format($grandTotal) }}</span>
                                </div>
                                <hr class="border-gray-300 dark:border-gray-700">
                                <div class="flex items-center justify-between text-base font-bold">
                                    <span>{{ ($bayar ?? 0) >= $grandTotal ? 'Kembalian' : 'Sisa / Kurang' }}</span>
                                    @php
                                        $selisih = ($bayar ?? 0) - $grandTotal;
                                    @endphp
                                    <span
                                        class="font-mono {{ $selisih >= 0 ? 'text-brand dark:text-brand-lime' : 'text-rose-600 dark:text-rose-400' }}">Rp
                                        {{ number_format(abs($selisih)) }}</span>
                                </div>
                            </div>
                        </div>
                    </x-border-form>
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-3 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    {{-- KIRI: Batalkan Transaksi --}}
                    <div class="flex items-center gap-3">
                        @hasanyrole('Admin|Tu')
                            @if ($formMode === 'edit' && $rcvNo && $rcvStatus !== 'F')
                                @php
                                    $batalMsg = $rcvStatus === 'A'
                                        ? 'Transaksi (Daftar Tunggu) akan di-set ke status Batal (F). Lanjut?'
                                        : 'Pembayaran akan di-rollback & status kembali ke Daftar Tunggu (A). Lanjut?';
                                @endphp
                                <x-confirm-button variant="danger" action="batalCurrentRcv()"
                                    title="Batalkan Transaksi" :message="$batalMsg"
                                    confirmText="Ya, batalkan" cancelText="Tidak">
                                    Batalkan Transaksi
                                </x-confirm-button>
                            @endif
                        @endhasanyrole
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <strong>{{ count($details) }}</strong> item &middot; Grand Total:
                            <strong class="font-mono text-brand dark:text-brand-lime">Rp
                                {{ number_format($grandTotal) }}</strong>
                        </div>
                    </div>

                    {{-- KANAN: Tutup + Bayar --}}
                    <div class="flex flex-wrap justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                        <x-primary-button type="button" wire:click="openBayar"
                            :disabled="count($details) === 0 || ($formMode === 'edit' && $rcvStatus !== 'A')">
                            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 10h18M5 10V6a2 2 0 012-2h10a2 2 0 012 2v4M5 10v10a2 2 0 002 2h10a2 2 0 002-2V10" />
                            </svg>
                            Proses Pembayaran
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL PEMBAYARAN — input diskon/PPN/materai/bayar + summary
         ═══════════════════════════════════════════════════════════════ --}}
    <x-modal name="penerimaan-medis-bayar" size="2xl" focusable>
        <div class="flex flex-col" wire:key="{{ $this->renderKey('bayar', [$rcvNo]) }}">
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand-lime/15">
                            <svg class="w-5 h-5 text-brand dark:text-brand-lime" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 10h18M5 10V6a2 2 0 012-2h10a2 2 0 012 2v4M5 10v10a2 2 0 002 2h10a2 2 0 002-2V10" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pembayaran</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Isi diskon, PPN, materai, dan nilai bayar sebelum posting.
                            </p>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeBayar" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {{-- Kiri: input --}}
                    <div class="space-y-3">
                        <div>
                            <livewire:lov.akun-co.lov-akun-co target="akun-co-rcv" label="Cara Bayar (Akun Kas)"
                                :initialAccId="$accId"
                                wire:key="lov-akun-co-{{ $rcvNo ?? 'new' }}-{{ $renderVersions['bayar'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                        </div>
                        <div class="flex items-center gap-3">
                            <x-input-label value="Diskon (Rp)" class="w-32 shrink-0" />
                            <x-text-input-number wire:model.live="rcvDiskon" />
                        </div>
                        <div class="flex items-center gap-3">
                            <x-input-label value="PPN (%)" class="w-32 shrink-0" />
                            <x-text-input-number wire:model.live="rcvPpn" />
                        </div>
                        <div class="flex items-center gap-3">
                            <x-input-label value="Materai (Rp)" class="w-32 shrink-0" />
                            <x-text-input-number wire:model.live="rcvMaterai" />
                        </div>
                        <div class="flex items-center gap-3">
                            <x-input-label value="Bayar (Rp)" class="w-32 shrink-0" />
                            <x-text-input-number wire:model.live="bayar" />
                        </div>
                    </div>

                    {{-- Kanan: ringkasan --}}
                    <div
                        class="p-4 space-y-2 text-sm border bg-gradient-to-br from-gray-50 to-white border-brand/20 rounded-xl dark:from-gray-800 dark:to-gray-900 dark:border-brand-lime/25">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Total Qty</span>
                            <span class="font-mono font-semibold">{{ number_format($totalQty) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Total</span>
                            <span class="font-mono">Rp {{ number_format($totalBarang) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Diskon</span>
                            <span class="font-mono text-rose-600 dark:text-rose-400">- Rp
                                {{ number_format($rcvDiskon ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Setelah Diskon</span>
                            <span class="font-mono">Rp {{ number_format($totalSetelahDiskon) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>PPN ({{ $rcvPpn ?? 0 }}%)</span>
                            <span class="font-mono">Rp {{ number_format($ppnNominal) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Materai</span>
                            <span class="font-mono">Rp {{ number_format($rcvMaterai ?? 0) }}</span>
                        </div>
                        <hr class="border-gray-300 dark:border-gray-700">
                        <div class="flex items-center justify-between text-lg font-bold">
                            <span class="tracking-wide uppercase text-brand dark:text-brand-lime">Grand Total</span>
                            <span class="font-mono text-brand dark:text-brand-lime">Rp
                                {{ number_format($grandTotal) }}</span>
                        </div>
                        <hr class="border-gray-300 dark:border-gray-700">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Bayar</span>
                            <span class="font-mono">Rp {{ number_format($bayar ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span>Sisa</span>
                            <span
                                class="font-mono {{ $sisa >= 0 ? 'text-brand dark:text-brand-lime' : 'text-rose-600 dark:text-rose-400' }}">Rp
                                {{ number_format($sisa) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer modal bayar --}}
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-50 border-t border-gray-200 dark:bg-gray-800/50 dark:border-gray-700">
                <x-secondary-button type="button" wire:click="closeBayar">
                    Batal
                </x-secondary-button>
                <x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled"
                    x-ref="btnSaveRcv">
                    <span wire:loading.remove>Simpan &amp; Posting</span>
                    <span wire:loading><x-loading /> Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
