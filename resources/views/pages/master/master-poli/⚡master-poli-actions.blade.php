<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

// Deklarasi Render Versioning Trait //
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;
    public string $formMode = 'create'; // create|edit
    public string $originalPoliId = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal']; // ← area ini tidak untuk poli

    // Array dengan struktur yang diminta
    public array $formPoli = [
        'poliId' => '',
        'poliName' => '',
        'bpjsPoliCode' => null,
        'poliUuid' => null,
        'isSpecialist' => '0',
    ];

    #[On('master.poli.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->originalPoliId = '';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-poli-actions');
        $this->dispatch('focus-poli-id'); // ← ID Dokter kosong saat create
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
        $this->originalPoliId = $poliId;

        $this->fillFormFromRow($row);

        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-poli-actions');
        $this->dispatch('focus-poli-name'); // ← ID sudah ada saat edit, langsung ke nama
    }

    protected function resetFormFields(): void
    {
        $this->formPoli = [
            'poliId' => '',
            'poliName' => '',
            'bpjsPoliCode' => null,
            'poliUuid' => null,
            'isSpecialist' => '0',
        ];

        $this->resetValidation();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->formPoli = [
            'poliId' => (string) $row->poli_id,
            'poliName' => (string) ($row->poli_desc ?? ''),
            'bpjsPoliCode' => $row->kd_poli_bpjs,
            'poliUuid' => $row->poli_uuid,
            'isSpecialist' => (string) ($row->spesialis_status ?? '0'),
        ];
    }

    protected function rules(): array
    {
        return [
            'formPoli.poliId' => $this->formMode === 'create' ? 'required|numeric|unique:rsmst_polis,poli_id' : 'required|numeric|unique:rsmst_polis,poli_id,' . $this->formPoli['poliId'] . ',poli_id',

            'formPoli.poliName' => 'required|string|max:255',
            'formPoli.bpjsPoliCode' => 'nullable|string|max:50',
            'formPoli.poliUuid' => 'nullable|string|max:100',
            'formPoli.isSpecialist' => 'required|in:0,1',
        ];
    }

    protected function messages(): array
    {
        return [
            'formPoli.poliId.required' => ':attribute wajib diisi.',
            'formPoli.poliId.numeric' => ':attribute harus berupa angka.',
            'formPoli.poliId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',

            'formPoli.poliName.required' => ':attribute wajib diisi.',
            'formPoli.poliName.max' => ':attribute maksimal :max karakter.',

            'formPoli.bpjsPoliCode.max' => ':attribute maksimal :max karakter.',

            'formPoli.poliUuid.max' => ':attribute maksimal :max karakter.',

            'formPoli.isSpecialist.required' => ':attribute wajib dipilih.',
            'formPoli.isSpecialist.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formPoli.poliId' => 'ID Poli',
            'formPoli.poliName' => 'Nama Poli',
            'formPoli.bpjsPoliCode' => 'Kode Poli BPJS',
            'formPoli.poliUuid' => 'UUID Poli',
            'formPoli.isSpecialist' => 'Status Poli',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'poli_desc' => $this->formPoli['poliName'],
            'kd_poli_bpjs' => $this->formPoli['bpjsPoliCode'],
            'poli_uuid' => $this->formPoli['poliUuid'],
            'spesialis_status' => $this->formPoli['isSpecialist'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_polis')->insert([
                'poli_id' => $this->formPoli['poliId'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_polis')->where('poli_id', $this->formPoli['poliId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data poli berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.poli.saved');
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'master-poli-actions');
        $this->resetVersion();
    }

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

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }
};
?>

<div>
    <x-modal name="master-poli-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $originalPoliId]) }}">

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

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl">
                    {{-- x-data: tangkap focus event dari PHP --}}
                    <x-border-form title="Data Poli"
                        x-data
                        x-on:focus-poli-id.window="$nextTick(() => setTimeout(() => $refs.inputPoliId?.focus(), 150))"
                        x-on:focus-poli-name.window="$nextTick(() => setTimeout(() => $refs.inputPoliName?.focus(), 150))">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                {{-- Poli ID --}}
                                <div>
                                    <x-input-label value="Poli ID" />
                                    <x-text-input wire:model.live="formPoli.poliId" x-ref="inputPoliId"
                                        :disabled="$formMode === 'edit'" :error="$errors->has('formPoli.poliId')"
                                        class="w-full mt-1" x-on:keydown.enter.prevent="$refs.inputPoliName?.focus()" />
                                    <x-input-error :messages="$errors->get('formPoli.poliId')" class="mt-1" />
                                </div>

                                {{-- Status --}}
                                <div>
                                    <x-input-label value="Status" />
                                    <x-select-input wire:model.live="formPoli.isSpecialist"
                                        :error="$errors->has('formPoli.isSpecialist')" class="w-full mt-1">
                                        <option value="0">Non Spesialis</option>
                                        <option value="1">Spesialis</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('formPoli.isSpecialist')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Nama Poli --}}
                            <div>
                                <x-input-label value="Nama Poli" />
                                <x-text-input wire:model.live="formPoli.poliName" x-ref="inputPoliName"
                                    :error="$errors->has('formPoli.poliName')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputBpjsPoliCode?.focus()" />
                                <x-input-error :messages="$errors->get('formPoli.poliName')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Kode BPJS --}}
                                <div>
                                    <x-input-label value="Kode Poli BPJS" />
                                    <x-text-input wire:model.live="formPoli.bpjsPoliCode" x-ref="inputBpjsPoliCode"
                                        :error="$errors->has('formPoli.bpjsPoliCode')" class="w-full mt-1"
                                        x-on:keydown.enter.prevent="$refs.inputPoliUuid?.focus()" />
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        Opsional — isi jika poli terhubung ke referensi BPJS.
                                    </p>
                                    <x-input-error :messages="$errors->get('formPoli.bpjsPoliCode')" class="mt-1" />
                                </div>

                                {{-- UUID --}}
                                <div>
                                    <x-input-label value="UUID" />
                                    <x-text-input wire:model.live="formPoli.poliUuid" x-ref="inputPoliUuid"
                                        :error="$errors->has('formPoli.poliUuid')" class="w-full mt-1"
                                        x-on:keydown.enter.prevent="$wire.save()" />
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        Opsional — untuk sinkronisasi sistem.
                                    </p>
                                    <x-input-error :messages="$errors->get('formPoli.poliUuid')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>
                </div>
            </div>
        </div>

        {{-- FOOTER --}}
        <div
            class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
            <div class="flex items-center justify-between gap-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="hidden sm:inline">Tekan </span>
                    <kbd
                        class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                    <span class="mx-0.5">untuk berpindah field,</span>
                    <kbd
                        class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                    <span class="hidden sm:inline"> di field terakhir untuk menyimpan</span>
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
    </x-modal>
</div>