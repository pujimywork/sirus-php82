<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public string $originalCode = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $form = [
        'sl_code' => '',
        'sl_name' => '',
        'stock_status' => '1',
        'active_status' => '1',
        'medis' => '1',
        'nonmedis' => '0',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.stocklocations.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalCode = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-stocklocations-actions');
        $this->dispatch('focus-sl-code');
    }

    #[On('master.stocklocations.openEdit')]
    public function openEdit(string $slCode): void
    {
        $row = DB::table('immst_stocklocations')->where('sl_code', $slCode)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data lokasi tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->originalCode = $slCode;
        $this->form = [
            'sl_code' => (string) $row->sl_code,
            'sl_name' => (string) ($row->sl_name ?? ''),
            'stock_status' => (string) ($row->stock_status ?? '0'),
            'active_status' => (string) ($row->active_status ?? '0'),
            'medis' => (string) ($row->medis ?? '0'),
            'nonmedis' => (string) ($row->nonmedis ?? '0'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-stocklocations-actions');
        $this->dispatch('focus-sl-name');
    }

    #[On('master.stocklocations.requestDelete')]
    public function deleteRow(string $slCode): void
    {
        try {
            $deleted = DB::table('immst_stocklocations')->where('sl_code', $slCode)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data lokasi tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Lokasi stok berhasil dihapus.');
            $this->dispatch('master.stocklocations.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error',
                    message: 'Lokasi tidak bisa dihapus karena masih dipakai di transaksi.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $codeRule = $this->formMode === 'create'
            ? ['required', 'string', 'max:10', 'unique:immst_stocklocations,sl_code']
            : ['required', 'string', 'max:10'];

        $rules = [
            'form.sl_code' => $codeRule,
            'form.sl_name' => ['required', 'string', 'max:50'],
            'form.stock_status' => ['required', 'in:0,1'],
            'form.active_status' => ['required', 'in:0,1'],
            'form.medis' => ['required', 'in:0,1'],
            'form.nonmedis' => ['required', 'in:0,1'],
        ];

        $messages = [
            'form.sl_code.required' => 'Kode Lokasi wajib diisi.',
            'form.sl_code.max' => 'Kode Lokasi maksimal 10 karakter.',
            'form.sl_code.unique' => 'Kode Lokasi sudah digunakan.',
            'form.sl_name.required' => 'Nama Lokasi wajib diisi.',
            'form.sl_name.max' => 'Nama Lokasi maksimal 50 karakter.',
        ];

        $attributes = [
            'form.sl_code' => 'Kode Lokasi',
            'form.sl_name' => 'Nama Lokasi',
            'form.stock_status' => 'Status Stok',
            'form.active_status' => 'Status Aktif',
            'form.medis' => 'Tipe Medis',
            'form.nonmedis' => 'Tipe Non-Medis',
        ];

        $this->validate($rules, $messages, $attributes);

        if ($this->form['medis'] !== '1' && $this->form['nonmedis'] !== '1') {
            $this->addError('form.medis', 'Pilih minimal salah satu: Medis atau Non-Medis.');
            return;
        }

        $payload = [
            'sl_name' => mb_strtoupper($this->form['sl_name']),
            'stock_status' => $this->form['stock_status'],
            'active_status' => $this->form['active_status'],
            'medis' => $this->form['medis'],
            'nonmedis' => $this->form['nonmedis'],
        ];

        if ($this->formMode === 'create') {
            DB::table('immst_stocklocations')->insert([
                'sl_code' => mb_strtoupper($this->form['sl_code']),
                ...$payload,
            ]);
        } else {
            DB::table('immst_stocklocations')
                ->where('sl_code', $this->originalCode)
                ->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data lokasi stok berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.stocklocations.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-stocklocations-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'sl_code' => '',
            'sl_name' => '',
            'stock_status' => '1',
            'active_status' => '1',
            'medis' => '1',
            'nonmedis' => '0',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-stocklocations-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-stocklocations-actions"
            event="master.stocklocations.saved"
            label="Master Stocklocations"
            :wireKey="$this->renderKey('modal', [$formMode, $originalCode])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Lokasi Stok' : 'Tambah Lokasi Stok' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Definisikan kode lokasi, tipe (medis / non-medis), dan status aktif.
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
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-sl-code.window="$nextTick(() => setTimeout(() => $refs.inputSlCode?.focus(), 150))"
                x-on:focus-sl-name.window="$nextTick(() => setTimeout(() => $refs.inputSlName?.focus(), 150))">

                <x-border-form title="Identitas Lokasi">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Kode Lokasi" />
                            <x-text-input wire:model.live="form.sl_code" x-ref="inputSlCode" type="text" maxlength="10"
                                :disabled="$formMode === 'edit'" :error="$errors->has('form.sl_code')"
                                class="w-full mt-1 font-mono uppercase"
                                placeholder="01, 02, dst." x-on:keydown.enter.prevent="$refs.inputSlName?.focus()" />
                            <x-input-error :messages="$errors->get('form.sl_code')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Maks 10 karakter. Tidak bisa diubah setelah disimpan.
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Nama Lokasi" />
                            <x-text-input wire:model.live="form.sl_name" x-ref="inputSlName" type="text" maxlength="50"
                                :error="$errors->has('form.sl_name')" class="w-full mt-1 uppercase"
                                placeholder="cth. RUANG OK, APOTEK, GUDANG MEDIS"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <x-input-error :messages="$errors->get('form.sl_name')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Tipe Lokasi" class="mt-4">
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        Lokasi boleh dipakai untuk obat medis, barang non-medis, atau keduanya.
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="mt-0.5">
                                <x-toggle wire:model.live="form.medis" trueValue="1" falseValue="0" />
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">Medis</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Menyimpan obat & alkes (stok di tabel <code>immst_products</code>).
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="mt-0.5">
                                <x-toggle wire:model.live="form.nonmedis" trueValue="1" falseValue="0" />
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">Non-Medis</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Menyimpan barang non-medis (ATK, RT, dll.).
                                </div>
                            </div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('form.medis')" class="mt-2" />
                </x-border-form>

                <x-border-form title="Status" class="mt-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="mt-0.5">
                                <x-toggle wire:model.live="form.stock_status" trueValue="1" falseValue="0" />
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">Status Stok Aktif</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Lokasi ini mengelola saldo stok (bisa jadi sumber / tujuan transfer).
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="mt-0.5">
                                <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0" />
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">Master Aktif</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Master ini tampil di pilihan / dropdown. Non-aktifkan untuk soft-disable.
                                </div>
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">di field Nama untuk simpan</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
