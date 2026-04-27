<?php
// resources/views/pages/master/master-dokter/master-dokter-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create | edit

    // ── Form fields ──
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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ===============================
     | LOV Poli selected
     =============================== */
    #[On('lov.selected.masterDokterPoli')]
    public function masterDokterPoli(string $target, array $payload): void
    {
        $this->poliId = $payload['poli_id'] ?? null;
        $this->incrementVersion('modal');
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        if ($name === 'poliId') {
            $this->incrementVersion('modal');
        }
    }

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('master.dokter.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-dokter-actions');
        $this->dispatch('focus-dr-id'); // ← ID Dokter kosong saat create
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('master.dokter.openEdit')]
    public function openEdit(string $drId): void
    {
        $row = DB::table('rsmst_doctors')->where('dr_id', $drId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data dokter tidak ditemukan.');
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-dokter-actions');
        $this->dispatch('focus-dr-name'); // ← ID sudah ada saat edit, langsung ke nama
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-dokter-actions');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        // Unique rule khusus Oracle — perlu kolom PK eksplisit
        $uniqueDrId = $this->formMode === 'create' ? 'required|string|max:50|unique:rsmst_doctors,dr_id' : 'required|string|max:50|unique:rsmst_doctors,dr_id,' . $this->drId . ',dr_id';

        return [
            'drId' => $uniqueDrId,
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
            'poliId' => 'Poli',
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

    /* ===============================
     | SAVE
     =============================== */
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

    /* ===============================
     | DELETE (delegate dari grid)
     =============================== */
    #[On('master.dokter.requestDelete')]
    public function deleteFromGrid(string $drId): void
    {
        try {
            // Cek apakah dokter masih dipakai di transaksi RJ
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
            // ORA-02292: constraint violation — data masih dipakai di tabel lain
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Dokter tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetFormFields(): void
    {
        $this->reset(['drId', 'drName', 'drAddress', 'drPhone', 'poliId', 'kdDrBpjs', 'drUuid', 'drNik', 'poliPrice', 'ugdPrice', 'basicSalary', 'poliPriceBpjs', 'ugdPriceBpjs']);

        $this->resetVersion();
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
        // Normalisasi konsisten dengan toggle/table: HANYA '1' → Aktif, sisanya
        // (null/'0'/'N'/whitespace) → Non-aktif.
        $this->activeStatus = (string) $row->active_status === '1' ? '1' : '0';
        $this->rsAdmin = (string) ($row->rs_admin ?? '0');
        $this->kdDrBpjs = $row->kd_dr_bpjs;
        $this->drUuid = $row->dr_uuid;
        $this->drNik = $row->dr_nik;
    }
};
?>

<div>
    <x-modal name="master-dokter-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $drId ?? 'new']) }}">

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

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
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
                <div class="max-w-full mx-auto">

                    {{-- x-data: tangkap focus event dari PHP --}}
                    <div x-data
                        x-on:focus-dr-id.window="$nextTick(() => setTimeout(() => $refs.inputDrId?.focus(), 150))"
                        x-on:focus-dr-name.window="$nextTick(() => setTimeout(() => $refs.inputDrName?.focus(), 150))">

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                            {{-- ══ KOLOM KIRI — Data Dokter ══ --}}
                            <x-border-form title="Data Dokter">
                                <div class="space-y-4">

                                {{-- ID Dokter --}}
                                <div>
                                    <x-input-label value="ID Dokter *" class="mb-1" />
                                    <x-text-input wire:model.live="drId" x-ref="inputDrId" :disabled="$formMode === 'edit'"
                                        :error="$errors->has('drId')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputDrName?.focus()" />
                                    <x-input-error :messages="$errors->get('drId')" class="mt-1" />
                                </div>

                                {{-- Nama Dokter --}}
                                <div>
                                    <x-input-label value="Nama Dokter *" class="mb-1" />
                                    <x-text-input wire:model.live="drName" x-ref="inputDrName" :error="$errors->has('drName')"
                                        class="w-full"
                                        x-on:keydown.enter.prevent="$refs.lovPoli?.querySelector('input')?.focus()" />
                                    <x-input-error :messages="$errors->get('drName')" class="mt-1" />
                                </div>

                                {{-- LOV Poli --}}
                                <div x-ref="lovPoli" x-on:keydown.enter.prevent="$refs.inputDrPhone?.focus()">
                                    <livewire:lov.poli.lov-poli target="masterDokterPoli" :initialPoliId="$poliId"
                                        wire:key="lov-poli-master-dokter-{{ $formMode }}-{{ $drId ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('poliId')" class="mt-1" />
                                </div>

                                {{-- Telepon --}}
                                <div>
                                    <x-input-label value="Telepon" class="mb-1" />
                                    <x-text-input wire:model.live="drPhone" x-ref="inputDrPhone" :error="$errors->has('drPhone')"
                                        class="w-full" x-on:keydown.enter.prevent="$refs.inputDrAddress?.focus()" />
                                    <x-input-error :messages="$errors->get('drPhone')" class="mt-1" />
                                </div>

                                {{-- Alamat --}}
                                <div>
                                    <x-input-label value="Alamat" class="mb-1" />
                                    <x-text-input wire:model.live="drAddress" x-ref="inputDrAddress" :error="$errors->has('drAddress')"
                                        class="w-full" x-on:keydown.enter.prevent="$refs.inputKdDrBpjs?.focus()" />
                                    <x-input-error :messages="$errors->get('drAddress')" class="mt-1" />
                                </div>

                                {{-- Kode Dokter BPJS --}}
                                <div>
                                    <x-input-label value="Kode Dokter BPJS" class="mb-1" />
                                    <x-text-input wire:model.live="kdDrBpjs" x-ref="inputKdDrBpjs" :error="$errors->has('kdDrBpjs')"
                                        class="w-full" x-on:keydown.enter.prevent="$refs.inputDrUuid?.focus()" />
                                    <x-input-error :messages="$errors->get('kdDrBpjs')" class="mt-1" />
                                </div>

                                {{-- UUID --}}
                                <div>
                                    <x-input-label value="UUID" class="mb-1" />
                                    <x-text-input wire:model.live="drUuid" x-ref="inputDrUuid" :error="$errors->has('drUuid')"
                                        class="w-full" x-on:keydown.enter.prevent="$refs.inputDrNik?.focus()" />
                                    <x-input-error :messages="$errors->get('drUuid')" class="mt-1" />
                                </div>

                                {{-- NIK --}}
                                <div>
                                    <x-input-label value="NIK" class="mb-1" />
                                    <x-text-input wire:model.live="drNik" x-ref="inputDrNik" :error="$errors->has('drNik')"
                                        class="w-full" x-on:keydown.enter.prevent="$refs.inputBasicSalary?.focus()" />
                                    <x-input-error :messages="$errors->get('drNik')" class="mt-1" />
                                </div>

                                {{-- Status Aktif — toggle --}}
                                <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                                    <x-toggle wire:model.live="activeStatus" trueValue="1" falseValue="0"
                                        label="Status Aktif" />
                                    <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                                </div>
                                </div>
                            </x-border-form>

                            {{-- ══ KOLOM KANAN — Tarif & Lainnya ══ --}}
                            <x-border-form title="Tarif & Administrasi">
                                <div class="space-y-4">

                                {{-- Gaji Pokok --}}
                                <div>
                                    <x-input-label value="Gaji Pokok" class="mb-1" />
                                    <x-text-input-number wire:model="basicSalary" x-ref="inputBasicSalary"
                                        :error="$errors->has('basicSalary')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputRsAdmin?.focus()" />
                                    <x-input-error :messages="$errors->get('basicSalary')" class="mt-1" />
                                </div>

                                {{-- RS Admin --}}
                                <div>
                                    <x-input-label value="RS Admin" class="mb-1" />
                                    <x-text-input-number wire:model="rsAdmin" x-ref="inputRsAdmin" :error="$errors->has('rsAdmin')"
                                        class="w-full" x-on:keydown.enter.prevent="$refs.inputPoliPrice?.focus()" />
                                    <x-input-error :messages="$errors->get('rsAdmin')" class="mt-1" />
                                </div>

                                {{-- Separator tarif --}}
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide pt-1">Tarif Umum
                                </p>

                                {{-- Tarif Poli & UGD side by side --}}
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Tarif Poli" class="mb-1" />
                                        <x-text-input-number wire:model="poliPrice" x-ref="inputPoliPrice"
                                            :error="$errors->has('poliPrice')" class="w-full"
                                            x-on:keydown.enter.prevent="$refs.inputUgdPrice?.focus()" />
                                        <x-input-error :messages="$errors->get('poliPrice')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Tarif UGD" class="mb-1" />
                                        <x-text-input-number wire:model="ugdPrice" x-ref="inputUgdPrice"
                                            :error="$errors->has('ugdPrice')" class="w-full"
                                            x-on:keydown.enter.prevent="$refs.inputPoliPriceBpjs?.focus()" />
                                        <x-input-error :messages="$errors->get('ugdPrice')" class="mt-1" />
                                    </div>
                                </div>

                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide pt-1">Tarif BPJS
                                </p>

                                {{-- Tarif Poli & UGD BPJS side by side --}}
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Tarif Poli BPJS" class="mb-1" />
                                        <x-text-input-number wire:model="poliPriceBpjs" x-ref="inputPoliPriceBpjs"
                                            :error="$errors->has('poliPriceBpjs')" class="w-full"
                                            x-on:keydown.enter.prevent="$refs.inputUgdPriceBpjs?.focus()" />
                                        <x-input-error :messages="$errors->get('poliPriceBpjs')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Tarif UGD BPJS" class="mb-1" />
                                        <x-text-input-number wire:model="ugdPriceBpjs" x-ref="inputUgdPriceBpjs"
                                            :error="$errors->has('ugdPriceBpjs')" class="w-full"
                                            x-on:keydown.enter.prevent="$wire.save()" />
                                        <x-input-error :messages="$errors->get('ugdPriceBpjs')" class="mt-1" />
                                    </div>
                                </div>
                                </div>
                            </x-border-form>

                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Pastikan data sudah benar sebelum menyimpan.
                    </p>
                    <div class="flex gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
