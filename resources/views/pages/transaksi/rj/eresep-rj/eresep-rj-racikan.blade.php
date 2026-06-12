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
    public array $formEresepRacikan = [];
    public string $noRacikan = 'R1';

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['eresep-racikan-rj'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['eresep-racikan-rj']);
        $this->findData($this->rjNo);
    }

    /* ===============================
     | OPEN ERESEP RACIKAN RJ
     =============================== */
    #[On('open-eresep-racikan-rj')]
    public function openEresepRacikan(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        $this->findData($rjNo);

        // 🔥 INCREMENT: Refresh seluruh area eresep racikan
        $this->incrementVersion('eresep-racikan-rj');
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

        $this->rjNo = $rjNo;
        $this->dataDaftarPoliRJ = $data;

        // Initialize eresepRacikan jika belum ada
        $this->dataDaftarPoliRJ['eresepRacikan'] ??= [];
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Tidak membungkus transaction/lock sendiri.
     =============================== */
    private function syncEresepRacikanJson(): void
    {
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        // Patch hanya key 'eresepRacikan' — key lain tidak tersentuh
        $data['eresepRacikan'] = $this->dataDaftarPoliRJ['eresepRacikan'] ?? [];

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
                $this->syncEresepRacikanJson();
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
    #[On('lov.selected.eresepRjObatRacikan')]
    public function eresepRjObatRacikan(string $target, array $payload): void
    {
        $this->addProduct($payload['product_id'], $payload['product_name'], (float) ($payload['sales_price'] ?? 0));
    }

    /* ===============================
     | ADD PRODUCT (dari LOV) — hanya set draft form, belum simpan ke DB
     =============================== */
    public function addProduct(string $productId, string $productName, float $salesPrice): void
    {
        // Auto-fill Satuan (takar) dari master dimatikan dulu — user input manual.
        // $takar = DB::table('immst_products')->where('product_id', $productId)->value('takar') ?? 'Tablet';

        $this->formEresepRacikan = [
            'productId' => $productId,
            'productName' => $productName,
            'jenisKeterangan' => 'Racikan',
            'sedia' => 1,
            'dosis' => '',
            'takar' => '',
            'qty' => '',
            'catatan' => '',
            'catatanKhusus' => '',
            'noRacikan' => $this->noRacikan,
            'signaX' => 1,
            'signaHari' => 1,
            'productPrice' => $salesPrice,
        ];

        // 🔥 INCREMENT: Refresh area untuk tampilkan form input
        $this->incrementVersion('eresep-racikan-rj');
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
                'formEresepRacikan.qty' => 'nullable|integer|digits_between:1,3',
                'formEresepRacikan.catatan' => 'nullable|max:150',
                'formEresepRacikan.catatanKhusus' => 'nullable|max:150',
            ],
            [
                'formEresepRacikan.productName.required' => 'Nama obat harus diisi.',
                'formEresepRacikan.dosis.required' => 'Dosis harus diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 2. Insert ke tabel transaksi
                $lastInserted = DB::table('rstxn_rjobatracikans')->select(DB::raw('nvl(max(rjobat_dtl)+1,1) as rjobat_dtl_max'))->first();

                $takar = $this->formEresepRacikan['takar'] ?: (DB::table('immst_products')->where('product_id', $this->formEresepRacikan['productId'])->value('takar') ?? 'Tablet');

                DB::table('rstxn_rjobatracikans')->insert([
                    'rjobat_dtl' => $lastInserted->rjobat_dtl_max,
                    'rj_no' => $this->rjNo,
                    'product_name' => $this->formEresepRacikan['productName'],
                    'sedia' => $this->formEresepRacikan['sedia'],
                    'dosis' => $this->formEresepRacikan['dosis'],
                    'qty' => $this->formEresepRacikan['qty'] ?: null,
                    'catatan' => $this->formEresepRacikan['catatan'] ?: null,
                    'catatan_khusus' => $this->formEresepRacikan['catatanKhusus'] ?: null,
                    'no_racikan' => $this->formEresepRacikan['noRacikan'],
                    'rj_takar' => $takar,
                    'exp_date' => now()->addDays(30),
                    'etiket_status' => 1,
                ]);

                // 3. Tambah ke array lokal (key 'takar' agar match DB column rj_takar + master immst_products.takar)
                $this->dataDaftarPoliRJ['eresepRacikan'][] = [
                    'jenisKeterangan' => 'Racikan',
                    'productId' => $this->formEresepRacikan['productId'],
                    'productName' => $this->formEresepRacikan['productName'],
                    'sedia' => $this->formEresepRacikan['sedia'],
                    'dosis' => $this->formEresepRacikan['dosis'],
                    'takar' => $takar,
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

                // 4. Sync JSON — row sudah di-lock, tidak perlu lock/transaction lagi
                $this->syncEresepRacikanJson();
            });

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
    public function updateProduct(int $rjobatDtl, mixed $qty, string $dosis, ?string $takar, ?string $catatan, ?string $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $validator = validator(compact('qty', 'dosis', 'takar', 'catatan', 'catatanKhusus'), [
            'dosis' => 'required|max:150',
            'qty' => 'nullable|integer|digits_between:1,3',
            'takar' => 'nullable|max:50',
            'catatan' => 'nullable|max:150',
            'catatanKhusus' => 'nullable|max:150',
        ]);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        $takar = $takar !== null && $takar !== '' ? $takar : null;

        try {
            DB::transaction(function () use ($rjobatDtl, $qty, $dosis, $takar, $catatan, $catatanKhusus) {
                // 1. Lock row dulu — update tabel + JSON harus atomik
                $this->lockRJRow($this->rjNo);

                // 2. Update tabel transaksi
                DB::table('rstxn_rjobatracikans')
                    ->where('rjobat_dtl', $rjobatDtl)
                    ->update([
                        'qty' => $qty ?: null,
                        'dosis' => $dosis,
                        'rj_takar' => $takar,
                        'catatan' => $catatan,
                        'catatan_khusus' => $catatanKhusus,
                    ]);

                // 3. Update array lokal
                foreach ($this->dataDaftarPoliRJ['eresepRacikan'] as &$item) {
                    if (($item['rjObatDtl'] ?? null) == $rjobatDtl) {
                        $item['qty'] = $qty;
                        $item['dosis'] = $dosis;
                        $item['takar'] = $takar;
                        $item['catatan'] = $catatan;
                        $item['catatanKhusus'] = $catatanKhusus;
                        break;
                    }
                }
                unset($item);

                // 4. Sync JSON
                $this->syncEresepRacikanJson();
            });

            $this->afterSave('Obat racikan diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui obat racikan: ' . $e->getMessage());
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
                $obatExists = collect($this->dataDaftarPoliRJ['eresepRacikan'] ?? [])->contains('rjObatDtl', $rjObatDtl);

                if (!$obatExists) {
                    throw new \RuntimeException("Obat racikan dengan ID {$rjObatDtl} tidak ditemukan.");
                }

                // 3. Hapus dari tabel transaksi
                DB::table('rstxn_rjobatracikans')->where('rjobat_dtl', $rjObatDtl)->delete();

                // 4. Hapus dari array lokal
                $this->dataDaftarPoliRJ['eresepRacikan'] = collect($this->dataDaftarPoliRJ['eresepRacikan'] ?? [])
                    ->where('rjObatDtl', '!=', $rjObatDtl)
                    ->values()
                    ->toArray();

                // 5. Sync JSON
                $this->syncEresepRacikanJson();
            });

            $this->afterSave('Obat racikan berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus obat racikan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET FORM ERESEP RACIKAN (draft)
     =============================== */
    public function resetFormEresepRacikan(): void
    {
        $this->reset('formEresepRacikan');
        $this->incrementVersion('eresep-racikan-rj');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'eresep-racikan-rj-actions');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('eresep-racikan-rj');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->formEresepRacikan = [];
        $this->noRacikan = 'R1';
    }
};
?>

<div>
    <div class="p-2 rounded-lg bg-surface-soft">
        <div class="px-4">

            {{-- CONTAINER UTAMA dengan wire:key --}}
            <div wire:key="{{ $this->renderKey('eresep-racikan-rj', [$rjNo ?? 'new']) }}">


                @role(['Dokter', 'Admin'])
                    <div x-data x-ref="racikanSection">

                        {{-- LOV + No Racikan --}}
                        @if (!$formEresepRacikan)
                            <div class="flex items-center gap-3 mt-2" x-init="$nextTick(() => $el.querySelector('input:not([disabled])')?.focus())">

                                <div class="flex-1">
                                    <livewire:lov.product.lov-product target="eresepRjObatRacikan" label="Nama Obat Racikan"
                                        :readonly="$isFormLocked" />
                                </div>

                                <div class="w-32">
                                    <x-input-label :value="__('No Racikan')" />
                                    <x-text-input wire:model="noRacikan" placeholder="R1" :disabled="$isFormLocked"
                                        class="mt-1" />
                                </div>
                            </div>
                        @endif

                        {{-- Form input obat racikan --}}
                        @if ($formEresepRacikan)
                            {{-- Input Row --}}
                            <div class="flex items-end w-full gap-1 mt-2">

                                {{-- No Racikan --}}
                                <div class="flex-[1]">
                                    <x-input-label :value="__('Racikan')" />
                                    <x-text-input class="w-full mt-1" :disabled="false"
                                        wire:model="formEresepRacikan.noRacikan" />
                                </div>

                                {{-- Nama obat (readonly) --}}
                                <div class="flex-[3]">
                                    <x-input-label :value="__('Nama Obat')" :required="true" />
                                    <x-text-input class="w-full mt-1" :disabled="true"
                                        wire:model="formEresepRacikan.productName" />
                                </div>

                                {{-- Dosis --}}
                                <div class="flex-[1]">
                                    <x-input-label :value="__('Dosis')" :required="true" />
                                    <x-text-input placeholder="Dosis" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.dosis" x-ref="dosis" x-init="$nextTick(() => $el.focus())"
                                        x-on:keydown.enter.prevent="$refs.takar.focus()" />
                                </div>

                                {{-- Satuan --}}
                                <div class="flex-[1]">
                                    <x-input-label :value="__('Satuan')" />
                                    <x-text-input placeholder="Satuan" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.takar" x-ref="takar"
                                        x-on:keydown.enter.prevent="$refs.qty.focus()" />
                                </div>

                                {{-- Qty --}}
                                <div class="flex-[1]">
                                    <x-input-label :value="__('Jml')" />
                                    <x-text-input placeholder="Jml" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.qty" x-ref="qty"
                                        x-on:keydown.enter.prevent="$refs.catatan.focus()" />
                                </div>

                                {{-- Catatan --}}
                                <div class="flex-[2]">
                                    <x-input-label :value="__('Catatan')" />
                                    <x-text-input placeholder="Catatan" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.catatan" x-ref="catatan"
                                        x-on:keydown.enter.prevent="$refs.signa.focus()" />
                                </div>

                                {{-- Signa --}}
                                <div class="flex-[2]">
                                    <x-input-label :value="__('Signa')" />
                                    <x-text-input placeholder="Signa" class="w-full mt-1" :disabled="$isFormLocked"
                                        wire:model="formEresepRacikan.catatanKhusus" x-ref="signa"
                                        x-on:keydown.enter.prevent="$wire.insertProduct()" />
                                </div>

                                {{-- Hapus draft --}}
                                <div class="ml-auto shrink-0">
                                    <x-input-label :value="__('')" />
                                    <x-outline-button type="button"
                                        wire:click.prevent="resetFormEresepRacikan"
                                        wire:loading.attr="disabled"
                                        :disabled="$isFormLocked"
                                        class="mt-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                        title="Hapus draft">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-outline-button>
                                </div>
                            </div>

                            {{-- Error Row --}}
                            <div class="flex w-full gap-1 text-xs">
                                <div class="flex-[1]"></div>
                                <div class="flex-[3]">
                                    <x-input-error :messages="$errors->get('formEresepRacikan.productName')" />
                                </div>
                                <div class="flex-[1]">
                                    <x-input-error :messages="$errors->get('formEresepRacikan.dosis')" />
                                </div>
                                <div class="flex-[1]"></div>
                                <div class="flex-[1]">
                                    <x-input-error :messages="$errors->get('formEresepRacikan.qty')" />
                                </div>
                                <div class="flex-[2]">
                                    <x-input-error :messages="$errors->get('formEresepRacikan.catatan')" />
                                </div>
                                <div class="flex-[2]">
                                    <x-input-error :messages="$errors->get('formEresepRacikan.catatanKhusus')" />
                                </div>
                                <div class="ml-auto shrink-0"></div>
                            </div>
                        @endif

                    </div>
                @endrole

                {{-- Tabel Resep Racikan --}}
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-body table-auto dark:text-gray-300">
                                    <thead class="text-xs font-bold text-ink uppercase border-b border-gray-300 bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">
                                        <tr>
                                            <th class="hidden">Racikan</th>
                                            <th class="px-4 py-3 min-w-[18rem] text-center">Obat</th>
                                            <th class="px-4 py-3 text-center">Dosis / Satuan / Jml / Catatan / Signa</th>
                                            <th class="w-8 px-4 py-3 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-canvas">
                                        @isset($dataDaftarPoliRJ['eresepRacikan'])
                                            @php $myPreviousRow = null; @endphp

                                            @foreach ($dataDaftarPoliRJ['eresepRacikan'] as $key => $eresep)
                                                @isset($eresep['jenisKeterangan'])
                                                    @php
                                                        $myRacikanBorder =
                                                            $myPreviousRow !== $eresep['noRacikan']
                                                                ? 'border-t-2 border-red-400'
                                                                : 'border-t-2 border-hairline';
                                                    @endphp

                                                    <tr wire:key="eresep-rj-racikan-{{ $key }}"
                                                        class="{{ $myRacikanBorder }} hover:bg-surface-soft dark:hover:bg-gray-800/40 group" x-data>

                                                        {{-- Racikan label (hidden) --}}
                                                        <td class="hidden">
                                                            {{ $eresep['jenisKeterangan'] . ' (' . $eresep['noRacikan'] . ')' }}
                                                        </td>

                                                        {{-- Nama Obat (Racikan label di atas) --}}
                                                        <td class="px-4 py-3">
                                                            <div class="text-xs text-muted">
                                                                Racikan ({{ $eresep['noRacikan'] }})
                                                            </div>
                                                            <div class="mt-0.5 font-semibold text-ink dark:text-gray-100">
                                                                {{ $eresep['productName'] }}
                                                            </div>
                                                        </td>

                                                        {{-- Detail (Dosis / Satuan / Jml / Catatan / Signa) — sebaris, mepet --}}
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center gap-1">
                                                                {{-- Dosis --}}
                                                                <div class="w-20 shrink-0">
                                                                    <x-text-input placeholder="Dosis" :disabled="$isFormLocked"
                                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.dosis"
                                                                        x-ref="dosis{{ $key }}"
                                                                        x-on:keydown.enter.prevent="$refs.takar{{ $key }}.focus()" />
                                                                </div>

                                                                {{-- Satuan --}}
                                                                <div class="w-20 shrink-0">
                                                                    <x-text-input placeholder="Satuan" :disabled="$isFormLocked"
                                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.takar"
                                                                        x-ref="takar{{ $key }}"
                                                                        x-on:keydown.enter.prevent="$refs.qty{{ $key }}.focus()" />
                                                                </div>

                                                                {{-- Jml Racikan --}}
                                                                <div class="w-16 shrink-0">
                                                                    <x-text-input placeholder="Jml" :disabled="$isFormLocked"
                                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.qty"
                                                                        x-ref="qty{{ $key }}"
                                                                        x-on:keydown.enter.prevent="$refs.catatan{{ $key }}.focus()" />
                                                                </div>

                                                                {{-- Catatan --}}
                                                                <div class="flex-1">
                                                                    <x-text-input placeholder="Catatan" :disabled="$isFormLocked"
                                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.catatan"
                                                                        x-ref="catatan{{ $key }}"
                                                                        x-on:keydown.enter.prevent="$refs.catatanKhusus{{ $key }}.focus()" />
                                                                </div>

                                                                {{-- Signa --}}
                                                                <div class="flex-1">
                                                                    <x-text-input placeholder="Signa" :disabled="$isFormLocked"
                                                                        wire:model="dataDaftarPoliRJ.eresepRacikan.{{ $key }}.catatanKhusus"
                                                                        x-ref="catatanKhusus{{ $key }}"
                                                                        x-on:keydown.enter.prevent="
                                                                            $wire.updateProduct(
                                                                                '{{ $eresep['rjObatDtl'] }}',
                                                                                $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].qty,
                                                                                $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].dosis,
                                                                                $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].takar,
                                                                                $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].catatan,
                                                                                $wire.dataDaftarPoliRJ.eresepRacikan[{{ $key }}].catatanKhusus
                                                                            );
                                                                            $nextTick(() => $refs.dosis{{ $key }}.focus())
                                                                        " />
                                                                </div>
                                                            </div>
                                                            @error("dataDaftarPoliRJ.eresepRacikan.{{ $key }}.dosis")
                                                                <x-input-error :messages="$message" class="mt-1" />
                                                            @enderror
                                                        </td>

                                                        {{-- Action --}}
                                                        <td class="px-4 py-3 text-center">
                                                            @role(['Dokter', 'Admin'])
                                                                <x-outline-button type="button"
                                                                    wire:click.prevent="removeProduct('{{ $eresep['rjObatDtl'] }}')"
                                                                    wire:confirm="Hapus obat racikan ini?"
                                                                    wire:loading.attr="disabled"
                                                                    :disabled="$isFormLocked"
                                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                    title="Hapus obat">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-outline-button>
                                                            @endrole
                                                        </td>
                                                    </tr>

                                                    {{-- Update tracker setelah row dirender --}}
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

            </div>{{-- end wire:key wrapper --}}
        </div>
    </div>
</div>
