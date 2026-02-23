<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

new class extends Component {
    public string $formMode = 'create'; // create|edit

    public ?string $diagId = null;
    public string $diagDesc = '';
    public ?string $icdx = null;

    #[On('master.diagnosa.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'master-diagnosa-actions');
    }

    #[On('master.diagnosa.openEdit')]
    public function openEdit(string $diagId): void
    {
        $row = DB::table('rsmst_mstdiags')->where('diag_id', $diagId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
    
        $this->dispatch('open-modal', name: 'master-diagnosa-actions');
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'master-diagnosa-actions');
    }

    protected function resetFormFields(): void
    {
        $this->reset(['diagId', 'diagDesc', 'icdx']);

        $this->resetValidation();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->diagId = (string) $row->diag_id;
        $this->diagDesc = (string) ($row->diag_desc ?? '');
        $this->icdx = $row->icdx;
    }

    protected function rules(): array
    {
        return [
            'diagId' => ['required', 'numeric', $this->formMode === 'create' ? Rule::unique('rsmst_mstdiags', 'diag_id') : Rule::unique('rsmst_mstdiags', 'diag_id')->ignore($this->diagId, 'diag_id')],
            'diagDesc' => ['required', 'string', 'max:255'],
            'icdx' => ['required', 'string', 'max:20'],
        ];
    }

    protected function messages(): array
    {
        return [
            'diagId.required' => ':attribute wajib diisi.',
            'diagId.numeric' => ':attribute harus berupa angka.',
            'diagId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',

            'diagDesc.required' => ':attribute wajib diisi.',
            'diagDesc.max' => ':attribute maksimal :max karakter.',

            'icdx.required' => ':attribute wajib diisi.',
            'icdx.max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'diagId' => 'ID Diagnosa',
            'diagDesc' => 'Nama Diagnosa',
            'icdx' => 'Kode ICD X',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'diag_desc' => $data['diagDesc'],
            'icdx' => $data['icdx'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_mstdiags')->insert([
                'diag_id' => $data['diagId'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_mstdiags')->where('diag_id', $data['diagId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data diagnosa berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.diagnosa.saved');
    }

    #[On('master.diagnosa.requestDelete')]
    public function deleteFromGrid(string $diagId): void
    {
        try {
            // Cek apakah diagnosa sudah dipakai di tabel transaksi (Rekam Medis)
            $isUsed = DB::table('rstxn_rjhdrs')->where('diag_id', $diagId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Data diagnosa sudah dipakai pada transaksi Rawat Jalan.');
                return;
            }

            $deleted = DB::table('rsmst_mstdiags')->where('diag_id', $diagId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data diagnosa tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data diagnosa berhasil dihapus.');
            $this->dispatch('master.diagnosa.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Diagnosa tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }
};
?>

<div>
    <x-modal name="master-diagnosa-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-diagnosa-actions-{{ $formMode }}-{{ $diagId ?? 'new' }}">

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
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Diagnosa' : 'Tambah Data Diagnosa' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi diagnosa sesuai standar ICD-10.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl">
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5 space-y-5">

                            {{-- Informasi Dasar --}}
                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-gray-700 uppercase dark:text-gray-300">
                                    Informasi Dasar
                                </h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {{-- Diagnosa ID --}}
                                    <div>
                                        <x-input-label value="ID Diagnosa" />
                                        <x-text-input wire:model.defer="diagId" :disabled="$formMode === 'edit'"
                                            :error="$errors->has('diagId')" class="w-full mt-1" />
                                        <x-input-error :messages="$errors->get('diagId')" class="mt-1" />
                                    </div>

                                    {{-- Kode ICD X --}}
                                    <div>
                                        <x-input-label value="Kode ICD X" />
                                        <x-text-input wire:model.defer="icdx" :error="$errors->has('icdx')"
                                            class="w-full mt-1" placeholder="Contoh: E11, I10, A09" />
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            Kode diagnosa sesuai standar ICD-10.
                                        </p>
                                        <x-input-error :messages="$errors->get('icdx')" class="mt-1" />
                                    </div>
                                </div>
                            </div>

                            {{-- Nama Diagnosa --}}
                            <div>
                                <x-input-label value="Nama Diagnosa" />
                                <x-text-input wire:model.defer="diagDesc" :error="$errors->has('diagDesc')"
                                    class="w-full mt-1" placeholder="Contoh: Diabetes Mellitus Tipe 2" />
                                <x-input-error :messages="$errors->get('diagDesc')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pastikan data sudah benar sebelum menyimpan.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

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