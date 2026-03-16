<?php
// resources/views/pages/transaksi/ugd/eresep-ugd/eresep-ugd-non-racikan.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];
    public array $formEresep = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['eresep-non-racikan-ugd'];

    public function mount(): void
    {
        $this->registerAreas(['eresep-non-racikan-ugd']);
        $this->findData($this->rjNo);
    }

    #[On('open-eresep-non-racikan-ugd')]
    public function openEresep(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }
        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();
        $this->findData($rjNo);
        $this->incrementVersion('eresep-non-racikan-ugd');
    }

    protected function findData($rjNo): void
    {
        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }
        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }
        $this->dataDaftarUGD = $data;
        $this->dataDaftarUGD['eresep'] ??= [];
    }

    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }
        try {
            DB::transaction(function () {
                $existingData = $this->findDataUGD($this->rjNo);
                $mergedData = array_replace_recursive($existingData ?? [], array_intersect_key($this->dataDaftarUGD, ['eresep' => true]));
                $mergedData['eresep'] = $this->dataDaftarUGD['eresep'] ?? [];
                $this->updateJsonUGD($this->rjNo, $mergedData);
            });
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    #[On('lov.selected.eresepUgdObatNonRacikan')]
    public function eresepUgdObatNonRacikan(string $target, array $payload): void
    {
        $this->addProduct($payload['product_id'], $payload['product_name'], (float) ($payload['sales_price'] ?? 0));
    }

    public function addProduct(string $productId, string $productName, float $salesPrice): void
    {
        $this->formEresep = [
            'productId' => $productId,
            'productName' => $productName,
            'jenisKeterangan' => 'NonRacikan',
            'signaX' => '',
            'signaHari' => '',
            'qty' => '',
            'productPrice' => $salesPrice,
            'catatanKhusus' => '',
        ];
        $this->incrementVersion('eresep-non-racikan-ugd');
    }

    public function insertProduct(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }
        $this->validate(
            [
                'formEresep.productId' => 'required',
                'formEresep.productName' => 'required',
                'formEresep.signaX' => 'required',
                'formEresep.signaHari' => 'required',
                'formEresep.qty' => 'required|integer|min:1|max:999',
                'formEresep.productPrice' => 'required|numeric',
                'formEresep.catatanKhusus' => 'nullable|string|max:255',
            ],
            [
                'formEresep.signaX.required' => 'Signa harus diisi.',
                'formEresep.signaHari.required' => 'Hari harus diisi.',
                'formEresep.qty.required' => 'Jumlah harus diisi.',
            ],
        );
        try {
            DB::transaction(function () {
                $lastDtl = DB::table('rstxn_ugdobats')->max('rjobat_dtl') + 1;
                $takar = DB::table('immst_products')->where('product_id', $this->formEresep['productId'])->value('takar') ?? 'Tablet';
                DB::table('rstxn_ugdobats')->insert([
                    'rjobat_dtl' => $lastDtl,
                    'rj_no' => $this->rjNo,
                    'product_id' => $this->formEresep['productId'],
                    'qty' => $this->formEresep['qty'],
                    'price' => $this->formEresep['productPrice'],
                    'rj_carapakai' => $this->formEresep['signaX'],
                    'rj_kapsul' => $this->formEresep['signaHari'],
                    'rj_takar' => $takar,
                    'catatan_khusus' => $this->formEresep['catatanKhusus'],
                    'rj_ket' => $this->formEresep['catatanKhusus'],
                    'exp_date' => now()->addDays(30),
                    'etiket_status' => 1,
                ]);
                $this->dataDaftarUGD['eresep'][] = [
                    'productId' => $this->formEresep['productId'],
                    'productName' => $this->formEresep['productName'],
                    'jenisKeterangan' => 'NonRacikan',
                    'signaX' => $this->formEresep['signaX'],
                    'signaHari' => $this->formEresep['signaHari'],
                    'qty' => $this->formEresep['qty'],
                    'productPrice' => $this->formEresep['productPrice'],
                    'catatanKhusus' => $this->formEresep['catatanKhusus'],
                    'rjObatDtl' => $lastDtl,
                    'rjNo' => $this->rjNo,
                ];
                $this->save();
            });
            $this->afterSave('Obat berhasil ditambahkan.');
            $this->reset('formEresep');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function updateProduct(int $rjobatDtl, mixed $qty, string $signaX, string $signaHari, ?string $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }
        $validator = validator(compact('qty', 'signaX', 'signaHari', 'catatanKhusus'), [
            'qty' => 'required|integer|min:1|max:999',
            'signaX' => 'required',
            'signaHari' => 'required',
            'catatanKhusus' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }
        DB::table('rstxn_ugdobats')
            ->where('rjobat_dtl', $rjobatDtl)
            ->update([
                'qty' => $qty,
                'rj_carapakai' => $signaX,
                'rj_kapsul' => $signaHari,
                'catatan_khusus' => $catatanKhusus,
                'rj_ket' => $catatanKhusus,
            ]);
        foreach ($this->dataDaftarUGD['eresep'] as &$item) {
            if (($item['rjObatDtl'] ?? null) == $rjobatDtl) {
                $item['qty'] = $qty;
                $item['signaX'] = $signaX;
                $item['signaHari'] = $signaHari;
                $item['catatanKhusus'] = $catatanKhusus;
                break;
            }
        }
        $this->save();
        $this->afterSave('Obat diperbarui.');
    }

    public function removeProduct(int $rjObatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }
        try {
            DB::transaction(function () use ($rjObatDtl) {
                $exists = collect($this->dataDaftarUGD['eresep'] ?? [])->contains('rjObatDtl', $rjObatDtl);
                if (!$exists) {
                    throw new \Exception("Obat dengan ID {$rjObatDtl} tidak ditemukan.");
                }
                DB::table('rstxn_ugdobats')->where('rjobat_dtl', $rjObatDtl)->delete();
                $this->dataDaftarUGD['eresep'] = collect($this->dataDaftarUGD['eresep'] ?? [])
                    ->where('rjObatDtl', '!=', $rjObatDtl)
                    ->values()
                    ->toArray();
                $this->save();
            });
            $this->afterSave('Obat berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function resetFormEresep(): void
    {
        $this->reset('formEresep');
        $this->incrementVersion('eresep-non-racikan-ugd');
    }

    private function afterSave(string $message): void
    {
        $this->incrementVersion('eresep-non-racikan-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->formEresep = [];
    }
};
?>

<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">
            <div wire:key="{{ $this->renderKey('eresep-non-racikan-ugd', [$rjNo ?? 'new']) }}">
                <x-input-label :value="__('Non Racikan')" :required="false" class="pt-2 sm:text-xl" />

                @role(['Dokter', 'Admin'])
                    <div x-data>
                        @if (!$formEresep)
                            <div class="mt-2">
                                <livewire:lov.product.lov-product target="eresepUgdObatNonRacikan" label="Nama Obat"
                                    :initialProductId="$formEresep['productId'] ?? null" :readonly="$isFormLocked" />
                            </div>
                        @endif

                        @if ($formEresep)
                            <div class="flex items-end w-full gap-1 mt-2">
                                <div class="flex-[3]">
                                    <x-input-label value="Nama Obat" :required="true" />
                                    <x-text-input class="w-full mt-1" :disabled="true"
                                        wire:model="formEresep.productName" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-label value="Jml" :required="true" />
                                    <x-text-input placeholder="Jml" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model.live="formEresep.qty" x-ref="qty" x-init="$nextTick(() => $el.focus())"
                                        x-on:keydown.enter.prevent="$refs.signaX.focus()" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-label value="Signa" />
                                    <x-text-input placeholder="Signa1" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresep.signaX" x-ref="signaX"
                                        x-on:keydown.enter.prevent="$refs.signaHari.focus()" />
                                </div>
                                <div class="pb-2 shrink-0"><span class="text-sm">dd</span></div>
                                <div class="flex-[1]">
                                    <x-input-label value="*" />
                                    <x-text-input placeholder="Signa2" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresep.signaHari" x-ref="signaHari"
                                        x-on:keydown.enter.prevent="$refs.catatanKhusus.focus()" />
                                </div>
                                <div class="flex-[3]">
                                    <x-input-label value="Catatan Khusus" />
                                    <x-text-input placeholder="Catatan Khusus" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresep.catatanKhusus" x-ref="catatanKhusus"
                                        x-on:keydown.enter.prevent="$wire.insertProduct()" />
                                </div>
                                <div class="ml-auto shrink-0">
                                    <x-input-label value="" />
                                    <x-secondary-button class="inline-flex mt-1" :disabled="$isFormLocked"
                                        wire:click="resetFormEresep">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 18 20">
                                            <path
                                                d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                        </svg>
                                    </x-secondary-button>
                                </div>
                            </div>
                            <div class="flex w-full gap-1 text-xs">
                                <div class="flex-[3]"><x-input-error :messages="$errors->get('formEresep.productName')" /></div>
                                <div class="flex-[1]"><x-input-error :messages="$errors->get('formEresep.qty')" /></div>
                                <div class="flex-[1]"><x-input-error :messages="$errors->get('formEresep.signaX')" /></div>
                                <div class="shrink-0"></div>
                                <div class="flex-[1]"><x-input-error :messages="$errors->get('formEresep.signaHari')" /></div>
                                <div class="flex-[3]"><x-input-error :messages="$errors->get('formEresep.catatanKhusus')" /></div>
                                <div class="ml-auto shrink-0"></div>
                            </div>
                        @endif
                    </div>
                @endrole

                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                        <tr>
                                            <th class="w-24 px-4 py-3">NonRacikan</th>
                                            <th class="px-4 py-3">Obat</th>
                                            <th class="w-20 px-4 py-3">Jumlah</th>
                                            <th class="px-4 py-3">Signa</th>
                                            <th class="w-8 px-4 py-3 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @foreach ($dataDaftarUGD['eresep'] ?? [] as $key => $eresep)
                                            <tr class="border-b group" x-data>
                                                <td class="w-24 px-4 py-3 whitespace-nowrap">
                                                    {{ $eresep['jenisKeterangan'] }}</td>
                                                <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                <td class="w-20 px-4 py-3">
                                                    <x-text-input placeholder="Jml" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarUGD.eresep.{{ $key }}.qty"
                                                        x-ref="qty{{ $key }}"
                                                        x-on:keydown.enter.prevent="$refs.signaX{{ $key }}.focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-1">
                                                        <div class="w-16 shrink-0">
                                                            <x-text-input placeholder="Signa1" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresep.{{ $key }}.signaX"
                                                                x-ref="signaX{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.signaHari{{ $key }}.focus()" />
                                                        </div>
                                                        <span class="text-sm text-gray-500 shrink-0">dd</span>
                                                        <div class="w-16 shrink-0">
                                                            <x-text-input placeholder="Signa2" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresep.{{ $key }}.signaHari"
                                                                x-ref="signaHari{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.catatanKhusus{{ $key }}.focus()" />
                                                        </div>
                                                        <div class="flex-1">
                                                            <x-text-input placeholder="Catatan Khusus"
                                                                :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresep.{{ $key }}.catatanKhusus"
                                                                x-ref="catatanKhusus{{ $key }}"
                                                                x-on:keydown.enter.prevent="
                                                                    $wire.updateProduct(
                                                                        '{{ $eresep['rjObatDtl'] }}',
                                                                        $wire.dataDaftarUGD.eresep[{{ $key }}].qty,
                                                                        $wire.dataDaftarUGD.eresep[{{ $key }}].signaX,
                                                                        $wire.dataDaftarUGD.eresep[{{ $key }}].signaHari,
                                                                        $wire.dataDaftarUGD.eresep[{{ $key }}].catatanKhusus
                                                                    );
                                                                    $nextTick(() => $refs.qty{{ $key }}.focus())
                                                                " />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="w-8 px-4 py-3 text-center">
                                                    @role(['Dokter', 'Admin'])
                                                        <x-secondary-button class="inline-flex" :disabled="$isFormLocked"
                                                            wire:click="removeProduct('{{ $eresep['rjObatDtl'] }}')">
                                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 18 20">
                                                                <path
                                                                    d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                                            </svg>
                                                        </x-secondary-button>
                                                    @endrole
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
