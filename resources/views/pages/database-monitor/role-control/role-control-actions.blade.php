<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public ?int $roleId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $formRole = [
        'name' => '',
        'guard_name' => 'web',
    ];

    public array $selectedPermissions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('roleControl.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'role-control-actions');
        $this->dispatch('focus-role-name');
    }

    #[On('roleControl.openEdit')]
    public function openEdit(?int $roleId = null): void
    {
        if ($roleId === null) {
            $this->dispatch('toast', type: 'error', message: 'ID role tidak valid.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->resetValidation();

        $role = DB::table('roles')->where('id', $roleId)->first();
        if (!$role) {
            $this->dispatch('toast', type: 'error', message: 'Role tidak ditemukan.');
            return;
        }

        $this->roleId = (int) $role->id;
        $this->formRole = [
            'name' => $role->name ?? '',
            'guard_name' => $role->guard_name ?? 'web',
        ];

        $this->selectedPermissions = DB::table('role_has_permissions')
            ->where('role_id', $this->roleId)
            ->pluck('permission_id')
            ->map(fn($v) => (int) $v)
            ->all();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'role-control-actions');
        $this->dispatch('focus-role-name');
    }

    #[On('roleControl.requestDelete')]
    public function deleteFromGrid(int $roleId): void
    {
        try {
            $isUsed = DB::table('model_has_roles')->where('role_id', $roleId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Role masih dipakai user. Lepas role dari user terlebih dahulu.');
                return;
            }

            DB::transaction(function () use ($roleId) {
                DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
                DB::table('roles')->where('id', $roleId)->delete();
            });

            $this->dispatch('toast', type: 'success', message: 'Role berhasil dihapus.');
            $this->dispatch('refresh-after-role-control.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Role tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    #[Computed]
    public function allPermissions()
    {
        return DB::table('permissions')
            ->where('guard_name', $this->formRole['guard_name'] ?? 'web')
            ->orderBy('name')
            ->get();
    }

    public function togglePermission(int $permissionId): void
    {
        $key = array_search($permissionId, $this->selectedPermissions, true);

        if ($key === false) {
            $this->selectedPermissions[] = $permissionId;
        } else {
            unset($this->selectedPermissions[$key]);
            $this->selectedPermissions = array_values($this->selectedPermissions);
        }
    }

    public function selectAllPermissions(): void
    {
        $this->selectedPermissions = $this->allPermissions->pluck('id')->map(fn($v) => (int) $v)->all();
    }

    public function clearAllPermissions(): void
    {
        $this->selectedPermissions = [];
    }

    protected function rules(): array
    {
        $rules = [
            'formRole.name' => 'required|string|max:125',
            'formRole.guard_name' => 'required|string|max:125',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'integer|exists:permissions,id',
        ];

        if ($this->formMode === 'create') {
            $rules['formRole.name'] = 'required|string|max:125|unique:roles,name,NULL,id,guard_name,' . ($this->formRole['guard_name'] ?? 'web');
        } else {
            $rules['formRole.name'] = 'required|string|max:125|unique:roles,name,' . $this->roleId . ',id,guard_name,' . ($this->formRole['guard_name'] ?? 'web');
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah digunakan pada guard yang sama.',
            'max' => ':attribute maksimal :max karakter.',
            'selectedPermissions.*.exists' => 'Salah satu permission tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formRole.name' => 'Nama Role',
            'formRole.guard_name' => 'Guard Name',
            'selectedPermissions' => 'Permissions',
        ];
    }

    public function save(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                if ($this->formMode === 'create') {
                    $role = Role::create([
                        'name' => $this->formRole['name'],
                        'guard_name' => $this->formRole['guard_name'],
                    ]);
                    $this->roleId = $role->id;
                } else {
                    $role = Role::findById($this->roleId, $this->formRole['guard_name']);
                    $role->update([
                        'name' => $this->formRole['name'],
                        'guard_name' => $this->formRole['guard_name'],
                    ]);
                }

                $permissions = Permission::whereIn('id', $this->selectedPermissions)
                    ->where('guard_name', $this->formRole['guard_name'])
                    ->get();

                $role->syncPermissions($permissions);
            });

            $this->dispatch('toast', type: 'success', message: $this->formMode === 'create' ? 'Role berhasil ditambahkan.' : 'Role berhasil diperbarui.');

            $this->closeModal();
            $this->dispatch('refresh-after-role-control.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'role-control-actions');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->formMode = 'create';
        $this->roleId = null;
        $this->formRole = [
            'name' => '',
            'guard_name' => 'web',
        ];
        $this->selectedPermissions = [];
    }
};
?>

<div>
    <x-modal name="role-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $roleId ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}"
                                    class="block w-6 h-6 dark:hidden" alt="RSI Madinah" />
                                <img src="{{ asset('images/Logogram white solid.png') }}"
                                    class="hidden w-6 h-6 dark:block" alt="RSI Madinah" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Role' : 'Tambah Role Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Kelola role & permission yang melekat pada role.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20" x-data
                x-on:focus-role-name.window="$nextTick(() => setTimeout(() => $refs.inputRoleName?.focus(), 150))">
                <div class="max-w-full mx-auto p-1">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                        {{-- KOLOM KIRI: INFO ROLE --}}
                        <x-border-form title="Data Role">
                            <div class="space-y-5">
                                {{-- Nama Role --}}
                                <div>
                                    <x-input-label value="Nama Role *" class="mb-1" />
                                    <x-text-input wire:model.live="formRole.name" x-ref="inputRoleName"
                                        :error="$errors->has('formRole.name')" class="w-full mt-1"
                                        placeholder="Contoh: Admin, Dokter, Perawat"
                                        x-on:keydown.enter.prevent="$refs.inputGuardName?.focus()" />
                                    <x-input-error :messages="$errors->get('formRole.name')" class="mt-1" />
                                    <p class="mt-1 text-[11px] text-muted dark:text-gray-400">
                                        Nama unik per guard — gunakan PascalCase (Admin, Dokter).
                                    </p>
                                </div>

                                {{-- Guard --}}
                                <div>
                                    <x-input-label value="Guard Name *" class="mb-1" />
                                    <x-select-input wire:model.live="formRole.guard_name" x-ref="inputGuardName"
                                        :error="$errors->has('formRole.guard_name')" class="w-full mt-1">
                                        <option value="web">web</option>
                                        <option value="api">api</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('formRole.guard_name')" class="mt-1" />
                                    <p class="mt-1 text-[11px] text-muted dark:text-gray-400">
                                        Biasanya <code class="font-mono">web</code> untuk aplikasi Livewire ini.
                                    </p>
                                </div>

                                @if ($formMode === 'edit' && $roleId)
                                    <div
                                        class="p-3 border border-amber-200 rounded-xl bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/10">
                                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">Role ID
                                        </p>
                                        <p class="text-sm font-mono text-amber-900 dark:text-amber-100">
                                            {{ $roleId }}</p>
                                    </div>
                                @endif
                            </div>
                        </x-border-form>

                        {{-- KOLOM KANAN: PERMISSIONS --}}
                        <x-border-form title="Permissions">
                            <div class="space-y-3">
                                @if ($this->allPermissions->isEmpty())
                                    <div class="p-4 text-center text-sm text-muted dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">
                                        Belum ada permission untuk guard
                                        <code class="font-mono">{{ $formRole['guard_name'] }}</code>.
                                    </div>
                                @else
                                    <div class="flex items-center justify-between pb-2 border-b border-hairline dark:border-gray-700">
                                        <p class="text-xs text-muted dark:text-gray-400">
                                            {{ count($selectedPermissions) }} dari {{ $this->allPermissions->count() }} permission aktif
                                        </p>
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="selectAllPermissions"
                                                class="text-xs font-medium text-brand-green dark:text-brand-lime hover:underline">
                                                Aktifkan semua
                                            </button>
                                            <span class="text-xs text-gray-300">|</span>
                                            <button type="button" wire:click="clearAllPermissions"
                                                class="text-xs font-medium text-muted hover:underline">
                                                Nonaktifkan semua
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-2 max-h-[50vh] overflow-y-auto pr-1">
                                        @foreach ($this->allPermissions as $perm)
                                            @php $isActive = in_array((int) $perm->id, $selectedPermissions, true); @endphp
                                            <div wire:key="perm-row-{{ $perm->id }}"
                                                wire:click="togglePermission({{ (int) $perm->id }})"
                                                class="flex items-center justify-between gap-3 p-3 rounded-lg border cursor-pointer transition
                                                    {{ $isActive
                                                        ? 'border-brand-green/50 bg-brand-green/5 dark:border-brand-lime/50 dark:bg-brand-lime/5'
                                                        : 'border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/60' }}">

                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-ink dark:text-gray-200 truncate">
                                                        {{ $perm->name }}
                                                    </div>
                                                    <div class="text-[11px] text-muted-soft font-mono">
                                                        id: {{ $perm->id }} · guard: {{ $perm->guard_name }}
                                                    </div>
                                                </div>

                                                {{-- Toggle visual (tidak bisa diklik sendiri — klik baris yg trigger) --}}
                                                <div aria-hidden="true"
                                                    class="relative h-6 transition rounded-full w-11 shrink-0
                                                        {{ $isActive ? 'bg-brand-green dark:bg-brand-lime' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                    <div
                                                        class="absolute top-1 w-4 h-4 transition-all bg-canvas rounded-full shadow
                                                            {{ $isActive ? 'left-6' : 'left-1' }}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <x-input-error :messages="$errors->get('selectedPermissions')" class="mt-1" />
                                <x-input-error :messages="$errors->get('selectedPermissions.*')" class="mt-1" />
                            </div>
                        </x-border-form>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <span class="hidden sm:inline">Tekan </span>
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-surface-soft border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled"
                            wire:target="save">
                            <span wire:loading.remove wire:target="save">{{ $formMode === 'edit' ? 'Perbarui' : 'Simpan' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
