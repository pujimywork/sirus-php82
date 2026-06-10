<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode      = 'create';
    public array  $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* int_desc_old menyimpan nilai awal saat edit (PK = int_desc) */
    public array $formInteraksi = [
        'int_desc'     => '',
        'int_desc_old' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.interaksi.openCreate')]
    public function openCreate(): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-interaksi-hdr');
        $this->dispatch('focus-int-desc');
    }

    #[On('master.interaksi.openEdit')]
    public function openEdit(string $intDesc): void
    {
        $row = DB::table('immst_interaksi_prodhdrs')->where('int_desc', $intDesc)->first();
        if (! $row) {
            $this->dispatch('toast', type: 'error', message: 'Data interaksi tidak ditemukan.');
            return;
        }

        $this->resetAll();
        $this->formMode         = 'edit';
        $this->formInteraksi = [
            'int_desc'     => (string) $row->int_desc,
            'int_desc_old' => (string) $row->int_desc,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-interaksi-hdr');
        $this->dispatch('focus-int-desc');
    }

    #[On('master.interaksi.delete')]
    public function delete(string $intDesc): void
    {
        try {
            DB::transaction(function () use ($intDesc) {
                DB::table('immst_interaksi_proddtls')->where('int_desc', $intDesc)->delete();
                DB::table('immst_interaksi_prodhdrs')->where('int_desc', $intDesc)->delete();
            });

            $this->dispatch('toast', type: 'success', message: 'Interaksi berhasil dihapus.');
            $this->dispatch('master.interaksi.saved', oldIntDesc: $intDesc, newIntDesc: '');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Interaksi tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'formInteraksi.int_desc' => 'required|string|max:100',
            ],
            [],
            [
                'formInteraksi.int_desc' => 'Nama Interaksi',
            ],
        );

        $newDesc = trim($this->formInteraksi['int_desc']);
        $oldDesc = $this->formInteraksi['int_desc_old'];

        // Cek duplikat (kecuali jika tidak berubah saat edit)
        if ($this->formMode === 'create' || $newDesc !== $oldDesc) {
            $exists = DB::table('immst_interaksi_prodhdrs')->where('int_desc', $newDesc)->exists();
            if ($exists) {
                $this->addError('formInteraksi.int_desc', 'Nama interaksi sudah ada.');
                return;
            }
        }

        if ($this->formMode === 'create') {
            DB::table('immst_interaksi_prodhdrs')->insert(['int_desc' => $newDesc]);
        } elseif ($newDesc !== $oldDesc) {
            // Rename PK → cascade ke detail
            DB::transaction(function () use ($newDesc, $oldDesc) {
                DB::table('immst_interaksi_prodhdrs')->where('int_desc', $oldDesc)->update(['int_desc' => $newDesc]);
                DB::table('immst_interaksi_proddtls')->where('int_desc', $oldDesc)->update(['int_desc' => $newDesc]);
            });
        }

        $this->dispatch('toast', type: 'success', message: 'Data interaksi berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.interaksi.saved', oldIntDesc: $oldDesc, newIntDesc: $newDesc);
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-interaksi-hdr');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formInteraksi = ['int_desc' => '', 'int_desc_old' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-interaksi-hdr" size="md" height="auto" focusable>
        <x-dirty-modal-content
            name="master-interaksi-hdr"
            event="master.interaksi.saved"
            label="Interaksi"
            :wireKey="$this->renderKey('modal', [$formMode])"
            wrapperClass="flex flex-col min-h-0">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah' : 'Tambah' }} Interaksi
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Lengkapi data berikut lalu klik Simpan.</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <x-border-form title="Data Interaksi" class="max-w-xl"
                    x-data
                    x-on:focus-int-desc.window="$nextTick(() => setTimeout(() => $refs.inputIntDesc?.focus(), 150))">
                    <div class="space-y-5">
                        <div>
                            <x-input-label value="Nama Interaksi" />
                            <x-text-input wire:model.live="formInteraksi.int_desc" x-ref="inputIntDesc"
                                maxlength="100" :error="$errors->has('formInteraksi.int_desc')" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                Nama kelompok interaksi obat.
                                @if ($formMode === 'edit')
                                    Mengubah nama akan otomatis memperbarui produk yang sudah terdaftar.
                                @endif
                            </p>
                            <x-input-error :messages="$errors->get('formInteraksi.int_desc')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="hidden sm:inline"> untuk simpan</span>
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
