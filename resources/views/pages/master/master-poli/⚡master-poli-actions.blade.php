<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

new class extends Component {
    public string $formMode = 'create'; // create|edit

    public ?string $poliId = null;
    public string $poliName = '';
    public ?string $bpjsPoliCode = null;
    public ?string $poliUuid = null;
    public string $isSpecialist = '0';

    #[On('master.poli.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'master-poli-actions');
    }

    #[On('master.poli.openEdit')]
    public function openEdit(string $poliId): void
    {
        $row = DB::table('rsmst_polis')->where('poli_id', $poliId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'master-poli-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-poli-actions');
    }

    protected function resetFormFields(): void
    {
        $this->reset(['poliId', 'poliName', 'bpjsPoliCode', 'poliUuid', 'isSpecialist']);
        $this->isSpecialist = '0';
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->poliId = (string) $row->poli_id;
        $this->poliName = (string) ($row->poli_desc ?? '');
        $this->bpjsPoliCode = $row->kd_poli_bpjs;
        $this->poliUuid = $row->poli_uuid;
        $this->isSpecialist = (string) ($row->spesialis_status ?? '0');
    }

    protected function rules(): array
    {
        return [
            'poliId' => ['required', 'numeric', $this->formMode === 'create' ? Rule::unique('rsmst_polis', 'poli_id') : Rule::unique('rsmst_polis', 'poli_id')->ignore($this->poliId, 'poli_id')],
            'poliName' => ['required', 'string', 'max:255'],
            'bpjsPoliCode' => ['nullable', 'string', 'max:50'],
            'poliUuid' => ['nullable', 'string', 'max:100'],
            'isSpecialist' => ['required', Rule::in(['0', '1'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'poliId.required' => ':attribute wajib diisi.',
            'poliId.numeric' => ':attribute harus berupa angka.',
            'poliId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',

            'poliName.required' => ':attribute wajib diisi.',
            'poliName.max' => ':attribute maksimal :max karakter.',

            'bpjsPoliCode.max' => ':attribute maksimal :max karakter.',

            'poliUuid.max' => ':attribute maksimal :max karakter.',

            'isSpecialist.required' => ':attribute wajib dipilih.',
            'isSpecialist.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'poliId' => 'ID Poli',
            'poliName' => 'Nama Poli',
            'bpjsPoliCode' => 'Kode Poli BPJS',
            'poliUuid' => 'UUID Poli',
            'isSpecialist' => 'Status Poli',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'poli_desc' => $data['poliName'],
            'kd_poli_bpjs' => $data['bpjsPoliCode'],
            'poli_uuid' => $data['poliUuid'],
            'spesialis_status' => $data['isSpecialist'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_polis')->insert([
                'poli_id' => $data['poliId'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_polis')->where('poli_id', $data['poliId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data poli berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.poli.saved');
    }

    // ✅ PINDAH KE DALAM CLASS
    #[On('master.poli.requestDelete')]
    public function deleteFromGrid(string $poliId): void
    {
        try {
            $isUsed = DB::table('rstxn_rjhdrs')->where('poli_id', $poliId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Data poli sudah dipakai pada transaksi Rawat Jalan.');
                return;
            }

            $deleted = DB::table('rsmst_polis')->where('poli_id', $poliId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data poli tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data poli berhasil dihapus.');
            $this->dispatch('master.poli.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Poli tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }
};
?>


<div>
    <x-modal name="master-poli-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-poli-actions-{{ $formMode }}-{{ $poliId ?? 'new' }}">

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
                                    {{ $formMode === 'edit' ? 'Ubah Data Poli' : 'Tambah Data Poli' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi poli untuk kebutuhan aplikasi.
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
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Poli ID --}}
                                <div>
                                    <x-input-label value="Poli ID" />
                                    <x-text-input wire:model.defer="poliId" :disabled="$formMode === 'edit'" :error="$errors->has('poliId')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('poliId')" class="mt-1" />
                                </div>

                                {{-- Status --}}
                                <div>
                                    <x-input-label value="Status" />
                                    <x-select-input wire:model.defer="isSpecialist" :error="$errors->has('isSpecialist')"
                                        class="w-full mt-1">
                                        <option value="0">Non Spesialis</option>
                                        <option value="1">Spesialis</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('isSpecialist')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Nama Poli --}}
                            <div>
                                <x-input-label value="Nama Poli" />
                                <x-text-input wire:model.defer="poliName" :error="$errors->has('poliName')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('poliName')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Kode BPJS --}}
                                <div>
                                    <x-input-label value="Kode Poli BPJS" />
                                    <x-text-input wire:model.defer="bpjsPoliCode" :error="$errors->has('bpjsPoliCode')"
                                        class="w-full mt-1" />
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        Opsional — isi jika poli terhubung ke referensi BPJS.
                                    </p>
                                    <x-input-error :messages="$errors->get('bpjsPoliCode')" class="mt-1" />
                                </div>

                                {{-- UUID --}}
                                <div>
                                    <x-input-label value="UUID" />
                                    <x-text-input wire:model.defer="poliUuid" :error="$errors->has('poliUuid')" class="w-full mt-1" />
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        Opsional — untuk sinkronisasi sistem.
                                    </p>
                                    <x-input-error :messages="$errors->get('poliUuid')" class="mt-1" />
                                </div>
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
