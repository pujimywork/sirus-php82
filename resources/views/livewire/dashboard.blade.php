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

    #[Computed]
    public function masterMenus(): array
    {
        return [
            // ── Master ─────────────────────────────────────────────────
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 1, 'title' => 'Master Poli', 'desc' => 'Kelola data poli & ruangan', 'href' => route('master.poli'), 'roles' => ['admin'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 2, 'title' => 'Master Dokter', 'desc' => 'Kelola data dokter & spesialis', 'href' => route('master.dokter'), 'roles' => ['admin'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 3, 'title' => 'Master Obat', 'desc' => 'Kelola data obat & farmasi', 'href' => route('master.obat'), 'roles' => ['admin'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 4, 'title' => 'Master Diagnosa', 'desc' => 'Kelola data diagnosa ICD-10', 'href' => route('master.diagnosa'), 'roles' => ['admin'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 5, 'title' => 'Master Lain-lain', 'desc' => 'Kelola data lain-lain', 'href' => route('master.others'), 'roles' => ['admin'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 6, 'title' => 'Master Radiologi', 'desc' => 'Kelola data radiologi', 'href' => route('master.radiologis'), 'roles' => ['admin'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 7, 'title' => 'Master Pasien', 'desc' => 'Kelola data pasien & rekam medis', 'href' => route('master.pasien'), 'roles' => ['admin', 'mr'], 'badge' => 'Master'],
            ['group' => 'Master', 'groupOrder' => 1, 'order' => 8, 'title' => 'Setup Jadwal BPJS', 'desc' => 'Sinkronisasi jadwal pelayanan dokter dari BPJS ke RS', 'href' => route('master.setup-jadwal-bpjs'), 'roles' => ['admin', 'mr'], 'badge' => 'BPJS'],

            // ── Rawat Jalan ─────────────────────────────────────────────
            ['group' => 'Rawat Jalan', 'groupOrder' => 2, 'order' => 1, 'title' => 'Daftar Rawat Jalan', 'desc' => 'Pendaftaran & manajemen pasien rawat jalan', 'href' => route('rawat-jalan.daftar'), 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'RJ'],
            ['group' => 'Rawat Jalan', 'groupOrder' => 2, 'order' => 2, 'title' => 'Booking RJ', 'desc' => 'Daftar pasien booking rawat jalan via Mobile JKN', 'href' => route('rawat-jalan.booking'), 'roles' => ['admin', 'mr'], 'badge' => 'BKG'],
            ['group' => 'Rawat Jalan', 'groupOrder' => 2, 'order' => 3, 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian rawat jalan', 'href' => route('transaksi.rj.antrian-apotek-rj'), 'roles' => ['admin', 'apotek'], 'badge' => 'APT'],

            // ── UGD ─────────────────────────────────────────────────────
            ['group' => 'UGD', 'groupOrder' => 3, 'order' => 1, 'title' => 'Daftar UGD', 'desc' => 'Pendaftaran & manajemen pasien UGD', 'href' => route('ugd.daftar'), 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'UGD'],
            ['group' => 'UGD', 'groupOrder' => 3, 'order' => 2, 'title' => 'Antrian Apotek UGD', 'desc' => 'Telaah resep & pelayanan kefarmasian UGD', 'href' => route('transaksi.ugd.antrian-apotek-ugd'), 'roles' => ['admin', 'apotek'], 'badge' => 'APT'],

            // ── RI ─────────────────────────────────────────────────────
            ['group' => 'RI', 'groupOrder' => 2, 'order' => 1, 'title' => 'Daftar RI', 'desc' => 'Pendaftaran & manajemen pasien Rawat Inap', 'href' => route('ri.daftar'), 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'RI'],
            //['group' => 'RI', 'groupOrder' => 2, 'order' => 2, 'title' => 'Antrian Apotek RI', 'desc' => 'Telaah resep & pelayanan kefarmasian RI', 'href' => route('transaksi.ri.antrian-apotek-ri'), 'roles' => ['admin', 'apotek'], 'badge' => 'APT'],

            // ── Database & Infrastruktur ─────────────────────────────────
            ['group' => 'Sistem', 'groupOrder' => 4, 'order' => 1, 'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session', 'href' => route('database-monitor.monitoring-dashboard'), 'roles' => ['admin'], 'badge' => 'DB'],
            ['group' => 'Sistem', 'groupOrder' => 4, 'order' => 2, 'title' => 'Mounting Control', 'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'href' => route('database-monitor.monitoring-mount-control'), 'roles' => ['admin'], 'badge' => 'MNT'],
            ['group' => 'Sistem', 'groupOrder' => 4, 'order' => 3, 'title' => 'User Control', 'desc' => 'Kelola user & hak akses sistem', 'href' => route('database-monitor.user-control'), 'roles' => ['admin'], 'badge' => 'USR'],
        ];
    }

    #[Computed]
    public function visibleMenus(): array
    {
        $userRoles = auth()->user()->getRoleNames()->map(fn($r) => strtolower($r))->toArray();

        return collect($this->masterMenus)->filter(fn($m) => !empty(array_intersect($m['roles'], $userRoles)))->values()->toArray();
    }

    #[Computed]
    public function groupedMenus()
    {
        return collect($this->visibleMenus)
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
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

            {{-- GRID MENU — Accordion --}}
            <div x-data="{ activeGroup: null }">

                @forelse ($this->groupedMenus as $groupName => $menus)
                    <div x-data="{ group: '{{ $groupName }}' }">

                        {{-- GROUP HEADER --}}
                        <button type="button" @click="activeGroup = (activeGroup === group) ? null : group"
                            class="flex items-center gap-3 w-full mt-6 mb-3 group/header">
                            <h2 class="text-xs font-bold tracking-wider uppercase whitespace-nowrap transition-colors
                    text-gray-400 dark:text-gray-500
                    group-hover/header:text-gray-600 dark:group-hover/header:text-gray-300"
                                :class="activeGroup === group ? 'text-gray-700 dark:text-gray-200' : ''">
                                {{ $groupName }}
                            </h2>
                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                                :class="activeGroup === group ? 'rotate-0' : '-rotate-90'" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- GRID --}}
                        <div x-show="activeGroup === group" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">

                            @foreach ($menus as $m)
                                <a href="{{ $m['href'] }}" wire:navigate
                                    class="flex flex-col gap-3 p-4 transition-colors duration-200 bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                                    <div class="grid grid-cols-4 gap-2">
                                        @if (!empty($m['badge']))
                                            <span
                                                class="col-span-1 self-start inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                    bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                {{ $m['badge'] }}
                                            </span>
                                        @endif
                                        <div class="flex-1 min-w-0 col-span-3">
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $m['title'] }}</h3>
                                            <p class="mt-0.5 text-xs text-gray-500 truncate dark:text-gray-400">
                                                {{ $m['desc'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach

                        </div>
                    </div>

                @empty
                    <div class="py-10 text-center text-gray-500 dark:text-gray-400">
                        Menu tidak ditemukan / tidak ada akses.
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</div>
