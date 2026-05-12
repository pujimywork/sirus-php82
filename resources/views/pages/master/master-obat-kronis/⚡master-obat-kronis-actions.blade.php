<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'product_id'       => '',
        'product_name'     => '',
        'maxqty'           => '',
        'tarif_klaim'      => '',
        'obat_kronis_bpjs' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.obat-kronis.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-obat-kronis-actions');
    }

    #[On('master.obat-kronis.openEdit')]
    public function openEdit(string $productId): void
    {
        $row = DB::table('rsmst_listobatbpjses as k')
            ->leftJoin('immst_products as p', 'k.product_id', '=', 'p.product_id')
            ->select('k.product_id', 'k.maxqty', 'k.tarif_klaim', 'k.obat_kronis_bpjs', 'p.product_name')
            ->where('k.product_id', $productId)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data obat kronis tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $productId;
        $this->form = [
            'product_id'       => (string) $row->product_id,
            'product_name'     => (string) ($row->product_name ?? ''),
            'maxqty'           => $row->maxqty !== null ? (string) (float) $row->maxqty : '',
            'tarif_klaim'      => $row->tarif_klaim !== null ? (string) (float) $row->tarif_klaim : '',
            'obat_kronis_bpjs' => (string) ($row->obat_kronis_bpjs ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-obat-kronis-actions');
    }

    #[On('master.obat-kronis.requestDelete')]
    public function deleteObatKronis(string $productId): void
    {
        $deleted = DB::table('rsmst_listobatbpjses')->where('product_id', $productId)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data obat kronis tidak ditemukan.');
            return;
        }
        $this->dispatch('toast', type: 'success', message: 'Obat kronis berhasil dihapus.');
        $this->dispatch('master.obat-kronis.saved');
    }

    /**
     * Listener LOV product — terima payload obat terpilih, isi product_id & product_name.
     */
    #[On('lov.selected.master-obat-kronis')]
    public function onProductSelected(string $target, array $payload): void
    {
        $this->form['product_id']   = (string) ($payload['product_id'] ?? '');
        $this->form['product_name'] = (string) ($payload['product_name'] ?? '');
        $this->resetValidation('form.product_id');
    }

    public function save(): void
    {
        $rules = [
            'form.product_id' => [
                'required', 'string', 'max:50',
                Rule::exists('immst_products', 'product_id'),
                $this->formMode === 'create'
                    ? Rule::unique('rsmst_listobatbpjses', 'product_id')
                    : 'string',
            ],
            'form.maxqty'           => 'nullable|numeric|min:0|max:9999999.99',
            'form.tarif_klaim'      => 'nullable|numeric|min:0|max:9999999.99',
            'form.obat_kronis_bpjs' => 'nullable|string|max:1000',
        ];

        $messages = [
            'form.product_id.required' => 'Obat wajib dipilih dari daftar.',
            'form.product_id.exists'   => 'Obat tidak ditemukan di master obat.',
            'form.product_id.unique'   => 'Obat sudah terdaftar sebagai obat kronis.',
            'form.maxqty.numeric'      => 'Max Qty harus berupa angka.',
            'form.tarif_klaim.numeric' => 'Tarif Klaim harus berupa angka.',
            'form.obat_kronis_bpjs.max' => 'Nama BPJS maksimal 1000 karakter.',
        ];

        $attributes = [
            'form.product_id'       => 'Obat',
            'form.maxqty'           => 'Max Qty',
            'form.tarif_klaim'      => 'Tarif Klaim',
            'form.obat_kronis_bpjs' => 'Nama BPJS',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'maxqty'           => $this->form['maxqty'] === '' ? null : (float) $this->form['maxqty'],
            'tarif_klaim'      => $this->form['tarif_klaim'] === '' ? null : (float) $this->form['tarif_klaim'],
            'obat_kronis_bpjs' => $this->form['obat_kronis_bpjs'] === '' ? null : $this->form['obat_kronis_bpjs'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_listobatbpjses')->insert([
                'product_id' => $this->form['product_id'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_listobatbpjses')->where('product_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Obat kronis berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.obat-kronis.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-obat-kronis-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'product_id'       => '',
            'product_name'     => '',
            'maxqty'           => '',
            'tarif_klaim'      => '',
            'obat_kronis_bpjs' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-obat-kronis-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$formMode, $originalId]) }}">

            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Obat Kronis BPJS' : 'Tambah Obat Kronis BPJS' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Obat kronis BPJS — max qty per resep & tarif klaim. Pilih obat dari master obat.
                        </p>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <x-border-form title="Data Obat Kronis BPJS">
                    <div class="space-y-4">
                        {{-- Pilih obat: LOV (mode tambah) atau readonly (mode edit) --}}
                        @if ($formMode === 'create')
                            <div>
                                <livewire:lov.product.lov-product
                                    target="master-obat-kronis"
                                    label="Obat (cari dari master obat)"
                                    placeholder="Ketik nama/kode/kandungan obat..."
                                    wire:key="lov-master-obat-kronis-{{ $renderVersions['modal'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('form.product_id')" class="mt-1" />
                                @if ($form['product_id'] !== '')
                                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                        Terpilih: <span class="font-mono">{{ $form['product_id'] }}</span>
                                        — {{ $form['product_name'] }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Product ID" />
                                    <x-text-input wire:model="form.product_id" disabled
                                        class="w-full mt-1 font-mono" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label value="Nama Obat" />
                                    <x-text-input wire:model="form.product_name" disabled class="w-full mt-1" />
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Max Qty (per resep)" />
                                <x-text-input type="number" step="0.01" min="0"
                                    wire:model.live="form.maxqty"
                                    :error="$errors->has('form.maxqty')"
                                    class="w-full mt-1" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Kosongkan jika tidak ada batasan.
                                </p>
                                <x-input-error :messages="$errors->get('form.maxqty')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tarif Klaim" />
                                <x-text-input type="number" step="0.01" min="0"
                                    wire:model.live="form.tarif_klaim"
                                    :error="$errors->has('form.tarif_klaim')"
                                    class="w-full mt-1" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Tarif klaim BPJS per unit obat.
                                </p>
                                <x-input-error :messages="$errors->get('form.tarif_klaim')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Nama BPJS (Obat Kronis BPJS)" />
                            <textarea wire:model.live="form.obat_kronis_bpjs"
                                maxlength="1000" rows="2"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Nama obat versi BPJS (opsional). Maks 1000 karakter.
                            </p>
                            <x-input-error :messages="$errors->get('form.obat_kronis_bpjs')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Saving...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
