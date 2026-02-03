<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component {

    public string $formMode = 'create';

    public ?string $drId = null;
    public ?string $drName = null;
    public ?string $drAddress = null;
    public ?string $drPhone = null;
    public ?string $poliId = null;

    public ?float $poliPrice = null;
    public ?float $ugdPrice = null;
    public ?float $basicSalary = null;

    public string $contributionStatus = '0';
    public string $activeStatus = '1';
    public string $rsAdmin = '0';

    public ?string $kdDrBpjs = null;
    public ?string $drUuid = null;
    public ?string $drNik = null;

    public ?float $poliPriceBpjs = null;
    public ?float $ugdPriceBpjs = null;

    #[On('master.dokter.openCreate')]
    public function openCreate(): void
    {
        $this->reset();
        $this->formMode = 'create';
        $this->dispatch('open-modal', name: 'master-dokter-actions');
       

    }

    #[On('master.dokter.openEdit')]
    public function openEdit(string $drId): void
    {
        $row = DB::table('rsmst_doctors')->where('dr_id', $drId)->first();
      
        if (!$row) return;

        $this->formMode = 'edit';

        $this->drId = $row->dr_id;
        $this->drName = $row->dr_name;
        $this->drAddress = $row->dr_address;
        $this->drPhone = $row->dr_phone;
        $this->poliId = $row->poli_id;

        $this->poliPrice = $row->poli_price;
        $this->ugdPrice = $row->ugd_price;
        $this->basicSalary = $row->basic_salary;

        $this->contributionStatus = $row->contribution_status ?? '0';
        $this->activeStatus = $row->active_status ?? '1';
        $this->rsAdmin = $row->rs_admin ?? '0';

        $this->kdDrBpjs = $row->kd_dr_bpjs;
        $this->drUuid = $row->dr_uuid;
        $this->drNik = $row->dr_nik;

        $this->poliPriceBpjs = $row->poli_price_bpjs;
        $this->ugdPriceBpjs = $row->ugd_price_bpjs;

        $this->dispatch('open-modal', name: 'master-dokter-actions');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'master-dokter-actions');
    }

    protected function rules(): array
    {
        return [
            'drId' => ['required', 'string'],
            'drName' => ['required', 'string', 'max:255'],
            'drPhone' => ['nullable', 'string'],
            'drAddress' => ['nullable', 'string'],
            'poliId' => ['nullable', 'string'],

            'poliPrice' => ['nullable', 'numeric'],
            'ugdPrice' => ['nullable', 'numeric'],
            'basicSalary' => ['nullable', 'numeric'],

            'contributionStatus' => ['required'],
            'activeStatus' => ['required'],
            'rsAdmin' => ['required'],

            'kdDrBpjs' => ['nullable', 'string'],
            'drUuid' => ['nullable', 'string'],
            'drNik' => ['nullable', 'string'],

            'poliPriceBpjs' => ['nullable', 'numeric'],
            'ugdPriceBpjs' => ['nullable', 'numeric'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
        'DR_ID' => $this->drId,
        'DR_NAME' => $this->drName,
        'DR_ADDRESS' => $this->drAddress,
        'DR_PHONE' => $this->drPhone,
        'POLI_ID' => $this->poliId,

        'POLI_PRICE' => $this->poliPrice,
        'UGD_PRICE' => $this->ugdPrice,
        'BASIC_SALARY' => $this->basicSalary,

        'CONTRIBUTION_STATUS' => $this->contributionStatus,
        'ACTIVE_STATUS' => $this->activeStatus,
        'RS_ADMIN' => $this->rsAdmin,

        'KD_DR_BPJS' => $this->kdDrBpjs,
        'DR_UUID' => $this->drUuid,
        'DR_NIK' => $this->drNik,

        'POLI_PRICE_BPJS' => $this->poliPriceBpjs,
        'UGD_PRICE_BPJS' => $this->ugdPriceBpjs,
    ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_doctors')->insert($payload);
        } else {
            DB::table('rsmst_doctors')
                ->where('dr_id', $this->drId)
                ->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data dokter berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.dokter.saved');
    }
};
?>

<<div>
    <x-modal name="master-dokter-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="master-dokter-actions">

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
                                <img src="{{ asset('images/Logogram black solid.png') }}"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}"
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
                        ✕
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

                                <x-text-input wire:model.defer="drId" placeholder="ID Dokter" />
                                <x-text-input wire:model.defer="drName" placeholder="Nama Dokter" />

                                <x-text-input wire:model.defer="drPhone" placeholder="Telepon" />
                                <x-text-input wire:model.defer="drAddress" placeholder="Alamat" />

                                <x-text-input wire:model.defer="poliId" placeholder="Poli ID" />
                                <x-text-input wire:model.defer="basicSalary" placeholder="Gaji Pokok" />

                                <x-text-input wire:model.defer="poliPrice" placeholder="Tarif Poli" />
                                <x-text-input wire:model.defer="ugdPrice" placeholder="Tarif UGD" />

                                <x-text-input wire:model.defer="poliPriceBpjs" placeholder="Tarif Poli BPJS" />
                                <x-text-input wire:model.defer="ugdPriceBpjs" placeholder="Tarif UGD BPJS" />

                                <x-select-input wire:model.defer="activeStatus">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </x-select-input>

                                <x-select-input wire:model.defer="rsAdmin">
                                    <option value="1">RS Admin</option>
                                    <option value="0">Non Admin</option>
                                </x-select-input>

                                <x-text-input wire:model.defer="kdDrBpjs" placeholder="Kode BPJS" />
                                <x-text-input wire:model.defer="drUuid" placeholder="UUID" />

                                <x-text-input wire:model.defer="drNik" placeholder="NIK" />

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
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
    </div>