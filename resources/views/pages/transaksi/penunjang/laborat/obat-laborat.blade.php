<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {

    public string $checkupNo = '';
    #[Reactive]
    public string $labStatus = 'P';
    public array $obatRows = [];
    public array $formObat = [
        'productId' => '',
        'productName' => '',
        'qty' => '',
        'price' => '',
    ];

    /* =======================
     | Mount
     * ======================= */
    public function mount(): void
    {
        if ($this->checkupNo) {
            $this->loadObatRows();
        }
    }

    /* =======================
     | Refresh from parent
     * ======================= */
    #[On('obat-lab.refresh')]
    public function onRefresh(string $checkupNo = ''): void
    {
        if ($checkupNo) {
            $this->checkupNo = $checkupNo;
        }
        $this->loadObatRows();
    }

    /* =======================
     | LOAD OBAT ROWS
     * ======================= */
    private function loadObatRows(): void
    {
        $rows = DB::table('lbtxn_checkupobats as a')
            ->leftJoin('immst_products as b', 'a.product_id', '=', 'b.product_id')
            ->select('a.id', 'a.product_id', 'b.product_name', 'a.qty', 'a.price')
            ->where('a.checkup_no', $this->checkupNo)
            ->orderBy('a.id', 'asc')
            ->get();

        $this->obatRows = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* =======================
     | LOV OBAT SELECTED
     * ======================= */
    #[On('lov.selected.labObatItem')]
    public function labObatSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formObat['productId'] = '';
            $this->formObat['productName'] = '';
            $this->formObat['price'] = '';
            return;
        }

        $this->formObat['productId'] = $payload['product_id'] ?? '';
        $this->formObat['productName'] = $payload['product_name'] ?? '';
        $this->formObat['price'] = $payload['sales_price'] ?? '';
        $this->formObat['qty'] = '1';
    }

    /* =======================
     | ADD OBAT
     * ======================= */
    public function addObat(): void
    {
        if ($this->labStatus !== 'P') {
            $this->dispatch('toast', type: 'warning', message: 'Tidak bisa menambah obat, pemeriksaan sudah diproses.');
            return;
        }

        $this->validate([
            'formObat.productId' => 'required',
            'formObat.qty' => 'required|numeric|min:0.01',
        ], [
            'formObat.productId.required' => 'Pilih obat terlebih dahulu.',
            'formObat.qty.required' => 'Jumlah harus diisi.',
            'formObat.qty.min' => 'Jumlah minimal 0.01.',
        ]);

        try {
            $id = DB::scalar('SELECT NVL(TO_NUMBER(MAX(id)) + 1, 1) FROM lbtxn_checkupobats');

            DB::table('lbtxn_checkupobats')->insert([
                'id' => $id,
                'checkup_no' => $this->checkupNo,
                'product_id' => $this->formObat['productId'],
                'qty' => (float) $this->formObat['qty'],
                'price' => !empty($this->formObat['price']) ? (float) $this->formObat['price'] : null,
            ]);

            $this->resetObatForm();
            $this->loadObatRows();
            $this->dispatch('lab-tab.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah obat: ' . $e->getMessage());
        }
    }

    /* =======================
     | DELETE OBAT
     * ======================= */
    public function deleteObatRow(int $id): void
    {
        if ($this->labStatus !== 'P') {
            $this->dispatch('toast', type: 'warning', message: 'Tidak bisa menghapus obat, pemeriksaan sudah diproses.');
            return;
        }

        DB::table('lbtxn_checkupobats')
            ->where('checkup_no', $this->checkupNo)
            ->where('id', $id)
            ->delete();

        $this->loadObatRows();
        $this->dispatch('lab-tab.updated');
        $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
    }

    /* =======================
     | RESET FORM
     * ======================= */
    private function resetObatForm(): void
    {
        $this->formObat = [
            'productId' => '',
            'productName' => '',
            'qty' => '',
            'price' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <div class="space-y-4">

        @if ($labStatus !== 'P')
            <div class="p-3 text-sm border rounded-lg {{ $labStatus === 'H' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-amber-50 border-amber-200 text-amber-700' }}">
                Status: <strong>{{ $labStatus === 'H' ? 'Selesai' : 'Proses Entry Hasil' }}</strong> — Data obat dan bahan terkunci.
            </div>
        @endif

        {{-- FORM ADD OBAT --}}
        @if ($labStatus === 'P')
        <div class="p-4 border rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Tambah Obat dan Bahan</h4>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <livewire:lov.product.lov-product target="labObatItem" label="Cari Obat"
                        wire:key="lov-lab-obat-child" />
                    @if (!empty($formObat['productName']))
                        <div class="mt-1 text-xs text-brand-green">
                            Dipilih: {{ $formObat['productName'] }}
                        </div>
                    @endif
                    @error('formObat.productId')
                        <span class="text-xs text-red-500">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <x-input-label value="Jumlah" />
                    <x-text-input type="number" wire:model="formObat.qty" class="w-full mt-1"
                        placeholder="Qty" step="0.01" min="0.01" />
                    @error('formObat.qty')
                        <span class="text-xs text-red-500">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <x-input-label value="Harga" />
                    <x-text-input type="number" wire:model="formObat.price" class="w-full mt-1"
                        placeholder="Harga" readonly />
                </div>
            </div>
            <div class="flex justify-end mt-3">
                <x-primary-button type="button" wire:click="addObat" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="addObat"
                        class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Obat dan Bahan
                    </span>
                    <span wire:loading wire:target="addObat" class="flex items-center gap-1.5">
                        <x-loading /> Menyimpan...
                    </span>
                </x-primary-button>
            </div>
        </div>

        @endif

        {{-- OBAT TABLE --}}
        <div class="overflow-x-auto border rounded-lg border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">No</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Kode Obat</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Nama Obat</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Qty</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Harga</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Subtotal</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                    @php $totalObat = 0; @endphp
                    @forelse ($obatRows as $idx => $ob)
                        @php
                            $subtotal = ($ob['qty'] ?? 0) * ($ob['price'] ?? 0);
                            $totalObat += $subtotal;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-3 py-2 text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2 font-mono text-gray-500">{{ $ob['product_id'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $ob['product_name'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($ob['qty'] ?? 0, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($ob['price'] ?? 0) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($subtotal) }}</td>
                            <td class="px-3 py-2 text-center">
                                @if ($labStatus === 'P')
                                    <button type="button"
                                        wire:click="deleteObatRow({{ $ob['id'] }})"
                                        wire:confirm="Yakin hapus obat ini?"
                                        class="text-red-500 hover:text-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-gray-400">
                                Belum ada obat
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($obatRows))
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td colspan="5"
                                class="px-3 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Total:</td>
                            <td class="px-3 py-2 text-right text-sm font-bold text-brand">
                                {{ number_format($totalObat) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
