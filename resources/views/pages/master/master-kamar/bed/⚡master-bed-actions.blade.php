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

    public array $formBed = [
        'bed_no'   => '',
        'bed_desc' => '',
        'room_id'  => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // BED
    // =========================================================

    #[On('master.kamar.openCreateBed')]
    public function openCreateBed(string $roomId): void
    {
        $this->resetAll();
        $this->formMode          = 'create';
        $this->formBed['room_id'] = $roomId;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-bed');
        $this->dispatch('focus-bed-no');
    }

    #[On('master.kamar.openEditBed')]
    public function openEditBed(string $bedNo, string $roomId): void
    {
        $row = DB::table('rsmst_beds')->where('bed_no', $bedNo)->where('room_id', $roomId)->first();
        if (! $row) {
            return;
        }

        $this->resetAll();
        $this->formMode = 'edit';
        $this->formBed  = [
            'bed_no'   => (string) $row->bed_no,
            'bed_desc' => (string) ($row->bed_desc ?? ''),
            'room_id'  => (string) $row->room_id,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-kamar-bed');
        $this->dispatch('focus-bed-desc');
    }

    #[On('master.kamar.deleteBed')]
    public function deleteBed(string $bedNo, string $roomId): void
    {
        try {
            $deleted = DB::table('rsmst_beds')->where('bed_no', $bedNo)->where('room_id', $roomId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data bed tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Bed berhasil dihapus.');
            $this->dispatch('master.kamar.saved', entity: 'bed', roomId: $roomId);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Bed tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'formBed.bed_no'   => 'required|string|max:5',
                'formBed.bed_desc' => 'nullable|string|max:50',
                'formBed.room_id'  => 'required|string',
            ],
            [],
            [
                'formBed.bed_no'   => 'No Bed',
                'formBed.bed_desc' => 'Keterangan Bed',
                'formBed.room_id'  => 'Kamar',
            ],
        );

        if ($this->formMode === 'create') {
            $exists = DB::table('rsmst_beds')
                ->where('bed_no', $this->formBed['bed_no'])
                ->where('room_id', $this->formBed['room_id'])
                ->exists();

            if ($exists) {
                $this->addError('formBed.bed_no', 'No Bed sudah ada di kamar ini.');
                return;
            }

            DB::table('rsmst_beds')->insert([
                'bed_no'   => $this->formBed['bed_no'],
                'bed_desc' => $this->formBed['bed_desc'] ?: null,
                'room_id'  => $this->formBed['room_id'],
            ]);
        } else {
            DB::table('rsmst_beds')
                ->where('bed_no', $this->formBed['bed_no'])
                ->where('room_id', $this->formBed['room_id'])
                ->update(['bed_desc' => $this->formBed['bed_desc'] ?: null]);
        }

        $roomId = $this->formBed['room_id'];

        $this->dispatch('toast', type: 'success', message: 'Data bed berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.kamar.saved', entity: 'bed', roomId: $roomId);
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-kamar-bed');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formBed = ['bed_no' => '', 'bed_desc' => '', 'room_id' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-kamar-bed" size="md" height="auto" focusable>
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
                                    {{ $formMode === 'edit' ? 'Ubah' : 'Tambah' }} Data Bed
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
                <x-border-form title="Data Bed" class="max-w-xl"
                    x-data
                    x-on:focus-bed-no.window="$nextTick(() => setTimeout(() => $refs.inputBedNo?.focus(), 150))"
                    x-on:focus-bed-desc.window="$nextTick(() => setTimeout(() => $refs.inputBedDesc?.focus(), 150))">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="No Bed" />
                                <x-text-input wire:model.live="formBed.bed_no" x-ref="inputBedNo"
                                    :disabled="$formMode === 'edit'" maxlength="5" :error="$errors->has('formBed.bed_no')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputBedDesc?.focus()" />
                                <x-input-error :messages="$errors->get('formBed.bed_no')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kamar" />
                                <x-text-input :value="$formBed['room_id']" disabled
                                    class="w-full mt-1 bg-gray-50 dark:bg-gray-800" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Keterangan Bed" />
                            <x-text-input wire:model.live="formBed.bed_desc" x-ref="inputBedDesc" maxlength="50"
                                :error="$errors->has('formBed.bed_desc')" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Opsional — misal: "Bed A", "Bed Pojok", dll.</p>
                            <x-input-error :messages="$errors->get('formBed.bed_desc')" class="mt-1" />
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
