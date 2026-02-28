<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\ProductLovTrait; // Asumsi trait ini sudah dibuat
use App\Http\Traits\Master\ObatKronisTrait; // Asumsi trait ini sudah dibuat
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, ProductLovTrait, ObatKronisTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $collectingMyProduct = [];

    // Untuk LOV
    public array $dataProductLov = [];
    public int $dataProductLovStatus = 0;
    public string $dataProductLovSearch = '';
    public int $selecteddataProductLovIndex = 0;

    #[On('storeAssessmentDokterRJ')]
    public function storeAssessmentDokterRJ(): void
    {
        $this->store();
    }

    #[On('syncronizeAssessmentDokterRJFindData')]
    #[On('syncronizeAssessmentPerawatRJFindData')]
    public function syncData(): void
    {
        $this->findData($this->rjNo);
    }

    public function mount($rjNo = null): void
    {
        if ($rjNo) {
            $this->rjNo = $rjNo;
            $this->findData($rjNo);
        }
    }

    protected function findData($rjno): void
    {
        // Cek apakah kunjungan terkunci
        if ($this->checkEmrRJStatus($rjno)) {
            $this->isFormLocked = true;
        }

        $data = $this->findDataRJ($rjno);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }
        $this->dataDaftarPoliRJ = $data;
        if (!isset($this->dataDaftarPoliRJ['eresep'])) {
            $this->dataDaftarPoliRJ['eresep'] = [];
        }
    }

    public function store(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan.');
            return;
        }
        $this->updateJsonRJ($this->rjNo, $this->dataDaftarPoliRJ);
        $this->dispatch('toast', type: 'success', message: 'Eresep berhasil disimpan.');
        $this->dispatch('syncronizeAssessmentDokterRJFindData');
        $this->dispatch('syncronizeAssessmentPerawatRJFindData');
    }

    // Method yang dipanggil dari trait ProductLovTrait saat memilih obat
    public function addProduct($productId, $productName, $salesPrice): void
    {
        $this->collectingMyProduct = [
            'productId' => $productId,
            'productName' => $productName,
            'jenisKeterangan' => 'NonRacikan',
            'signaX' => '',
            'signaHari' => '',
            'qty' => '',
            'productPrice' => $salesPrice,
            'catatanKhusus' => '',
        ];

        if ($this->isBpjsOrKronis()) {
            $this->checkObatKronis($productId, 1);
        }
    }

    public function updatedCollectingMyProductQty($value): void
    {
        if ($this->isFormLocked || !$this->isBpjsOrKronis()) {
            return;
        }

        $productId = $this->collectingMyProduct['productId'] ?? null;
        $qty = (int) ($value ?: 0);
        if (!$productId || $qty <= 0) {
            return;
        }

        // Throttle sederhana (bisa ditambahkan jika perlu)
        $this->checkObatKronis($productId, $qty);
    }

    public function insertProduct(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $rules = [
            'collectingMyProduct.productId' => 'required',
            'collectingMyProduct.productName' => 'required',
            'collectingMyProduct.signaX' => 'required',
            'collectingMyProduct.signaHari' => 'required',
            'collectingMyProduct.qty' => 'required|integer|min:1|max:999',
            'collectingMyProduct.productPrice' => 'required|numeric',
            'collectingMyProduct.catatanKhusus' => 'nullable|string|max:255',
        ];
        $messages = [
            'collectingMyProduct.signaX.required' => 'Signa harus diisi.',
            'collectingMyProduct.signaHari.required' => 'Hari harus diisi.',
            'collectingMyProduct.qty.required' => 'Jumlah harus diisi.',
        ];
        $this->validate($rules, $messages);

        if ($this->isBpjsOrKronis()) {
            $this->checkObatKronis($this->collectingMyProduct['productId'], (int) $this->collectingMyProduct['qty']);
            // Jika ingin memblokir saat warning, bisa tambahkan kondisi di sini
        }

        try {
            DB::transaction(function () {
                $lastDtl = DB::table('rstxn_rjobats')->max('rjobat_dtl') + 1;
                $takar = DB::table('immst_products')->where('product_id', $this->collectingMyProduct['productId'])->value('takar') ?? 'Tablet';

                DB::table('rstxn_rjobats')->insert([
                    'rjobat_dtl' => $lastDtl,
                    'rj_no' => $this->rjNo,
                    'product_id' => $this->collectingMyProduct['productId'],
                    'qty' => $this->collectingMyProduct['qty'],
                    'price' => $this->collectingMyProduct['productPrice'],
                    'rj_carapakai' => $this->collectingMyProduct['signaX'],
                    'rj_kapsul' => $this->collectingMyProduct['signaHari'],
                    'rj_takar' => $takar,
                    'catatan_khusus' => $this->collectingMyProduct['catatanKhusus'],
                    'rj_ket' => $this->collectingMyProduct['catatanKhusus'],
                    'exp_date' => now()->addDays(30), // atau dari tanggal kunjungan
                    'etiket_status' => 1,
                ]);

                $this->dataDaftarPoliRJ['eresep'][] = [
                    'productId' => $this->collectingMyProduct['productId'],
                    'productName' => $this->collectingMyProduct['productName'],
                    'jenisKeterangan' => 'NonRacikan',
                    'signaX' => $this->collectingMyProduct['signaX'],
                    'signaHari' => $this->collectingMyProduct['signaHari'],
                    'qty' => $this->collectingMyProduct['qty'],
                    'productPrice' => $this->collectingMyProduct['productPrice'],
                    'catatanKhusus' => $this->collectingMyProduct['catatanKhusus'],
                    'rjObatDtl' => $lastDtl,
                    'rjNo' => $this->rjNo,
                ];

                $this->store(); // update JSON
            });

            $this->reset('collectingMyProduct');
            $this->resetKronisState();
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function updateProduct($rjobat_dtl, $qty, $signaX, $signaHari, $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $rules = [
            'qty' => 'required|integer|min:1|max:999',
            'signaX' => 'required',
            'signaHari' => 'required',
            'catatanKhusus' => 'nullable|string|max:255',
        ];
        $validator = validator(compact('qty', 'signaX', 'signaHari', 'catatanKhusus'), $rules);
        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        // Cek kronis
        $row = DB::table('rstxn_rjobats')->select('product_id')->where('rjobat_dtl', $rjobat_dtl)->first();
        if ($row && $this->isBpjsOrKronis()) {
            $this->checkObatKronis($row->product_id, (int) $qty);
        }

        DB::table('rstxn_rjobats')
            ->where('rjobat_dtl', $rjobat_dtl)
            ->update([
                'qty' => $qty,
                'rj_carapakai' => $signaX,
                'rj_kapsul' => $signaHari,
                'catatan_khusus' => $catatanKhusus,
                'rj_ket' => $catatanKhusus,
            ]);

        // Update di array lokal
        foreach ($this->dataDaftarPoliRJ['eresep'] as &$item) {
            if (($item['rjObatDtl'] ?? null) == $rjobat_dtl) {
                $item['qty'] = $qty;
                $item['signaX'] = $signaX;
                $item['signaHari'] = $signaHari;
                $item['catatanKhusus'] = $catatanKhusus;
                break;
            }
        }

        $this->store();
        $this->resetKronisState();
        $this->dispatch('toast', type: 'success', message: 'Obat diperbarui.');
    }

    public function removeProduct($rjObatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        DB::table('rstxn_rjobats')->where('rjobat_dtl', $rjObatDtl)->delete();

        $this->dataDaftarPoliRJ['eresep'] = array_values(array_filter($this->dataDaftarPoliRJ['eresep'], fn($item) => ($item['rjObatDtl'] ?? null) != $rjObatDtl));

        $this->store();
        $this->resetKronisState();
        $this->dispatch('toast', type: 'success', message: 'Obat dihapus.');
    }

    public function resetcollectingMyProduct(): void
    {
        $this->reset('collectingMyProduct');
        $this->resetKronisState();
    }

    // Override jika trait checkRjStatus tidak diperlukan
    // public function checkRjStatus() {}
};
?>
<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">
            <x-input-label for="" :value="__('Non Racikan')" :required="false" class="pt-2 sm:text-xl" />

            {{-- Peringatan Obat Kronis --}}
            @if ($isChronic && ($warnRepeatUnder30d || $warnOverMaxQty))
                <div class="p-3 my-2 text-sm border rounded bg-amber-50 border-amber-300 text-amber-900">
                    <div class="font-semibold">Peringatan Obat Kronis</div>
                    <ul class="pl-5 mt-1 space-y-1 list-disc">
                        @if ($warnRepeatUnder30d)
                            <li>Pengambilan obat terakhir: <span class="font-medium">{{ $lastTebusDate }}</span>
                                ({{ $daysSince }} hari lalu).</li>
                        @endif
                        @if ($warnOverMaxQty)
                            <li>Pemberian dalam 30 hari terakhir: <span
                                    class="font-medium">{{ $qty30d + (float) data_get($collectingMyProduct, 'qty', 0) }}</span>
                                / Maks: <span class="font-medium">{{ $maxQty }}</span>.</li>
                        @endif
                    </ul>
                    @if ($kronisMessage)
                        <div class="mt-2 text-xs opacity-80">{{ $kronisMessage }}</div>
                    @endif
                </div>
            @endif

            @role(['Dokter', 'Admin'])
                {{-- LOV NAMA OBAT --}}
                @if (!$collectingMyProduct)
                    <div x-data="{ selecteddataProductLovIndex: @entangle('selecteddataProductLovIndex') }" x-on:click.away="$wire.resetdataProductLov()" data-lov-wrapper>
                        <x-input-label for="dataProductLovSearch" :value="__('Nama Obat')" :required="true" />
                        <x-text-input id="dataProductLovSearch" placeholder="Nama Obat" class="mt-1 ml-2" :disabled="$isFormLocked"
                            wire:model.live.debounce.500ms="dataProductLovSearch"
                            x-on:keydown.down.prevent="$wire.selectNextdataProductLov()"
                            x-on:keydown.up.prevent="$wire.selectPreviousdataProductLov()"
                            x-on:keydown.enter.prevent="if ($wire.dataProductLov?.length > 0 && selecteddataProductLovIndex >= 0) $wire.enterMydataProductLov(selecteddataProductLovIndex)"
                            x-on:keyup.escape="$wire.resetdataProductLov()" data-lov-search />

                        <div class="py-2 mt-1 overflow-y-auto bg-white border rounded-md shadow-lg max-h-64"
                            x-show="$wire.dataProductLovSearch.length > 3 && $wire.dataProductLov.length > 0" data-lov-list
                            wire:ignore>
                            @foreach ($dataProductLov as $key => $lov)
                                <li wire:key="lov-{{ $lov['product_id'] }}">
                                    <x-dropdown-link wire:click="setMydataProductLov('{{ $key }}')"
                                        class="text-base font-normal {{ $key === $selecteddataProductLovIndex ? 'bg-gray-100 outline-none' : '' }}">
                                        <div>{{ $lov['product_name'] }} / {{ number_format($lov['sales_price']) }}</div>
                                        <div class="text-xs">{{ '(' . ($lov['product_content'] ?? '') . ')' }}</div>
                                    </x-dropdown-link>
                                </li>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Form input obat --}}
                @if ($collectingMyProduct)
                    <div class="flex items-baseline space-x-2" data-form="rjNonRacikHeader">
                        {{-- Nama obat (readonly) --}}
                        <div class="basis-3/6">
                            <x-input-label for="collectingMyProduct.productName" :value="__('Nama Obat')" :required="true" />
                            <x-text-input id="collectingMyProduct.productName" class="mt-1 ml-2" :disabled="true"
                                wire:model="collectingMyProduct.productName" />
                            @error('collectingMyProduct.productName')
                                <x-input-error :messages="$message" />
                            @enderror
                        </div>

                        {{-- Qty --}}
                        <div class="basis-1/12">
                            <x-input-label for="collectingMyProduct.qty" :value="__('Jml')" :required="true" />
                            <x-text-input id="collectingMyProduct.qty" placeholder="Jml" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model.live="collectingMyProduct.qty" data-seq="1"
                                x-init="$nextTick(() => $el.focus())"
                                x-on:keydown.enter.prevent="$el.closest('[data-form]').querySelector('[data-seq=\"2\"]').focus()" />
                            @error('collectingMyProduct.qty')
                                <x-input-error :messages="$message" />
                            @enderror
                        </div>

                        {{-- Signa X --}}
                        <div class="basis-1/12">
                            <x-input-label for="collectingMyProduct.signaX" :value="__('Signa')" :required="false" />
                            <x-text-input id="collectingMyProduct.signaX" placeholder="Signa1" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model="collectingMyProduct.signaX" data-seq="2"
                                x-on:keydown.enter.prevent="$el.closest('[data-form]').querySelector('[data-seq=\"3\"]').focus()" />
                            @error('collectingMyProduct.signaX')
                                <x-input-error :messages="$message" />
                            @enderror
                        </div>
                        <div class="basis-[4%]"><span class="text-sm">dd</span></div>

                        {{-- Signa Hari --}}
                        <div class="basis-1/12">
                            <x-input-label for="collectingMyProduct.signaHari" :value="__('*')" :required="false" />
                            <x-text-input id="collectingMyProduct.signaHari" placeholder="Signa2" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model="collectingMyProduct.signaHari" data-seq="3"
                                x-on:keydown.enter.prevent="$el.closest('[data-form]').querySelector('[data-seq=\"4\"]').focus()" />
                            @error('collectingMyProduct.signaHari')
                                <x-input-error :messages="$message" />
                            @enderror
                        </div>

                        {{-- Catatan Khusus --}}
                        <div class="basis-3/6">
                            <x-input-label for="collectingMyProduct.catatanKhusus" :value="__('Catatan Khusus')" :required="false" />
                            <x-text-input id="collectingMyProduct.catatanKhusus" placeholder="Catatan Khusus"
                                class="mt-1 ml-2" :disabled="$isFormLocked" wire:model="collectingMyProduct.catatanKhusus"
                                data-seq="4"
                                x-on:keydown.enter.prevent="$wire.insertProduct(); $nextTick(() => document.querySelector('[data-lov-search]')?.focus())" />
                            @error('collectingMyProduct.catatanKhusus')
                                <x-input-error :messages="$message" />
                            @enderror
                        </div>

                        {{-- Hapus draft --}}
                        <div class="basis-1/6">
                            <x-input-label for="" :value="__('Hapus')" :required="false" />
                            <x-alternative-button class="inline-flex ml-2" :disabled="$isFormLocked"
                                x-on:click.prevent="$wire.resetcollectingMyProduct().then(() => document.querySelector('[data-lov-search]')?.focus())">
                                <svg class="w-5 h-5 text-gray-800 dark:text-white" aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                    <path
                                        d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                </svg>
                            </x-alternative-button>
                        </div>
                    </div>
                @endif
            @endrole

            {{-- Tabel Resep --}}
            <div class="flex flex-col my-2">
                <div class="overflow-x-auto rounded-lg">
                    <div class="inline-block min-w-full align-middle">
                        <div class="overflow-hidden shadow sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-3">NonRacikan</th>
                                        <th class="px-4 py-3">Obat</th>
                                        <th class="px-4 py-3">Jumlah</th>
                                        <th class="px-4 py-3">Signa</th>
                                        <th class="w-8 px-4 py-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @isset($dataDaftarPoliRJ['eresep'])
                                        @foreach ($dataDaftarPoliRJ['eresep'] as $key => $eresep)
                                            <tr class="border-b group">
                                                <td class="px-4 py-3">{{ $eresep['jenisKeterangan'] }}</td>
                                                <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                <td class="px-4 py-3">
                                                    <x-text-input placeholder="Jml" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.qty"
                                                        data-seq="1"
                                                        x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-seq=\"2\"]').focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-baseline space-x-2">
                                                        <div class="basis-1/5">
                                                            <x-text-input placeholder="Signa1" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.signaX"
                                                                data-seq="2"
                                                                x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-seq=\"3\"]').focus()" />
                                                        </div>
                                                        <div class="flex-none"><span class="text-sm">dd</span></div>
                                                        <div class="basis-1/5">
                                                            <x-text-input placeholder="Signa2" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.signaHari"
                                                                data-seq="3"
                                                                x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-seq=\"4\"]').focus()" />
                                                        </div>
                                                        <div class="flex-1">
                                                            <x-text-input placeholder="Catatan Khusus" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.catatanKhusus"
                                                                data-seq="4"
                                                                x-on:keydown.enter.prevent="$wire.updateProduct(
                                                                              '{{ $eresep['rjObatDtl'] }}',
                                                                              $wire.dataDaftarPoliRJ.eresep[{{ $key }}].qty,
                                                                              $wire.dataDaftarPoliRJ.eresep[{{ $key }}].signaX,
                                                                              $wire.dataDaftarPoliRJ.eresep[{{ $key }}].signaHari,
                                                                              $wire.dataDaftarPoliRJ.eresep[{{ $key }}].catatanKhusus
                                                                          ); $nextTick(() => $el.closest('tr').querySelector('[data-seq=\"1\"]').focus())" />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    @role(['Dokter', 'Admin'])
                                                        <x-alternative-button class="inline-flex" :disabled="$isFormLocked"
                                                            wire:click="removeProduct('{{ $eresep['rjObatDtl'] }}')">
                                                            <svg class="w-5 h-5 text-gray-800 dark:text-white"
                                                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                                                fill="currentColor" viewBox="0 0 18 20">
                                                                <path
                                                                    d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                                            </svg>
                                                        </x-alternative-button>
                                                    @endrole
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endisset
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
