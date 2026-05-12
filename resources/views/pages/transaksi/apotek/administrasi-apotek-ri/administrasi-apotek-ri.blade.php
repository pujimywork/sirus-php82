<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-administrasi-apotek-ri'];

    /* ── State umum ── */
    public bool $isLoaded = false;
    public string $activeTab = 'obat';   // obat | kasir
    public ?int $slsNo = null;
    public ?int $rihdrNo = null;

    public ?string $regNo = null;
    public ?string $regName = null;
    public ?string $sex = null;
    public ?string $birthDate = null;
    public ?string $drName = null;
    public ?string $klaimId = null;
    public ?string $klaimDesc = null;
    public ?string $riStatus = null;
    public ?string $slsDateDisplay = null;
    public ?string $status = null;       // A | L

    public ?int $jasaKaryawan = 3000;    // alias acte_price (label baru)

    /** @var array<int,array<string,mixed>> */
    public array $items = [];

    /* ── Inline edit obat ── */
    public ?int $editingDtl = null;
    public array $editRow = [];

    public array $formEntryObat = [
        'productId' => '',
        'productName' => '',
        'price' => '',
        'qty' => 1,
        'carapakai' => 1,
        'kapsul' => 1,
        'takar' => 'Tablet',
        'ket' => '',
        'expDate' => '',
        'etiketStatus' => 0,
    ];

    /* ── State kasir ── */
    public ?int $bayar = null;
    public int $kembalian = 0;
    public int $kekurangan = 0;
    public ?string $accId = null;
    public ?string $accName = null;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    #[On('administrasi-apotek-ri.open')]
    public function open(int $slsNo, ?string $tab = null): void
    {
        $this->resetForm();
        $this->slsNo = $slsNo;
        $this->loadData();

        if (!$this->isLoaded) {
            return;
        }

        // Default tab: obat (kalau belum kasir), kasir (kalau sudah)
        $this->activeTab = $tab ?? ($this->status === 'L' ? 'kasir' : 'obat');

        $this->incrementVersion('modal-administrasi-apotek-ri');
        $this->dispatch('open-modal', name: 'administrasi-apotek-ri');
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['obat', 'kasir'])) {
            $this->activeTab = $tab;
        }
    }

    /* ===============================
     | LOAD DATA
     =============================== */
    private function loadData(): void
    {
        $hdr = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'r.klaim_id')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 's.acc_id')
            ->select(
                's.sls_no', 's.status', 's.rihdr_no', 's.reg_no', 's.acte_price',
                's.acc_id', 'a.acc_name', 's.sls_bayar', 's.sls_bon',
                DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date_display"),
                'p.reg_name', 'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'd.dr_name',
                'r.ri_status', 'r.klaim_id', 'k.klaim_desc',
            )
            ->where('s.sls_no', $this->slsNo)
            ->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data resep tidak ditemukan.');
            $this->isLoaded = false;
            return;
        }

        $this->rihdrNo = (int) $hdr->rihdr_no;
        $this->regNo = $hdr->reg_no;
        $this->regName = $hdr->reg_name;
        $this->sex = $hdr->sex;
        $this->birthDate = $hdr->birth_date;
        $this->drName = $hdr->dr_name;
        $this->klaimId = $hdr->klaim_id;
        $this->klaimDesc = $hdr->klaim_desc;
        $this->riStatus = $hdr->ri_status;
        $this->slsDateDisplay = $hdr->sls_date_display;
        $this->status = $hdr->status ?: 'A';
        $this->jasaKaryawan = (int) ($hdr->acte_price ?? 3000);
        $this->accId = $hdr->acc_id;
        $this->accName = $hdr->acc_name ?: $hdr->acc_id;
        $this->bayar = $this->status === 'L' ? (int) ($hdr->sls_bayar ?? 0) : null;

        $this->loadItems();
        $this->recalcKasir();
        $this->isLoaded = true;
    }

    private function loadItems(): void
    {
        $this->items = DB::table('imtxn_slsdtls as dtl')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'dtl.product_id')
            ->select(
                'dtl.sls_dtl', 'dtl.product_id',
                DB::raw("nvl(p.product_name,dtl.product_id) as product_name"),
                'dtl.qty', 'dtl.sales_price',
                'dtl.resep_carapakai', 'dtl.resep_kapsul', 'dtl.resep_takar', 'dtl.resep_ket',
                'dtl.etiket_status',
                DB::raw("to_char(dtl.exp_date,'yyyy-mm-dd') as exp_date"),
            )
            ->where('dtl.sls_no', $this->slsNo)
            ->orderBy('dtl.sls_dtl')
            ->get()
            ->map(fn($r) => [
                'slsDtl' => (int) $r->sls_dtl,
                'productId' => $r->product_id,
                'productName' => $r->product_name,
                'qty' => (int) ($r->qty ?? 0),
                'price' => (int) ($r->sales_price ?? 0),
                'total' => (int) ($r->sales_price ?? 0) * (int) ($r->qty ?? 0),
                'carapakai' => $r->resep_carapakai,
                'kapsul' => $r->resep_kapsul,
                'takar' => $r->resep_takar ?: 'Tablet',
                'ket' => $r->resep_ket,
                'etiketStatus' => (int) ($r->etiket_status ?? 0),
                'expDate' => $r->exp_date,                                                                  // yyyy-mm-dd (untuk input type="date" saat edit)
                'expDateDisplay' => $r->exp_date ? Carbon::parse($r->exp_date)->format('d/m/Y') : '-',     // dd/mm/yyyy (untuk tampilan tabel)
            ])
            ->toArray();
    }

    /* ===============================
     | GUARDS (computed)
     =============================== */
    #[Computed]
    public function isObatLocked(): bool
    {
        return $this->status === 'L' || strtoupper($this->riStatus ?? '') === 'P';
    }

    #[Computed]
    public function isKasirPosted(): bool
    {
        return $this->status === 'L';
    }

    #[Computed]
    public function canEditJasa(): bool
    {
        return !$this->isKasirPosted && auth()->user()->hasAnyRole(['Admin', 'Tu']);
    }

    /* ===============================
     | LOV PRODUCT (obat)
     =============================== */
    #[On('lov.selected.obat-apotek-ri')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isObatLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }
        if (!$payload) {
            $this->resetFormEntry();
            return;
        }

        $this->formEntryObat['productId'] = $payload['product_id'] ?? '';
        $this->formEntryObat['productName'] = $payload['product_name'] ?? '';
        $this->formEntryObat['price'] = $payload['sales_price'] ?? 0;
        $this->formEntryObat['expDate'] = Carbon::now()->addMonths(12)->format('Y-m-d');

        $this->dispatch('focus-input-qty-obat-ri');
    }

    /* ===============================
     | INSERT OBAT
     =============================== */
    public function insertObat(): void
    {
        if ($this->isObatLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi terkunci, tidak dapat ditambah.');
            return;
        }

        $this->validate(
            [
                'formEntryObat.productId' => 'required|exists:immst_products,product_id',
                'formEntryObat.price' => 'required|numeric|min:0',
                'formEntryObat.qty' => 'required|numeric|min:1',
                'formEntryObat.carapakai' => 'required|numeric|min:1',
                'formEntryObat.kapsul' => 'required|numeric|min:1',
                'formEntryObat.takar' => 'required|string',
                'formEntryObat.expDate' => 'required|date',
            ],
            [
                'formEntryObat.productId.required' => 'Obat wajib dipilih.',
                'formEntryObat.qty.min' => 'Qty minimal 1.',
                'formEntryObat.price.min' => 'Harga tidak valid.',
                'formEntryObat.expDate.required' => 'Exp Date wajib diisi.',
                'formEntryObat.expDate.date' => 'Format Exp Date tidak valid.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockSlshdrAndGuard();

                $maxDtl = (int) DB::table('imtxn_slsdtls')->select(DB::raw('nvl(max(sls_dtl)+1,1) as m'))->value('m');
                $expFormatted = Carbon::parse($this->formEntryObat['expDate'])->format('Y-m-d H:i:s');

                DB::table('imtxn_slsdtls')->insert([
                    'sls_dtl' => $maxDtl,
                    'sls_no' => $this->slsNo,
                    'product_id' => $this->formEntryObat['productId'],
                    'qty' => $this->formEntryObat['qty'],
                    'sales_price' => $this->formEntryObat['price'],
                    'exp_date' => DB::raw("to_date('{$expFormatted}','yyyy-mm-dd hh24:mi:ss')"),
                    'resep_carapakai' => $this->formEntryObat['carapakai'],
                    'resep_kapsul' => $this->formEntryObat['kapsul'],
                    'resep_takar' => $this->formEntryObat['takar'],
                    'resep_ket' => $this->formEntryObat['ket'] ?: null,
                    'etiket_status' => $this->formEntryObat['etiketStatus'],
                ]);
            });

            $this->loadItems();
            $this->recalcKasir();
            $this->resetFormEntry();
            $this->dispatch('focus-lov-obat-apotek-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
            $this->dispatch('refresh-after-antrian-apotek-ri.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT OBAT
     =============================== */
    public function startEdit(int $slsDtl): void
    {
        if ($this->isObatLocked) {
            return;
        }
        $row = collect($this->items)->firstWhere('slsDtl', $slsDtl);
        if (!$row) {
            return;
        }
        $this->editingDtl = $slsDtl;
        $this->editRow = [
            'qty' => $row['qty'],
            'carapakai' => $row['carapakai'],
            'kapsul' => $row['kapsul'],
            'takar' => $row['takar'],
            'ket' => $row['ket'] ?? '',
            'expDate' => $row['expDate'] ?? '',
            'etiketStatus' => $row['etiketStatus'],
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingDtl = null;
        $this->editRow = [];
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        if ($this->isObatLocked || !$this->editingDtl) {
            return;
        }

        $this->validate(
            [
                'editRow.qty' => 'required|numeric|min:1',
                'editRow.carapakai' => 'required|numeric|min:1',
                'editRow.kapsul' => 'required|numeric|min:1',
                'editRow.takar' => 'required|string',
                'editRow.expDate' => 'required|date',
            ],
            [
                'editRow.qty.required' => 'Qty wajib diisi.',
                'editRow.qty.min' => 'Qty minimal 1.',
                'editRow.expDate.required' => 'Exp Date wajib diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockSlshdrAndGuard();

                $expFormatted = Carbon::parse($this->editRow['expDate'])->format('Y-m-d H:i:s');

                DB::table('imtxn_slsdtls')
                    ->where('sls_dtl', $this->editingDtl)
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'qty' => $this->editRow['qty'],
                        'resep_carapakai' => $this->editRow['carapakai'],
                        'resep_kapsul' => $this->editRow['kapsul'],
                        'resep_takar' => $this->editRow['takar'],
                        'resep_ket' => $this->editRow['ket'] ?: null,
                        'exp_date' => DB::raw("to_date('{$expFormatted}','yyyy-mm-dd hh24:mi:ss')"),
                        'etiket_status' => $this->editRow['etiketStatus'],
                    ]);
            });

            $this->loadItems();
            $this->recalcKasir();
            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil diperbarui.');
            $this->dispatch('refresh-after-antrian-apotek-ri.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeObat(int $slsDtl): void
    {
        if ($this->isObatLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi terkunci, tidak dapat dihapus.');
            return;
        }

        try {
            DB::transaction(function () use ($slsDtl) {
                $this->lockSlshdrAndGuard();
                DB::table('imtxn_slsdtls')
                    ->where('sls_dtl', $slsDtl)
                    ->where('sls_no', $this->slsNo)
                    ->delete();
            });

            $this->loadItems();
            $this->recalcKasir();
            if ($this->editingDtl === $slsDtl) {
                $this->cancelEdit();
            }
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
            $this->dispatch('refresh-after-antrian-apotek-ri.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | JASA KARYAWAN (acte_price)
     =============================== */
    public function updatedJasaKaryawan(): void
    {
        // Mirip pola kasir-rj.updatedRjDiskon() — hanya update state lokal,
        // tidak persist ke DB di sini. Persist acte_price terjadi saat postTransaksi().
        $this->jasaKaryawan = max(0, (int) $this->jasaKaryawan);
        $this->recalcKasir();
    }

    /* ===============================
     | KASIR
     =============================== */
    #[On('lov.selected.kas-administrasi-apotek-ri')]
    public function onKasSelected(?array $payload = null): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        $this->dispatch('focus-input-bayar-ri');
    }

    public function updatedBayar(): void
    {
        $this->recalcKasir();
    }

    private function recalcKasir(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $totalAll = $this->totalAll;
        $this->kembalian = $bayar >= $totalAll ? $bayar - $totalAll : 0;
        $this->kekurangan = $bayar < $totalAll ? $totalAll - $bayar : 0;
    }

    public function postTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Apoteker', 'Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk memproses kasir.');
            return;
        }
        if ($this->isKasirPosted) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah diproses.');
            return;
        }

        $cekKas = DB::table('user_kas')->where('user_id', auth()->id())->count();
        if ($cekKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }
        if (strtoupper($this->riStatus ?? '') === 'P') {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi tidak dapat diproses.');
            return;
        }

        $empId = auth()->user()->emp_id;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $this->validate(
            [
                'accId' => 'required|string',
                'bayar' => 'required|integer|min:0',
            ],
            [
                'accId.required' => 'Akun kas belum dipilih.',
                'bayar.required' => 'Nominal bayar belum diisi.',
            ],
        );

        $bayar = (int) $this->bayar;
        $totalAll = $this->totalAll;
        $isBon = $bayar < $totalAll;
        $sisa = $isBon ? $totalAll - $bayar : 0;

        try {
            DB::transaction(function () use ($bayar, $totalAll, $isBon, $sisa, $empId) {
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
                $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
                if (!$current) {
                    throw new \RuntimeException('Data resep tidak ditemukan.');
                }
                if (strtoupper($current->status ?? 'A') === 'L') {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $shift =
                    DB::table('rstxn_shiftctls')
                        ->select('shift')
                        ->whereRaw("to_char(sysdate,'HH24:MI:SS') between shift_start and shift_end")
                        ->value('shift') ?? ($current->shift ?? 1);

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'status' => 'L',
                        'sls_total' => $totalAll,
                        'sls_bayar' => $bayar,
                        'sls_bon' => $isBon ? $bayar : null,
                        'bayar' => $bayar,        // legacy: nominal cash yang dibayar
                        'sisa' => $sisa,          // legacy: sisa kurang bayar (= bon)
                        'acc_id' => $this->accId,
                        'acte_price' => $this->jasaKaryawan,
                        'shift' => $shift,
                        'emp_id' => $empId,       // kasir yang post (sebelumnya placeholder '1' dari eresep)
                        'waktu_selesai_pelayanan' => DB::raw('sysdate'),
                    ]);

                if ($isBon) {
                    $maxBonNo = (int) DB::table('rstxn_ribonobats')->select(DB::raw('nvl(max(ribon_no)+1,1) as m'))->value('m');
                    DB::table('rstxn_ribonobats')->insert([
                        'ribon_no' => $maxBonNo,
                        'ribon_desc' => 'BR TGL: ' . ($current->sls_date ? Carbon::parse($current->sls_date)->format('d/m/Y') : '-') . ' NO BR: ' . $this->slsNo,
                        'ribon_date' => $current->sls_date,
                        'ribon_price' => $totalAll - $bayar,
                        'rihdr_no' => $this->rihdrNo,
                        'sls_no' => $this->slsNo,
                    ]);
                }
            });

            $this->status = 'L';
            // Invalidate computed cache (Livewire 3 caches per-request)
            unset($this->isKasirPosted, $this->isObatLocked, $this->canEditJasa);
            $this->incrementVersion('modal-administrasi-apotek-ri');

            $msg = $isBon
                ? 'Transaksi tersimpan. Sisa Rp ' . number_format($totalAll - $bayar) . ' masuk Bon Inap.'
                : 'Transaksi LUNAS tersimpan.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('refresh-after-antrian-apotek-ri.saved');
            $this->dispatch('cetak-kwitansi-ri-obat.open', slsNo: $this->slsNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function batalTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Apoteker', 'Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk membatalkan transaksi.');
            return;
        }
        if ($this->status !== 'L') {
            $this->dispatch('toast', type: 'error', message: 'Transaksi belum diproses, tidak perlu dibatalkan.');
            return;
        }
        if (strtoupper($this->riStatus ?? '') === 'P') {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi tidak dapat dibatalkan.');
            return;
        }

        try {
            DB::transaction(function () {
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
                $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
                if (!$current || strtoupper($current->status ?? 'A') !== 'L') {
                    throw new \RuntimeException('Transaksi sudah dalam status belum diproses.');
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'status' => 'A',
                        'sls_bayar' => null,
                        'sls_bon' => null,
                        'bayar' => null,
                        'sisa' => null,
                        'acc_id' => null,
                        'waktu_selesai_pelayanan' => null,
                        // emp_id sengaja TIDAK direset (audit trail siapa terakhir post)
                    ]);

                DB::table('rstxn_ribonobats')->where('sls_no', $this->slsNo)->delete();
            });

            $this->status = 'A';
            $this->bayar = null;
            $this->accId = null;
            $this->accName = null;
            // Invalidate computed cache
            unset($this->isKasirPosted, $this->isObatLocked, $this->canEditJasa);
            $this->recalcKasir();
            $this->incrementVersion('modal-administrasi-apotek-ri');

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
            $this->dispatch('refresh-after-antrian-apotek-ri.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function cetakKwitansi(): void
    {
        $this->dispatch('cetak-kwitansi-ri-obat.open', slsNo: $this->slsNo);
    }

    public function cetakEtiketItem(int $slsDtl): void
    {
        $this->dispatch('cetak-etiket-obat-ri.open', slsDtl: $slsDtl);
    }

    /* ===============================
     | CLOSE & HELPERS
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'administrasi-apotek-ri');
        $this->resetForm();
    }

    private function lockSlshdrAndGuard(): void
    {
        DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
        $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
        if (!$current) {
            throw new \RuntimeException('Data SLS tidak ditemukan.');
        }
        if (strtoupper($current->status ?? 'A') === 'L') {
            throw new \RuntimeException('Transaksi sudah diproses kasir, tidak dapat diubah.');
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntryObat']);
        $this->formEntryObat['qty'] = 1;
        $this->formEntryObat['carapakai'] = 1;
        $this->formEntryObat['kapsul'] = 1;
        $this->formEntryObat['takar'] = 'Tablet';
        $this->formEntryObat['etiketStatus'] = 0;
        $this->resetValidation();
        $this->incrementVersion('modal-administrasi-apotek-ri');
    }

    private function resetForm(): void
    {
        $this->reset([
            'slsNo', 'rihdrNo', 'isLoaded', 'activeTab',
            'regNo', 'regName', 'sex', 'birthDate',
            'drName', 'klaimId', 'klaimDesc', 'riStatus',
            'slsDateDisplay', 'status', 'items',
            'editingDtl', 'editRow',
            'bayar', 'kembalian', 'kekurangan', 'accId', 'accName',
        ]);
        $this->jasaKaryawan = 3000;
        $this->activeTab = 'obat';
        $this->resetFormEntry();
        $this->resetVersion();
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) collect($this->items)->sum('total');
    }

    #[Computed]
    public function totalAll(): int
    {
        return $this->subtotal + (int) ($this->jasaKaryawan ?? 0);
    }

    #[Computed]
    public function umurFormat(): string
    {
        if (!$this->birthDate) {
            return '-';
        }
        try {
            $diff = Carbon::createFromFormat('d/m/Y', $this->birthDate)->diff(now());
            return "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
        } catch (\Exception $e) {
            return '-';
        }
    }
};
?>

<div>
    <x-modal name="administrasi-apotek-ri" size="full" height="full" focusable>
        <div wire:key="{{ $this->renderKey('modal-administrasi-apotek-ri', [$slsNo ?? 'new']) }}"
            x-data
            x-on:focus-input-qty-obat-ri.window="$nextTick(() => $refs.inputQtyRi?.focus())"
            x-on:focus-lov-obat-apotek-ri.window="$nextTick(() => $refs.lovObatRi?.querySelector('input')?.focus())"
            x-on:focus-input-bayar-ri.window="$nextTick(() => $refs.inputBayarRi?.focus())">

            {{-- HEADER --}}
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M12 11h4m-4 4h4m-6-4h.01M10 15h.01" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Administrasi Apotek Pasien
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Kelola obat &amp; kasir resep rawat inap
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    {{-- Ringkasan biaya --}}
                    @if ($isLoaded)
                        <div class="flex gap-2">
                            <div class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-800/50 dark:border-gray-700">
                                <p class="text-[10px] uppercase text-gray-500">Subtotal Obat</p>
                                <p class="text-sm font-mono font-semibold text-gray-800 dark:text-gray-200">
                                    Rp {{ number_format($this->subtotal) }}
                                </p>
                            </div>
                            <div class="px-3 py-1.5 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-700">
                                <p class="text-[10px] uppercase text-amber-700 dark:text-amber-300">Jasa Karyawan</p>
                                <p class="text-sm font-mono font-semibold text-amber-800 dark:text-amber-200">
                                    Rp {{ number_format($jasaKaryawan) }}
                                </p>
                            </div>
                            <div class="px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-700">
                                <p class="text-[10px] uppercase text-blue-700 dark:text-blue-300">Total</p>
                                <p class="text-base font-mono font-bold text-blue-700 dark:text-blue-300">
                                    Rp {{ number_format($this->totalAll) }}
                                </p>
                            </div>
                        </div>
                    @endif
                    <button wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            @if (!$isLoaded)
                <div class="px-6 py-12 text-center text-gray-400">Memuat data...</div>
            @else

                {{-- INFO PASIEN --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 px-6 py-3 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <p class="text-xs text-gray-500">Pasien</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $regName }} ({{ $regNo }})
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $sex === 'L' ? 'Laki-laki' : 'Perempuan' }} · {{ $this->umurFormat }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Resep</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            No SLS {{ $slsNo }} · No RI {{ $rihdrNo }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $drName ?? '-' }} · {{ $slsDateDisplay }}
                        </p>
                    </div>
                    <div class="flex items-start gap-2 flex-wrap">
                        <x-badge :variant="$klaimId === 'JM' ? 'brand' : ($klaimId === 'UM' ? 'success' : 'alternative')">
                            {{ $klaimId === 'UM' ? 'UMUM' : ($klaimId === 'JM' ? 'BPJS' : ($klaimDesc ?? 'Asuransi Lain')) }}
                        </x-badge>
                        @if (strtoupper($riStatus ?? '') === 'P')
                            <x-badge variant="gray">Sudah Pulang</x-badge>
                        @elseif (strtoupper($riStatus ?? '') === 'A')
                            <x-badge variant="brand">Dirawat</x-badge>
                        @endif
                        @if ($status === 'L')
                            <x-badge variant="success">Sudah Diproses Kasir</x-badge>
                        @else
                            <x-badge variant="warning">Belum Diproses</x-badge>
                        @endif
                    </div>
                </div>

                {{-- TAB STRIP --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700 px-6">
                    <button type="button" wire:click="setActiveTab('obat')"
                        class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition
                            {{ $activeTab === 'obat'
                                ? 'text-blue-700 border-blue-600 dark:text-blue-300 dark:border-blue-400'
                                : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                        Obat
                    </button>
                    <button type="button" wire:click="setActiveTab('kasir')"
                        class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition
                            {{ $activeTab === 'kasir'
                                ? 'text-violet-700 border-violet-600 dark:text-violet-300 dark:border-violet-400'
                                : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                        Kasir
                    </button>
                </div>

                {{-- ═════════════════ TAB OBAT ═════════════════ --}}
                @if ($activeTab === 'obat')
                    <div class="px-6 py-4 max-h-[calc(100vh-380px)] overflow-y-auto space-y-4">

                        @if ($this->isObatLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                @if ($status === 'L')
                                    Transaksi sudah diproses kasir — daftar obat terkunci.
                                @else
                                    Pasien sudah pulang — daftar obat terkunci.
                                @endif
                            </div>
                        @endif

                        {{-- FORM INPUT --}}
                        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            @if ($this->isObatLocked)
                                <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
                            @elseif (empty($formEntryObat['productId']))
                                <div x-ref="lovObatRi">
                                    <livewire:lov.product.lov-product target="obat-apotek-ri" label="Cari Obat"
                                        placeholder="Ketik nama/kode obat..."
                                        wire:key="lov-obat-apotek-ri-{{ $slsNo }}-{{ $renderVersions['modal-administrasi-apotek-ri'] ?? 0 }}" />
                                </div>
                            @else
                                <div class="flex items-end gap-3 mb-3">
                                    <div class="w-28">
                                        <x-input-label value="Kode" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.productId" disabled class="w-full text-sm" />
                                    </div>
                                    <div class="flex-1">
                                        <x-input-label value="Nama Obat" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.productName" disabled class="w-full text-sm" />
                                    </div>
                                    <div class="w-20">
                                        <x-input-label value="Qty" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.qty" class="w-full text-sm" x-ref="inputQtyRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputHargaRi?.focus())" />
                                        @error('formEntryObat.qty')<x-input-error :messages="$message" class="mt-1" />@enderror
                                    </div>
                                    <div class="w-32">
                                        <x-input-label value="Harga" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.price" class="w-full text-sm" x-ref="inputHargaRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputCarapakaiRi?.focus())" />
                                        @error('formEntryObat.price')<x-input-error :messages="$message" class="mt-1" />@enderror
                                    </div>
                                </div>
                                <div class="flex items-end gap-3">
                                    <div class="w-20">
                                        <x-input-label value="x/Hari" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.carapakai" class="w-full text-sm" x-ref="inputCarapakaiRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputKapsulRi?.focus())" />
                                    </div>
                                    <div class="w-24">
                                        <x-input-label value="Per Minum" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.kapsul" class="w-full text-sm" x-ref="inputKapsulRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputTakarRi?.focus())" />
                                    </div>
                                    <div class="w-32">
                                        <x-input-label value="Takar" class="mb-1" />
                                        <x-select-input wire:model="formEntryObat.takar" x-ref="inputTakarRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputKetRi?.focus())"
                                            class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option>Tablet</option><option>Kapsul</option><option>Sirup</option>
                                            <option>Sachet</option><option>Tetes</option><option>Salep</option>
                                            <option>Injeksi</option><option>Lainnya</option>
                                        </x-select-input>
                                    </div>
                                    <div class="w-32">
                                        <x-input-label value="Keterangan" class="mb-1" />
                                        <x-text-input wire:model="formEntryObat.ket" class="w-full text-sm" x-ref="inputKetRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputExpDateRi?.focus())" />
                                    </div>
                                    <div class="w-36">
                                        <x-input-label value="Exp. Date" class="mb-1" />
                                        <x-text-input type="date" wire:model="formEntryObat.expDate" class="w-full text-sm" x-ref="inputExpDateRi"
                                            x-on:keyup.enter="$nextTick(() => $refs.inputEtiketRi?.focus())" />
                                        @error('formEntryObat.expDate')<x-input-error :messages="$message" class="mt-1" />@enderror
                                    </div>
                                    <div class="w-24">
                                        <x-input-label value="Etiket" class="mb-1" />
                                        <x-select-input wire:model="formEntryObat.etiketStatus" x-ref="inputEtiketRi"
                                            x-on:keyup.enter="$wire.insertObat()"
                                            class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="0">Belum</option><option value="1">Sudah</option>
                                        </x-select-input>
                                    </div>
                                    <div class="flex gap-2 pb-0.5">
                                        <x-primary-button wire:click="insertObat" wire:loading.attr="disabled" wire:target="insertObat">
                                            <span wire:loading.remove wire:target="insertObat" class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Tambah
                                            </span>
                                            <span wire:loading wire:target="insertObat"><x-loading /></span>
                                        </x-primary-button>
                                        <x-secondary-button wire:click="resetFormEntry">Batal</x-secondary-button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- TABEL OBAT --}}
                        <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Obat</h3>
                                <x-badge variant="gray">{{ count($items) }} item</x-badge>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                                        <tr>
                                            <th class="px-3 py-2">Kode</th>
                                            <th class="px-3 py-2">Nama Obat</th>
                                            <th class="px-3 py-2 text-center w-20">Qty</th>
                                            <th class="px-3 py-2 text-center w-24">Signa</th>
                                            <th class="px-3 py-2 text-center w-24">Takar</th>
                                            <th class="px-3 py-2 w-32">Ket.</th>
                                            <th class="px-3 py-2 w-32">Exp Date</th>
                                            <th class="px-3 py-2 text-center w-24">Etiket</th>
                                            <th class="px-3 py-2 text-right w-24">Harga</th>
                                            <th class="px-3 py-2 text-right w-28">Total</th>
                                            <th class="px-3 py-2 text-center w-28">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @forelse ($items as $item)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                                <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $item['productId'] }}</td>
                                                <td class="px-3 py-2 uppercase">{{ $item['productName'] }}</td>

                                                @if ($editingDtl === $item['slsDtl'])
                                                    <td class="px-3 py-2"><x-text-input wire:model="editRow.qty" class="w-full text-xs py-1" /></td>
                                                    <td class="px-3 py-2">
                                                        <div class="flex gap-1">
                                                            <x-text-input wire:model="editRow.carapakai" class="w-12 text-xs py-1" placeholder="x" />
                                                            <x-text-input wire:model="editRow.kapsul" class="w-12 text-xs py-1" placeholder="dd" />
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <x-select-input wire:model="editRow.takar" class="w-full text-xs py-1">
                                                            <option>Tablet</option><option>Kapsul</option><option>Sirup</option>
                                                            <option>Sachet</option><option>Tetes</option><option>Salep</option>
                                                            <option>Injeksi</option><option>Lainnya</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td class="px-3 py-2"><x-text-input wire:model="editRow.ket" class="w-full text-xs py-1" /></td>
                                                    <td class="px-3 py-2"><x-text-input type="date" wire:model="editRow.expDate" class="w-full text-xs py-1" /></td>
                                                    {{-- Etiket (read-only saat edit) --}}
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="text-xs text-gray-400">—</span>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right text-xs">{{ number_format($item['price']) }}</td>
                                                    <td class="px-3 py-2 font-mono text-right text-xs font-semibold">
                                                        {{ number_format($item['price'] * (int) ($editRow['qty'] ?? 0)) }}
                                                    </td>
                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                        <div class="flex items-center gap-1">
                                                            <x-secondary-button type="button" wire:click="saveEdit"
                                                                wire:loading.attr="disabled" wire:target="saveEdit"
                                                                class="px-3 py-1 text-xs text-green-700 border-green-300 hover:bg-green-50 dark:text-green-400 dark:border-green-600 dark:hover:bg-green-900/20">
                                                                Simpan
                                                            </x-secondary-button>
                                                            <x-secondary-button type="button" wire:click="cancelEdit" class="px-3 py-1 text-xs">
                                                                Batal
                                                            </x-secondary-button>
                                                        </div>
                                                    </td>
                                                @else
                                                    <td class="px-3 py-2 text-center font-mono">{{ $item['qty'] }}</td>
                                                    <td class="px-3 py-2 text-center text-xs text-gray-600">
                                                        S{{ $item['carapakai'] ?? '-' }}dd{{ $item['kapsul'] ?? '-' }}
                                                    </td>
                                                    <td class="px-3 py-2 text-center text-xs">{{ $item['takar'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-xs text-gray-600">{{ $item['ket'] ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ $item['expDateDisplay'] ?? '-' }}</td>

                                                    {{-- Etiket: kolom terpisah, ghost-button dengan ikon + teks --}}
                                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                                        <x-ghost-button wire:click="cetakEtiketItem({{ $item['slsDtl'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="cetakEtiketItem({{ $item['slsDtl'] }})"
                                                            class="!px-2 !py-1 !text-xs">
                                                            <span wire:loading.remove wire:target="cetakEtiketItem({{ $item['slsDtl'] }})">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                            </span>
                                                            <span wire:loading wire:target="cetakEtiketItem({{ $item['slsDtl'] }})">
                                                                <x-loading class="w-3 h-3" />
                                                            </span>
                                                            Etiket
                                                        </x-ghost-button>
                                                    </td>

                                                    <td class="px-3 py-2 font-mono text-right text-xs">{{ number_format($item['price']) }}</td>
                                                    <td class="px-3 py-2 font-mono text-right text-xs font-semibold">{{ number_format($item['total']) }}</td>

                                                    {{-- Aksi: Edit + Hapus (pola RJ — text + native delete) --}}
                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                        @if (!$this->isObatLocked)
                                                            <div class="flex items-center gap-1">
                                                                <x-secondary-button type="button"
                                                                    wire:click="startEdit({{ $item['slsDtl'] }})"
                                                                    class="px-3 py-1 text-xs">
                                                                    Edit
                                                                </x-secondary-button>
                                                                <button type="button"
                                                                    wire:click.prevent="removeObat({{ $item['slsDtl'] }})"
                                                                    wire:confirm="Hapus obat ini?"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="removeObat({{ $item['slsDtl'] }})"
                                                                    class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        @else
                                                            <span class="text-xs text-gray-400">—</span>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="11" class="px-3 py-8 text-center text-gray-400">Belum ada obat.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if (count($items) > 0)
                                        <tfoot class="bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700">
                                            <tr>
                                                <td colspan="9" class="px-3 py-2 text-right text-xs font-semibold">Subtotal</td>
                                                <td class="px-3 py-2 text-right font-mono font-bold">Rp {{ number_format($this->subtotal) }}</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>

                    </div>
                @endif

                {{-- ═════════════════ TAB KASIR (samakan dengan kasir-rj) ═════════════════ --}}
                @if ($activeTab === 'kasir')
                    @php
                        $sudahBayar = $this->isKasirPosted ? (int) ($bayar ?? 0) : 0;
                        $totalAll = $this->totalAll;
                        $sisaTagihan = max(0, $totalAll - $sudahBayar);
                    @endphp

                    <div class="px-6 py-4 max-h-[calc(100vh-380px)] overflow-y-auto space-y-4"
                        wire:key="{{ $this->renderKey('modal-administrasi-apotek-ri', [$slsNo ?? 'new']) }}-kasir-tab">

                        {{-- LOCKED BANNER --}}
                        @if ($this->isKasirPosted)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Transaksi sudah diproses — data terkunci, tidak dapat diubah.
                            </div>
                        @endif

                        {{-- RINGKASAN BIAYA — pola kasir-rj (flex horizontal dengan panah) --}}
                        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            <div class="flex items-stretch gap-3">

                                {{-- Subtotal Obat --}}
                                <div class="flex-1 px-4 py-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Subtotal Obat</p>
                                    <p class="text-base font-bold text-gray-800 dark:text-gray-100">Rp {{ number_format($this->subtotal) }}</p>
                                </div>

                                <div class="flex items-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>

                                {{-- Jasa Karyawan (editable) --}}
                                <div class="flex-1 px-4 py-3 border border-amber-200 rounded-xl dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                                    <p class="mb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                        Jasa Karyawan
                                        @if ($this->canEditJasa)
                                            <span class="opacity-60">(dapat diubah)</span>
                                        @endif
                                    </p>
                                    @if ($this->canEditJasa)
                                        <x-text-input wire:model.live.debounce.300ms="jasaKaryawan" type="number" min="0"
                                            class="w-full px-0 py-0 text-base font-bold text-amber-700 bg-transparent border-0
                                                dark:text-amber-300 focus:ring-0 focus:outline-none
                                                [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none
                                                [&::-webkit-inner-spin-button]:appearance-none"
                                            placeholder="0"
                                            x-on:keyup.enter="$dispatch('focus-lov-kas-administrasi-apotek-ri')" />
                                    @else
                                        <p class="text-base font-bold text-amber-700 dark:text-amber-300">Rp {{ number_format($jasaKaryawan) }}</p>
                                    @endif
                                </div>

                                <div class="flex items-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>

                                {{-- Total Tagihan --}}
                                <div class="flex-1 px-4 py-3 border border-blue-200 rounded-xl dark:border-blue-800/40 bg-blue-50 dark:bg-blue-900/10">
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mb-0.5">Total Tagihan</p>
                                    <p class="text-base font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($totalAll) }}</p>
                                </div>

                                <div class="flex items-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>

                                @if ($sudahBayar > 0)
                                    <div class="flex-1 px-4 py-3 border border-violet-200 rounded-xl dark:border-violet-800/40 bg-violet-50 dark:bg-violet-900/10">
                                        <p class="text-xs text-violet-600 dark:text-violet-400 mb-0.5">Dibayar</p>
                                        <p class="text-base font-bold text-violet-700 dark:text-violet-300">Rp {{ number_format($sudahBayar) }}</p>
                                    </div>
                                    <div class="flex items-center text-gray-300 dark:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                @endif

                                {{-- Sisa Tagihan / Bon --}}
                                <div class="flex-1 px-4 py-3 border rounded-xl
                                    {{ $sisaTagihan > 0
                                        ? 'border-rose-200 dark:border-rose-800/40 bg-rose-50 dark:bg-rose-900/10'
                                        : 'border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10' }}">
                                    <p class="text-xs mb-0.5 {{ $sisaTagihan > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        @if ($this->isKasirPosted && $sisaTagihan > 0)
                                            Bon Inap
                                        @else
                                            Sisa Tagihan
                                        @endif
                                    </p>
                                    <p class="text-base font-bold {{ $sisaTagihan > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                        Rp {{ number_format($sisaTagihan) }}
                                    </p>
                                </div>

                            </div>
                        </div>

                        {{-- FORM INPUT PEMBAYARAN --}}
                        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">

                            @if ($this->isKasirPosted)
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
                                        @hasanyrole('Apoteker|Admin|Tu')
                                            @if (strtoupper($riStatus ?? '') !== 'P')
                                                <x-confirm-button variant="danger" :action="'batalTransaksi()'"
                                                    title="Batal Transaksi"
                                                    message="Yakin ingin membatalkan transaksi ini? Bon Inap (jika ada) akan dihapus."
                                                    confirmText="Ya, batalkan" cancelText="Batal">
                                                    Batal Transaksi
                                                </x-confirm-button>
                                            @endif
                                        @endhasanyrole
                                    </div>

                                    @if ($sisaTagihan > 0)
                                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <p class="font-semibold">Status: Bon (sisa masuk Bon Inap)</p>
                                                <p class="mt-0.5">Sisa Rp {{ number_format($sisaTagihan) }} ditagih saat pasien pulang.</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-start gap-2 px-3 py-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-300">
                                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <p class="font-semibold">Status: Lunas</p>
                                                <p class="mt-0.5">Pembayaran sudah selesai. Klik "Batal Transaksi" jika perlu membatalkan.</p>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                        <div>Cara Bayar: <span class="font-mono">{{ $accName ?? $accId ?? '-' }}</span></div>
                                    </div>
                                </div>
                            @else
                                @if (strtoupper($riStatus ?? '') === 'P')
                                    <div class="px-3 py-2 mb-3 text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-lg dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-300">
                                        Pasien sudah pulang. Transaksi tidak dapat diproses.
                                    </div>
                                @else
                                    <div class="flex items-start gap-2 px-3 py-2 mb-3 text-xs text-gray-600 bg-gray-100 border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-gray-700 dark:text-gray-300">Panduan Kasir Apotek RI:</p>
                                            <ul class="mt-1 space-y-0.5 list-disc list-inside">
                                                <li>Pilih Akun Kas, isi nominal bayar, lalu klik "Post Transaksi".</li>
                                                <li>Bayar penuh = LUNAS. Bayar sebagian = BON, sisanya masuk Bon Inap (ditagih saat pulang).</li>
                                            </ul>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-end gap-3" x-data
                                    x-on:focus-lov-kas-administrasi-apotek-ri.window="$nextTick(() => $el.querySelector('input')?.focus())">
                                    <div class="w-80">
                                        <livewire:lov.kas.lov-kas
                                            target="kas-administrasi-apotek-ri"
                                            tipe="ri"
                                            label="Akun Kas"
                                            :initialAccId="$accId"
                                            wire:key="lov-kas-administrasi-apotek-ri-{{ $slsNo }}-{{ $renderVersions['modal-administrasi-apotek-ri'] ?? 0 }}" />
                                        <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                    </div>

                                    <div class="w-52">
                                        <x-input-label value="Nominal Bayar (Rp)" class="mb-1" />
                                        <x-text-input type="number" wire:model.live.debounce.300ms="bayar" placeholder="0"
                                            class="w-full font-mono text-right" min="0" x-ref="inputBayarRi"
                                            x-on:keyup.enter="$wire.postTransaksi()" />
                                        <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                                    </div>

                                    @if ((int) ($bayar ?? 0) >= $sisaTagihan && $sisaTagihan > 0)
                                        <div class="flex-1 px-4 py-2.5 rounded-xl border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10">
                                            <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Kembalian</p>
                                            <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($kembalian) }}</p>
                                        </div>
                                    @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $sisaTagihan)
                                        <div class="flex-1 px-4 py-2.5 rounded-xl border border-amber-200 dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                                            <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Kurang Bayar</p>
                                            <p class="text-lg font-bold text-amber-700 dark:text-amber-300">Rp {{ number_format($sisaTagihan - (int) ($bayar ?? 0)) }}</p>
                                        </div>
                                    @else
                                        <div class="flex-1"></div>
                                    @endif

                                    <div class="flex gap-2 pb-0.5">
                                        @hasanyrole('Apoteker|Admin|Tu')
                                            @if (strtoupper($riStatus ?? '') !== 'P')
                                                <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled" wire:target="postTransaksi">
                                                    <span wire:loading.remove wire:target="postTransaksi">Post Transaksi</span>
                                                    <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                                                </x-primary-button>
                                            @endif
                                        @endhasanyrole
                                    </div>
                                </div>

                                @if ((int) ($bayar ?? 0) >= $sisaTagihan && $sisaTagihan > 0)
                                    <div class="flex items-center gap-1.5 mt-3">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            Pembayaran akan diproses sebagai LUNAS
                                        </span>
                                    </div>
                                @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $sisaTagihan)
                                    <div class="flex items-center gap-1.5 mt-3">
                                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                            Pembayaran akan diproses sebagai BON — sisa Rp {{ number_format($sisaTagihan - (int) ($bayar ?? 0)) }} masuk Bon Inap
                                        </span>
                                    </div>
                                @endif
                            @endif

                        </div>
                    </div>
                @endif

                {{-- FOOTER --}}
                <div class="flex items-center justify-between gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    <div class="flex gap-2">
                        @if ($activeTab === 'kasir' && $this->isKasirPosted)
                            <x-info-button wire:click="cetakKwitansi" wire:loading.attr="disabled" wire:target="cetakKwitansi">
                                <span wire:loading.remove wire:target="cetakKwitansi" class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak Kwitansi
                                </span>
                                <span wire:loading wire:target="cetakKwitansi" class="flex items-center gap-1">
                                    <x-loading /> Menyiapkan...
                                </span>
                            </x-info-button>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </x-modal>
</div>
