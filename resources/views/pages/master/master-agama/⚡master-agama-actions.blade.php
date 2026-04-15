<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'rel_id'   => '',
        'rel_desc' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // ─── Open Create ──────────────────────────────────────────────────────────
    #[On('master.agama.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-agama-actions');
        $this->dispatch('focus-rel-id');
    }

    // ─── Open Edit ────────────────────────────────────────────────────────────
    #[On('master.agama.openEdit')]
    public function openEdit(int $relId): void
    {
        $row = DB::table('rsmst_religions')->where('rel_id', $relId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $relId;
        $this->form = [
            'rel_id'   => (string) $row->rel_id,
            'rel_desc' => (string) ($row->rel_desc ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-agama-actions');
        $this->dispatch('focus-rel-desc');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────
    #[On('master.agama.requestDelete')]
    public function deleteAgama(int $relId): void
    {
        try {
            $deleted = DB::table('rsmst_religions')->where('rel_id', $relId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data agama tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Agama berhasil dihapus.');
            $this->dispatch('master.agama.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Agama tidak bisa dihapus karena masih dipakai di data pasien.');
                return;
            }
            throw $e;
        }
    }

    // ─── Save ─────────────────────────────────────────────────────────────────
    public function save(): void
    {
        $rules = [
            'form.rel_id'   => $this->formMode === 'create'
                ? 'required|integer|min:1|max:99|unique:rsmst_religions,rel_id'
                : 'required|integer',
            'form.rel_desc' => 'required|string|max:15',
        ];

        $messages = [
            'form.rel_id.required' => 'ID Agama wajib diisi.',
            'form.rel_id.integer'  => 'ID Agama harus berupa angka.',
            'form.rel_id.min'      => 'ID Agama minimal 1.',
            'form.rel_id.max'      => 'ID Agama maksimal 99.',
            'form.rel_id.unique'   => 'ID Agama sudah digunakan.',
            'form.rel_desc.required' => 'Nama Agama wajib diisi.',
            'form.rel_desc.max'      => 'Nama Agama maksimal 15 karakter.',
        ];

        $attributes = [
            'form.rel_id'   => 'ID Agama',
            'form.rel_desc' => 'Nama Agama',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = ['rel_desc' => mb_strtoupper($this->form['rel_desc'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_religions')->insert(['rel_id' => (int) $this->form['rel_id'], ...$payload]);
        } else {
            DB::table('rsmst_religions')->where('rel_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data agama berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.agama.saved');
    }

    // ─── Close ────────────────────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-agama-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['rel_id' => '', 'rel_desc' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-agama-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$formMode, $originalId]) }}">

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
                                    {{ $formMode === 'edit' ? 'Ubah Data Agama' : 'Tambah Data Agama' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi agama pasien.
                                </p>
                            </div>
                        </div>
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

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20"
                 x-data
                 x-on:focus-rel-id.window="$nextTick(() => setTimeout(() => $refs.inputRelId?.focus(), 150))"
                 x-on:focus-rel-desc.window="$nextTick(() => setTimeout(() => $refs.inputRelDesc?.focus(), 150))">

                <x-border-form title="Data Agama">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID Agama" />
                                <x-text-input wire:model.live="form.rel_id" x-ref="inputRelId"
                                    type="number" min="1" max="99"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.rel_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputRelDesc?.focus()" />
                                <x-input-error :messages="$errors->get('form.rel_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Agama" />
                                <x-text-input wire:model.live="form.rel_desc" x-ref="inputRelDesc"
                                    maxlength="15"
                                    :error="$errors->has('form.rel_desc')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.rel_desc')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">di field terakhir untuk simpan</span>
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
