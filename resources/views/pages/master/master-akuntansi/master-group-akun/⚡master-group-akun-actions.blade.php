<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'gra_id'     => '',
        'gra_desc'   => '',
        'gra_status' => 'N',
        'dk_status'  => 'D',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.group-akun.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-group-akun-actions');
    }

    #[On('master.group-akun.openEdit')]
    public function openEdit(string $graId): void
    {
        $row = DB::table('tkacc_gr_accountses')->where('gra_id', $graId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $graId;
        $this->form = [
            'gra_id'     => (string) $row->gra_id,
            'gra_desc'   => (string) ($row->gra_desc ?? ''),
            'gra_status' => (string) ($row->gra_status ?? 'N'),
            'dk_status'  => (string) ($row->dk_status ?? 'D'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-group-akun-actions');
    }

    #[On('master.group-akun.requestDelete')]
    public function deleteGroupAkun(string $graId): void
    {
        try {
            $deleted = DB::table('tkacc_gr_accountses')->where('gra_id', $graId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Group akun tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Group akun berhasil dihapus.');
            $this->dispatch('master.group-akun.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error',
                    message: 'Group akun masih dipakai di chart of accounts. Non-aktifkan saja.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.gra_id'     => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_-]+$/|unique:tkacc_gr_accountses,gra_id'
                : 'required|string',
            'form.gra_desc'   => 'required|string|max:100',
            'form.gra_status' => 'required|in:N,L',
            'form.dk_status'  => 'required|in:D,K',
        ];

        $messages = [
            'form.gra_id.required' => 'ID Group Akun wajib diisi.',
            'form.gra_id.max'      => 'ID Group Akun maksimal 25 karakter.',
            'form.gra_id.regex'    => 'ID hanya boleh huruf besar/angka/underscore/dash.',
            'form.gra_id.unique'   => 'ID Group Akun sudah digunakan.',
            'form.gra_desc.required' => 'Deskripsi wajib diisi.',
            'form.gra_desc.max'      => 'Deskripsi maksimal 100 karakter.',
            'form.gra_status.in'     => 'Tipe laporan hanya boleh N (Neraca) atau L (Laba-Rugi).',
            'form.dk_status.in'      => 'Debit/Kredit hanya boleh D atau K.',
        ];

        $attributes = [
            'form.gra_id'     => 'ID Group',
            'form.gra_desc'   => 'Deskripsi',
            'form.gra_status' => 'Tipe Laporan',
            'form.dk_status'  => 'Debit/Kredit',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'gra_desc'   => mb_strtoupper($this->form['gra_desc']),
            'gra_status' => $this->form['gra_status'],
            'dk_status'  => $this->form['dk_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkacc_gr_accountses')->insert([
                'gra_id' => mb_strtoupper($this->form['gra_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkacc_gr_accountses')->where('gra_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Group akun berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.group-akun.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-group-akun-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['gra_id' => '', 'gra_desc' => '', 'gra_status' => 'N', 'dk_status' => 'D'];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-group-akun-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-group-akun-actions"
            event="master.group-akun.saved"
            label="Group Akun"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Group Akun' : 'Tambah Group Akun' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Pengelompokan akun (mis. AKTIVA-LANCAR, KAS-BANK, PENDAPATAN-KLINIK).
                        </p>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <x-border-form title="Data Group Akun">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID" :required="true" />
                                <x-text-input wire:model.live="form.gra_id"
                                    maxlength="25" :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.gra_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.gra_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" :required="true" />
                                <x-text-input wire:model.live="form.gra_desc"
                                    maxlength="100"
                                    :error="$errors->has('form.gra_desc')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.gra_desc')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Debit / Kredit" :required="true" />
                                <x-select-input wire:model.live="form.dk_status" class="w-full mt-1">
                                    <option value="D">D — Debit</option>
                                    <option value="K">K — Kredit</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Aktiva/Biaya = Debit, Pasiva/Modal/Pendapatan = Kredit.
                                </p>
                                <x-input-error :messages="$errors->get('form.dk_status')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tipe Laporan" :required="true" />
                                <x-select-input wire:model.live="form.gra_status" class="w-full mt-1">
                                    <option value="N">N — Neraca</option>
                                    <option value="L">L — Laba-Rugi</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Aktiva/Hutang/Ekuitas → Neraca · Pendapatan/Beban → Laba-Rugi.
                                </p>
                                <x-input-error :messages="$errors->get('form.gra_status')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Saving...</span>
                    </x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
