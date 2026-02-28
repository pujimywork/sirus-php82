<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\ProductLovTrait; // Asumsi trait ini sudah ada
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, ProductLovTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $collectingMyProduct = [];
    public string $noRacikan = 'R1'; // default nomor racikan

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
        if ($this->checkEmrRJStatus($rjno)) {
            $this->isFormLocked = true;
        }

        $data = $this->findDataRJ($rjno);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }
        $this->dataDaftarPoliRJ = $data;
        if (!isset($this->dataDaftarPoliRJ['eresepRacikan'])) {
            $this->dataDaftarPoliRJ['eresepRacikan'] = [];
        }
    }

    public function store(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan.');
            return;
        }
        $this->updateJsonRJ($this->rjNo, $this->dataDaftarPoliRJ);
        $this->dispatch('toast', type: 'success', message: 'Eresep racikan berhasil disimpan.');
        $this->dispatch('syncronizeAssessmentDokterRJFindData');
        $this->dispatch('syncronizeAssessmentPerawatRJFindData');
    }

    // Method yang dipanggil dari trait ProductLovTrait saat memilih obat
    public function addProduct($productId, $productName, $salesPrice): void
    {
        $this->collectingMyProduct = [
            'productId' => $productId,
            'productName' => $productName,
            'jenisKeterangan' => 'Racikan',
            'sedia' => '1', // default sedia (bisa diisi manual)
            'dosis' => '',
            'qty' => '',
            'catatan' => '',
            'catatanKhusus' => '',
            'productPrice' => $salesPrice,
        ];
    }

    public function insertProduct(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $rules = [
            'collectingMyProduct.productName' => 'required',
            'collectingMyProduct.dosis' => 'required|max:150',
            'collectingMyProduct.qty' => 'nullable|integer|min:1|max:999',
            'collectingMyProduct.catatan' => 'nullable|max:150',
            'collectingMyProduct.catatanKhusus' => 'nullable|max:150',
            'noRacikan' => 'required|max:20',
        ];
        $messages = [
            'collectingMyProduct.dosis.required' => 'Dosis harus diisi.',
        ];
        $this->validate($rules, $messages);

        try {
            DB::transaction(function () {
                $lastDtl = DB::table('rstxn_rjobatracikans')->max('rjobat_dtl') + 1;
                $takar =
                    DB::table('immst_products')
                        ->where('product_id', $this->collectingMyProduct['productId'] ?? 0)
                        ->value('takar') ?? 'Tablet';

                DB::table('rstxn_rjobatracikans')->insert([
                    'rjobat_dtl' => $lastDtl,
                    'rj_no' => $this->rjNo,
                    'product_name' => $this->collectingMyProduct['productName'],
                    'sedia' => $this->collectingMyProduct['sedia'] ?? '1',
                    'dosis' => $this->collectingMyProduct['dosis'],
                    'qty' => $this->collectingMyProduct['qty'] ?: null,
                    'catatan' => $this->collectingMyProduct['catatan'] ?: null,
                    'catatan_khusus' => $this->collectingMyProduct['catatanKhusus'] ?: null,
                    'no_racikan' => $this->noRacikan,
                    'rj_takar' => $takar,
                    'exp_date' => now()->addDays(30),
                    'etiket_status' => 1,
                ]);

                $this->dataDaftarPoliRJ['eresepRacikan'][] = [
                    'productName' => $this->collectingMyProduct['productName'],
                    'jenisKeterangan' => 'Racikan',
                    'sedia' => $this->collectingMyProduct['sedia'] ?? '1',
                    'dosis' => $this->collectingMyProduct['dosis'],
                    'qty' => $this->collectingMyProduct['qty'] ?: '',
                    'catatan' => $this->collectingMyProduct['catatan'] ?: '',
                    'catatanKhusus' => $this->collectingMyProduct['catatanKhusus'] ?: '',
                    'noRacikan' => $this->noRacikan,
                    'rjObatDtl' => $lastDtl,
                    'rjNo' => $this->rjNo,
                ];

                $this->store(); // update JSON
            });

            $this->reset('collectingMyProduct');
            $this->dispatch('toast', type: 'success', message: 'Racikan berhasil ditambahkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function updateProduct($rjobat_dtl, $dosis, $qty, $catatan, $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $rules = [
            'dosis' => 'required|max:150',
            'qty' => 'nullable|integer|min:1|max:999',
            'catatan' => 'nullable|max:150',
            'catatanKhusus' => 'nullable|max:150',
        ];
        $validator = validator(compact('dosis', 'qty', 'catatan', 'catatanKhusus'), $rules);
        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        DB::table('rstxn_rjobatracikans')
            ->where('rjobat_dtl', $rjobat_dtl)
            ->update([
                'dosis' => $dosis,
                'qty' => $qty ?: null,
                'catatan' => $catatan ?: null,
                'catatan_khusus' => $catatanKhusus ?: null,
            ]);

        // Update di array lokal
        foreach ($this->dataDaftarPoliRJ['eresepRacikan'] as &$item) {
            if (($item['rjObatDtl'] ?? null) == $rjobat_dtl) {
                $item['dosis'] = $dosis;
                $item['qty'] = $qty;
                $item['catatan'] = $catatan;
                $item['catatanKhusus'] = $catatanKhusus;
                break;
            }
        }

        $this->store();
        $this->dispatch('toast', type: 'success', message: 'Racikan diperbarui.');
    }

    public function removeProduct($rjObatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        DB::table('rstxn_rjobatracikans')->where('rjobat_dtl', $rjObatDtl)->delete();

        $this->dataDaftarPoliRJ['eresepRacikan'] = array_values(array_filter($this->dataDaftarPoliRJ['eresepRacikan'], fn($item) => ($item['rjObatDtl'] ?? null) != $rjObatDtl));

        $this->store();
        $this->dispatch('toast', type: 'success', message: 'Racikan dihapus.');
    }

    public function resetcollectingMyProduct(): void
    {
        $this->reset('collectingMyProduct');
    }
};
?>
<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">
            <x-input-label for="" :value="__('Racikan')" :required="false" class="pt-2 sm:text-xl" />

            @role(['Dokter', 'Admin'])
                {{-- STEP 1: Pilih No Racikan + LOV Obat --}}
                @if (!$collectingMyProduct)
                    <div class="grid grid-cols-8 gap-4" x-data="{ selecteddataProductLovIndex: @entangle('selecteddataProductLovIndex') }" x-on:click.away="$wire.resetdataProductLov()"
                        data-lov-wrapper>
                        {{-- No Racikan --}}
                        <div class="col-span-1">
                            <x-input-label for="noRacikan" :value="__('Racikan')" :required="true" />
                            <x-text-input id="noRacikan" placeholder="Racikan" class="mt-1 ml-2" :disabled="$isFormLocked"
                                wire:model="noRacikan" data-racikan-no
                                x-on:keydown.enter.prevent="$el.closest('[data-lov-wrapper]').querySelector('[data-lov-search]').focus()" />
                        </div>

                        {{-- LOV Nama Obat --}}
                        <div class="col-span-7">
                            <x-input-label for="dataProductLovSearch" :value="__('Nama Obat')" :required="true" />
                            <x-text-input id="dataProductLovSearch" placeholder="Nama Obat" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model.live.debounce.500ms="dataProductLovSearch"
                                x-on:keydown.down.prevent="$wire.selectNextdataProductLov()"
                                x-on:keydown.up.prevent="$wire.selectPreviousdataProductLov()"
                                x-on:keydown.enter.prevent="if ($wire.dataProductLov?.length > 0 && selecteddataProductLovIndex >= 0) $wire.enterMydataProductLov(selecteddataProductLovIndex)"
                                x-on:keyup.escape="$wire.resetdataProductLov()" data-lov-search />

                            <div class="py-2 mt-1 overflow-y-auto bg-white border rounded-md shadow-lg max-h-64"
                                x-show="$wire.dataProductLovSearch.length > 3 && $wire.dataProductLov.length > 0"
                                data-lov-list wire:ignore>
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
                    </div>
                @endif

                {{-- STEP 2: Form Detail Racikan --}}
                @if ($collectingMyProduct)
                    <div class="inline-flex space-x-0.5 w-full" data-form="racikanHeader">
                        {{-- No Racikan (readonly) --}}
                        <div class="basis-1/4">
                            <x-input-label for="noRacikan_display" :value="__('Racikan')" :required="true" />
                            <x-text-input id="noRacikan_display" class="mt-1 ml-2" :disabled="true"
                                wire:model="noRacikan" />
                        </div>

                        {{-- Nama Obat (readonly) --}}
                        <div class="basis-3/6">
                            <x-input-label for="collectingMyProduct.productName" :value="__('Nama Obat')" :required="true" />
                            <x-text-input id="collectingMyProduct.productName" class="mt-1 ml-2" :disabled="true"
                                wire:model="collectingMyProduct.productName" />
                        </div>

                        {{-- Dosis --}}
                        <div class="basis-1/4">
                            <x-input-label for="collectingMyProduct.dosis" :value="__('Dosis')" :required="true" />
                            <x-text-input id="collectingMyProduct.dosis" placeholder="Dosis" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model="collectingMyProduct.dosis" data-seq="1"
                                x-init="$nextTick(() => $el.focus())"
                                x-on:keydown.enter.prevent="$el.closest('[data-form]').querySelector('[data-seq=\"2\"]').focus()" />
                            @error('collectingMyProduct.dosis')
                                <x-input-error :messages="$message" />
                            @enderror
                        </div>

                        {{-- Jml Racikan (qty) --}}
                        <div class="basis-2/4">
                            <x-input-label for="collectingMyProduct.qty" :value="__('Jml Racikan')" :required="false" />
                            <x-text-input id="collectingMyProduct.qty" placeholder="Jml" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model="collectingMyProduct.qty" data-seq="2"
                                x-on:keydown.enter.prevent="$el.closest('[data-form]').querySelector('[data-seq=\"3\"]').focus()" />
                        </div>

                        {{-- Catatan --}}
                        <div class="basis-1/4">
                            <x-input-label for="collectingMyProduct.catatan" :value="__('Catatan')" :required="false" />
                            <x-text-input id="collectingMyProduct.catatan" placeholder="Catatan" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model="collectingMyProduct.catatan" data-seq="3"
                                x-on:keydown.enter.prevent="$el.closest('[data-form]').querySelector('[data-seq=\"4\"]').focus()" />
                        </div>

                        {{-- Signa (catatanKhusus) --}}
                        <div class="basis-3/4">
                            <x-input-label for="collectingMyProduct.catatanKhusus" :value="__('Signa')" :required="false" />
                            <x-text-input id="collectingMyProduct.catatanKhusus" placeholder="Signa" class="mt-1 ml-2"
                                :disabled="$isFormLocked" wire:model="collectingMyProduct.catatanKhusus" data-seq="4"
                                x-on:keydown.enter.prevent="$wire.insertProduct(); $nextTick(() => document.querySelector('[data-lov-search]')?.focus())" />
                        </div>

                        {{-- Hapus draft --}}
                        <div class="basis-1/4">
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

            {{-- Tabel Racikan --}}
            <div class="flex flex-col my-2">
                <div class="overflow-x-auto rounded-lg">
                    <div class="inline-block min-w-full align-middle">
                        <div class="overflow-hidden shadow sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-3">Racikan</th>
                                        <th class="px-4 py-3">Obat</th>
                                        <th class="px-4 py-3">Dosis</th>
                                        <th class="px-4 py-3">Jml Racikan</th>
                                        <th class="px-4 py-3">Catatan</th>
                                        <th class="px-4 py-3">Signa</th>
                                        <th class="w-8 px-4 py-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @php $lastNoRacikan = ''; @endphp
                                    @isset($dataDaftarPoliRJ['eresepRacikan'])
                                        @foreach ($dataDaftarPoliRJ['eresepRacikan'] as $key => $eresep)
                                            @php
                                                $borderClass =
                                                    $lastNoRacikan !== $eresep['noRacikan']
                                                        ? 'border-t-2 border-gray-300'
                                                        : '';
                                                $lastNoRacikan = $eresep['noRacikan'];
                                            @endphp
                                            <tr class="{{ $borderClass }} group">
                                                <td class="px-4 py-3">{{ $eresep['jenisKeterangan'] }}
                                                    ({{ $eresep['noRacikan'] }})</td>
                                                <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                <td class="px-4 py-3">
                                                    <x-text-input placeholder="Dosis" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.dosis"
                                                        data-seq="1"
                                                        x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-seq=\"2\"]').focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <x-text-input placeholder="Jml" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.qty"
                                                        data-seq="2"
                                                        x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-seq=\"3\"]').focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <x-text-input placeholder="Catatan" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.catatan"
                                                        data-seq="3"
                                                        x-on:keydown.enter.prevent="$el.closest('tr').querySelector('[data-seq=\"4\"]').focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <x-text-input placeholder="Signa" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.catatanKhusus"
                                                        data-seq="4"
                                                        x-on:keydown.enter.prevent="$wire.updateProduct(
                                                                      '{{ $eresep['rjObatDtl'] }}',
                                                                      $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].dosis,
                                                                      $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].qty,
                                                                      $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].catatan,
                                                                      $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].catatanKhusus
                                                                  ); $nextTick(() => $el.closest('tr').querySelector('[data-seq=\"1\"]').focus())" />
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
