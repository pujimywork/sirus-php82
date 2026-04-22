<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterGuard = '';
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFilterGuard(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('roleControl.openCreate');
    }

    public function openEdit(int $roleId): void
    {
        $this->dispatch('roleControl.openEdit', roleId: $roleId);
    }

    public function requestDelete(int $roleId): void
    {
        $this->dispatch('roleControl.requestDelete', roleId: $roleId);
    }

    #[On('refresh-after-role-control.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $query = DB::table('roles as r')->select('r.id', 'r.name', 'r.guard_name', DB::raw("TO_CHAR(r.created_at, 'dd/mm/yyyy HH24:MI:SS') as created_at"), DB::raw("TO_CHAR(r.updated_at, 'dd/mm/yyyy HH24:MI:SS') as updated_at"))->orderBy('r.name', 'asc');

        if ($this->filterGuard !== '') {
            $query->where('r.guard_name', $this->filterGuard);
        }

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);
            $query->where(function ($q) use ($upper, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $q->orWhere('r.id', $searchKeyword);
                }
                $q->orWhereRaw('UPPER(r.name) LIKE ?', ["%{$upper}%"])->orWhereRaw('UPPER(r.guard_name) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $query;
    }

    #[Computed]
    public function rows()
    {
        $roles = $this->baseQuery()->paginate($this->itemsPerPage);

        foreach ($roles as $role) {
            $role->permission_list = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')->where('role_has_permissions.role_id', $role->id)->pluck('permissions.name')->all();

            $role->user_count = DB::table('model_has_roles')->where('role_id', $role->id)->where('model_type', \App\Models\User::class)->count();
        }

        return $roles;
    }

    #[Computed]
    public function guardList(): array
    {
        return DB::table('roles')->select('guard_name')->distinct()->orderBy('guard_name')->pluck('guard_name')->all();
    }

    public function roleBadgeClass(string $role): string
    {
        $base = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium';
        $color = match ($role) {
            'Tu' => 'bg-gray-100    text-gray-700    dark:bg-gray-800      dark:text-gray-200',
            'Dokter' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200',
            'Apoteker' => 'bg-amber-100   text-amber-800   dark:bg-amber-900/30  dark:text-amber-200',
            'Admin' => 'bg-red-100     text-red-800     dark:bg-red-900/30    dark:text-red-200',
            'Perawat' => 'bg-blue-100    text-blue-800    dark:bg-blue-900/30   dark:text-blue-200',
            'Mr' => 'bg-violet-100  text-violet-800  dark:bg-violet-900/30 dark:text-violet-200',
            'Gizi' => 'bg-teal-100    text-teal-800    dark:bg-teal-900/30   dark:text-teal-200',
            'Casemix' => 'bg-pink-100    text-pink-800    dark:bg-pink-900/30   dark:text-pink-200',
            default => 'bg-slate-100   text-slate-800   dark:bg-slate-800     dark:text-slate-200',
        };
        return "{$base} {$color}";
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">Role Control</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Kelola role & permission sistem</p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-full lg:w-72">
                            <x-input-label for="searchKeyword" value="Cari Role" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword" placeholder="Cari nama / guard..."
                                class="block w-full" />
                        </div>

                        {{-- Filter guard --}}
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" wire:click="$set('filterGuard', '')"
                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium transition border
                                    {{ $filterGuard === ''
                                        ? 'bg-gray-800 text-white border-gray-800 dark:bg-gray-200 dark:text-gray-900 dark:border-gray-200'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400 dark:bg-gray-900 dark:text-gray-400 dark:border-gray-700 dark:hover:border-gray-500' }}">
                                Semua Guard
                            </button>
                            @foreach ($this->guardList as $g)
                                <button type="button" wire:click="$set('filterGuard', '{{ $g }}')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition border
                                        {{ $filterGuard === $g
                                            ? 'bg-brand-green text-white border-brand-green dark:bg-brand-lime dark:text-gray-900 dark:border-brand-lime'
                                            : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400 dark:bg-gray-900 dark:text-gray-400 dark:border-gray-700 dark:hover:border-gray-500' }}">
                                    {{ $g }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 shrink-0">
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
                            + Tambah Role
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
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">Nama Role</th>
                                <th class="px-4 py-3 font-semibold">Guard</th>
                                <th class="px-4 py-3 font-semibold">Permissions</th>
                                <th class="px-4 py-3 font-semibold">Jml User</th>
                                <th class="px-4 py-3 font-semibold">Dibuat</th>
                                <th class="px-4 py-3 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="role-row-{{ $row->id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ $row->id }}</td>

                                    <td class="px-4 py-3">
                                        <span class="{{ $this->roleBadgeClass($row->name) }}">{{ $row->name }}</span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <x-badge variant="alternative">{{ $row->guard_name }}</x-badge>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($row->permission_list as $p)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                    {{ $p }}
                                                </span>
                                            @empty
                                                <span class="text-xs italic text-gray-400">Tanpa permission</span>
                                            @endforelse
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($row->user_count > 0)
                                            <x-badge variant="success">{{ $row->user_count }} user</x-badge>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $row->created_at ?? '-' }}</td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit({{ $row->id }})" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(' . $row->id . ')'" title="Hapus Role"
                                                message="Yakin hapus role {{ $row->name }}? Role yang masih dipakai user tidak bisa dihapus."
                                                confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data role belum ada.
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

            <livewire:pages::database-monitor.role-control.role-control-actions wire:key="role-control-actions" />

        </div>
    </div>
</div>
