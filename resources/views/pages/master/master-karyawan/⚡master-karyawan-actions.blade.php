<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public string $originalEmpId = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $formKaryawan = [
        'empId' => '',
        'empName' => '',
        'phone' => null,
        'address' => null,
        'activeRecord' => '1',
    ];

    #[On('master.karyawan.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->originalEmpId = '';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-karyawan-actions');
        $this->dispatch('focus-emp-id');
    }

    #[On('master.karyawan.openEdit')]
    public function openEdit(string $empId): void
    {
        $row = DB::table('immst_employers')->where('emp_id', $empId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->originalEmpId = $empId;

        $this->fillFormFromRow($row);

        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-karyawan-actions');
        $this->dispatch('focus-emp-name');
    }

    protected function resetFormFields(): void
    {
        $this->formKaryawan = [
            'empId' => '',
            'empName' => '',
            'phone' => null,
            'address' => null,
            'activeRecord' => '1',
        ];

        $this->resetValidation();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->formKaryawan = [
            'empId' => (string) $row->emp_id,
            'empName' => (string) ($row->emp_name ?? ''),
            'phone' => $row->phone,
            'address' => $row->address,
            // Normalisasi konsisten dengan table: HANYA '1' → Aktif, sisanya
            // (null/'0'/'N'/whitespace) → Non-aktif. Ini fix bug di mana null
            // di DB → toggle muncul 'Aktif' tapi badge table 'Non-aktif'.
            'activeRecord' => (string) $row->active_record === '1' ? '1' : '0',
        ];
    }

    protected function rules(): array
    {
        return [
            'formKaryawan.empId' => $this->formMode === 'create'
                ? 'required|string|max:15|unique:immst_employers,emp_id'
                : 'required|string|max:15|unique:immst_employers,emp_id,' . $this->formKaryawan['empId'] . ',emp_id',

            'formKaryawan.empName' => 'required|string|max:100',
            'formKaryawan.phone' => 'nullable|string|max:15',
            'formKaryawan.address' => 'nullable|string|max:100',
            'formKaryawan.activeRecord' => 'required|in:0,1',
        ];
    }

    protected function messages(): array
    {
        return [
            'formKaryawan.empId.required' => ':attribute wajib diisi.',
            'formKaryawan.empId.max' => ':attribute maksimal :max karakter.',
            'formKaryawan.empId.unique' => ':attribute sudah digunakan.',

            'formKaryawan.empName.required' => ':attribute wajib diisi.',
            'formKaryawan.empName.max' => ':attribute maksimal :max karakter.',

            'formKaryawan.phone.max' => ':attribute maksimal :max karakter.',
            'formKaryawan.address.max' => ':attribute maksimal :max karakter.',

            'formKaryawan.activeRecord.required' => ':attribute wajib dipilih.',
            'formKaryawan.activeRecord.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formKaryawan.empId' => 'NIK / Emp ID',
            'formKaryawan.empName' => 'Nama Karyawan',
            'formKaryawan.phone' => 'Phone',
            'formKaryawan.address' => 'Alamat',
            'formKaryawan.activeRecord' => 'Status Aktif',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'emp_name' => $this->formKaryawan['empName'],
            'phone' => $this->formKaryawan['phone'],
            'address' => $this->formKaryawan['address'],
            'active_record' => $this->formKaryawan['activeRecord'],
        ];

        if ($this->formMode === 'create') {
            DB::table('immst_employers')->insert([
                'emp_id' => $this->formKaryawan['empId'],
                ...$payload,
            ]);
        } else {
            DB::table('immst_employers')->where('emp_id', $this->formKaryawan['empId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data karyawan berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.karyawan.saved');
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'master-karyawan-actions');
        $this->resetVersion();
    }

    #[On('master.karyawan.requestDelete')]
    public function deleteFromGrid(string $empId): void
    {
        try {
            // Cek pemakaian di tabel users (FK ke karyawan)
            $isUsedByUser = DB::table('users')->where('emp_id', $empId)->exists();
            if ($isUsedByUser) {
                $this->dispatch('toast', type: 'error', message: 'Karyawan tidak bisa dihapus karena masih dipakai oleh user login (cek master User Control).');
                return;
            }

            // Cek pemakaian di transaksi RJ (kasir)
            $isUsedInRj = DB::table('rstxn_rjhdrs')->where('emp_id', $empId)->exists();
            if ($isUsedInRj) {
                $this->dispatch('toast', type: 'error', message: 'Karyawan tidak bisa dihapus karena masih dipakai sebagai kasir di transaksi Rawat Jalan.');
                return;
            }

            $deleted = DB::table('immst_employers')->where('emp_id', $empId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data karyawan tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data karyawan berhasil dihapus.');
            $this->dispatch('master.karyawan.saved');
        } catch (QueryException $e) {
            // Oracle FK violation
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Karyawan tidak bisa dihapus karena masih dipakai di data lain (mis. transaksi kasir).');
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
    <x-modal name="master-karyawan-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-karyawan-actions"
            event="master.karyawan.saved"
            label="Karyawan"
            :wireKey="$this->renderKey('modal', [$formMode, $originalEmpId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">

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
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Karyawan' : 'Tambah Data Karyawan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    NIK karyawan dipakai untuk login user & coder iDRG. Pastikan terdaftar juga di Personnel Registration app E-Klaim untuk klaim BPJS.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
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
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <div class="max-w-4xl">
                    <x-border-form title="Data Karyawan"
                        x-data
                        x-on:focus-emp-id.window="$nextTick(() => setTimeout(() => $refs.inputEmpId?.focus(), 150))"
                        x-on:focus-emp-name.window="$nextTick(() => setTimeout(() => $refs.inputEmpName?.focus(), 150))">
                        <div class="space-y-5">
                            {{-- NIK / Emp ID --}}
                            <div>
                                <x-input-label value="NIK / Emp ID" />
                                <x-text-input wire:model.live="formKaryawan.empId" x-ref="inputEmpId"
                                    :disabled="$formMode === 'edit'" :error="$errors->has('formKaryawan.empId')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputEmpName?.focus()" />
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    Untuk klaim BPJS iDRG, NIK ini wajib terdaftar juga di Personnel Registration app E-Klaim.
                                </p>
                                <x-input-error :messages="$errors->get('formKaryawan.empId')" class="mt-1" />
                            </div>

                            {{-- Nama --}}
                            <div>
                                <x-input-label value="Nama Karyawan" />
                                <x-text-input wire:model.live="formKaryawan.empName" x-ref="inputEmpName"
                                    :error="$errors->has('formKaryawan.empName')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputPhone?.focus()" />
                                <x-input-error :messages="$errors->get('formKaryawan.empName')" class="mt-1" />
                            </div>

                            {{-- Phone --}}
                            <div>
                                <x-input-label value="Phone" />
                                <x-text-input wire:model.live="formKaryawan.phone" x-ref="inputPhone"
                                    :error="$errors->has('formKaryawan.phone')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputAddress?.focus()" />
                                <x-input-error :messages="$errors->get('formKaryawan.phone')" class="mt-1" />
                            </div>

                            {{-- Address --}}
                            <div>
                                <x-input-label value="Alamat" />
                                <x-text-input wire:model.live="formKaryawan.address" x-ref="inputAddress"
                                    :error="$errors->has('formKaryawan.address')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('formKaryawan.address')" class="mt-1" />
                            </div>

                            {{-- Status (toggle, paling bawah) --}}
                            <div class="pt-2">
                                <x-toggle wire:model.live="formKaryawan.activeRecord" trueValue="1" falseValue="0">
                                    Status Aktif
                                </x-toggle>
                                <x-input-error :messages="$errors->get('formKaryawan.activeRecord')" class="mt-1" />
                            </div>

                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <span class="hidden sm:inline">Tekan </span>
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field,</span>
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="hidden sm:inline"> di field terakhir untuk menyimpan</span>
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
