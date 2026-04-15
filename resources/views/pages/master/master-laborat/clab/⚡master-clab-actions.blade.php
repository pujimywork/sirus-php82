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

    public array $formClab = [
        'clab_id'   => '',
        'clab_desc' => '',
        'app_seq'   => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // CLAB
    // =========================================================

    #[On('master.laborat.openCreateClab')]
    public function openCreateClab(): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clab');
        $this->dispatch('focus-clab-id');
    }

    #[On('master.laborat.openEditClab')]
    public function openEditClab(string $clabId): void
    {
        $row = DB::table('lbmst_clabs')->where('clab_id', $clabId)->first();
        if (! $row) {
            return;
        }

        $this->resetAll();
        $this->formMode = 'edit';
        $this->formClab = [
            'clab_id'   => (string) $row->clab_id,
            'clab_desc' => (string) ($row->clab_desc ?? ''),
            'app_seq'   => (string) ($row->app_seq ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clab');
        $this->dispatch('focus-clab-desc');
    }

    #[On('master.laborat.deleteClab')]
    public function deleteClab(string $clabId): void
    {
        try {
            $hasItems = DB::table('lbmst_clabitems')->where('clab_id', $clabId)->exists();
            if ($hasItems) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih memiliki item pemeriksaan.');
                return;
            }

            $deleted = DB::table('lbmst_clabs')->where('clab_id', $clabId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kategori lab tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kategori lab berhasil dihapus.');
            $this->dispatch('master.laborat.saved', entity: 'clab');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'formClab.clab_id'   => $this->formMode === 'create' ? 'required|string|max:5|unique:lbmst_clabs,clab_id' : 'required|string|max:5',
                'formClab.clab_desc' => 'required|string|max:50',
                'formClab.app_seq'   => 'nullable|integer|min:0',
            ],
            [],
            [
                'formClab.clab_id'   => 'ID Kategori',
                'formClab.clab_desc' => 'Nama Kategori',
                'formClab.app_seq'   => 'Urutan',
            ],
        );

        $payload = [
            'clab_desc' => $this->formClab['clab_desc'],
            'app_seq'   => $this->formClab['app_seq'] !== '' ? (int) $this->formClab['app_seq'] : null,
        ];

        if ($this->formMode === 'create') {
            DB::table('lbmst_clabs')->insert(['clab_id' => $this->formClab['clab_id'], ...$payload]);
        } else {
            DB::table('lbmst_clabs')->where('clab_id', $this->formClab['clab_id'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kategori lab berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.laborat.saved', entity: 'clab');
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-laborat-clab');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formClab = ['clab_id' => '', 'clab_desc' => '', 'app_seq' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-laborat-clab" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0"
            wire:key="{{ $this->renderKey('modal', [$formMode]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah' : 'Tambah' }} Kategori Lab
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Lengkapi data berikut lalu klik Simpan.</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
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
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <x-border-form title="Data Kategori Lab" class="max-w-xl"
                    x-data
                    x-on:focus-clab-id.window="$nextTick(() => setTimeout(() => $refs.inputClabId?.focus(), 150))"
                    x-on:focus-clab-desc.window="$nextTick(() => setTimeout(() => $refs.inputClabDesc?.focus(), 150))">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="ID Kategori" />
                                <x-text-input wire:model.live="formClab.clab_id" x-ref="inputClabId"
                                    :disabled="$formMode === 'edit'" maxlength="5" :error="$errors->has('formClab.clab_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputClabDesc?.focus()" />
                                <x-input-error :messages="$errors->get('formClab.clab_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Urutan (Seq)" />
                                <x-text-input-number wire:model="formClab.app_seq"
                                    :error="$errors->has('formClab.app_seq')" class="mt-1"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('formClab.app_seq')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Nama Kategori" />
                            <x-text-input wire:model.live="formClab.clab_desc" x-ref="inputClabDesc"
                                maxlength="50" :error="$errors->has('formClab.clab_desc')" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <x-input-error :messages="$errors->get('formClab.clab_desc')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field,</span>
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="hidden sm:inline"> di field terakhir untuk simpan</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
