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

    public array $formBangsal = [
        'bangsal_id'   => '',
        'bangsal_name' => '',
        'sl_codefrom'  => '',
        'bangsal_seq'  => '',
        'bed_bangsal'  => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // BANGSAL
    // =========================================================

    #[On('master.kamar.openCreateBangsal')]
    public function openCreateBangsal(): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-bangsal');
        $this->dispatch('focus-bangsal-id');
    }

    #[On('master.kamar.openEditBangsal')]
    public function openEditBangsal(string $bangsalId): void
    {
        $row = DB::table('rsmst_bangsals')->where('bangsal_id', $bangsalId)->first();
        if (! $row) {
            return;
        }

        $this->resetAll();
        $this->formMode   = 'edit';
        $this->formBangsal = [
            'bangsal_id'   => (string) $row->bangsal_id,
            'bangsal_name' => (string) ($row->bangsal_name ?? ''),
            'sl_codefrom'  => (string) ($row->sl_codefrom ?? ''),
            'bangsal_seq'  => (string) ($row->bangsal_seq ?? ''),
            'bed_bangsal'  => (string) ($row->bed_bangsal ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-bangsal');
        $this->dispatch('focus-bangsal-name');
    }

    #[On('master.kamar.deleteBangsal')]
    public function deleteBangsal(string $bangsalId): void
    {
        try {
            $hasRooms = DB::table('rsmst_rooms')->where('bangsal_id', $bangsalId)->exists();
            if ($hasRooms) {
                $this->dispatch('toast', type: 'error', message: 'Bangsal tidak bisa dihapus karena masih memiliki kamar.');
                return;
            }

            $deleted = DB::table('rsmst_bangsals')->where('bangsal_id', $bangsalId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data bangsal tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Bangsal berhasil dihapus.');
            $this->dispatch('master.kamar.saved', entity: 'bangsal');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Bangsal tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'formBangsal.bangsal_id'   => $this->formMode === 'create' ? 'required|string|max:5|unique:rsmst_bangsals,bangsal_id' : 'required|string|max:5',
                'formBangsal.bangsal_name' => 'required|string|max:25',
                'formBangsal.sl_codefrom'  => 'nullable|string|max:3',
                'formBangsal.bangsal_seq'  => 'nullable|integer|min:0',
                'formBangsal.bed_bangsal'  => 'nullable|integer|min:0',
            ],
            [],
            [
                'formBangsal.bangsal_id'   => 'ID Bangsal',
                'formBangsal.bangsal_name' => 'Nama Bangsal',
                'formBangsal.sl_codefrom'  => 'Kode SL',
                'formBangsal.bangsal_seq'  => 'Urutan',
                'formBangsal.bed_bangsal'  => 'Bed Bangsal',
            ],
        );

        $payload = [
            'bangsal_name' => $this->formBangsal['bangsal_name'],
            'sl_codefrom'  => $this->formBangsal['sl_codefrom'] ?: null,
            'bangsal_seq'  => $this->formBangsal['bangsal_seq'] !== '' ? (int) $this->formBangsal['bangsal_seq'] : null,
            'bed_bangsal'  => $this->formBangsal['bed_bangsal'] !== '' ? (int) $this->formBangsal['bed_bangsal'] : null,
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_bangsals')->insert(['bangsal_id' => $this->formBangsal['bangsal_id'], ...$payload]);
        } else {
            DB::table('rsmst_bangsals')->where('bangsal_id', $this->formBangsal['bangsal_id'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data bangsal berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kamar.saved', entity: 'bangsal');
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-kamar-bangsal');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formBangsal = ['bangsal_id' => '', 'bangsal_name' => '', 'sl_codefrom' => '', 'bangsal_seq' => '', 'bed_bangsal' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kamar-bangsal" size="md" height="auto" focusable>
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
                                    {{ $formMode === 'edit' ? 'Ubah' : 'Tambah' }} Data Bangsal
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
                <x-border-form title="Data Bangsal" class="max-w-xl"
                    x-data
                    x-on:focus-bangsal-id.window="$nextTick(() => setTimeout(() => $refs.inputBangsalId?.focus(), 150))"
                    x-on:focus-bangsal-name.window="$nextTick(() => setTimeout(() => $refs.inputBangsalName?.focus(), 150))">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="ID Bangsal" />
                                <x-text-input wire:model.live="formBangsal.bangsal_id" x-ref="inputBangsalId"
                                    :disabled="$formMode === 'edit'" maxlength="5" :error="$errors->has('formBangsal.bangsal_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputBangsalName?.focus()" />
                                <x-input-error :messages="$errors->get('formBangsal.bangsal_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Urutan (Seq)" />
                                <x-text-input wire:model.live="formBangsal.bangsal_seq" type="number" min="0"
                                    :error="$errors->has('formBangsal.bangsal_seq')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('formBangsal.bangsal_seq')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Nama Bangsal" />
                            <x-text-input wire:model.live="formBangsal.bangsal_name" x-ref="inputBangsalName"
                                maxlength="25" :error="$errors->has('formBangsal.bangsal_name')" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$refs.inputSlCode?.focus()" />
                            <x-input-error :messages="$errors->get('formBangsal.bangsal_name')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Kode SL" />
                                <x-text-input wire:model.live="formBangsal.sl_codefrom" x-ref="inputSlCode"
                                    maxlength="3" :error="$errors->has('formBangsal.sl_codefrom')" class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputBedBangsal?.focus()" />
                                <x-input-error :messages="$errors->get('formBangsal.sl_codefrom')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Bed Bangsal" />
                                <x-text-input wire:model.live="formBangsal.bed_bangsal" x-ref="inputBedBangsal"
                                    type="number" min="0" :error="$errors->has('formBangsal.bed_bangsal')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('formBangsal.bed_bangsal')" class="mt-1" />
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
