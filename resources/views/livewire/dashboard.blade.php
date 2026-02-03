<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public string $search = '';

    // ✅ Semua role user (lowercase) dipakai untuk filtering menu
    #[Computed]
    public function userRoles(): array
    {
        return auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->values()->toArray();
    }

    // ✅ Definisi menu (rapi & konsisten)
    #[Computed]
    public function masterMenus(): array
    {
        return [
            [
                'title' => 'Master Poli',
                'desc' => 'Kelola data poli & ruangan',
                'href' => route('master.poli'),
                'roles' => ['admin'], // ✅ wajib lowercase
                'badge' => 'Master',
            ],
            // tambahkan menu lain di sini...
        ];
    }

    // ✅ Menu yang boleh tampil (role + search)
    #[Computed]
    public function visibleMenus(): array
    {
        $queryMenu = trim(mb_strtolower($this->search));
        $userRoles = $this->userRoles();

        return array_values(
            array_filter($this->masterMenus(), function ($m) use ($queryMenu, $userRoles) {
                $allowed = array_map('strtolower', $m['roles'] ?? []);

                // role check (boleh lihat kalau ada irisan)
                $roleOk = count(array_intersect($allowed, $userRoles)) > 0;
                if (!$roleOk) {
                    return false;
                }

                // search check
                if ($queryMenu === '') {
                    return true;
                }

                $hay = mb_strtolower(($m['title'] ?? '') . ' ' . ($m['desc'] ?? ''));
                return str_contains($hay, $queryMenu);
            }),
        );
    }
};
?>

<div>
    {{-- HEADER (harus di sini, jangan di dalam div) --}}
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Dashboard
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Pusat menu aplikasi —
                <span class="font-medium">
                    Role Aktif : {{ auth()->user()->getRoleNames()->implode(', ') }}
                </span>
            </p>
        </div>
    </header>

    {{-- BODY WRAPPER: SAMA kayak Master Poli --}}
    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: mirip sticky toolbar poli (optional) --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label value="Cari Menu" class="sr-only" />
                        <x-text-input wire:model.live.debounce.250ms="search" placeholder="Cari menu..."
                            class="block w-full" />
                    </div>

                    {{-- (optional) right side action kalau mau nanti --}}
                    <div class="hidden lg:block"></div>
                </div>
            </div>

            {{-- GRID MENU --}}
            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                @forelse($this->visibleMenus as $m)
                    <a href="{{ $m['href'] }}" wire:navigate
                        class="flex items-center justify-between gap-4 p-4 transition-colors duration-200 bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">

                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900 truncate dark:text-gray-100">
                                    {{ $m['title'] }}
                                </h3>

                                @if (!empty($m['badge']))
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                                 bg-emerald-50 text-emerald-700
                                                 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {{ $m['badge'] }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                {{ $m['desc'] }}
                            </p>
                        </div>

                        <span
                            class="pointer-events-none shrink-0 transition-transform duration-200 group-hover:translate-x-0.5">
                            <x-outline-button type="button">Buka</x-outline-button>
                        </span>
                    </a>
                @empty
                    <div class="py-10 text-center text-gray-500 col-span-full dark:text-gray-400">
                        Menu tidak ditemukan / tidak ada akses.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</div>
