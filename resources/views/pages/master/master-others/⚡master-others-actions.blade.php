<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public ?string $otherId = null;
    public string $otherDesc = '';
    public ?string $otherPrice = null;
    public string $activeStatus = '1'; // default active

       public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.others.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-others-actions');
    }

    #[On('master.others.openEdit')]
    public function openEdit(string $otherId): void
    {
        $row = DB::table('rsmst_others')->where('other_id', $otherId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-others-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-others-actions');
    }

    protected function resetFormFields(): void
    {
        $this->reset(['otherId', 'otherDesc', 'otherPrice', 'activeStatus']);
        $this->activeStatus = '1';
        $this->resetVersion();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->otherId = (string) $row->other_id;
        $this->otherDesc = (string) ($row->other_desc ?? '');
        $this->otherPrice = $row->other_price;
        $this->activeStatus = (string) ($row->active_status ?? '1');
    }

    protected function rules(): array
    {
        return [
            'otherId' => ['required', 'numeric', $this->formMode === 'create' ? Rule::unique('rsmst_others', 'other_id') : Rule::unique('rsmst_others', 'other_id')->ignore($this->otherId, 'other_id')],
            'otherDesc' => ['required', 'string', 'max:255'],
            'otherPrice' => ['required', 'numeric', 'min:0'],
            'activeStatus' => ['required', Rule::in(['0', '1'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'otherId.required' => ':attribute wajib diisi.',
            'otherId.numeric' => ':attribute harus berupa angka.',
            'otherId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',

            'otherDesc.required' => ':attribute wajib diisi.',
            'otherDesc.max' => ':attribute maksimal :max karakter.',

            'otherPrice.required' => ':attribute wajib diisi.',
            'otherPrice.numeric' => ':attribute harus berupa angka.',
            'otherPrice.min' => ':attribute tidak boleh kurang dari 0.',

            'activeStatus.required' => ':attribute wajib dipilih.',
            'activeStatus.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'otherId' => 'ID Lain-lain',
            'otherDesc' => 'Nama Lain-lain',
            'otherPrice' => 'Harga',
            'activeStatus' => 'Status Aktif',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'other_desc' => $data['otherDesc'],
            'other_price' => $data['otherPrice'],
            'active_status' => $data['activeStatus'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_others')->insert([
                'other_id' => $data['otherId'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_others')->where('other_id', $data['otherId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data lain-lain berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.others.saved');
    }

    #[On('master.others.requestDelete')]
    public function deleteFromGrid(string $otherId): void
    {
        try {
            // Cek apakah data sudah dipakai di tabel transaksi
            $isUsed = DB::table('rstxn_rjhdrs')->where('other_id', $otherId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Data lain-lain sudah dipakai pada transaksi Rawat Jalan.');
                return;
            }

            $deleted = DB::table('rsmst_others')->where('other_id', $otherId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data lain-lain tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data lain-lain berhasil dihapus.');
            $this->dispatch('master.others.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }
};
?>

<div>
    <x-modal name="master-others-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $otherId ?? 'new']) }}">

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
                                    {{ $formMode === 'edit' ? 'Ubah Data Lain-lain' : 'Tambah Data Lain-lain' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi lain-lain untuk kebutuhan aplikasi.
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
                    <x-border-form title="Informasi Dasar">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Other ID --}}
                                <div>
                                    <x-input-label value="ID Lain-lain" />
                                    <x-text-input wire:model.live="otherId" :disabled="$formMode === 'edit'"
                                        :error="$errors->has('otherId')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('otherId')" class="mt-1" />
                                </div>

                                {{-- Status Aktif --}}
                                <div>
                                    <x-input-label value="Status Aktif" />
                                    <x-select-input wire:model.live="activeStatus"
                                        :error="$errors->has('activeStatus')" class="w-full mt-1">
                                        <option value="1">Aktif</option>
                                        <option value="0">Tidak Aktif</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Nama Lain-lain --}}
                            <div>
                                <x-input-label value="Nama Lain-lain" />
                                <x-text-input wire:model.live="otherDesc" :error="$errors->has('otherDesc')"
                                    class="w-full mt-1" placeholder="Contoh: Administrasi, Ambulans, dll" />
                                <x-input-error :messages="$errors->get('otherDesc')" class="mt-1" />
                            </div>

                            {{-- Harga --}}
                            <div>
                                <x-input-label value="Harga" />
                                <x-text-input-number wire:model="otherPrice" :error="$errors->has('otherPrice')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('otherPrice')" class="mt-1" />
                            </div>
                        </div>
                    </x-border-form>
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