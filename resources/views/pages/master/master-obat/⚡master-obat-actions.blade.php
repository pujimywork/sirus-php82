<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

new class extends Component {
    /* -------------------------
     | Form Mode
     * ------------------------- */
    public string $formMode = 'create'; // create|edit

    /* -------------------------
     | Informasi Dasar
     * ------------------------- */
    public ?string $productId = null;
    public string $productName = '';
    public string $kode = '';
    
    /* -------------------------
     | Relasi Master
     * ------------------------- */
    public ?string $uomId = null;
    public ?string $catId = null;
    public ?string $grpId = null;
    public ?string $suppId = null;
    
    /* -------------------------
     | Harga
     * ------------------------- */
    public ?string $costPrice = null;
    public ?string $salesPrice = null;
    
    /* -------------------------
     | Stok Gudang Utama
     * ------------------------- */
    public ?string $stock = '0';
    public ?string $stockwh = '0';
    public ?string $stockklinik = '0';
    
    /* -------------------------
     | Stok Per Unit
     * ------------------------- */
    public ?string $stockOk = '0';
    public ?string $stockUgd = '0';
    public ?string $stockLaborat = '0';
    public ?string $stockUtara = '0';
    public ?string $stockSelatan = '0';
    public ?string $stockVk = '0';
    public ?string $stockTu = '0';
    public ?string $stockArm = '0';
    public ?string $stockRd = '0';
    
    /* -------------------------
     | Limit Stok
     * ------------------------- */
    public ?string $limitStock = '0';
    public ?string $limitStockwh = '0';
    public ?string $limitStockklinik = '0';
    
    /* -------------------------
     | Informasi Tambahan
     * ------------------------- */
    public ?string $qtyPerBox = null;
    public ?string $takar = null;
    public ?string $qtyBox = null;
    public ?string $stockPrintNumber = null;
    public ?string $stockwhPrintNumber = null;
    
    /* -------------------------
     | Status & Integrasi
     * ------------------------- */
    public string $productStatus = '1';
    public string $activeStatus = '1';
    public string $fornasNonfornasStatus = '0';
    public ?string $productIdSatusehat = null;
    public ?string $productNameSatusehat = null;
    
    /* -------------------------
     | Auto-Generate (readonly)
     * ------------------------- */
    public ?string $productNumber = null;

    /* -------------------------
     | Dropdown Options
     * ------------------------- */
    public $uomOptions = [];
    public $catOptions = [];
    public $grpOptions = [];
    public $suppOptions = [];

    /* -------------------------
     | Open Create Modal
     * ------------------------- */
    #[On('master.obat.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->loadDropdownOptions();

        $this->dispatch('open-modal', name: 'master-obat-actions');
    }

    /* -------------------------
     | Open Edit Modal
     * ------------------------- */
    #[On('master.obat.openEdit')]
    public function openEdit(string $productId): void
    {
        $row = DB::table('immst_products')->where('product_id', $productId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();
        $this->loadDropdownOptions();

        $this->dispatch('open-modal', name: 'master-obat-actions');
    }

    /* -------------------------
     | Close Modal
     * ------------------------- */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-obat-actions');
    }

    /* -------------------------
     | Load Dropdown Options
     * ------------------------- */
    protected function loadDropdownOptions(): void
    {
        // Load UOM
        $this->uomOptions = DB::table('immst_uoms')
            ->select('uom_id', 'uom_desc')
            ->orderBy('uom_desc')
            ->get();

        // Load Kategori
        $this->catOptions = DB::table('immst_catproducts')
            ->select('cat_id', 'cat_desc')
            ->orderBy('cat_desc')
            ->get();

        // Load Grup
        $this->grpOptions = DB::table('immst_groupproducts')
            ->select('grp_id', 'grp_name')
            ->orderBy('grp_name')
            ->get();

        // Load Supplier
        $this->suppOptions = DB::table('immst_suppliers')
            ->select('supp_id', 'supp_name')
            ->orderBy('supp_name')
            ->get();
    }

    /* -------------------------
     | Reset Form Fields
     * ------------------------- */
    protected function resetFormFields(): void
    {
        $this->reset([
            'productId', 'productName', 'kode',
            'uomId', 'catId', 'grpId', 'suppId',
            'costPrice', 'salesPrice',
            'stock', 'stockwh', 'stockklinik',
            'stockOk', 'stockUgd', 'stockLaborat', 'stockUtara', 'stockSelatan',
            'stockVk', 'stockTu', 'stockArm', 'stockRd',
            'limitStock', 'limitStockwh', 'limitStockklinik',
            'qtyPerBox', 'takar', 'qtyBox', 'stockPrintNumber', 'stockwhPrintNumber',
            'productStatus', 'activeStatus', 'fornasNonfornasStatus',
            'productIdSatusehat', 'productNameSatusehat', 'productNumber'
        ]);
        
        // Set default values
        $this->stock = '0';
        $this->stockwh = '0';
        $this->stockklinik = '0';
        $this->stockOk = '0';
        $this->stockUgd = '0';
        $this->stockLaborat = '0';
        $this->stockUtara = '0';
        $this->stockSelatan = '0';
        $this->stockVk = '0';
        $this->stockTu = '0';
        $this->stockArm = '0';
        $this->stockRd = '0';
        $this->limitStock = '0';
        $this->limitStockwh = '0';
        $this->limitStockklinik = '0';
        $this->productStatus = '1';
        $this->activeStatus = '1';
        $this->fornasNonfornasStatus = '0';
    }

    /* -------------------------
     | Fill Form From Row
     * ------------------------- */
    protected function fillFormFromRow(object $row): void
    {
        // Informasi Dasar
        $this->productId = (string) $row->product_id;
        $this->productName = (string) ($row->product_name ?? '');
        $this->kode = (string) ($row->kode ?? '');
        
        // Relasi Master
        $this->uomId = $row->uom_id;
        $this->catId = $row->cat_id;
        $this->grpId = $row->grp_id;
        $this->suppId = $row->supp_id;
        
        // Harga
        $this->costPrice = $row->cost_price;
        $this->salesPrice = $row->sales_price;
        
        // Stok Gudang Utama
        $this->stock = (string) ($row->stock ?? '0');
        $this->stockwh = (string) ($row->stockwh ?? '0');
        $this->stockklinik = (string) ($row->stockklinik ?? '0');
        
        // Stok Per Unit
        $this->stockOk = (string) ($row->stock_ok ?? '0');
        $this->stockUgd = (string) ($row->stock_ugd ?? '0');
        $this->stockLaborat = (string) ($row->stock_laborat ?? '0');
        $this->stockUtara = (string) ($row->stock_utara ?? '0');
        $this->stockSelatan = (string) ($row->stock_selatan ?? '0');
        $this->stockVk = (string) ($row->stock_vk ?? '0');
        $this->stockTu = (string) ($row->stock_tu ?? '0');
        $this->stockArm = (string) ($row->stock_arm ?? '0');
        $this->stockRd = (string) ($row->stock_rd ?? '0');
        
        // Limit Stok
        $this->limitStock = (string) ($row->limit_stock ?? '0');
        $this->limitStockwh = (string) ($row->limit_stockwh ?? '0');
        $this->limitStockklinik = (string) ($row->limit_stockklinik ?? '0');
        
        // Informasi Tambahan
        $this->qtyPerBox = $row->qty_per_box;
        $this->takar = $row->takar;
        $this->qtyBox = $row->qty_box;
        $this->stockPrintNumber = $row->stock_print_number;
        $this->stockwhPrintNumber = $row->stockwh_print_number;
        
        // Status & Integrasi
        $this->productStatus = (string) ($row->product_status ?? '1');
        $this->activeStatus = (string) ($row->active_status ?? '1');
        $this->fornasNonfornasStatus = (string) ($row->fornas_nonfornas_status ?? '0');
        $this->productIdSatusehat = $row->product_id_satusehat;
        $this->productNameSatusehat = $row->product_name_satusehat;
        
        // Auto-Generate
        $this->productNumber = $row->product_number;
    }

    /* -------------------------
     | Validation Rules
     * ------------------------- */
    protected function rules(): array
    {
        return [
            // Informasi Dasar
            'productId' => [
                'required', 
                'string', 
                'max:50', 
                $this->formMode === 'create' 
                    ? Rule::unique('immst_products', 'product_id') 
                    : Rule::unique('immst_products', 'product_id')->ignore($this->productId, 'product_id')
            ],
            'productName' => ['required', 'string', 'max:255'],
            'kode' => ['required', 'string', 'max:100'],
            
            // Relasi Master
            'uomId' => ['required', 'exists:immst_uoms,uom_id'],
            'catId' => ['required', 'exists:immst_catproducts,cat_id'],
            'grpId' => ['required', 'exists:immst_groupproducts,grp_id'],
            'suppId' => ['required', 'exists:immst_suppliers,supp_id'],
            
            // Harga
            'costPrice' => ['required', 'numeric', 'min:0'],
            'salesPrice' => ['required', 'numeric', 'min:0'],
            
            // Stok Gudang Utama
            'stock' => ['nullable', 'numeric', 'min:0'],
            'stockwh' => ['nullable', 'numeric', 'min:0'],
            'stockklinik' => ['nullable', 'numeric', 'min:0'],
            
            // Stok Per Unit
            'stockOk' => ['nullable', 'numeric', 'min:0'],
            'stockUgd' => ['nullable', 'numeric', 'min:0'],
            'stockLaborat' => ['nullable', 'numeric', 'min:0'],
            'stockUtara' => ['nullable', 'numeric', 'min:0'],
            'stockSelatan' => ['nullable', 'numeric', 'min:0'],
            'stockVk' => ['nullable', 'numeric', 'min:0'],
            'stockTu' => ['nullable', 'numeric', 'min:0'],
            'stockArm' => ['nullable', 'numeric', 'min:0'],
            'stockRd' => ['nullable', 'numeric', 'min:0'],
            
            // Limit Stok
            'limitStock' => ['nullable', 'numeric', 'min:0'],
            'limitStockwh' => ['nullable', 'numeric', 'min:0'],
            'limitStockklinik' => ['nullable', 'numeric', 'min:0'],
            
            // Informasi Tambahan
            'qtyPerBox' => ['nullable', 'numeric', 'min:0'],
            'takar' => ['nullable', 'string', 'max:50'],
            'qtyBox' => ['nullable', 'numeric', 'min:0'],
            'stockPrintNumber' => ['nullable', 'string', 'max:50'],
            'stockwhPrintNumber' => ['nullable', 'string', 'max:50'],
            
            // Status & Integrasi
            'productStatus' => ['required', Rule::in(['0', '1'])],
            'activeStatus' => ['required', Rule::in(['0', '1'])],
            'fornasNonfornasStatus' => ['required', Rule::in(['0', '1'])],
            'productIdSatusehat' => ['nullable', 'string', 'max:100'],
            'productNameSatusehat' => ['nullable', 'string', 'max:255'],
        ];
    }

    /* -------------------------
     | Custom Validation Messages
     * ------------------------- */
    protected function messages(): array
    {
        return [
            // Informasi Dasar
            'productId.required' => ':attribute wajib diisi.',
            'productId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',
            'productName.required' => ':attribute wajib diisi.',
            'productName.max' => ':attribute maksimal :max karakter.',
            'kode.required' => ':attribute wajib diisi.',
            'kode.max' => ':attribute maksimal :max karakter.',
            
            // Relasi Master
            'uomId.required' => ':attribute wajib dipilih.',
            'uomId.exists' => ':attribute tidak valid.',
            'catId.required' => ':attribute wajib dipilih.',
            'catId.exists' => ':attribute tidak valid.',
            'grpId.required' => ':attribute wajib dipilih.',
            'grpId.exists' => ':attribute tidak valid.',
            'suppId.required' => ':attribute wajib dipilih.',
            'suppId.exists' => ':attribute tidak valid.',
            
            // Harga
            'costPrice.required' => ':attribute wajib diisi.',
            'costPrice.numeric' => ':attribute harus berupa angka.',
            'costPrice.min' => ':attribute minimal :min.',
            'salesPrice.required' => ':attribute wajib diisi.',
            'salesPrice.numeric' => ':attribute harus berupa angka.',
            'salesPrice.min' => ':attribute minimal :min.',
        ];
    }

    /* -------------------------
     | Validation Attributes
     * ------------------------- */
    protected function validationAttributes(): array
    {
        return [
            'productId' => 'ID Produk',
            'productName' => 'Nama Produk',
            'kode' => 'Kode',
            'uomId' => 'Satuan',
            'catId' => 'Kategori',
            'grpId' => 'Grup',
            'suppId' => 'Supplier',
            'costPrice' => 'Harga Beli',
            'salesPrice' => 'Harga Jual',
            'stock' => 'Stok',
            'stockwh' => 'Stok Warehouse',
            'stockklinik' => 'Stok Klinik',
        ];
    }

    /* -------------------------
     | Save Data
     * ------------------------- */
    public function save(): void
    {
        $data = $this->validate();

        // Prepare payload untuk insert/update
        $payload = [
            'product_name' => $data['productName'],
            'kode' => $data['kode'],
            'uom_id' => $data['uomId'],
            'cat_id' => $data['catId'],
            'grp_id' => $data['grpId'],
            'supp_id' => $data['suppId'],
            'cost_price' => $data['costPrice'],
            'sales_price' => $data['salesPrice'],
            'stock' => $data['stock'] ?? 0,
            'stockwh' => $data['stockwh'] ?? 0,
            'stockklinik' => $data['stockklinik'] ?? 0,
            'stock_ok' => $data['stockOk'] ?? 0,
            'stock_ugd' => $data['stockUgd'] ?? 0,
            'stock_laborat' => $data['stockLaborat'] ?? 0,
            'stock_utara' => $data['stockUtara'] ?? 0,
            'stock_selatan' => $data['stockSelatan'] ?? 0,
            'stock_vk' => $data['stockVk'] ?? 0,
            'stock_tu' => $data['stockTu'] ?? 0,
            'stock_arm' => $data['stockArm'] ?? 0,
            'stock_rd' => $data['stockRd'] ?? 0,
            'limit_stock' => $data['limitStock'] ?? 0,
            'limit_stockwh' => $data['limitStockwh'] ?? 0,
            'limit_stockklinik' => $data['limitStockklinik'] ?? 0,
            'qty_per_box' => $data['qtyPerBox'],
            'takar' => $data['takar'],
            'qty_box' => $data['qtyBox'],
            'stock_print_number' => $data['stockPrintNumber'],
            'stockwh_print_number' => $data['stockwhPrintNumber'],
            'product_status' => $data['productStatus'],
            'active_status' => $data['activeStatus'],
            'fornas_nonfornas_status' => $data['fornasNonfornasStatus'],
            'product_id_satusehat' => $data['productIdSatusehat'],
            'product_name_satusehat' => $data['productNameSatusehat'],
        ];

        if ($this->formMode === 'create') {
            // Auto-generate PRODUCT_NUMBER
            $maxNumber = DB::table('immst_products')->max('product_number');
            $nextNumber = $maxNumber ? (int)$maxNumber + 1 : 1;
            
            DB::table('immst_products')->insert([
                'product_id' => $data['productId'],
                'product_number' => str_pad($nextNumber, 6, '0', STR_PAD_LEFT),
                ...$payload,
            ]);
        } else {
            // Update existing record
            DB::table('immst_products')->where('product_id', $data['productId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data obat berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.obat.saved');
    }

    /* -------------------------
     | Delete Data
     * ------------------------- */
    #[On('master.obat.requestDelete')]
    public function deleteFromGrid(string $productId): void
    {
        try {
            // TODO: Cek apakah obat sudah dipakai di transaksi (sesuaikan dengan tabel transaksi Anda)
            // Contoh:
            // $isUsed = DB::table('rstxn_rjdtls')->where('product_id', $productId)->exists();
            // if ($isUsed) {
            //     $this->dispatch('toast', type: 'error', message: 'Data obat sudah dipakai pada transaksi.');
            //     return;
            // }

            $deleted = DB::table('immst_products')->where('product_id', $productId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data obat tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data obat berhasil dihapus.');
            $this->dispatch('master.obat.saved');
        } catch (QueryException $e) {
            // Handle foreign key constraint error
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Obat tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }

    /* -------------------------
     | Handle LOV Selected
     * ------------------------- */
    #[On('lov.selected')]
    public function handleLovSelected(string $target, array $payload): void
    {
        $this->uomId = $payload['uom_id'] ?? '';
    }
};
?>


<div>
    <x-modal name="master-obat-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-obat-actions-{{ $formMode }}-{{ $productId ?? 'new' }}">

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

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Obat' : 'Tambah Data Obat' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi obat untuk kebutuhan aplikasi.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-6xl">

                    {{-- SECTION 1: Informasi Dasar --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">1. Informasi Dasar</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Product ID --}}
                                <div>
                                    <x-input-label value="ID Produk *" />
                                    <x-text-input wire:model.defer="productId" :disabled="$formMode === 'edit'"
                                        :error="$errors->has('productId')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('productId')" class="mt-1" />
                                </div>

                                {{-- Product Name --}}
                                <div class="sm:col-span-2">
                                    <x-input-label value="Nama Produk *" />
                                    <x-text-input wire:model.defer="productName" :error="$errors->has('productName')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('productName')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Kode --}}
                                <div>
                                    <x-input-label value="Kode *" />
                                    <x-text-input wire:model.defer="kode" :error="$errors->has('kode')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('kode')" class="mt-1" />
                                </div>

                                {{-- Product Number (Auto-generate, readonly) --}}
                                <div>
                                    <x-input-label value="Nomor Produk (Auto)" />
                                    <x-text-input wire:model.defer="productNumber" disabled
                                        class="w-full mt-1 bg-gray-100 dark:bg-gray-800" />
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        Akan di-generate otomatis saat menyimpan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: Relasi Master --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">2. Relasi Master Data</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- UOM --}}
                                <div>
                                    <x-input-label value="Satuan (UOM) *" />
                                    @if($formMode == 'create')
                                    <livewire:lov.uom.lov-uom target="masterObatUom" />
                                    @else
                                    <livewire:lov.uom.lov-uom target="masterObatUom" :initial-uom-id="$uomId" />
                                    @endif
                                    <x-input-error :messages="$errors->get('uomId')" class="mt-1" />
                                </div>

                                {{-- Category --}}
                                <div>
                                    <x-input-label value="Kategori *" />
                                    @if($formMode == 'create')
                                    <livewire:lov.cat-product.lov-cat-product target="masterObatCat" />
                                    @else
                                    <livewire:lov.cat-product.lov-cat-product target="masterObatCat"
                                        :initial-cat-id="$catId" />
                                    @endif
                                    <x-input-error :messages="$errors->get('catId')" class="mt-1" />
                                </div>

                                {{-- Group --}}
                                <div>
                                    <x-input-label value="Grup *" />
                                    @if($formMode == 'create')
                                    <livewire:lov.group-product.lov-group-product target="masterObatGrp" />
                                    @else
                                    <livewire:lov.group-product.lov-group-product target="masterObatGrp"
                                        :initial-grp-id="$grpId" />
                                    @endif
                                    <x-input-error :messages="$errors->get('grpId')" class="mt-1" />
                                </div>

                                {{-- Supplier --}}
                                <div>
                                    <x-input-label value="Supplier *" />
                                    @if($formMode == 'create')
                                    <livewire:lov.supplier.lov-supplier target="masterObatSupp" />
                                    @else
                                    <livewire:lov.supplier.lov-supplier target="masterObatSupp"
                                        :initial-supp-id="$suppId" />
                                    @endif
                                    <x-input-error :messages="$errors->get('suppId')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 3: Harga --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">3. Harga</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Cost Price --}}
                                <div>
                                    <x-input-label value="Harga Beli *" />
                                    <x-text-input wire:model.defer="costPrice" type="number" step="0.01"
                                        :error="$errors->has('costPrice')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('costPrice')" class="mt-1" />
                                </div>

                                {{-- Sales Price --}}
                                <div>
                                    <x-input-label value="Harga Jual *" />
                                    <x-text-input wire:model.defer="salesPrice" type="number" step="0.01"
                                        :error="$errors->has('salesPrice')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('salesPrice')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 4: Stok Gudang Utama --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">4. Stok Gudang Utama</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Stok" />
                                    <x-text-input wire:model.defer="stock" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok Warehouse" />
                                    <x-text-input wire:model.defer="stockwh" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok Klinik" />
                                    <x-text-input wire:model.defer="stockklinik" type="number" class="w-full mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 5: Stok Per Unit --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">5. Stok Per Unit</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Stok OK" />
                                    <x-text-input wire:model.defer="stockOk" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok UGD" />
                                    <x-text-input wire:model.defer="stockUgd" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok Laborat" />
                                    <x-text-input wire:model.defer="stockLaborat" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok Utara" />
                                    <x-text-input wire:model.defer="stockUtara" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok Selatan" />
                                    <x-text-input wire:model.defer="stockSelatan" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok VK" />
                                    <x-text-input wire:model.defer="stockVk" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok TU" />
                                    <x-text-input wire:model.defer="stockTu" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok ARM" />
                                    <x-text-input wire:model.defer="stockArm" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stok RD" />
                                    <x-text-input wire:model.defer="stockRd" type="number" class="w-full mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 6: Limit Stok --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">6. Limit Stok</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Limit Stok" />
                                    <x-text-input wire:model.defer="limitStock" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Limit Stok Warehouse" />
                                    <x-text-input wire:model.defer="limitStockwh" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Limit Stok Klinik" />
                                    <x-text-input wire:model.defer="limitStockklinik" type="number"
                                        class="w-full mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 7: Informasi Tambahan --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">7. Informasi Tambahan</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Qty Per Box" />
                                    <x-text-input wire:model.defer="qtyPerBox" type="number" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Takar" />
                                    <x-text-input wire:model.defer="takar" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Qty Box" />
                                    <x-text-input wire:model.defer="qtyBox" type="number" class="w-full mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Stock Print Number" />
                                    <x-text-input wire:model.defer="stockPrintNumber" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Stockwh Print Number" />
                                    <x-text-input wire:model.defer="stockwhPrintNumber" class="w-full mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 8: Status & Integrasi --}}
                    <div
                        class="mb-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">8. Status & Integrasi</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Product Status --}}
                                <div>
                                    <x-input-label value="Status Produk *" />
                                    <x-select-input wire:model.defer="productStatus"
                                        :error="$errors->has('productStatus')" class="w-full mt-1">
                                        <option value="0">Tidak Aktif</option>
                                        <option value="1">Aktif</option>
                                    </x-select-input>
                                </div>

                                {{-- Active Status --}}
                                <div>
                                    <x-input-label value="Status Aktif *" />
                                    <x-select-input wire:model.defer="activeStatus"
                                        :error="$errors->has('activeStatus')" class="w-full mt-1">
                                        <option value="0">Tidak Aktif</option>
                                        <option value="1">Aktif</option>
                                    </x-select-input>
                                </div>

                                {{-- Fornas Status --}}
                                <div>
                                    <x-input-label value="Fornas/Non-Fornas *" />
                                    <x-select-input wire:model.defer="fornasNonfornasStatus"
                                        :error="$errors->has('fornasNonfornasStatus')" class="w-full mt-1">
                                        <option value="0">Non-Fornas</option>
                                        <option value="1">Fornas</option>
                                    </x-select-input>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Product ID Satusehat" />
                                    <x-text-input wire:model.defer="productIdSatusehat" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Product Name Satusehat" />
                                    <x-text-input wire:model.defer="productNameSatusehat" class="w-full mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pastikan data sudah benar sebelum menyimpan. (* = wajib diisi)
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>