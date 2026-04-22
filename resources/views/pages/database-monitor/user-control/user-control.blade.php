<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterRole = '';
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }
    public function updatedFilterRole(): void
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

    public function openKasManage(int $userId): void
    {
        $this->dispatch('kasUserControl.openManage', userId: $userId);
    }

    public function requestDelete(int $userId): void
    {
        $this->dispatch('userControl.requestDelete', userId: $userId);
    }

    #[On('refresh-after-user-control.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $query = DB::table('users as u')->select('u.id', 'u.myuser_code', 'u.myuser_name', 'u.email', 'u.myuser_sip', 'u.myuser_ttd_image', 'u.emp_id', DB::raw("TO_CHAR(u.created_at, 'dd/mm/yyyy HH24:MI:SS') as created_at"))->orderBy('u.myuser_name', 'asc');

        if ($this->filterRole !== '') {
            $query->whereIn('u.id', function ($sub) {
                $sub->select('model_id')->from('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', $this->filterRole);
            });
        }

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);
            $query->where(function ($q) use ($upper, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $q->orWhere('u.id', $searchKeyword);
                }
                $q->orWhereRaw('UPPER(u.myuser_name) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(u.email) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(u.myuser_code) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $query;
    }

    #[Computed]
    public function allRoles(): array
    {
        return DB::table('roles')->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
    }

    #[Computed]
    public function rows()
    {
        $users = $this->baseQuery()->paginate($this->itemsPerPage);

        foreach ($users as $user) {
            $user->role_list = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('model_has_roles.model_id', $user->id)->pluck('roles.name')->all();

            $user->kas_count = DB::table('user_kas')->where('user_id', $user->id)->count();
        }

        return $users;
    }

    public function toggleRole(int $userId, string $roleName): void
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }

        if ($user->hasRole($roleName)) {
            $user->removeRole($roleName);
            $this->dispatch('toast', type: 'info', message: "Role {$roleName} dicabut.");
        } else {
            $user->assignRole($roleName);
            $this->dispatch('toast', type: 'success', message: "Role {$roleName} diberikan.");
        }

        $this->dispatch('refresh-after-user-control.saved');
    }

    protected function rolePalette(string $role): string
    {
        return match ($role) {
            'Tu' => 'gray',
            'Dokter' => 'emerald',
            'Apoteker' => 'amber',
            'Admin' => 'red',
            'Perawat' => 'blue',
            'Mr' => 'violet',
            'Gizi' => 'teal',
            'Casemix' => 'pink',
            default => $this->hashPalette($role),
        };
    }

    protected function hashPalette(string $role): string
    {
        $palettes = ['orange', 'yellow', 'lime', 'green', 'cyan', 'sky', 'indigo', 'purple', 'fuchsia', 'rose'];
        return $palettes[abs(crc32($role)) % count($palettes)];
    }

    public function roleBadgeClass(string $role): string
    {
        $base = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium';
        $color = match ($this->rolePalette($role)) {
            'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
            'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200',
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200',
            'violet' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-200',
            'teal' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-200',
            'pink' => 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-200',
            'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200',
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200',
            'lime' => 'bg-lime-100 text-lime-800 dark:bg-lime-900/30 dark:text-lime-200',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
            'cyan' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-200',
            'sky' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-200',
            'indigo' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200',
            'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200',
            'fuchsia' => 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/30 dark:text-fuchsia-200',
            'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200',
            default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        };
        return "{$base} {$color}";
    }

    public function roleDropdownActiveClass(string $role): string
    {
        return match ($this->rolePalette($role)) {
            'gray' => 'text-gray-700 dark:text-gray-300',
            'emerald' => 'text-emerald-700 dark:text-emerald-400',
            'amber' => 'text-amber-700 dark:text-amber-400',
            'red' => 'text-red-700 dark:text-red-400',
            'blue' => 'text-blue-700 dark:text-blue-400',
            'violet' => 'text-violet-700 dark:text-violet-400',
            'teal' => 'text-teal-700 dark:text-teal-400',
            'pink' => 'text-pink-700 dark:text-pink-400',
            'orange' => 'text-orange-700 dark:text-orange-400',
            'yellow' => 'text-yellow-700 dark:text-yellow-400',
            'lime' => 'text-lime-700 dark:text-lime-400',
            'green' => 'text-green-700 dark:text-green-400',
            'cyan' => 'text-cyan-700 dark:text-cyan-400',
            'sky' => 'text-sky-700 dark:text-sky-400',
            'indigo' => 'text-indigo-700 dark:text-indigo-400',
            'purple' => 'text-purple-700 dark:text-purple-400',
            'fuchsia' => 'text-fuchsia-700 dark:text-fuchsia-400',
            'rose' => 'text-rose-700 dark:text-rose-400',
            default => 'text-gray-700 dark:text-gray-300',
        };
    }

    public function roleFilterDotClass(string $role): string
    {
        return match ($this->rolePalette($role)) {
            'gray' => 'bg-gray-400',
            'emerald' => 'bg-emerald-500',
            'amber' => 'bg-amber-500',
            'red' => 'bg-red-500',
            'blue' => 'bg-blue-500',
            'violet' => 'bg-violet-500',
            'teal' => 'bg-teal-500',
            'pink' => 'bg-pink-500',
            'orange' => 'bg-orange-500',
            'yellow' => 'bg-yellow-500',
            'lime' => 'bg-lime-500',
            'green' => 'bg-green-500',
            'cyan' => 'bg-cyan-500',
            'sky' => 'bg-sky-500',
            'indigo' => 'bg-indigo-500',
            'purple' => 'bg-purple-500',
            'fuchsia' => 'bg-fuchsia-500',
            'rose' => 'bg-rose-500',
            default => 'bg-gray-400',
        };
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">User Control</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Kelola user, hak akses, & akun kas</p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- Kiri: search + filter role --}}
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-full lg:w-72">
                            <x-input-label for="searchKeyword" value="Cari User" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword" placeholder="Cari kode / nama / email..."
                                class="block w-full" />
                        </div>

                        {{-- Filter role pill --}}
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" wire:click="$set('filterRole', '')"
                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium transition border
                                    {{ $filterRole === ''
                                        ? 'bg-gray-800 text-white border-gray-800 dark:bg-gray-200 dark:text-gray-900 dark:border-gray-200'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400 dark:bg-gray-900 dark:text-gray-400 dark:border-gray-700 dark:hover:border-gray-500' }}">
                                Semua
                            </button>
                            @foreach ($this->allRoles as $role)
                                <button type="button" wire:click="$set('filterRole', '{{ $role }}')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition border
                                        {{ $filterRole === $role
                                            ? $this->roleBadgeClass($role) . ' border-transparent ring-2 ring-offset-1 ring-current'
                                            : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400 dark:bg-gray-900 dark:text-gray-400 dark:border-gray-700 dark:hover:border-gray-500' }}">
                                    <span
                                        class="w-1.5 h-1.5 rounded-full shrink-0 {{ $this->roleFilterDotClass($role) }}"></span>
                                    {{ $role }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Kanan: per halaman + tambah --}}
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
                        {{-- ✅ Tambah: x-primary-button --}}
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah User
                        </x-primary-button>
                    </div>
                </div>

                {{-- Indikator filter aktif --}}
                @if ($filterRole !== '')
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Menampilkan role:</span>
                        <span class="{{ $this->roleBadgeClass($filterRole) }}">{{ $filterRole }}</span>
                        <button type="button" wire:click="$set('filterRole', '')"
                            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                            ✕ hapus filter
                        </button>
                    </div>
                @endif
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">Nama & Kode</th>
                                <th class="px-4 py-3 font-semibold">Email</th>
                                <th class="px-4 py-3 font-semibold">EMP ID</th>
                                <th class="px-4 py-3 font-semibold">TTD</th>
                                <th class="px-4 py-3 font-semibold">Role</th>
                                <th class="px-4 py-3 font-semibold">Dibuat</th>
                                <th class="px-4 py-3 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                @php
                                    $allRoles = $this->allRoles;
                                    $userRoles = $row->role_list ?? [];
                                @endphp
                                <tr wire:key="user-row-{{ $row->id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $row->myuser_name ?? '-' }}</div>
                                        <div class="text-xs font-mono text-gray-500">{{ $row->myuser_code ?? '-' }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div>{{ $row->email ?? '-' }}</div>
                                        @if ($row->myuser_sip)
                                            <div class="text-xs text-gray-500">SIP: {{ $row->myuser_sip }}</div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($row->emp_id)
                                            <x-badge variant="alternative">{{ $row->emp_id }}</x-badge>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($row->myuser_ttd_image)
                                            <img src="{{ asset('storage/' . $row->myuser_ttd_image) }}"
                                                class="h-8 w-auto rounded border border-gray-200 dark:border-gray-600"
                                                alt="TTD">
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>

                                    {{-- Role — badge berwarna + dropdown --}}
                                    <td class="px-4 py-3">
                                        <div class="relative" x-data="{ open: false }">
                                            <button type="button" @click="open = !open" @click.outside="open = false"
                                                class="flex items-center gap-1.5 group">
                                                <div class="flex flex-wrap gap-1">
                                                    @if (count($userRoles) > 0)
                                                        @foreach ($userRoles as $r)
                                                            <span
                                                                class="{{ $this->roleBadgeClass($r) }}">{{ $r }}</span>
                                                        @endforeach
                                                    @else
                                                        <span class="text-xs italic text-gray-400">Belum ada</span>
                                                    @endif
                                                </div>
                                                <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-brand-green dark:group-hover:text-brand-lime shrink-0 transition"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <div x-show="open" x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95"
                                                class="absolute left-0 z-50 mt-1 w-48 bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-gray-900 dark:border-gray-700"
                                                style="display: none;">
                                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                                                    <p
                                                        class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                                        Kelola Role</p>
                                                </div>
                                                <ul class="py-1">
                                                    @foreach ($allRoles as $role)
                                                        @php $active = in_array($role, $userRoles); @endphp
                                                        <li>
                                                            {{-- ✅ Toggle role: raw <button> (inline list item) --}}
                                                            <button type="button"
                                                                wire:click="toggleRole({{ $row->id }}, '{{ $role }}')"
                                                                @click="open = false"
                                                                class="flex items-center w-full gap-2 px-3 py-1.5 text-sm transition
                                                                    hover:bg-gray-50 dark:hover:bg-gray-800
                                                                    {{ $active ? 'font-semibold ' . $this->roleDropdownActiveClass($role) : 'text-gray-500 dark:text-gray-400' }}">
                                                                @if ($active)
                                                                    <span
                                                                        class="{{ $this->roleBadgeClass($role) }} !px-1.5 !py-0 text-[10px]">✓</span>
                                                                @else
                                                                    <span
                                                                        class="w-5 h-5 flex items-center justify-center">
                                                                        <span
                                                                            class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                                                    </span>
                                                                @endif
                                                                {{ $role }}
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $row->created_at ?? '-' }}</td>

                                    {{-- Aksi — ikut pola master-poli --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            {{-- ✅ Edit: x-outline-button (sama seperti master-poli) --}}
                                            <x-outline-button type="button"
                                                wire:click="openEdit({{ $row->id }})">
                                                Edit User
                                            </x-outline-button>

                                            {{-- ✅ Kelola Kas: x-secondary-button (aksi sekunder/manajemen) --}}
                                            <x-secondary-button type="button"
                                                wire:click="openKasManage({{ $row->id }})">
                                                Kelola Kas
                                                @if ($row->kas_count > 0)
                                                    <span
                                                        class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-green text-white dark:bg-brand-lime dark:text-gray-900">
                                                        {{ $row->kas_count }}
                                                    </span>
                                                @endif
                                            </x-secondary-button>

                                            {{-- ✅ Hapus: x-confirm-button variant="danger" (sama seperti master-poli) --}}
                                            <x-confirm-button variant="danger" :action="'requestDelete(' . $row->id . ')'" title="Hapus User"
                                                message="Yakin hapus user {{ $row->myuser_name }}? Semua data terkait termasuk akses kas akan dihapus."
                                                confirmText="Ya, hapus" cancelText="Batal">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7"
                                        class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        @if ($filterRole !== '')
                                            Tidak ada user dengan role
                                            <span
                                                class="{{ $this->roleBadgeClass($filterRole) }}">{{ $filterRole }}</span>.
                                        @else
                                            Data user belum ada.
                                        @endif
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

            {{-- Modals --}}
            <livewire:pages::database-monitor.user-control.user-control-actions wire:key="user-control-actions" />

            <livewire:pages::database-monitor.user-control.kas-user-control.kas-user-control-actions
                wire:key="kas-user-control-actions" />

        </div>
    </div>
</div>
