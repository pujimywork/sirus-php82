<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode      = 'create';
    public string $originalCatatan = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'catatan'       => '',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.signa-catatan.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode        = 'create';
        $this->originalCatatan = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-signa-catatan-actions');
    }

    #[On('master.signa-catatan.openEdit')]
    public function openEdit(string $catatan): void
    {
        $row = DB::table('rsmst_signa_catatans')
            ->where('catatan', $catatan)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data catatan tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode        = 'edit';
        $this->originalCatatan = (string) $row->catatan;
        $this->form = [
            'catatan'       => (string) $row->catatan,
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-signa-catatan-actions');
    }

    #[On('master.signa-catatan.requestDelete')]
    public function deleteCatatan(string $catatan): void
    {
        $deleted = DB::table('rsmst_signa_catatans')->where('catatan', $catatan)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data catatan tidak ditemukan.');
            return;
        }
        $this->dispatch('toast', type: 'success', message: 'Catatan berhasil dihapus.');
        $this->dispatch('master.signa-catatan.saved');
    }

    public function save(): void
    {
        $rules = [
            'form.catatan' => [
                'required', 'string', 'max:255',
                $this->formMode === 'create'
                    ? Rule::unique('rsmst_signa_catatans', 'catatan')
                    : Rule::unique('rsmst_signa_catatans', 'catatan')->ignore($this->originalCatatan, 'catatan'),
            ],
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.catatan.required' => 'Teks catatan wajib diisi.',
            'form.catatan.max'      => 'Teks catatan maksimal 255 karakter.',
            'form.catatan.unique'   => 'Catatan ini sudah terdaftar.',
            'form.active_status.in' => 'Status tidak valid.',
        ];

        $attributes = [
            'form.catatan'       => 'Catatan',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $catatan       = trim($this->form['catatan']);
        $activeStatus  = $this->form['active_status'] === '0' ? '0' : '1';

        if ($this->formMode === 'create') {
            DB::table('rsmst_signa_catatans')->insert([
                'catatan'       => $catatan,
                'active_status' => $activeStatus,
            ]);
        } else {
            DB::table('rsmst_signa_catatans')
                ->where('catatan', $this->originalCatatan)
                ->update([
                    'catatan'       => $catatan,
                    'active_status' => $activeStatus,
                ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Catatan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.signa-catatan.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-signa-catatan-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'catatan'       => '',
            'active_status' => '1',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-signa-catatan-actions" size="md" focusable>
        <x-dirty-modal-content
            name="master-signa-catatan-actions"
            event="master.signa-catatan.saved"
            label="Catatan Khusus Signa"
            :wireKey="$this->renderKey('modal', [$formMode, $originalCatatan])">

            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Catatan Khusus Signa' : 'Tambah Catatan Khusus Signa' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            LOV catatan khusus signa untuk e-resep (RJ/UGD/RI).
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

            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20">
                <x-border-form title="Data Catatan">
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="form.catatan" value="Catatan" />
                            <x-text-input id="form.catatan" type="text"
                                wire:model.live="form.catatan"
                                maxlength="255"
                                placeholder="cth: Habiskan, Untuk obat luar, Sesudah makan..."
                                class="w-full mt-1" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Maks 255 karakter. Teks ini yang akan muncul di dropdown e-resep.
                            </p>
                            <x-input-error :messages="$errors->get('form.catatan')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Status" />
                            <div class="mt-2">
                                <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0">
                                    {{ $form['active_status'] === '1' ? 'Aktif' : 'Nonaktif' }}
                                </x-toggle>
                            </div>
                            <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
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
