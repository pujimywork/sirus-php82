<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* -------------------- HEADER -------------------- */
    public ?string $pactId = null;
    public string $pactDesc = '';
    public ?int $pactPrice = null;
    public ?int $pactPriceBpjs = null;
    public string $activeStatus = '1';

    /* -------------------- PAKET LAIN-LAIN -------------------- */
    /** array of ['other_id', 'other_desc', 'acto_price'] */
    public array $paketLainLain = [];

    public array $formPaketLain = [
        'other_id' => '',
        'other_desc' => '',
        'acto_price' => null,
    ];

    /* -------------------- PAKET OBAT -------------------- */
    /** array of ['product_id', 'product_name', 'sales_price', 'actprod_qty'] */
    public array $paketObat = [];

    public array $formPaketObat = [
        'product_id' => '',
        'product_name' => '',
        'sales_price' => null,
        'actprod_qty' => 1,
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ===============================
     | OPEN / CLOSE
     =============================== */
    #[On('master.jasa-medis.openCreate')]
    public function openCreate(): void
    {
        $this->resetAllFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-jasa-medis-actions');
    }

    #[On('master.jasa-medis.openEdit')]
    public function openEdit(string $pactId): void
    {
        $row = DB::table('rsmst_actparamedics')->where('pact_id', $pactId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Jasa medis tidak ditemukan.');
            return;
        }

        $this->resetAllFields();
        $this->formMode = 'edit';

        $this->pactId = (string) $row->pact_id;
        $this->pactDesc = (string) ($row->pact_desc ?? '');
        $this->pactPrice = (int) ($row->pact_price ?? 0);
        $this->pactPriceBpjs = (int) ($row->pact_price_bpjs ?? 0);
        $this->activeStatus = (string) ($row->active_status ?? '1');

        $this->loadPaketFromDb($pactId);

        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-jasa-medis-actions');
    }

    private function loadPaketFromDb(string $pactId): void
    {
        $this->paketLainLain = DB::table('rsmst_actparothers as ap')
            ->leftJoin('rsmst_others as o', 'o.other_id', '=', 'ap.other_id')
            ->where('ap.pact_id', $pactId)
            ->select('ap.other_id', 'o.other_desc', 'ap.acto_price')
            ->orderBy('ap.other_id')
            ->get()
            ->map(fn($r) => [
                'other_id' => (string) $r->other_id,
                'other_desc' => (string) ($r->other_desc ?? '-'),
                'acto_price' => (int) ($r->acto_price ?? 0),
            ])
            ->toArray();

        $this->paketObat = DB::table('rsmst_actparproducts as ap')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'ap.product_id')
            ->where('ap.pact_id', $pactId)
            ->select('ap.product_id', 'p.product_name', 'p.sales_price', 'ap.actprod_qty')
            ->orderBy('ap.product_id')
            ->get()
            ->map(fn($r) => [
                'product_id' => (string) $r->product_id,
                'product_name' => (string) ($r->product_name ?? '-'),
                'sales_price' => (int) ($r->sales_price ?? 0),
                'actprod_qty' => (int) ($r->actprod_qty ?? 1),
            ])
            ->toArray();
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-jasa-medis-actions');
    }

    protected function resetAllFields(): void
    {
        $this->reset(['pactId', 'pactDesc', 'pactPrice', 'pactPriceBpjs', 'activeStatus', 'paketLainLain', 'paketObat', 'formPaketLain', 'formPaketObat']);
        $this->activeStatus = '1';
        $this->formPaketObat['actprod_qty'] = 1;
        $this->resetVersion();
    }

    /* ===============================
     | LOV LISTENERS — PAKET LAIN-LAIN
     =============================== */
    #[On('lov.selected.paket-lain-master-jm')]
    public function onLainLainSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formPaketLain = ['other_id' => '', 'other_desc' => '', 'acto_price' => null];
            return;
        }

        // Cegah duplikat — kalau sudah ada di list, skip + toast.
        foreach ($this->paketLainLain as $row) {
            if ($row['other_id'] === (string) $payload['other_id']) {
                $this->dispatch('toast', type: 'error', message: 'Item lain-lain sudah ada di paket.');
                return;
            }
        }

        $this->formPaketLain = [
            'other_id' => (string) $payload['other_id'],
            'other_desc' => (string) ($payload['other_desc'] ?? '-'),
            'acto_price' => (int) ($payload['other_price'] ?? 0),
        ];

        $this->dispatch('focus-input-paket-lain-price');
    }

    public function addPaketLainLain(): void
    {
        $this->validate(
            [
                'formPaketLain.other_id' => ['required', 'exists:rsmst_others,other_id'],
                'formPaketLain.acto_price' => ['required', 'numeric', 'min:0'],
            ],
            [
                'formPaketLain.other_id.required' => 'Item lain-lain wajib dipilih.',
                'formPaketLain.other_id.exists' => 'Item lain-lain tidak valid.',
                'formPaketLain.acto_price.required' => 'Harga wajib diisi.',
                'formPaketLain.acto_price.numeric' => 'Harga harus berupa angka.',
            ],
        );

        $this->paketLainLain[] = [
            'other_id' => $this->formPaketLain['other_id'],
            'other_desc' => $this->formPaketLain['other_desc'],
            'acto_price' => (int) $this->formPaketLain['acto_price'],
        ];

        $this->formPaketLain = ['other_id' => '', 'other_desc' => '', 'acto_price' => null];
        $this->incrementVersion('modal');
    }

    public function removePaketLainLain(int $idx): void
    {
        if (isset($this->paketLainLain[$idx])) {
            unset($this->paketLainLain[$idx]);
            $this->paketLainLain = array_values($this->paketLainLain);
        }
    }

    public function cancelFormPaketLain(): void
    {
        $this->formPaketLain = ['other_id' => '', 'other_desc' => '', 'acto_price' => null];
        $this->resetValidation('formPaketLain.*');
        $this->incrementVersion('modal');
    }

    /* ===============================
     | LOV LISTENERS — PAKET OBAT
     =============================== */
    #[On('lov.selected.paket-obat-master-jm')]
    public function onObatSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formPaketObat = ['product_id' => '', 'product_name' => '', 'sales_price' => null, 'actprod_qty' => 1];
            return;
        }

        foreach ($this->paketObat as $row) {
            if ($row['product_id'] === (string) $payload['product_id']) {
                $this->dispatch('toast', type: 'error', message: 'Produk obat sudah ada di paket.');
                return;
            }
        }

        $this->formPaketObat = [
            'product_id' => (string) $payload['product_id'],
            'product_name' => (string) ($payload['product_name'] ?? '-'),
            'sales_price' => (int) ($payload['sales_price'] ?? 0),
            'actprod_qty' => 1,
        ];

        $this->dispatch('focus-input-paket-obat-qty');
    }

    public function addPaketObat(): void
    {
        $this->validate(
            [
                'formPaketObat.product_id' => ['required', 'exists:immst_products,product_id'],
                'formPaketObat.actprod_qty' => ['required', 'numeric', 'min:1'],
            ],
            [
                'formPaketObat.product_id.required' => 'Produk wajib dipilih.',
                'formPaketObat.product_id.exists' => 'Produk tidak valid.',
                'formPaketObat.actprod_qty.required' => 'Qty wajib diisi.',
                'formPaketObat.actprod_qty.min' => 'Qty minimal 1.',
            ],
        );

        $this->paketObat[] = [
            'product_id' => $this->formPaketObat['product_id'],
            'product_name' => $this->formPaketObat['product_name'],
            'sales_price' => (int) $this->formPaketObat['sales_price'],
            'actprod_qty' => (int) $this->formPaketObat['actprod_qty'],
        ];

        $this->formPaketObat = ['product_id' => '', 'product_name' => '', 'sales_price' => null, 'actprod_qty' => 1];
        $this->incrementVersion('modal');
    }

    public function removePaketObat(int $idx): void
    {
        if (isset($this->paketObat[$idx])) {
            unset($this->paketObat[$idx]);
            $this->paketObat = array_values($this->paketObat);
        }
    }

    public function cancelFormPaketObat(): void
    {
        $this->formPaketObat = ['product_id' => '', 'product_name' => '', 'sales_price' => null, 'actprod_qty' => 1];
        $this->resetValidation('formPaketObat.*');
        $this->incrementVersion('modal');
    }

    /* ===============================
     | SAVE — header + paket (delete-then-insert)
     =============================== */
    public function save(): void
    {
        $rules = [
            'pactId' => ['required', 'string', 'max:10', $this->formMode === 'create' ? Rule::unique('rsmst_actparamedics', 'pact_id') : Rule::unique('rsmst_actparamedics', 'pact_id')->ignore($this->pactId, 'pact_id')],
            'pactDesc' => ['required', 'string', 'max:100'],
            'pactPrice' => ['required', 'numeric', 'min:0'],
            'pactPriceBpjs' => ['nullable', 'numeric', 'min:0'],
            'activeStatus' => ['required', Rule::in(['1', '0'])],
        ];

        $messages = [
            'pactId.required' => 'Kode wajib diisi.',
            'pactId.max' => 'Kode maksimal 10 karakter.',
            'pactId.unique' => 'Kode jasa medis sudah dipakai.',
            'pactDesc.required' => 'Nama wajib diisi.',
            'pactDesc.max' => 'Nama maksimal 100 karakter.',
            'pactPrice.required' => 'Tarif Umum wajib diisi.',
            'pactPrice.numeric' => 'Tarif Umum harus berupa angka.',
            'pactPriceBpjs.numeric' => 'Tarif BPJS harus berupa angka.',
            'activeStatus.required' => 'Status wajib dipilih.',
        ];

        $this->validate($rules, $messages);

        try {
            DB::transaction(function () {
                $payload = [
                    'pact_desc' => $this->pactDesc,
                    'pact_price' => (int) ($this->pactPrice ?? 0),
                    'pact_price_bpjs' => (int) ($this->pactPriceBpjs ?? 0),
                    'active_status' => $this->activeStatus,
                ];

                if ($this->formMode === 'create') {
                    DB::table('rsmst_actparamedics')->insert([
                        'pact_id' => $this->pactId,
                        ...$payload,
                    ]);
                } else {
                    DB::table('rsmst_actparamedics')->where('pact_id', $this->pactId)->update($payload);
                }

                // Delete-then-insert paket detail (idempotent, sederhana).
                DB::table('rsmst_actparothers')->where('pact_id', $this->pactId)->delete();
                foreach ($this->paketLainLain as $item) {
                    DB::table('rsmst_actparothers')->insert([
                        'pact_id' => $this->pactId,
                        'other_id' => $item['other_id'],
                        'acto_price' => (int) ($item['acto_price'] ?? 0),
                    ]);
                }

                DB::table('rsmst_actparproducts')->where('pact_id', $this->pactId)->delete();
                foreach ($this->paketObat as $item) {
                    DB::table('rsmst_actparproducts')->insert([
                        'pact_id' => $this->pactId,
                        'product_id' => $item['product_id'],
                        'actprod_qty' => (int) ($item['actprod_qty'] ?? 1),
                    ]);
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil disimpan.');
            $this->closeModal();
            $this->dispatch('master.jasa-medis.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TOGGLE ACTIVE
     =============================== */
    #[On('master.jasa-medis.toggleActive')]
    public function toggleActive(string $pactId): void
    {
        $cur = (string) DB::table('rsmst_actparamedics')->where('pact_id', $pactId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('rsmst_actparamedics')->where('pact_id', $pactId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success', message: 'Status → ' . ($next === '1' ? 'Aktif' : 'Tidak Aktif'));
        $this->dispatch('master.jasa-medis.saved');
    }

    /* ===============================
     | DELETE — cek transaksi dulu
     =============================== */
    #[On('master.jasa-medis.requestDelete')]
    public function deleteFromGrid(string $pactId): void
    {
        try {
            // Cek pemakaian di transaksi RJ/UGD/RI.
            $used = DB::table('rstxn_rjactparams')->where('pact_id', $pactId)->exists() || DB::table('rstxn_ugdactparams')->where('pact_id', $pactId)->exists() || DB::table('rstxn_riactparams')->where('pact_id', $pactId)->exists();

            if ($used) {
                $this->dispatch('toast', type: 'error', message: 'Jasa medis sudah dipakai di transaksi RJ/UGD/RI, tidak bisa dihapus.');
                return;
            }

            DB::transaction(function () use ($pactId) {
                // Cascade hapus paket & tarif per kelas dulu, baru header.
                DB::table('rsmst_actparothers')->where('pact_id', $pactId)->delete();
                DB::table('rsmst_actparproducts')->where('pact_id', $pactId)->delete();
                DB::table('rsmst_actpclasses')->where('pact_id', $pactId)->delete();
                $deleted = DB::table('rsmst_actparamedics')->where('pact_id', $pactId)->delete();

                if ($deleted === 0) {
                    throw new \RuntimeException('Jasa medis tidak ditemukan.');
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Jasa medis berhasil dihapus.');
            $this->dispatch('master.jasa-medis.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function formatRupiah($price): string
    {
        return 'Rp ' . number_format((int) ($price ?? 0), 0, ',', '.');
    }
};
?>

<div>
    <x-modal name="master-jasa-medis-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-jasa-medis-actions"
            event="master.jasa-medis.saved"
            label="Jasa Medis"
            :wireKey="$this->renderKey('modal', [$formMode, $pactId ?? 'new'])">
            <div x-data
                x-on:focus-input-paket-lain-price.window="$nextTick(() => $refs.inputPaketLainPrice?.focus())"
                x-on:focus-input-paket-obat-qty.window="$nextTick(() => $refs.inputPaketObatQty?.focus())">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Jasa Medis' : 'Tambah Jasa Medis' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi tarif & paket bundling lain-lain / obat.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20 space-y-4">

                {{-- INFORMASI DASAR --}}
                <x-border-form title="Informasi Dasar">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        {{-- Kode --}}
                        <div class="md:col-span-2">
                            <x-input-label value="Kode" />
                            <x-text-input wire:model.live="pactId" :disabled="$formMode === 'edit'"
                                :error="$errors->has('pactId')" class="w-full mt-1 font-mono uppercase" maxlength="10" />
                            <x-input-error :messages="$errors->get('pactId')" class="mt-1" />
                        </div>

                        {{-- Nama --}}
                        <div class="md:col-span-6">
                            <x-input-label value="Nama Jasa Medis" />
                            <x-text-input wire:model.live="pactDesc" :error="$errors->has('pactDesc')"
                                class="w-full mt-1" placeholder="Contoh: Caesar, Tonsilektomi" maxlength="100" />
                            <x-input-error :messages="$errors->get('pactDesc')" class="mt-1" />
                        </div>

                        {{-- Status Aktif --}}
                        <div class="md:col-span-4">
                            <x-input-label value="Status Aktif" />
                            <x-select-input wire:model.live="activeStatus"
                                :error="$errors->has('activeStatus')" class="w-full mt-1">
                                <option value="1">Aktif</option>
                                <option value="0">Tidak Aktif</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                        </div>

                        {{-- Tarif Umum --}}
                        <div class="md:col-span-6">
                            <x-input-label value="Tarif Umum" />
                            <x-text-input-number wire:model="pactPrice" :error="$errors->has('pactPrice')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('pactPrice')" class="mt-1" />
                        </div>

                        {{-- Tarif BPJS --}}
                        <div class="md:col-span-6">
                            <x-input-label value="Tarif BPJS" />
                            <x-text-input-number wire:model="pactPriceBpjs"
                                :error="$errors->has('pactPriceBpjs')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('pactPriceBpjs')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- PAKET LAIN-LAIN --}}
                <x-border-form title="Paket Lain-Lain (auto-tambah saat jasa medis dipilih di transaksi)">
                    <div class="space-y-3">
                        {{-- Form add --}}
                        <div class="p-3 border border-gray-200 rounded-xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            @if (empty($formPaketLain['other_id']))
                                <livewire:lov.lain-lain.lov-lain-lain target="paket-lain-master-jm"
                                    label="Tambah Item Lain-Lain"
                                    placeholder="Ketik kode / nama lain-lain..."
                                    wire:key="lov-paket-lain-{{ $renderVersions['modal'] ?? 0 }}" />
                            @else
                                <div class="flex flex-wrap gap-3 items-end">
                                    <div class="w-24">
                                        <x-input-label value="Kode" class="mb-1" />
                                        <x-text-input wire:model="formPaketLain.other_id" disabled
                                            class="w-full text-sm font-mono" />
                                    </div>
                                    <div class="flex-1 min-w-[10rem]">
                                        <x-input-label value="Nama" class="mb-1" />
                                        <x-text-input wire:model="formPaketLain.other_desc" disabled
                                            class="w-full text-sm" />
                                    </div>
                                    <div class="w-32">
                                        <x-input-label value="Harga" class="mb-1" />
                                        <x-text-input-number wire:model="formPaketLain.acto_price"
                                            x-ref="inputPaketLainPrice"
                                            x-on:keydown.enter.prevent="$el.blur(); $wire.addPaketLainLain()" />
                                        <x-input-error :messages="$errors->get('formPaketLain.acto_price')" class="mt-1" />
                                    </div>
                                    <div class="flex gap-2 items-end shrink-0">
                                        <x-primary-button type="button" wire:click="addPaketLainLain"
                                            class="text-xs px-3 py-1.5">+ Tambah</x-primary-button>
                                        <x-secondary-button type="button" wire:click="cancelFormPaketLain"
                                            class="text-xs px-3 py-1.5">Batal</x-secondary-button>
                                    </div>
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('formPaketLain.other_id')" class="mt-1" />
                        </div>

                        {{-- List --}}
                        <div class="overflow-hidden bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Kode</th>
                                        <th class="px-3 py-2 font-medium">Nama</th>
                                        <th class="px-3 py-2 font-medium text-right">Harga</th>
                                        <th class="px-3 py-2 w-16 text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($paketLainLain as $idx => $row)
                                        <tr wire:key="paket-lain-{{ $idx }}-{{ $row['other_id'] }}">
                                            <td class="px-3 py-2 font-mono text-xs">{{ $row['other_id'] }}</td>
                                            <td class="px-3 py-2">{{ $row['other_desc'] }}</td>
                                            <td class="px-3 py-2 text-right font-mono">
                                                {{ $this->formatRupiah($row['acto_price']) }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button"
                                                    wire:click="removePaketLainLain({{ $idx }})"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4"
                                                class="px-3 py-6 text-center text-xs text-gray-400 italic">
                                                Belum ada paket lain-lain
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-border-form>

                {{-- PAKET OBAT --}}
                <x-border-form title="Paket Obat (auto-tambah saat jasa medis dipilih di transaksi)">
                    <div class="space-y-3">
                        {{-- Form add --}}
                        <div class="p-3 border border-gray-200 rounded-xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            @if (empty($formPaketObat['product_id']))
                                <livewire:lov.product.lov-product target="paket-obat-master-jm"
                                    label="Tambah Item Obat"
                                    placeholder="Ketik kode / nama produk..."
                                    wire:key="lov-paket-obat-{{ $renderVersions['modal'] ?? 0 }}" />
                            @else
                                <div class="flex flex-wrap gap-3 items-end">
                                    <div class="w-20">
                                        <x-input-label value="Kode" class="mb-1" />
                                        <x-text-input wire:model="formPaketObat.product_id" disabled
                                            class="w-full text-sm font-mono" />
                                    </div>
                                    <div class="flex-1 min-w-[8rem]">
                                        <x-input-label value="Produk" class="mb-1" />
                                        <x-text-input wire:model="formPaketObat.product_name" disabled
                                            class="w-full text-sm" />
                                    </div>
                                    <div class="w-28">
                                        <x-input-label value="Harga Jual" class="mb-1" />
                                        <x-text-input-number wire:model="formPaketObat.sales_price" disabled />
                                    </div>
                                    <div class="w-20">
                                        <x-input-label value="Qty" class="mb-1" />
                                        <x-text-input-number wire:model="formPaketObat.actprod_qty"
                                            x-ref="inputPaketObatQty"
                                            x-on:keydown.enter.prevent="$el.blur(); $wire.addPaketObat()" />
                                        <x-input-error :messages="$errors->get('formPaketObat.actprod_qty')" class="mt-1" />
                                    </div>
                                    <div class="flex gap-2 items-end shrink-0">
                                        <x-primary-button type="button" wire:click="addPaketObat"
                                            class="text-xs px-3 py-1.5">+ Tambah</x-primary-button>
                                        <x-secondary-button type="button" wire:click="cancelFormPaketObat"
                                            class="text-xs px-3 py-1.5">Batal</x-secondary-button>
                                    </div>
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('formPaketObat.product_id')" class="mt-1" />
                        </div>

                        {{-- List --}}
                        <div class="overflow-hidden bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Kode</th>
                                        <th class="px-3 py-2 font-medium">Produk</th>
                                        <th class="px-3 py-2 font-medium text-right">Harga Jual</th>
                                        <th class="px-3 py-2 font-medium text-right">Qty</th>
                                        <th class="px-3 py-2 w-16 text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($paketObat as $idx => $row)
                                        <tr wire:key="paket-obat-{{ $idx }}-{{ $row['product_id'] }}">
                                            <td class="px-3 py-2 font-mono text-xs">{{ $row['product_id'] }}</td>
                                            <td class="px-3 py-2">{{ $row['product_name'] }}</td>
                                            <td class="px-3 py-2 text-right font-mono">
                                                {{ $this->formatRupiah($row['sales_price']) }}
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['actprod_qty'] }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button"
                                                    wire:click="removePaketObat({{ $idx }})"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5"
                                                class="px-3 py-6 text-center text-xs text-gray-400 italic">
                                                Belum ada paket obat
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Paket lain-lain & obat akan otomatis ter-insert saat jasa medis dipilih di transaksi RJ/UGD/RI.
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">
                            Batal
                        </x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

            </div>
        </x-dirty-modal-content>
    </x-modal>
</div>
