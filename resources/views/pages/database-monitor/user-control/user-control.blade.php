<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('userControl.openCreate');
    }

    public function openEdit(int $userId): void
    {
        $this->dispatch('userControl.openEdit', userId: $userId);
    }

    public function requestDelete(int $userId): void
    {
        $this->dispatch('userControl.requestDelete', userId: $userId);
    }

    #[On('refresh-after-user-control.saved')]
    public function refreshAfterSaved(): void
    {
        $this->dispatch('$refresh');
    }

    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);
        $queryBuilder = DB::table('users')->select('id', 'myuser_code', 'myuser_name', 'email', 'user_code', 'myuser_sip', 'myuser_ttd_image', DB::raw("TO_CHAR(created_at, 'dd/mm/yyyy HH24:MI:SS') as created_at"))->orderBy('myuser_name', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);
            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('id', $searchKeyword);
                }
                $subQuery
                    ->orWhereRaw('UPPER(myuser_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(email) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(myuser_code) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        // Ambil data user dengan pagination (tanpa role names)
        $users = $this->baseQuery()->paginate($this->itemsPerPage);

        // Tambahkan role names per user (N+1 query, aman untuk Oracle)
        foreach ($users as $user) {
            $roles = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('model_has_roles.model_id', $user->id)->pluck('roles.name')->implode(', ');
            $user->role_names = $roles;
        }

        return $users;
    }

    public function assignRole(int $userId, string $roleName): void
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }
        if ($user->hasRole($roleName)) {
            $this->dispatch('toast', type: 'warning', message: "User sudah memiliki role {$roleName}.");
            return;
        }
        $user->assignRole($roleName);
        $this->dispatch('toast', type: 'success', message: "Role {$roleName} diberikan ke {$user->name}.");
        $this->dispatch('refresh-after-user-control.saved');
    }

    public function removeRole(int $userId, string $roleName): void
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }
        if (!$user->hasRole($roleName)) {
            $this->dispatch('toast', type: 'warning', message: "User tidak memiliki role {$roleName}.");
            return;
        }
        $user->removeRole($roleName);
        $this->dispatch('toast', type: 'success', message: "Role {$roleName} dicabut dari {$user->name}.");
        $this->dispatch('refresh-after-user-control.saved');
    }

    public function deleteUser(int $userId): void
    {
        $this->dispatch('userControl.requestDelete', userId: $userId);
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                User Control
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola user & hak akses sistem
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari User" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari kode / nama / email..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah User
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">Nama & Kode</th>
                                <th class="px-4 py-3 font-semibold">Email / Info User</th>
                                <th class="px-4 py-3 font-semibold">TTD</th>
                                <th class="px-4 py-3 font-semibold">Role & Manajemen</th>
                                <th class="px-4 py-3 font-semibold">Dibuat</th>
                                <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="user-row-{{ $row->id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <!-- Kolom Nama + Kode -->
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $row->myuser_name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $row->myuser_code ?? '-' }}
                                        </div>
                                    </td>

                                    <!-- Kolom Email + User Code + SIP -->
                                    <td class="px-4 py-3">
                                        <div>{{ $row->email ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">
                                            User Code: {{ $row->user_code ?? '-' }} | SIP: {{ $row->myuser_sip ?? '-' }}
                                        </div>
                                    </td>

                                    <!-- Kolom TTD -->
                                    <td class="px-4 py-3">
                                        @if ($row->myuser_ttd_image)
                                            <img src="{{ asset('storage/' . $row->myuser_ttd_image) }}"
                                                class="h-8 w-auto rounded border border-gray-200 dark:border-gray-600"
                                                alt="TTD">
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>

                                    <!-- Kolom Role + Tombol manajemen role -->
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            @php $roles = explode(', ', $row->role_names ?? ''); @endphp
                                            @foreach ($roles as $role)
                                                <x-badge variant="info" class="text-xs">{{ $role }}</x-badge>
                                            @endforeach
                                            @if (empty($roles[0]))
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            @php
                                                $allRoles = [
                                                    'Tu',
                                                    'Perawat',
                                                    'Dokter',
                                                    'Mr',
                                                    'Apoteker',
                                                    'Gizi',
                                                    'Casmix',
                                                    'Admin',
                                                ];
                                                $userRoles = explode(', ', $row->role_names ?? '');
                                            @endphp
                                            @foreach ($allRoles as $role)
                                                @if (in_array($role, $userRoles))
                                                    <x-secondary-button type="button"
                                                        wire:click="removeRole({{ $row->id }}, '{{ $role }}')"
                                                        class="text-xs py-0 px-1">-
                                                        {{ $role }}</x-secondary-button>
                                                @else
                                                    <x-primary-button type="button"
                                                        wire:click="assignRole({{ $row->id }}, '{{ $role }}')"
                                                        class="text-xs py-0 px-1">+
                                                        {{ $role }}</x-primary-button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>

                                    <!-- Kolom Dibuat -->
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $row->created_at ?? '-' }}</td>

                                    <!-- Kolom Aksi -->
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-col gap-1">
                                            <x-outline-button type="button"
                                                wire:click="openEdit('{{ $row->id }}')"
                                                class="px-2 py-1 text-xs">Edit</x-outline-button>
                                            {{-- <x-confirm-button variant="danger" :action="'deleteUser(' . $row->id . ')'" title="Hapus User"
                                                message="Yakin hapus user {{ $row->myuser_name }}?"
                                                confirmText="Ya, hapus" cancelText="Batal">
                                                Hapus
                                            </x-confirm-button> --}}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data user belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Actions modal --}}
            <livewire:pages::database-monitor.user-control.user-control-actions wire:key="user-control-actions" />
        </div>
    </div>
</div>
