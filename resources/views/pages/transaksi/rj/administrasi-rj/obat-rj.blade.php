<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-obat-rj'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjObat = [];

    // State inline editing
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
        'catatanKhusus' => '-',
        'etiketStatus' => 0,
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo) {
            $this->findData($this->rjNo);
            $this->isFormLocked = $this->checkRJStatus($this->rjNo);
        }
    }

    /* ===============================
     | FIND DATA
     =============================== */
    private function findData(int $rjNo): void
    {
        $this->rjObat = DB::table('rstxn_rjobats')
            ->join('immst_products', 'immst_products.product_id', 'rstxn_rjobats.product_id')
            ->select('rstxn_rjobats.rjobat_dtl', 'rstxn_rjobats.product_id', 'immst_products.product_name', 'rstxn_rjobats.qty', 'rstxn_rjobats.price', 'rstxn_rjobats.rj_carapakai', 'rstxn_rjobats.rj_kapsul', 'rstxn_rjobats.rj_takar', 'rstxn_rjobats.rj_ket', 'rstxn_rjobats.exp_date', 'rstxn_rjobats.catatan_khusus', 'rstxn_rjobats.etiket_status')
            ->where('rj_no', $rjNo)
            ->orderBy('rstxn_rjobats.rjobat_dtl')
            ->get()
            ->map(
                fn($r) => [
                    'rjobatDtl' => (int) $r->rjobat_dtl,
                    'productId' => $r->product_id,
                    'productName' => $r->product_name,
                    'qty' => $r->qty,
                    'price' => $r->price,
                    'total' => $r->price * $r->qty,
                    'carapakai' => $r->rj_carapakai,
                    'kapsul' => $r->rj_kapsul,
                    'takar' => $r->rj_takar,
                    'ket' => $r->rj_ket,
                    'expDate' => $r->exp_date,
                    'catatanKhusus' => $r->catatan_khusus,
                    'etiketStatus' => $r->etiket_status,
                ],
            )
            ->toArray();
    }

    /* ===============================
     | REFRESH — event dari parent
     =============================== */
    #[On('administrasi-obat-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    /* ===============================
     | LOV SELECTED — PRODUCT
     =============================== */
    #[On('lov.selected.obat-rj')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntryObat['productId'] = '';
            $this->formEntryObat['productName'] = '';
            $this->formEntryObat['price'] = '';
            return;
        }

        $rjDate = DB::table('rstxn_rjhdrs')->where('rj_no', $this->rjNo)->value('rj_date');

        $this->formEntryObat['productId'] = $payload['product_id'];
        $this->formEntryObat['productName'] = $payload['product_name'];
        $this->formEntryObat['price'] = $payload['sales_price'];
        $this->formEntryObat['expDate'] = $rjDate ? Carbon::parse($rjDate)->addDays(30)->format('Y-m-d') : Carbon::now()->addDays(30)->format('Y-m-d');

        $this->dispatch('focus-input-qty-obat');
    }

    /* ===============================
     | INSERT OBAT
     =============================== */
    public function insertObat(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryObat.productId' => 'bail|required|exists:immst_products,product_id',
                'formEntryObat.price' => 'bail|required|numeric|min:0',
                'formEntryObat.qty' => 'bail|required|numeric|min:1',
                'formEntryObat.carapakai' => 'bail|required|numeric|min:1',
                'formEntryObat.kapsul' => 'bail|required|numeric|min:1',
                'formEntryObat.takar' => 'bail|required|string',
                'formEntryObat.expDate' => 'bail|required|date',
                'formEntryObat.catatanKhusus' => 'bail|nullable|string',
                'formEntryObat.etiketStatus' => 'bail|required|integer',
            ],
            [
                'formEntryObat.productId.required' => 'Obat harus dipilih.',
                'formEntryObat.productId.exists' => 'Obat tidak valid.',
                'formEntryObat.price.required' => 'Harga harus diisi.',
                'formEntryObat.price.numeric' => 'Harga harus berupa angka.',
                'formEntryObat.qty.required' => 'Jumlah harus diisi.',
                'formEntryObat.qty.min' => 'Jumlah minimal 1.',
                'formEntryObat.carapakai.required' => 'Cara pakai harus diisi.',
                'formEntryObat.kapsul.required' => 'Jumlah per minum harus diisi.',
                'formEntryObat.takar.required' => 'Takaran harus diisi.',
                'formEntryObat.expDate.required' => 'Tanggal kadaluarsa harus diisi.',
                'formEntryObat.expDate.date' => 'Format tanggal kadaluarsa tidak valid.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock row RJ — cegah race condition sequence rjobat_dtl
                $this->lockRJRow($this->rjNo);

                $last = DB::table('rstxn_rjobats')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();

                $expDateFormatted = Carbon::parse($this->formEntryObat['expDate'])->format('Y-m-d H:i:s');

                DB::table('rstxn_rjobats')->insert([
                    'rjobat_dtl' => $last->rjobat_dtl_max,
                    'rj_no' => $this->rjNo,
                    'product_id' => $this->formEntryObat['productId'],
                    'qty' => $this->formEntryObat['qty'],
                    'price' => $this->formEntryObat['price'],
                    'rj_carapakai' => $this->formEntryObat['carapakai'],
                    'rj_kapsul' => $this->formEntryObat['kapsul'],
                    'rj_takar' => $this->formEntryObat['takar'],
                    'rj_ket' => $this->formEntryObat['ket'] ?: null,
                    'catatan_khusus' => $this->formEntryObat['catatanKhusus'] ?: '-',
                    'exp_date' => DB::raw("to_date('" . $expDateFormatted . "','yyyy-mm-dd hh24:mi:ss')"),
                    'etiket_status' => $this->formEntryObat['etiketStatus'],
                ]);

                $this->rjObat[] = [
                    'rjobatDtl' => (int) $last->rjobat_dtl_max,
                    'productId' => $this->formEntryObat['productId'],
                    'productName' => $this->formEntryObat['productName'],
                    'qty' => $this->formEntryObat['qty'],
                    'price' => $this->formEntryObat['price'],
                    'total' => $this->formEntryObat['price'] * $this->formEntryObat['qty'],
                    'carapakai' => $this->formEntryObat['carapakai'],
                    'kapsul' => $this->formEntryObat['kapsul'],
                    'takar' => $this->formEntryObat['takar'],
                    'ket' => $this->formEntryObat['ket'],
                    'expDate' => $this->formEntryObat['expDate'],
                    'catatanKhusus' => $this->formEntryObat['catatanKhusus'],
                    'etiketStatus' => $this->formEntryObat['etiketStatus'],
                ];
            });

            $this->resetFormEntry();
            $this->dispatch('focus-lov-obat-rj');
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT — START / CANCEL
     =============================== */
    public function startEdit(int $rjobatDtl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $row = collect($this->rjObat)->firstWhere('rjobatDtl', $rjobatDtl);
        if (!$row) {
            return;
        }

        $this->editingDtl = $rjobatDtl;
        $this->editRow = [
            'qty' => $row['qty'],
            'carapakai' => $row['carapakai'],
            'kapsul' => $row['kapsul'],
            'takar' => $row['takar'],
            'ket' => $row['ket'] ?? '',
            'expDate' => $row['expDate'] ? Carbon::parse($row['expDate'])->format('Y-m-d') : '',
            'catatanKhusus' => $row['catatanKhusus'] ?? '-',
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingDtl = null;
        $this->editRow = [];
        $this->resetValidation();
    }

    /* ===============================
     | INLINE EDIT — SAVE
     =============================== */
    public function saveEdit(): void
    {
        if ($this->isFormLocked || !$this->editingDtl) {
            return;
        }

        $this->validateOnly('editRow.qty', ['editRow.qty' => 'required|numeric|min:1'], ['editRow.qty.required' => 'Qty wajib diisi.', 'editRow.qty.min' => 'Qty minimal 1.']);
        $this->validateOnly('editRow.carapakai', ['editRow.carapakai' => 'required|numeric|min:1'], ['editRow.carapakai.required' => 'x/Hari wajib diisi.']);
        $this->validateOnly('editRow.kapsul', ['editRow.kapsul' => 'required|numeric|min:1'], ['editRow.kapsul.required' => 'Per minum wajib diisi.']);
        $this->validateOnly('editRow.takar', ['editRow.takar' => 'required|string'], ['editRow.takar.required' => 'Takar wajib diisi.']);
        $this->validateOnly('editRow.expDate', ['editRow.expDate' => 'required|date'], ['editRow.expDate.required' => 'Exp. Date wajib diisi.', 'editRow.expDate.date' => 'Format tanggal tidak valid.']);

        try {
            DB::transaction(function () {
                // Lock row RJ — update + array lokal harus atomik
                $this->lockRJRow($this->rjNo);

                $expDateFormatted = Carbon::parse($this->editRow['expDate'])->format('Y-m-d H:i:s');

                DB::table('rstxn_rjobats')
                    ->where('rjobat_dtl', $this->editingDtl)
                    ->update([
                        'qty' => $this->editRow['qty'],
                        'rj_carapakai' => $this->editRow['carapakai'],
                        'rj_kapsul' => $this->editRow['kapsul'],
                        'rj_takar' => $this->editRow['takar'],
                        'rj_ket' => $this->editRow['ket'] ?: null,
                        'catatan_khusus' => $this->editRow['catatanKhusus'] ?: '-',
                        'exp_date' => DB::raw("to_date('" . $expDateFormatted . "','yyyy-mm-dd hh24:mi:ss')"),
                    ]);

                $this->rjObat = collect($this->rjObat)
                    ->map(function ($item) {
                        if ($item['rjobatDtl'] !== $this->editingDtl) {
                            return $item;
                        }
                        return array_merge($item, [
                            'qty' => $this->editRow['qty'],
                            'total' => $item['price'] * $this->editRow['qty'],
                            'carapakai' => $this->editRow['carapakai'],
                            'kapsul' => $this->editRow['kapsul'],
                            'takar' => $this->editRow['takar'],
                            'ket' => $this->editRow['ket'],
                            'expDate' => $this->editRow['expDate'],
                            'catatanKhusus' => $this->editRow['catatanKhusus'],
                        ]);
                    })
                    ->toArray();
            });

            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE OBAT
     =============================== */
    public function removeObat(int $rjobatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($rjobatDtl) {
                // Lock row RJ dulu
                $this->lockRJRow($this->rjNo);

                DB::table('rstxn_rjobats')->where('rjobat_dtl', $rjobatDtl)->delete();

                $this->rjObat = collect($this->rjObat)->where('rjobatDtl', '!=', $rjobatDtl)->values()->toArray();
            });

            if ($this->editingDtl === $rjobatDtl) {
                $this->cancelEdit();
            }

            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK ETIKET
     =============================== */
    public function cetakEtiketItem(int $rjobatDtl): void
    {
        $this->dispatch('cetak-etiket-obat.open', rjObatNo: $rjobatDtl);
    }

    /* ===============================
     | RESET FORM ENTRY
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryObat']);
        $this->formEntryObat['qty'] = 1;
        $this->formEntryObat['carapakai'] = 1;
        $this->formEntryObat['kapsul'] = 1;
        $this->formEntryObat['takar'] = 'Tablet';
        $this->formEntryObat['catatanKhusus'] = '-';
        $this->formEntryObat['etiketStatus'] = 0;
        $this->resetValidation();
        $this->incrementVersion('modal-obat-rj');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-obat-rj', [$rjNo ?? 'new']) }}" x-data>

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — data obat terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- FORM INPUT --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
        x-on:focus-lov-obat-rj.window="$nextTick(() => $refs.lovObatRj?.querySelector('input')?.focus())"
        x-on:focus-input-qty-obat.window="$nextTick(() => { $refs.inputQty?.focus(); $refs.inputQty?.select(); })">

        @if ($isFormLocked)
            <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
        @elseif (empty($formEntryObat['productId']))
            <div x-ref="lovObatRj">
                <livewire:lov.product.lov-product target="obat-rj" label="Cari Obat"
                    placeholder="Ketik nama/kode/kandungan obat..."
                    wire:key="lov-obat-rj-{{ $rjNo }}-{{ $renderVersions['modal-obat-rj'] ?? 0 }}" />
            </div>
        @else
            {{-- Baris 1 --}}
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
                    <x-text-input wire:model="formEntryObat.qty" placeholder="Qty" class="w-full text-sm"
                        x-ref="inputQty" x-on:keyup.enter="$nextTick(() => $refs.inputHarga?.focus())" />
                    @error('formEntryObat.qty')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-36">
                    <x-input-label value="Harga" class="mb-1" />
                    <x-text-input wire:model="formEntryObat.price" placeholder="Harga" class="w-full text-sm"
                        x-ref="inputHarga" x-on:keyup.enter="$nextTick(() => $refs.inputCarapakai?.focus())" />
                    @error('formEntryObat.price')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
            </div>

            {{-- Baris 2 --}}
            <div class="flex items-end gap-3">
                <div class="w-20">
                    <x-input-label value="x/Hari" class="mb-1" />
                    <x-text-input wire:model="formEntryObat.carapakai" placeholder="1" class="w-full text-sm"
                        x-ref="inputCarapakai" x-on:keyup.enter="$nextTick(() => $refs.inputKapsul?.focus())" />
                    @error('formEntryObat.carapakai')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-24">
                    <x-input-label value="Per Minum" class="mb-1" />
                    <x-text-input wire:model="formEntryObat.kapsul" placeholder="1" class="w-full text-sm"
                        x-ref="inputKapsul" x-on:keyup.enter="$nextTick(() => $refs.inputTakar?.focus())" />
                    @error('formEntryObat.kapsul')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-32">
                    <x-input-label value="Takar" class="mb-1" />
                    <x-select-input id="takar" wire:model="formEntryObat.takar" x-ref="inputTakar"
                        class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-green focus:border-brand-green">
                        <option>Tablet</option>
                        <option>Kapsul</option>
                        <option>Sirup</option>
                        <option>Sachet</option>
                        <option>Tetes</option>
                        <option>Salep</option>
                        <option>Injeksi</option>
                        <option>Lainnya</option>
                    </x-select-input>
                </div>
                <div class="w-32">
                    <x-input-label value="Keterangan" class="mb-1" />
                    <x-text-input wire:model="formEntryObat.ket" placeholder="Ket." class="w-full text-sm"
                        x-ref="inputKet" x-on:keyup.enter="$nextTick(() => $refs.inputExpDate?.focus())" />
                </div>
                <div class="w-36">
                    <x-input-label value="Exp. Date" class="mb-1" />
                    <x-text-input type="date" wire:model="formEntryObat.expDate" class="w-full text-sm"
                        x-ref="inputExpDate" x-on:keyup.enter="$nextTick(() => $refs.inputCatatan?.focus())" />
                    @error('formEntryObat.expDate')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex-1">
                    <x-input-label value="Catatan Khusus" class="mb-1" />
                    <x-text-input wire:model="formEntryObat.catatanKhusus" placeholder="Catatan..."
                        class="w-full text-sm" x-ref="inputCatatan" x-on:keyup.enter="$wire.insertObat()" />
                </div>
                <div class="w-24">
                    <x-input-label value="Etiket" class="mb-1" />
                    <x-select-input wire:model="formEntryObat.etiketStatus"
                        class="block w-full text-sm border-gray-300 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-green focus:border-brand-green">
                        <option value="0">Belum</option>
                        <option value="1">Sudah</option>
                    </x-select-input>
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertObat" wire:loading.attr="disabled"
                        wire:target="insertObat"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                            text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60
                            dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertObat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertObat"><x-loading class="w-4 h-4" /></span>
                        Tambah
                    </button>
                    <button type="button" wire:click.prevent="resetFormEntry"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium
                            text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                            border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Batal
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Obat</h3>
            <x-badge variant="gray">{{ count($rjObat) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-3 py-3">Kode</th>
                        <th class="px-3 py-3">Nama Obat</th>
                        <th class="px-3 py-3 text-right">Qty</th>
                        <th class="px-3 py-3 text-center">Signa</th>
                        <th class="px-3 py-3">Takar</th>
                        <th class="px-3 py-3">Ket</th>
                        <th class="px-3 py-3">Exp. Date</th>
                        <th class="px-3 py-3">Catatan</th>
                        <th class="px-3 py-3 text-center">Etiket</th>
                        <th class="px-3 py-3 text-right">Harga</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        @if (!$isFormLocked)
                            <th class="w-24 px-3 py-3 text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjObat as $item)
                        @php $isEditing = $editingDtl === $item['rjobatDtl']; @endphp
                        <tr wire:key="obat-row-{{ $item['rjobatDtl'] }}-{{ $isEditing ? 'edit' : 'view' }}" x-data
                            class="{{ $isEditing ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }} transition">

                            {{-- Kode --}}
                            <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['productId'] }}
                            </td>
                            {{-- Nama Obat --}}
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['productName'] }}
                            </td>
                            {{-- Qty --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <x-text-input wire:model="editRow.qty" class="w-16 text-sm text-right"
                                        x-ref="editQty" x-init="$el.focus();
                                        $el.select()"
                                        x-on:keyup.enter="$nextTick(() => $refs.editCarapakai?.focus())" />
                                    @error('editRow.qty')
                                        <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                                    @enderror
                                @else
                                    <span
                                        class="block text-right text-gray-700 dark:text-gray-300">{{ number_format($item['qty']) }}</span>
                                @endif
                            </td>
                            {{-- Signa --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <div class="flex items-center gap-1">
                                        <x-text-input wire:model="editRow.carapakai" class="w-12 text-sm text-center"
                                            x-ref="editCarapakai"
                                            x-on:keyup.enter="$nextTick(() => $refs.editKapsul?.focus())" />
                                        <span class="text-xs text-gray-400">x</span>
                                        <x-text-input wire:model="editRow.kapsul" class="w-12 text-sm text-center"
                                            x-ref="editKapsul"
                                            x-on:keyup.enter="$nextTick(() => $refs.editTakar?.focus())" />
                                    </div>
                                @else
                                    <span
                                        class="block text-center text-gray-700 dark:text-gray-300">{{ $item['carapakai'] }}x{{ $item['kapsul'] }}</span>
                                @endif
                            </td>
                            {{-- Takar --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <x-select-input wire:model="editRow.takar" x-ref="editTakar"
                                        x-on:keyup.enter="$nextTick(() => $refs.editKet?.focus())"
                                        class="text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-green focus:border-brand-green">
                                        <option>Tablet</option>
                                        <option>Kapsul</option>
                                        <option>Sirup</option>
                                        <option>Sachet</option>
                                        <option>Tetes</option>
                                        <option>Salep</option>
                                        <option>Injeksi</option>
                                        <option>Lainnya</option>
                                    </x-select-input>
                                @else
                                    <span class="text-gray-700 dark:text-gray-300">{{ $item['takar'] }}</span>
                                @endif
                            </td>
                            {{-- Ket --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <x-text-input wire:model="editRow.ket" placeholder="Ket." class="text-sm w-28"
                                        x-ref="editKet"
                                        x-on:keyup.enter="$nextTick(() => $refs.editExpDate?.focus())" />
                                @else
                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400">{{ $item['ket'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- Exp Date --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <x-text-input type="date" wire:model="editRow.expDate" class="text-sm w-36"
                                        x-ref="editExpDate"
                                        x-on:keyup.enter="$nextTick(() => $refs.editCatatan?.focus())" />
                                    @error('editRow.expDate')
                                        <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                                    @enderror
                                @else
                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400">{{ $item['expDate'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- Catatan --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <x-text-input wire:model="editRow.catatanKhusus" placeholder="Catatan..."
                                        class="text-sm w-36" x-ref="editCatatan"
                                        x-on:keyup.enter="$wire.saveEdit()" />
                                @else
                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400">{{ $item['catatanKhusus'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- Etiket --}}
                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                <x-ghost-button wire:click="cetakEtiketItem({{ $item['rjobatDtl'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})"
                                    class="!px-2 !py-1 !text-xs">
                                    <span wire:loading.remove wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </span>
                                    <span wire:loading wire:target="cetakEtiketItem({{ $item['rjobatDtl'] }})">
                                        <x-loading class="w-3 h-3" />
                                    </span>
                                    Etiket
                                </x-ghost-button>
                            </td>
                            {{-- Harga --}}
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                Rp {{ number_format($item['price']) }}
                            </td>
                            {{-- Total --}}
                            <td
                                class="px-3 py-2 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp
                                {{ number_format($isEditing ? $item['price'] * ($editRow['qty'] ?? $item['qty']) : $item['total']) }}
                            </td>
                            {{-- Aksi --}}
                            @if (!$isFormLocked)
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($isEditing)
                                        <div class="flex items-center gap-1">
                                            <x-secondary-button type="button" wire:click="saveEdit"
                                                wire:loading.attr="disabled" wire:target="saveEdit"
                                                class="px-3 py-1 text-xs text-green-700 border-green-300 hover:bg-green-50 dark:text-green-400 dark:border-green-600 dark:hover:bg-green-900/20">
                                                Simpan
                                            </x-secondary-button>
                                            <x-secondary-button type="button" wire:click="cancelEdit"
                                                class="px-3 py-1 text-xs">
                                                Batal
                                            </x-secondary-button>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-1">
                                            <x-secondary-button type="button"
                                                wire:click="startEdit({{ $item['rjobatDtl'] }})"
                                                class="px-3 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <button type="button"
                                                wire:click.prevent="removeObat({{ $item['rjobatDtl'] }})"
                                                wire:confirm="Hapus obat ini?" wire:loading.attr="disabled"
                                                wire:target="removeObat({{ $item['rjobatDtl'] }})"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 11 : 12 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada data obat
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjObat))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="{{ $isFormLocked ? 10 : 11 }}"
                                class="px-3 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-3 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($rjObat)->sum('total')) }}
                            </td>
                            @if (!$isFormLocked)
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <livewire:pages::components.rekam-medis.r-j.etiket-obat.cetak-etiket-obat wire:key="cetak-etiket-obat" />

</div>
