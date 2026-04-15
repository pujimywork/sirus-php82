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

    // Primary Key
    public ?string $radId = null;
    
    // Informasi Dasar
    public string $radDesc = '';
    public string $radPrice = '';
    
    // Status
    public string $activeStatus = '1'; // default aktif
    
    // Informasi Waktu (JD = Jam Dokter, JM = Jam Mulai?)
    public ?string $radJd = null;
    public ?string $radJm = null;
        public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // ==================== OPEN CREATE MODAL ====================
    #[On('master.radiologis.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-radiologis-actions');
    }

    // ==================== OPEN EDIT MODAL ====================
    #[On('master.radiologis.openEdit')]
    public function openEdit(string $radId): void
    {
        $row = DB::table('rsmst_radiologis')->where('rad_id', $radId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data radiologis tidak ditemukan.');
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();
         $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-radiologis-actions');
    }

    // ==================== CLOSE MODAL ====================
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-radiologis-actions');
    }

    // ==================== RESET FORM FIELDS ====================
    protected function resetFormFields(): void
    {
        $this->reset(['radId', 'radDesc', 'radPrice', 'radJd', 'radJm']);
        $this->activeStatus = '1'; // default aktif
        $this->resetVersion();
    }

    // ==================== FILL FORM FROM ROW ====================
    protected function fillFormFromRow(object $row): void
    {
        $this->radId = (string) $row->rad_id;
        $this->radDesc = (string) ($row->rad_desc ?? '');
        $this->radPrice = (string) ($row->rad_price ?? '0');
        $this->activeStatus = (string) ($row->active_status ?? '1');
        $this->radJd = $row->rad_jd;
        $this->radJm = $row->rad_jm;
    }

    // ==================== LOAD DROPDOWN OPTIONS ====================
    // (Jika diperlukan di masa depan)
    
    // ==================== VALIDATION RULES ====================
    protected function rules(): array
    {
        return [
            'radId' => [
                'required', 
                'numeric', 
                $this->formMode === 'create' 
                    ? Rule::unique('rsmst_radiologis', 'rad_id') 
                    : Rule::unique('rsmst_radiologis', 'rad_id')->ignore($this->radId, 'rad_id')
            ],
            'radDesc' => ['required', 'string', 'max:255'],
            'radPrice' => ['required', 'numeric', 'min:0'],
            'activeStatus' => ['required', Rule::in(['0', '1'])],
            'radJd' => ['nullable', 'string', 'max:50'],
            'radJm' => ['nullable', 'string', 'max:50'],
        ];
    }

    // ==================== CUSTOM VALIDATION MESSAGES ====================
    protected function messages(): array
    {
        return [
            'radId.required' => ':attribute wajib diisi.',
            'radId.numeric' => ':attribute harus berupa angka.',
            'radId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',

            'radDesc.required' => ':attribute wajib diisi.',
            'radDesc.max' => ':attribute maksimal :max karakter.',

            'radPrice.required' => ':attribute wajib diisi.',
            'radPrice.numeric' => ':attribute harus berupa angka.',
            'radPrice.min' => ':attribute tidak boleh kurang dari 0.',

            'activeStatus.required' => ':attribute wajib dipilih.',
            'activeStatus.in' => ':attribute tidak valid.',

            'radJd.max' => ':attribute maksimal :max karakter.',
            'radJm.max' => ':attribute maksimal :max karakter.',
        ];
    }

    // ==================== VALIDATION ATTRIBUTES ====================
    protected function validationAttributes(): array
    {
        return [
            'radId' => 'ID Radiologis',
            'radDesc' => 'Nama Tindakan',
            'radPrice' => 'Harga',
            'activeStatus' => 'Status Aktif',
            'radJd' => 'Jam Dokter',
            'radJm' => 'Jam Mulai',
        ];
    }

    // ==================== SAVE DATA ====================
    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'rad_desc' => $data['radDesc'],
            'rad_price' => $data['radPrice'],
            'active_status' => $data['activeStatus'],
            'rad_jd' => $data['radJd'],
            'rad_jm' => $data['radJm'],
        ];

        try {
            if ($this->formMode === 'create') {
                DB::table('rsmst_radiologis')->insert([
                    'rad_id' => $data['radId'],
                    ...$payload,
                ]);
            } else {
                DB::table('rsmst_radiologis')->where('rad_id', $data['radId'])->update($payload);
            }

            $this->dispatch('toast', type: 'success', message: 'Data radiologis berhasil disimpan.');
            $this->closeModal();

            $this->dispatch('master.radiologis.saved');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan database: ' . $e->getMessage());
        }
    }

    // ==================== DELETE DATA ====================
    #[On('master.radiologis.requestDelete')]
    public function deleteFromGrid(string $radId): void
    {
        try {
            // Cek apakah data sudah dipakai di tabel transaksi (sesuaikan dengan struktur DB Anda)
            $isUsed = DB::table('rstxn_rjhdrs')->where('rad_id', $radId)->exists();
            // Tambahkan pengecekan tabel lain jika diperlukan

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Data radiologis sudah dipakai pada transaksi.');
                return;
            }

            $deleted = DB::table('rsmst_radiologis')->where('rad_id', $radId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data radiologis tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data radiologis berhasil dihapus.');
            $this->dispatch('master.radiologis.saved');
        } catch (QueryException $e) {
            // Handle Oracle foreign key constraint error
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Radiologis tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }
};
?>


<div>
    <x-modal name="master-radiologis-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $radId ?? 'new']) }}">

            {{-- ==================== HEADER ==================== --}}
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
                                    {{ $formMode === 'edit' ? 'Ubah Data Radiologis' : 'Tambah Data Radiologis' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi tindakan radiologi untuk kebutuhan aplikasi.
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

            {{-- ==================== BODY ==================== --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl">
                    <x-border-form title="Data Radiologis">
                        <div class="space-y-5">
                            {{-- Baris 1: ID Radiologis & Status Aktif --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- RAD ID --}}
                                <div>
                                    <x-input-label value="ID Radiologis" />
                                    <x-text-input wire:model.live="radId" :disabled="$formMode === 'edit'"
                                        :error="$errors->has('radId')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('radId')" class="mt-1" />
                                </div>

                                {{-- Status Aktif --}}
                                <div>
                                    <x-input-label value="Status Aktif" />
                                    <x-select-input wire:model.live="activeStatus" :error="$errors->has('activeStatus')"
                                        class="w-full mt-1">
                                        <option value="1">Aktif</option>
                                        <option value="0">Tidak Aktif</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Nama Tindakan Radiologi --}}
                            <div>
                                <x-input-label value="Nama Tindakan" />
                                <x-text-input wire:model.live="radDesc" :error="$errors->has('radDesc')"
                                    class="w-full mt-1" placeholder="Contoh: Foto Thorax, CT Scan Kepala, MRI Lumbal" />
                                <x-input-error :messages="$errors->get('radDesc')" class="mt-1" />
                            </div>

                            {{-- Harga --}}
                            <div>
                                <x-input-label value="Harga" />
                                <x-text-input-number wire:model="radPrice" :error="$errors->has('radPrice')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('radPrice')" class="mt-1" />
                            </div>

                            {{-- Baris 2: RAD JD & RAD JM --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- RAD JD --}}
                                <div>
                                    <x-input-label value="Jasa Dokter (RAD JD)" />
                                    <x-text-input-number wire:model="radJd" :error="$errors->has('radJd')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('radJd')" class="mt-1" />
                                </div>

                                {{-- RAD JM --}}
                                <div>
                                    <x-input-label value="Jasa Medis (RAD JM)" />
                                    <x-text-input-number wire:model="radJm" :error="$errors->has('radJm')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('radJm')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- ==================== FOOTER ==================== --}}
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