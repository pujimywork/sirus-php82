<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

new class extends Component {
    public string $formMode = 'create'; // create|edit

    public ?string $drId = null;
    public string $drName = '';
    public ?string $drAddress = null;
    public ?string $drPhone = null;
    public ?string $poliId = null;

    public ?string $kdDrBpjs = null;
    public ?string $drUuid = null;
    public ?string $drNik = null;

    public ?string $poliPrice = null;
    public ?string $ugdPrice = null;
    public ?string $basicSalary = null;

    public ?string $poliPriceBpjs = null;
    public ?string $ugdPriceBpjs = null;

    public string $contributionStatus = '0';
    public string $activeStatus = '1';
    public string $rsAdmin = '0';

    /* -------------------------
     | Open modal handlers
     * ------------------------- */
    #[On('master.dokter.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'master-dokter-actions');
    }

    #[On('master.dokter.openEdit')]
    public function openEdit(string $drId): void
    {
        $row = DB::table('rsmst_doctors')->where('dr_id', $drId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'master-dokter-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-dokter-actions');
    }

    /* -------------------------
     | Helpers
     * ------------------------- */
    protected function resetFormFields(): void
    {
        $this->reset(['drId', 'drName', 'drAddress', 'drPhone', 'poliId', 'kdDrBpjs', 'drUuid', 'drNik', 'poliPrice', 'ugdPrice', 'basicSalary', 'poliPriceBpjs', 'ugdPriceBpjs', 'contributionStatus', 'activeStatus', 'rsAdmin']);

        // default values
        $this->formMode = 'create';
        $this->contributionStatus = '0';
        $this->activeStatus = '1';
        $this->rsAdmin = '0';
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->drId = (string) $row->dr_id;
        $this->drName = (string) ($row->dr_name ?? '');
        $this->drAddress = $row->dr_address;
        $this->drPhone = $row->dr_phone;
        $this->poliId = $row->poli_id;

        $this->basicSalary = $row->basic_salary !== null ? (string) $row->basic_salary : null;
        $this->poliPrice = $row->poli_price !== null ? (string) $row->poli_price : null;
        $this->ugdPrice = $row->ugd_price !== null ? (string) $row->ugd_price : null;

        $this->poliPriceBpjs = $row->poli_price_bpjs !== null ? (string) $row->poli_price_bpjs : null;
        $this->ugdPriceBpjs = $row->ugd_price_bpjs !== null ? (string) $row->ugd_price_bpjs : null;

        $this->contributionStatus = (string) ($row->contribution_status ?? '0');
        $this->activeStatus = (string) ($row->active_status ?? '1');
        $this->rsAdmin = (string) ($row->rs_admin ?? '0');

        $this->kdDrBpjs = $row->kd_dr_bpjs;
        $this->drUuid = $row->dr_uuid;
        $this->drNik = $row->dr_nik;
    }

    /* -------------------------
     | Validation
     * ------------------------- */
  protected function rules(): array
{
    return [
        'drId' => $this->formMode === 'create'
            ? 'required|string|max:50|unique:rsmst_doctors,dr_id'
            : 'required|string|max:50|unique:rsmst_doctors,dr_id,' . $this->drId . ',dr_id',

        'drName' => 'required|string|max:255',
        'drPhone' => 'nullable|string|max:100',
        'drAddress' => 'nullable|string|max:255',
        'poliId' => 'required|string|max:250|exists:rsmst_polis,poli_id',

        'basicSalary' => 'nullable|numeric',
        'poliPrice' => 'nullable|numeric',
        'ugdPrice' => 'nullable|numeric',
        'poliPriceBpjs' => 'nullable|numeric',
        'ugdPriceBpjs' => 'nullable|numeric',

        'contributionStatus' => 'required|in:0,1',
        'activeStatus' => 'required|in:0,1',
        'rsAdmin' => 'required|numeric',

        'kdDrBpjs' => 'nullable|string|max:50',
        'drUuid' => 'nullable|string|max:100',
        'drNik' => 'nullable|string|max:50',
    ];
}


protected function messages(): array
{
    return [
        '*.required' => ':attribute wajib diisi.',
        '*.string' => ':attribute harus berupa teks.',
        '*.numeric' => ':attribute harus berupa angka.',
        '*.max' => ':attribute maksimal :max karakter.',
        '*.unique' => ':attribute sudah digunakan.',
        '*.in' => ':attribute tidak valid.',
        '*.exists' => ':attribute tidak ditemukan di database.',
            ];
}

protected function validationAttributes(): array
{
    return [
        'drId' => 'ID Dokter',
        'drName' => 'Nama Dokter',
        'drPhone' => 'Telepon',
        'drAddress' => 'Alamat',
        'poliId' => 'Poli ID',
        'basicSalary' => 'Gaji Pokok',
        'poliPrice' => 'Tarif Poli',
        'ugdPrice' => 'Tarif UGD',
        'poliPriceBpjs' => 'Tarif Poli BPJS',
        'ugdPriceBpjs' => 'Tarif UGD BPJS',
        'contributionStatus' => 'Status Kontribusi',
        'activeStatus' => 'Status Aktif',
        'rsAdmin' => 'RS Admin',
        'kdDrBpjs' => 'Kode Dokter BPJS',
        'drUuid' => 'UUID',
        'drNik' => 'NIK',
    ];
}


    /* -------------------------
     | Save
     * ------------------------- */
    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'dr_id' => $data['drId'],
            'dr_name' => $data['drName'],
            'dr_address' => $data['drAddress'],
            'dr_phone' => $data['drPhone'],
            'poli_id' => $data['poliId'],

            'basic_salary' => $data['basicSalary'],
            'poli_price' => $data['poliPrice'],
            'ugd_price' => $data['ugdPrice'],

            'poli_price_bpjs' => $data['poliPriceBpjs'],
            'ugd_price_bpjs' => $data['ugdPriceBpjs'],

            'contribution_status' => $data['contributionStatus'],
            'active_status' => $data['activeStatus'],
            'rs_admin' => $data['rsAdmin'],

            'kd_dr_bpjs' => $data['kdDrBpjs'],
            'dr_uuid' => $data['drUuid'],
            'dr_nik' => $data['drNik'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_doctors')->insert($payload);
        } else {
            DB::table('rsmst_doctors')->where('dr_id', $this->drId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data dokter berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.dokter.saved');
    }

    /* -------------------------
     | Delete (delegate from grid)
     * ------------------------- */
    #[On('master.dokter.requestDelete')]
    public function deleteFromGrid(string $drId): void
    {
        try {
            // TODO: ganti sesuai tabel transaksi kamu yang benar
            // contoh: cek kalau dokter sudah dipakai di transaksi
            $isUsed = DB::table('rstxn_rjhdrs')->where('dr_id', $drId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Dokter sudah dipakai pada transaksi Rawat Jalan.');
                return;
            }

            $deleted = DB::table('rsmst_doctors')->where('dr_id', $drId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data dokter tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data dokter berhasil dihapus.');
            $this->dispatch('master.dokter.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Dokter tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }
    #[On('lov.selected')]
    public function handleLovSelected(string $target, array $payload): void
    {

        $this->poliId = $payload['poli_id'] ?? '';

    
    }
};
?>

<div>
    <x-modal name="master-dokter-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-dokter-actions-{{ $formMode }}-{{ $drId ?? 'new' }}">

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
                                    {{ $formMode === 'edit' ? 'Ubah Data Dokter' : 'Tambah Data Dokter' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi dokter untuk kebutuhan aplikasi.
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
                <div class="max-w-5xl">
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5 space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                {{-- ID --}}
                                <div>
                                    <x-input-label value="ID Dokter" />
                                    <x-text-input wire:model.defer="drId" :disabled="$formMode === 'edit'"
                                        :error="$errors->has('drId')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('drId')" class="mt-1" />
                                </div>

                                {{-- Nama --}}
                                <div>
                                    <x-input-label value="Nama Dokter" />
                                    <x-text-input wire:model.defer="drName" :error="$errors->has('drName')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('drName')" class="mt-1" />
                                </div>

                                {{-- Poli ID --}}
                                <div>
                                    @if($this->formMode == 'create')
                                    <livewire:lov.poli.lov-poli target="masterDokterPoli" />
                                    @else
                                    <livewire:lov.poli.lov-poli target="masterDokterPoli" :initialPoliId="$poliId" />
                                    @endif
                                    <x-input-error :messages="$errors->get('poliId')" class="mt-1" />
                                </div>

                                {{-- Telepon --}}
                                <div>
                                    <x-input-label value="Telepon" />
                                    <x-text-input wire:model.defer="drPhone" :error="$errors->has('drPhone')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('drPhone')" class="mt-1" />
                                </div>

                                {{-- Alamat --}}
                                <div class="sm:col-span-2">
                                    <x-input-label value="Alamat" />
                                    <x-text-input wire:model.defer="drAddress" :error="$errors->has('drAddress')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('drAddress')" class="mt-1" />
                                </div>

                                {{-- Gaji --}}
                                <div>
                                    <x-input-label value="Gaji Pokok" />
                                    <x-text-input wire:model.defer="basicSalary" :error="$errors->has('basicSalary')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('basicSalary')" class="mt-1" />
                                </div>

                                {{-- Tarif Poli --}}
                                <div>
                                    <x-input-label value="Tarif Poli" />
                                    <x-text-input wire:model.defer="poliPrice" :error="$errors->has('poliPrice')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('poliPrice')" class="mt-1" />
                                </div>

                                {{-- Tarif UGD --}}
                                <div>
                                    <x-input-label value="Tarif UGD" />
                                    <x-text-input wire:model.defer="ugdPrice" :error="$errors->has('ugdPrice')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('ugdPrice')" class="mt-1" />
                                </div>

                                {{-- Tarif Poli BPJS --}}
                                <div>
                                    <x-input-label value="Tarif Poli BPJS" />
                                    <x-text-input wire:model.defer="poliPriceBpjs"
                                        :error="$errors->has('poliPriceBpjs')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('poliPriceBpjs')" class="mt-1" />
                                </div>

                                {{-- Tarif UGD BPJS --}}
                                <div>
                                    <x-input-label value="Tarif UGD BPJS" />
                                    <x-text-input wire:model.defer="ugdPriceBpjs" :error="$errors->has('ugdPriceBpjs')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('ugdPriceBpjs')" class="mt-1" />
                                </div>

                                {{-- Status Aktif --}}
                                <div>
                                    <x-input-label value="Status" />
                                    <x-select-input wire:model.defer="activeStatus"
                                        :error="$errors->has('activeStatus')" class="w-full mt-1">
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                                </div>

                                {{-- RS Admin --}}
                                <div>
                                    <x-input-label value="RS Admin" />
                                    <x-text-input wire:model.defer="rsAdmin" :error="$errors->has('rsAdmin')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('rsAdmin')" class="mt-1" />
                                </div>

                                {{-- Kode BPJS --}}
                                <div>
                                    <x-input-label value="Kode Dokter BPJS" />
                                    <x-text-input wire:model.defer="kdDrBpjs" :error="$errors->has('kdDrBpjs')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('kdDrBpjs')" class="mt-1" />
                                </div>

                                {{-- UUID --}}
                                <div>
                                    <x-input-label value="UUID" />
                                    <x-text-input wire:model.defer="drUuid" :error="$errors->has('drUuid')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('drUuid')" class="mt-1" />
                                </div>

                                {{-- NIK --}}
                                <div class="sm:col-span-2">
                                    <x-input-label value="NIK" />
                                    <x-text-input wire:model.defer="drNik" :error="$errors->has('drNik')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('drNik')" class="mt-1" />
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