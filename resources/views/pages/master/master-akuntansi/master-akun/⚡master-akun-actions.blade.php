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
        'acc_id'        => '',
        'acc_name'      => '',
        'active_status' => '1',
        'kas_status'    => '0',
        'gra_id'        => '',
        'acc_dk_status' => 'D',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.akun.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-akun-actions');
    }

    #[On('master.akun.openEdit')]
    public function openEdit(string $accId): void
    {
        $row = DB::table('acmst_accounts')->where('acc_id', $accId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $accId;
        $this->form = [
            'acc_id'        => (string) $row->acc_id,
            'acc_name'      => (string) ($row->acc_name ?? ''),
            'active_status' => (string) ($row->active_status ?? '1'),
            'kas_status'    => (string) ($row->kas_status ?? '0'),
            'gra_id'        => (string) ($row->gra_id ?? ''),
            'acc_dk_status' => (string) ($row->acc_dk_status ?? 'D'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-akun-actions');
    }

    #[On('lov.selected.akun-gra')]
    public function onGroupSelected(string $target, ?array $payload): void
    {
        $this->form['gra_id']        = (string) ($payload['gra_id'] ?? '');
        // Auto-fill D/K dari group bila kosong
        if (empty($this->form['acc_dk_status']) && !empty($payload['dk_status'])) {
            $this->form['acc_dk_status'] = (string) $payload['dk_status'];
        }
    }

    #[On('master.akun.requestDelete')]
    public function deleteAkun(string $accId): void
    {
        try {
            $deleted = DB::table('acmst_accounts')->where('acc_id', $accId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Akun tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Akun berhasil dihapus.');
            $this->dispatch('master.akun.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error',
                    message: 'Akun masih dipakai (cara bayar / saldo / transaksi). Non-aktifkan saja.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.acc_id'        => $this->formMode === 'create'
                ? 'required|string|max:25|regex:/^[A-Z0-9_.-]+$/|unique:acmst_accounts,acc_id'
                : 'required|string',
            'form.acc_name'      => 'required|string|max:100',
            'form.active_status' => 'required|in:0,1',
            'form.kas_status'    => 'required|in:0,1',
            'form.gra_id'        => 'required|string|max:25|exists:tkacc_gr_accountses,gra_id',
            'form.acc_dk_status' => 'required|in:D,K',
        ];

        $messages = [
            'form.acc_id.required' => 'ID Akun wajib diisi.',
            'form.acc_id.regex'    => 'ID hanya boleh huruf besar/angka/underscore/dot/dash.',
            'form.acc_id.unique'   => 'ID Akun sudah digunakan.',
            'form.gra_id.required' => 'Group Akun wajib dipilih.',
            'form.gra_id.exists'   => 'Group Akun tidak valid.',
            'form.acc_dk_status.in' => 'Debit/Kredit hanya D atau K.',
        ];

        $attributes = [
            'form.acc_id'        => 'ID Akun',
            'form.acc_name'      => 'Deskripsi',
            'form.gra_id'        => 'Group Akun',
            'form.kas_status'    => 'Tipe Kas',
            'form.acc_dk_status' => 'Debit/Kredit',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'acc_name'      => mb_strtoupper($this->form['acc_name']),
            'active_status' => $this->form['active_status'],
            'kas_status'    => $this->form['kas_status'],
            'gra_id'        => $this->form['gra_id'],
            'acc_dk_status' => $this->form['acc_dk_status'],
        ];

        if ($this->formMode === 'create') {
            DB::table('acmst_accounts')->insert([
                'acc_id' => mb_strtoupper($this->form['acc_id']),
                ...$payload,
            ]);
        } else {
            DB::table('acmst_accounts')->where('acc_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Akun berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.akun.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-akun-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'acc_id' => '', 'acc_name' => '', 'active_status' => '1',
            'kas_status' => '0', 'gra_id' => '', 'acc_dk_status' => 'D',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-akun-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-akun-actions"
            event="master.akun.saved"
            label="Akun"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="ds-display-sm dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Akun' : 'Tambah Akun' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Chart of accounts. Tipe <em>Kas</em> dipakai oleh kasir & cara bayar.
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

            <div class="flex-1 px-4 py-4 bg-canvas dark:bg-gray-950/20">
                <x-border-form title="Data Akun">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="ID" :required="true" />
                                <x-text-input wire:model.live="form.acc_id"
                                    maxlength="25" :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.acc_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.acc_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" :required="true" />
                                <x-text-input wire:model.live="form.acc_name"
                                    maxlength="100"
                                    :error="$errors->has('form.acc_name')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.acc_name')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <livewire:lov.group-akun.lov-group-akun
                                target="akun-gra"
                                label="Group Akun"
                                placeholder="Cari group akun..."
                                :initialGraId="$form['gra_id'] ?? null"
                                wire:key="lov-gra-akun-{{ $originalId ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('form.gra_id')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Debit / Kredit" :required="true" />
                                <x-select-input wire:model.live="form.acc_dk_status" class="w-full mt-1">
                                    <option value="D">D — Debit</option>
                                    <option value="K">K — Kredit</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.acc_dk_status')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tipe Akun" :required="true" />
                                <x-select-input wire:model.live="form.kas_status" class="w-full mt-1">
                                    <option value="0">Akun Biasa</option>
                                    <option value="1">Akun Kas</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    "Akun Kas" muncul di LOV kasir & cara bayar.
                                </p>
                                <x-input-error :messages="$errors->get('form.kas_status')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Status" :required="true" />
                                <x-select-input wire:model.live="form.active_status" class="w-full mt-1">
                                    <option value="1">Aktif</option>
                                    <option value="0">Non-aktif</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
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
