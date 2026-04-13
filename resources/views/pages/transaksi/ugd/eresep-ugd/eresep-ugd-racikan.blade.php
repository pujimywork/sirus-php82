<?php
// resources/views/pages/transaksi/ugd/eresep-ugd/eresep-ugd-racikan.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];
    public array $formEresepRacikan = [];
    public string $noRacikan = 'R1';

    public array $renderVersions = [];
    protected array $renderAreas = ['eresep-racikan-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['eresep-racikan-ugd']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-eresep-racikan-ugd')]
    public function openEresepRacikan(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();
        $this->loadData($rjNo);
        $this->incrementVersion('eresep-racikan-ugd');
    }

    /* ===============================
     | LOAD DATA
     =============================== */
    protected function loadData($rjNo): void
    {
        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        $this->rjNo = $rjNo;
        $this->dataDaftarUGD = $data;
        $this->dataDaftarUGD['eresepRacikan'] ??= [];
    }

    /* ===============================
     | SYNC ERESEP RACIKAN JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockUGDRow()-nya.
     =============================== */
    private function syncEresepRacikanJson(): void
    {
        $data = $this->findDataUGD($this->rjNo);

        if (empty($data)) {
            throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
        }

        $data['eresepRacikan'] = $this->dataDaftarUGD['eresepRacikan'] ?? [];

        $this->updateJsonUGD($this->rjNo, $data);
        $this->dataDaftarUGD = $data;
    }

    /* ===============================
     | SAVE (explicit / event)
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (empty($this->dataDaftarUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);
                $this->syncEresepRacikanJson();
            });

            $this->incrementVersion('eresep-racikan-ugd');
            $this->dispatch('toast', type: 'success', message: 'E-Resep Racikan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV SELECTED
     =============================== */
    #[On('lov.selected.eresepUgdObatRacikan')]
    public function eresepUgdObatRacikan(string $target, array $payload): void
    {
        $this->addProduct($payload['product_id'], $payload['product_name'], (float) ($payload['sales_price'] ?? 0));
    }

    public function addProduct(string $productId, string $productName, float $salesPrice): void
    {
        $this->formEresepRacikan = [
            'productId' => $productId,
            'productName' => $productName,
            'jenisKeterangan' => 'Racikan',
            'sedia' => 1,
            'dosis' => '',
            'qty' => '',
            'catatan' => '',
            'catatanKhusus' => '',
            'noRacikan' => $this->noRacikan,
            'signaX' => 1,
            'signaHari' => 1,
            'productPrice' => $salesPrice,
        ];
        $this->incrementVersion('eresep-racikan-ugd');
    }

    /* ===============================
     | INSERT PRODUCT
     =============================== */
    public function insertProduct(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $this->validate(
            [
                'formEresepRacikan.productName' => 'required',
                'formEresepRacikan.dosis' => 'required|max:150',
                'formEresepRacikan.sedia' => 'required',
                'formEresepRacikan.qty' => 'nullable|integer|digits_between:1,3',
                'formEresepRacikan.catatan' => 'nullable|max:150',
                'formEresepRacikan.catatanKhusus' => 'nullable|max:150',
            ],
            [
                'formEresepRacikan.productName.required' => 'Nama obat harus diisi.',
                'formEresepRacikan.dosis.required' => 'Dosis harus diisi.',
                'formEresepRacikan.sedia.required' => 'Sediaan harus diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Insert ke tabel racikan
                $lastInserted = DB::table('rstxn_ugdobatracikans')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();

                $takar = DB::table('immst_products')->where('product_id', $this->formEresepRacikan['productId'])->value('takar') ?? 'Tablet';

                DB::table('rstxn_ugdobatracikans')->insert([
                    'rjobat_dtl' => $lastInserted->rjobat_dtl_max,
                    'rj_no' => $this->rjNo,
                    'product_name' => $this->formEresepRacikan['productName'],
                    'sedia' => $this->formEresepRacikan['sedia'],
                    'dosis' => $this->formEresepRacikan['dosis'],
                    'qty' => $this->formEresepRacikan['qty'] ?: null,
                    'catatan' => $this->formEresepRacikan['catatan'] ?: null,
                    'catatan_khusus' => $this->formEresepRacikan['catatanKhusus'] ?: null,
                    'no_racikan' => $this->formEresepRacikan['noRacikan'],
                    'ugd_takar' => $takar,
                    'exp_date' => Carbon::now(config('app.timezone'))->addDays(30),
                    'etiket_status' => 1,
                ]);

                // 3. Append ke array lokal
                $this->dataDaftarUGD['eresepRacikan'][] = [
                    'jenisKeterangan' => 'Racikan',
                    'productId' => $this->formEresepRacikan['productId'],
                    'productName' => $this->formEresepRacikan['productName'],
                    'sedia' => $this->formEresepRacikan['sedia'],
                    'dosis' => $this->formEresepRacikan['dosis'],
                    'qty' => $this->formEresepRacikan['qty'] ?? '',
                    'catatan' => $this->formEresepRacikan['catatan'] ?? '',
                    'catatanKhusus' => $this->formEresepRacikan['catatanKhusus'] ?? '',
                    'noRacikan' => $this->formEresepRacikan['noRacikan'],
                    'signaX' => $this->formEresepRacikan['signaX'],
                    'signaHari' => $this->formEresepRacikan['signaHari'],
                    'productPrice' => 0,
                    'rjObatDtl' => $lastInserted->rjobat_dtl_max,
                    'rjNo' => $this->rjNo,
                ];

                // 4. Sync JSON — row sudah di-lock
                $this->syncEresepRacikanJson();
            });

            // 5. Notify + reset — di luar transaksi
            $this->afterSave('Obat racikan berhasil ditambahkan.');
            $this->reset('formEresepRacikan');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE PRODUCT
     =============================== */
    public function updateProduct(int $rjobatDtl, mixed $qty, string $dosis, ?string $catatan, ?string $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $validator = validator(compact('qty', 'dosis', 'catatan', 'catatanKhusus'), [
            'dosis' => 'required|max:150',
            'qty' => 'nullable|integer|digits_between:1,3',
            'catatan' => 'nullable|max:150',
            'catatanKhusus' => 'nullable|max:150',
        ]);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        try {
            DB::transaction(function () use ($rjobatDtl, $qty, $dosis, $catatan, $catatanKhusus) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Update tabel racikan
                DB::table('rstxn_ugdobatracikans')
                    ->where('rjobat_dtl', $rjobatDtl)
                    ->update([
                        'qty' => $qty ?: null,
                        'dosis' => $dosis,
                        'catatan' => $catatan,
                        'catatan_khusus' => $catatanKhusus,
                    ]);

                // 3. Update array lokal
                foreach ($this->dataDaftarUGD['eresepRacikan'] as &$item) {
                    if (($item['rjObatDtl'] ?? null) == $rjobatDtl) {
                        $item['qty'] = $qty;
                        $item['dosis'] = $dosis;
                        $item['catatan'] = $catatan;
                        $item['catatanKhusus'] = $catatanKhusus;
                        break;
                    }
                }
                unset($item);

                // 4. Sync JSON — row sudah di-lock
                $this->syncEresepRacikanJson();
            });

            $this->afterSave('Obat racikan diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE PRODUCT
     =============================== */
    public function removeProduct(int $rjObatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($rjObatDtl) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Cek keberadaan
                $exists = collect($this->dataDaftarUGD['eresepRacikan'] ?? [])->contains('rjObatDtl', $rjObatDtl);
                if (!$exists) {
                    throw new \RuntimeException("Obat racikan dengan ID {$rjObatDtl} tidak ditemukan.");
                }

                // 3. Hapus dari tabel
                DB::table('rstxn_ugdobatracikans')->where('rjobat_dtl', $rjObatDtl)->delete();

                // 4. Hapus dari array lokal
                $this->dataDaftarUGD['eresepRacikan'] = collect($this->dataDaftarUGD['eresepRacikan'] ?? [])
                    ->where('rjObatDtl', '!=', $rjObatDtl)
                    ->values()
                    ->toArray();

                // 5. Sync JSON — row sudah di-lock
                $this->syncEresepRacikanJson();
            });

            $this->afterSave('Obat racikan berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    public function resetFormEresepRacikan(): void
    {
        $this->reset('formEresepRacikan');
        $this->incrementVersion('eresep-racikan-ugd');
    }

    private function afterSave(string $message): void
    {
        $this->incrementVersion('eresep-racikan-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->formEresepRacikan = [];
        $this->noRacikan = 'R1';
    }
};
?>

<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">
            <div wire:key="{{ $this->renderKey('eresep-racikan-ugd', [$rjNo ?? 'new']) }}">
                <x-input-label :value="__('Racikan')" :required="false" class="pt-2 sm:text-xl" />

                @role(['Dokter', 'Admin'])
                    <div x-data>
                        @if (!$formEresepRacikan)
                            <div class="flex items-center gap-3 mt-2">
                                <div class="flex-1">
                                    <livewire:lov.product.lov-product target="eresepUgdObatRacikan" label="Nama Obat Racikan"
                                        :readonly="$isFormLocked" />
                                </div>
                                <div class="w-32">
                                    <x-input-label value="No Racikan" />
                                    <x-text-input wire:model="noRacikan" placeholder="R1" :disabled="$isFormLocked"
                                        class="mt-1" />
                                </div>
                            </div>
                        @endif

                        @if ($formEresepRacikan)
                            <div class="flex items-end w-full gap-1 mt-2">
                                <div class="flex-[1]">
                                    <x-input-label value="Racikan" />
                                    <x-text-input class="w-full mt-1" wire:model="formEresepRacikan.noRacikan" />
                                </div>
                                <div class="flex-[3]">
                                    <x-input-label value="Nama Obat" :required="true" />
                                    <x-text-input class="w-full mt-1" :disabled="true"
                                        wire:model="formEresepRacikan.productName" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-label value="Sedia" :required="true" />
                                    <x-text-input placeholder="Sedia" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.sedia" x-ref="sedia" x-init="$nextTick(() => $el.focus())"
                                        x-on:keydown.enter.prevent="$refs.dosis.focus()" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-label value="Dosis" :required="true" />
                                    <x-text-input placeholder="Dosis" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.dosis" x-ref="dosis"
                                        x-on:keydown.enter.prevent="$refs.qty.focus()" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-label value="Jml" />
                                    <x-text-input placeholder="Jml" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.qty" x-ref="qty"
                                        x-on:keydown.enter.prevent="$refs.catatan.focus()" />
                                </div>
                                <div class="flex-[2]">
                                    <x-input-label value="Catatan" />
                                    <x-text-input placeholder="Catatan" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.catatan" x-ref="catatan"
                                        x-on:keydown.enter.prevent="$refs.signa.focus()" />
                                </div>
                                <div class="flex-[2]">
                                    <x-input-label value="Signa" />
                                    <x-text-input placeholder="Signa" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.catatanKhusus" x-ref="signa"
                                        x-on:keydown.enter.prevent="$wire.insertProduct()" />
                                </div>
                                <div class="ml-auto shrink-0">
                                    <x-input-label value="" />
                                    <x-secondary-button class="inline-flex mt-1" :disabled="$isFormLocked"
                                        wire:click="resetFormEresepRacikan">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 18 20">
                                            <path
                                                d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                        </svg>
                                    </x-secondary-button>
                                </div>
                            </div>
                            <div class="flex w-full gap-1 text-xs">
                                <div class="flex-[1]"></div>
                                <div class="flex-[3]"><x-input-error :messages="$errors->get('formEresepRacikan.productName')" /></div>
                                <div class="flex-[1]"><x-input-error :messages="$errors->get('formEresepRacikan.sedia')" /></div>
                                <div class="flex-[1]"><x-input-error :messages="$errors->get('formEresepRacikan.dosis')" /></div>
                                <div class="flex-[1]"><x-input-error :messages="$errors->get('formEresepRacikan.qty')" /></div>
                                <div class="flex-[2]"><x-input-error :messages="$errors->get('formEresepRacikan.catatan')" /></div>
                                <div class="flex-[2]"><x-input-error :messages="$errors->get('formEresepRacikan.catatanKhusus')" /></div>
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
                                            <th class="px-4 py-3 w-28">Racikan</th>
                                            <th class="px-4 py-3">Obat</th>
                                            <th class="w-16 px-4 py-3">Sedia</th>
                                            <th class="w-24 px-4 py-3">Dosis</th>
                                            <th class="w-20 px-4 py-3">Jml Racikan</th>
                                            <th class="px-4 py-3">Catatan</th>
                                            <th class="px-4 py-3">Signa</th>
                                            <th class="w-8 px-4 py-3 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @isset($dataDaftarUGD['eresepRacikan'])
                                            @php $myPreviousRow = null; @endphp
                                            @foreach ($dataDaftarUGD['eresepRacikan'] as $key => $eresep)
                                                @isset($eresep['jenisKeterangan'])
                                                    @php
                                                        $myRacikanBorder =
                                                            $myPreviousRow !== $eresep['noRacikan']
                                                                ? 'border-t-2 border-red-400'
                                                                : 'border-t-2 border-gray-200';
                                                    @endphp
                                                    <tr class="{{ $myRacikanBorder }} group" x-data>
                                                        <td class="px-4 py-3 w-28 whitespace-nowrap">
                                                            {{ $eresep['jenisKeterangan'] . ' (' . $eresep['noRacikan'] . ')' }}
                                                        </td>
                                                        <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                        <td class="w-16 px-4 py-3">{{ $eresep['sedia'] }}</td>
                                                        <td class="w-24 px-4 py-3">
                                                            <x-text-input placeholder="Dosis" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresepRacikan.{{ $key }}.dosis"
                                                                x-ref="dosis{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.qty{{ $key }}.focus()" />
                                                        </td>
                                                        <td class="w-20 px-4 py-3">
                                                            <x-text-input placeholder="Jml" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresepRacikan.{{ $key }}.qty"
                                                                x-ref="qty{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.catatan{{ $key }}.focus()" />
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <x-text-input placeholder="Catatan" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresepRacikan.{{ $key }}.catatan"
                                                                x-ref="catatan{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.catatanKhusus{{ $key }}.focus()" />
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <x-text-input placeholder="Signa" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarUGD.eresepRacikan.{{ $key }}.catatanKhusus"
                                                                x-ref="catatanKhusus{{ $key }}"
                                                                x-on:keydown.enter.prevent="
                                                                    $wire.updateProduct(
                                                                        '{{ $eresep['rjObatDtl'] }}',
                                                                        $wire.dataDaftarUGD.eresepRacikan[{{ $key }}].qty,
                                                                        $wire.dataDaftarUGD.eresepRacikan[{{ $key }}].dosis,
                                                                        $wire.dataDaftarUGD.eresepRacikan[{{ $key }}].catatan,
                                                                        $wire.dataDaftarUGD.eresepRacikan[{{ $key }}].catatanKhusus
                                                                    );
                                                                    $nextTick(() => $refs.dosis{{ $key }}.focus())
                                                                " />
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
                                                    @php $myPreviousRow = $eresep['noRacikan']; @endphp
                                                @endisset
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
</div>
