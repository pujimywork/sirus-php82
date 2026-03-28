<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public array $formEresep = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['eresep-non-racikan-rj'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['eresep-non-racikan-rj']);
        $this->findData($this->rjNo);
    }

    /* ===============================
     | OPEN ERESEP RJ
     =============================== */
    #[On('open-eresep-non-racikan-rj')]
    public function openEresep(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        $this->findData($rjNo);

        // 🔥 INCREMENT: Refresh seluruh area eresep
        $this->incrementVersion('eresep-non-racikan-rj');
    }

    /* ===============================
     | FIND DATA
     =============================== */
    protected function findData($rjNo): void
    {
        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;

        // Initialize eresep jika belum ada
        $this->dataDaftarPoliRJ['eresep'] ??= [];
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Tidak membungkus transaction/lock sendiri.
     =============================== */
    private function syncEresepJson(): void
    {
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        // Patch hanya key 'eresep' — key lain tidak tersentuh
        $data['eresep'] = $this->dataDaftarPoliRJ['eresep'] ?? [];

        $this->updateJsonRJ($this->rjNo, $data);
        $this->dataDaftarPoliRJ = $data;
    }

    /* ===============================
     | SAVE — standalone (tombol simpan manual / external call)
     =============================== */
    public function save(): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () {
                // 3. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 4. Sync JSON via helper
                $this->syncEresepJson();
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV SELECTED — DARI livewire:lov.product.lov-product
     =============================== */
    #[On('lov.selected.eresepRjObatNonRacikan')]
    public function eresepRjObatNonRacikan(string $target, array $payload): void
    {
        $this->addProduct($payload['product_id'], $payload['product_name'], (float) ($payload['sales_price'] ?? 0));
    }

    /* ===============================
     | ADD PRODUCT (dari LOV) — hanya set draft form, belum simpan ke DB
     =============================== */
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

        // 🔥 INCREMENT: Refresh area untuk tampilkan form input
        $this->incrementVersion('eresep-non-racikan-rj');
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
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Insert ke tabel transaksi
                $lastDtl = DB::table('rstxn_rjobats')->max('rjobat_dtl') + 1;
                $takar = DB::table('immst_products')->where('product_id', $this->formEresep['productId'])->value('takar') ?? 'Tablet';

                DB::table('rstxn_rjobats')->insert([
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

                // 3. Tambah ke array lokal
                $this->dataDaftarPoliRJ['eresep'][] = [
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

                // 4. Sync JSON — row sudah di-lock, tidak perlu lock/transaction lagi
                $this->syncEresepJson();
            });

            $this->afterSave('Obat berhasil ditambahkan.');
            $this->reset('formEresep');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE PRODUCT
     =============================== */
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

        try {
            DB::transaction(function () use ($rjobatDtl, $qty, $signaX, $signaHari, $catatanKhusus) {
                // 1. Lock row dulu — update tabel + JSON harus atomik
                $this->lockRJRow($this->rjNo);

                // 2. Update tabel transaksi
                DB::table('rstxn_rjobats')
                    ->where('rjobat_dtl', $rjobatDtl)
                    ->update([
                        'qty' => $qty,
                        'rj_carapakai' => $signaX,
                        'rj_kapsul' => $signaHari,
                        'catatan_khusus' => $catatanKhusus,
                        'rj_ket' => $catatanKhusus,
                    ]);

                // 3. Update array lokal
                foreach ($this->dataDaftarPoliRJ['eresep'] as &$item) {
                    if (($item['rjObatDtl'] ?? null) == $rjobatDtl) {
                        $item['qty'] = $qty;
                        $item['signaX'] = $signaX;
                        $item['signaHari'] = $signaHari;
                        $item['catatanKhusus'] = $catatanKhusus;
                        break;
                    }
                }
                unset($item);

                // 4. Sync JSON
                $this->syncEresepJson();
            });

            $this->afterSave('Obat diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui obat: ' . $e->getMessage());
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
                $this->lockRJRow($this->rjNo);

                // 2. Validasi keberadaan obat
                $obatExists = collect($this->dataDaftarPoliRJ['eresep'] ?? [])->contains('rjObatDtl', $rjObatDtl);

                if (!$obatExists) {
                    throw new \RuntimeException("Obat dengan ID {$rjObatDtl} tidak ditemukan.");
                }

                // 3. Hapus dari tabel transaksi
                DB::table('rstxn_rjobats')->where('rjobat_dtl', $rjObatDtl)->delete();

                // 4. Hapus dari array lokal
                $this->dataDaftarPoliRJ['eresep'] = collect($this->dataDaftarPoliRJ['eresep'] ?? [])
                    ->where('rjObatDtl', '!=', $rjObatDtl)
                    ->values()
                    ->toArray();

                // 5. Sync JSON
                $this->syncEresepJson();
            });

            $this->afterSave('Obat berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus obat: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET FORM ERESEP (draft)
     =============================== */
    public function resetFormEresep(): void
    {
        $this->reset('formEresep');

        // 🔥 INCREMENT: Kembali ke tampilan LOV
        $this->incrementVersion('eresep-non-racikan-rj');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'eresep-non-racikan-rj-actions');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('eresep-non-racikan-rj');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->formEresep = [];
    }
};
?>

<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">

            {{-- CONTAINER UTAMA dengan wire:key --}}
            <div wire:key="{{ $this->renderKey('eresep-non-racikan-rj', [$rjNo ?? 'new']) }}">
                <x-input-label for="" :value="__('Non Racikan')" :required="false" class="pt-2 sm:text-xl" />

                @role(['Dokter', 'Admin'])
                    <div x-data x-ref="nonRacikanSection">

                        {{-- LOV Obat --}}
                        @if (!$formEresep)
                            <div class="mt-2" x-init="$nextTick(() => $el.querySelector('input:not([disabled])')?.focus())">
                                <livewire:lov.product.lov-product target="eresepRjObatNonRacikan" label="Nama Obat"
                                    :initialProductId="$formEresep['productId'] ?? null" :readonly="$isFormLocked" />
                            </div>
                        @endif

                        {{-- Form input obat --}}
                        @if ($formEresep)
                            {{-- Input Row --}}
                            <div class="flex items-end w-full gap-1 mt-2">

                                {{-- Nama obat (readonly) --}}
                                <div class="flex-[3]">
                                    <x-input-label for="formEresep.productName" :value="__('Nama Obat')" :required="true" />
                                    <x-text-input id="formEresep.productName" class="w-full mt-1" :disabled="true"
                                        wire:model="formEresep.productName" />
                                </div>

                                {{-- Qty --}}
                                <div class="flex-[1]">
                                    <x-input-label for="formEresep.qty" :value="__('Jml')" :required="true" />
                                    <x-text-input id="formEresep.qty" placeholder="Jml" class="w-full mt-1"
                                        :disabled="$isFormLocked" wire:model.live="formEresep.qty" x-ref="qty"
                                        x-init="$nextTick(() => $el.focus())" x-on:keydown.enter.prevent="$refs.signaX.focus()" />
                                </div>

                                {{-- Signa X --}}
                                <div class="flex-[1]">
                                    <x-input-label for="formEresep.signaX" :value="__('Signa')" />
                                    <x-text-input id="formEresep.signaX" placeholder="Signa1" class="w-full mt-1"
                                        :disabled="$isFormLocked" wire:model="formEresep.signaX" x-ref="signaX"
                                        x-on:keydown.enter.prevent="$refs.signaHari.focus()" />
                                </div>

                                <div class="pb-2 shrink-0"><span class="text-sm">dd</span></div>

                                {{-- Signa Hari --}}
                                <div class="flex-[1]">
                                    <x-input-label for="formEresep.signaHari" :value="__('*')" />
                                    <x-text-input id="formEresep.signaHari" placeholder="Signa2" class="w-full mt-1"
                                        :disabled="$isFormLocked" wire:model="formEresep.signaHari" x-ref="signaHari"
                                        x-on:keydown.enter.prevent="$refs.catatanKhusus.focus()" />
                                </div>

                                {{-- Catatan Khusus --}}
                                <div class="flex-[3]">
                                    <x-input-label for="formEresep.catatanKhusus" :value="__('Catatan Khusus')" />
                                    <x-text-input id="formEresep.catatanKhusus" placeholder="Catatan Khusus"
                                        class="w-full mt-1" :disabled="$isFormLocked" wire:model="formEresep.catatanKhusus"
                                        x-ref="catatanKhusus" x-on:keydown.enter.prevent="$wire.insertProduct()" />
                                </div>

                                {{-- Hapus draft --}}
                                <div class="ml-auto shrink-0">
                                    <x-input-label :value="__('')" />
                                    <x-secondary-button class="inline-flex mt-1" :disabled="$isFormLocked"
                                        wire:click="resetFormEresep">
                                        <svg class="w-5 h-5 text-gray-800 dark:text-white" aria-hidden="true"
                                            xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                            <path
                                                d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                        </svg>
                                    </x-secondary-button>
                                </div>
                            </div>

                            {{-- Error Row --}}
                            <div class="flex w-full gap-1 text-xs">
                                <div class="flex-[3]">
                                    <x-input-error :messages="$errors->get('formEresep.productName')" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-error :messages="$errors->get('formEresep.qty')" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-error :messages="$errors->get('formEresep.signaX')" />
                                </div>
                                <div class="shrink-0"></div>
                                <div class="flex-[1]">
                                    <x-input-error :messages="$errors->get('formEresep.signaHari')" />
                                </div>
                                <div class="flex-[3]">
                                    <x-input-error :messages="$errors->get('formEresep.catatanKhusus')" />
                                </div>
                                <div class="ml-auto shrink-0"></div>
                            </div>
                        @endif

                    </div>
                @endrole

                {{-- Tabel Resep --}}
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
                                        @foreach ($dataDaftarPoliRJ['eresep'] ?? [] as $key => $eresep)
                                            <tr class="border-b group" x-data>
                                                <td class="w-24 px-4 py-3 whitespace-nowrap">
                                                    {{ $eresep['jenisKeterangan'] }}
                                                </td>
                                                <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                <td class="w-20 px-4 py-3">
                                                    <x-text-input placeholder="Jml" :disabled="$isFormLocked"
                                                        wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.qty"
                                                        x-ref="qty{{ $key }}"
                                                        x-on:keydown.enter.prevent="$refs.signaX{{ $key }}.focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-1">
                                                        <div class="w-16 shrink-0">
                                                            <x-text-input placeholder="Signa1" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.signaX"
                                                                x-ref="signaX{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.signaHari{{ $key }}.focus()" />
                                                        </div>
                                                        <span class="text-sm text-gray-500 shrink-0">dd</span>
                                                        <div class="w-16 shrink-0">
                                                            <x-text-input placeholder="Signa2" :disabled="$isFormLocked"
                                                                wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.signaHari"
                                                                x-ref="signaHari{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.catatanKhusus{{ $key }}.focus()" />
                                                        </div>
                                                        <div class="flex-1">
                                                            <x-text-input placeholder="Catatan Khusus"
                                                                :disabled="$isFormLocked"
                                                                wire:model="dataDaftarPoliRJ.eresep.{{ $key }}.catatanKhusus"
                                                                x-ref="catatanKhusus{{ $key }}"
                                                                x-on:keydown.enter.prevent="
                                                                    $wire.updateProduct(
                                                                        '{{ $eresep['rjObatDtl'] }}',
                                                                        $wire.dataDaftarPoliRJ.eresep[{{ $key }}].qty,
                                                                        $wire.dataDaftarPoliRJ.eresep[{{ $key }}].signaX,
                                                                        $wire.dataDaftarPoliRJ.eresep[{{ $key }}].signaHari,
                                                                        $wire.dataDaftarPoliRJ.eresep[{{ $key }}].catatanKhusus
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
                                                            <svg class="w-5 h-5 text-gray-800 dark:text-white"
                                                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                                                fill="currentColor" viewBox="0 0 18 20">
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

            </div>{{-- end wire:key wrapper --}}
        </div>
    </div>
</div>
