<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-rtn-obat-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataRtnObat = [];

    public array $formEntry = [
        'riobatDate'   => '',
        'productId'    => '',
        'productName'  => '',
        'productPrice' => '',
        'productQty'   => '1',
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('ri.administrasi-selesai')]
    public function onAdministrasiSelesai(?int $riHdrNo = null): void
    {
        if (!$riHdrNo) return;
        // Re-check status DB — lock kalau completed, unlock kalau di-batal-kan.
        if ((int) ($this->riHdrNo ?? 0) === $riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        }
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->formEntry['riobatDate'] = $this->nowFormatted();

        if ($this->riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
            $this->findData($this->riHdrNo);
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riobatrtns')
            ->join('immst_products', 'immst_products.product_id', '=', 'rstxn_riobatrtns.product_id')
            ->select(
                DB::raw("to_char(riobat_date, 'dd/mm/yyyy hh24:mi:ss') as riobat_date"),
                'rstxn_riobatrtns.product_id',
                'immst_products.product_name',
                'rstxn_riobatrtns.riobat_qty',
                'rstxn_riobatrtns.riobat_price',
                'rstxn_riobatrtns.riobat_no',
            )
            ->where('rstxn_riobatrtns.rihdr_no', $riHdrNo)
            ->orderByDesc('rstxn_riobatrtns.riobat_date')
            ->get();

        $this->dataRtnObat = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | REFRESH — event dari sibling / parent
     =============================== */
    #[On('administrasi-ri.updated')]
    public function refresh(): void
    {
        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        }
    }

    /* ===============================
     | LOV SELECTED — PRODUCT
     =============================== */
    #[On('lov.selected.product-rtn-obat-ri')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['productId']    = '';
            $this->formEntry['productName']  = '';
            $this->formEntry['productPrice'] = '';
            return;
        }

        $this->formEntry['productId']    = $payload['product_id'];
        $this->formEntry['productName']  = $payload['product_name'];
        // Sementara return pakai harga beli (cost_price), bukan harga jual.
        $this->formEntry['productPrice'] = $payload['cost_price'] ?? 0;

        $this->dispatch('focus-input-rtn-qty');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertRtnObat(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.productId'    => 'bail|required|exists:immst_products,product_id',
                'formEntry.productPrice' => 'bail|required|numeric|min:0',
                'formEntry.productQty'   => 'bail|required|numeric|min:1',
            ],
            [
                'formEntry.productId.required'    => 'Produk wajib dipilih.',
                'formEntry.productId.exists'      => 'Produk tidak valid.',
                'formEntry.productPrice.required' => 'Harga wajib diisi.',
                'formEntry.productPrice.numeric'  => 'Harga harus berupa angka.',
                'formEntry.productQty.required'   => 'Jumlah wajib diisi.',
                'formEntry.productQty.min'        => 'Jumlah minimal 1.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                // PK legacy: select nvl(max(riobat_no)+1,1) from RSTXN_RIOBATRTNS
                $last = DB::table('rstxn_riobatrtns')
                    ->select(DB::raw("nvl(max(riobat_no)+1,1) as riobat_no_max"))
                    ->first();

                DB::table('rstxn_riobatrtns')->insert([
                    'riobat_no'    => $last->riobat_no_max,
                    'rihdr_no'     => $this->riHdrNo,
                    'product_id'   => $this->formEntry['productId'],
                    'riobat_date'  => DB::raw("TO_DATE('" . $this->formEntry['riobatDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'riobat_price' => $this->formEntry['productPrice'],
                    'riobat_qty'   => $this->formEntry['productQty'],
                ]);
                $this->appendAdminLogRI($this->riHdrNo, 'Return Obat: ' . $this->formEntry['productName']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('focus-lov-rtn-obat-ri');
            $this->dispatch('toast', type: 'success', message: 'Return obat berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeRtnObat(int $riobatNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($riobatNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rstxn_riobatrtns')->where('riobat_no', $riobatNo)->delete();
                $this->appendAdminLogRI($this->riHdrNo, 'Hapus Return Obat #' . $riobatNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Return obat berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function refreshRiobatDate(): void
    {
        $this->formEntry['riobatDate'] = $this->nowFormatted();
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['riobatDate'] = $this->nowFormatted();
        $this->formEntry['productQty'] = '1';
        $this->resetValidation();
        $this->incrementVersion('modal-rtn-obat-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-rtn-obat-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-rtn-qty.window="$nextTick(() => $refs.inputRtnQty?.focus())"
            x-on:focus-lov-rtn-obat-ri.window="$nextTick(() => $refs.lovRtnObat?.querySelector('input')?.focus())">

            @if (empty($formEntry['productId']))
                <div x-ref="lovRtnObat">
                    <livewire:lov.product.lov-product target="product-rtn-obat-ri" label="Produk / Obat"
                        placeholder="Ketik kode/nama produk..."
                        wire:key="lov-product-rtn-{{ $riHdrNo }}-{{ $renderVersions['modal-rtn-obat-ri'] ?? 0 }}" />
                </div>
            @else
                <div class="grid grid-cols-12 gap-3 items-end">
                    {{-- Tanggal --}}
                    <div class="col-span-2">
                        <x-input-label value="Tanggal" class="mb-1" />
                        <div class="flex gap-1">
                            <x-text-input wire:model="formEntry.riobatDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                class="flex-1 text-sm font-mono min-w-0" />
                            <button type="button" wire:click="refreshRiobatDate" title="Waktu sekarang"
                                class="shrink-0 px-2 text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    {{-- Kode --}}
                    <div class="col-span-1">
                        <x-input-label value="Kode" class="mb-1" />
                        <x-text-input wire:model="formEntry.productId" disabled class="w-full text-sm" />
                    </div>
                    {{-- Produk --}}
                    <div class="col-span-3">
                        <x-input-label value="Produk" class="mb-1" />
                        <x-text-input wire:model="formEntry.productName" disabled class="w-full text-sm" />
                    </div>
                    {{-- Harga --}}
                    <div class="col-span-2">
                        <x-input-label value="Harga" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.productPrice"
                            x-on:keydown.enter.prevent="$nextTick(() => $refs.inputRtnQty?.focus())" />
                        @error('formEntry.productPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Qty --}}
                    <div class="col-span-2">
                        <x-input-label value="Qty" class="mb-1" />
                        <x-text-input-number wire:model="formEntry.productQty"
                            placeholder="Qty"
                            x-ref="inputRtnQty"
                            x-on:keydown.enter.prevent="$el.blur(); $wire.insertRtnObat()" />
                        @error('formEntry.productQty') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    {{-- Buttons --}}
                    <div class="col-span-2 flex gap-2 items-end">
                        <x-primary-button wire:click.prevent="insertRtnObat" wire:loading.attr="disabled"
                            wire:target="insertRtnObat">
                            <span wire:loading.remove wire:target="insertRtnObat">Tambah</span>
                            <span wire:loading wire:target="insertRtnObat"><x-loading class="w-4 h-4" /></span>
                        </x-primary-button>
                        <x-secondary-button wire:click.prevent="resetFormEntry">Batal</x-secondary-button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Return Obat RI</h3>
            <x-badge variant="gray">{{ count($dataRtnObat) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataRtnObat as $item)
                        <tr wire:key="rtn-obat-ri-{{ $item['riobat_no'] ?? $loop->index }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['riobat_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['product_name'] ?? $item['product_id'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $item['riobat_qty'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['riobat_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-red-600 dark:text-red-400 whitespace-nowrap">
                                -Rp {{ number_format(($item['riobat_qty'] ?? 0) * ($item['riobat_price'] ?? 0)) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <x-outline-button type="button"
                                        wire:click.prevent="removeRtnObat({{ $item['riobat_no'] }})"
                                        wire:confirm="Hapus return obat ini?" wire:loading.attr="disabled"
                                        wire:target="removeRtnObat({{ $item['riobat_no'] }})"
                                        class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-outline-button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 5 : 6 }}" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada return obat
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataRtnObat))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total Return</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-red-600 dark:text-red-400">
                                -Rp {{ number_format(collect($dataRtnObat)->sum(fn($i) => ($i['riobat_qty'] ?? 0) * ($i['riobat_price'] ?? 0))) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
