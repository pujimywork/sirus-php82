<?php

/**
 * pages/database-monitor/user-control/kas-user-control/kas-user-control-actions.blade.php
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /* ── User yang sedang dikelola ── */
    public ?int $userId = null;
    public string $userName = '';
    public string $userEmail = '';
    public ?string $empId = null; // read-only di modal ini, set via Edit User
    public ?string $empName = null; // nama karyawan dari IMMST_EMPLOYERS

    /* ── Kas ── */
    public array $availableKas = [];
    public array $userKasIds = [];
    public string $searchKas = '';
    public string $filterTipe = '';

    public array $tipeOptions = [
        '' => 'Semua Tipe',
        'rj' => 'Rawat Jalan',
        'ugd' => 'UGD',
        'ri' => 'Rawat Inap',
    ];

    /* ── Open modal ── */

    #[On('kasUserControl.openManage')]
    public function openManage(int $userId): void
    {
        $user = DB::table('users')->select('id', 'name', 'email', 'emp_id')->where('id', $userId)->first();

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }

        $this->userId = $user->id;
        $this->userName = $user->name ?? '';
        $this->userEmail = $user->email ?? '';
        $this->empId = $user->emp_id ? (string) $user->emp_id : null;
        $this->empName = null;
        $this->searchKas = '';
        $this->filterTipe = '';

        // Load nama karyawan dari Oracle jika emp_id ada
        if ($this->empId) {
            $emp = DB::table('immst_employers')->select('emp_name', 'emp_grade', 'bu_id', 'phone')->where('emp_id', $this->empId)->first();

            $this->empName = $emp?->emp_name ?? null;
        }

        $this->loadUserKas();
        $this->loadAvailableKas();

        $this->dispatch('open-modal', name: 'kas-user-control-actions');
    }

    /* ── Reactive ── */

    public function updatedSearchKas(): void
    {
        $this->loadAvailableKas();
    }
    public function updatedFilterTipe(): void
    {
        $this->loadAvailableKas();
    }

    /* ── Load kas milik user ── */

    protected function loadUserKas(): void
    {
        $this->userKasIds = DB::table('user_kas')->where('user_id', $this->userId)->pluck('acc_id')->map(fn($v) => (string) $v)->all();
    }

    /* ── Load semua kas tersedia ── */

    protected function loadAvailableKas(): void
    {
        $keyword = trim($this->searchKas);
        $upperKeyword = mb_strtoupper($keyword);

        $rows = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name', 'b.rj', 'b.ugd', 'b.ri')
            ->when($this->filterTipe !== '', fn($q) => $q->where('b.' . $this->filterTipe, '1'))
            ->when($keyword !== '', fn($q) => $q->where(fn($q2) => $q2->where('a.acc_id', 'like', "%{$keyword}%")->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$upperKeyword}%"])))
            ->orderBy('a.acc_id')
            ->get();

        $this->availableKas = $rows
            ->map(
                fn($row) => [
                    'acc_id' => (string) $row->acc_id,
                    'acc_name' => (string) ($row->acc_name ?? ''),
                    'tipe_rj' => ($row->rj ?? '') === '1',
                    'tipe_ugd' => ($row->ugd ?? '') === '1',
                    'tipe_ri' => ($row->ri ?? '') === '1',
                    'assigned' => in_array((string) $row->acc_id, $this->userKasIds),
                ],
            )
            ->toArray();
    }

    /* ── Toggle assign / revoke ── */

    public function toggleKas(string $accId): void
    {
        if (!$this->userId) {
            return;
        }

        $exists = DB::table('user_kas')->where('user_id', $this->userId)->where('acc_id', $accId)->exists();

        if ($exists) {
            DB::table('user_kas')->where('user_id', $this->userId)->where('acc_id', $accId)->delete();

            $this->dispatch('toast', type: 'info', message: "Kas {$accId} dicabut.");
        } else {
            DB::table('user_kas')->insert([
                'id_user_kas' => DB::raw('(SELECT NVL(MAX(id_user_kas), 0) + 1 FROM user_kas)'),
                'user_id' => $this->userId,
                'acc_id' => $accId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: "Kas {$accId} diberikan.");
        }

        $this->loadUserKas();
        $this->loadAvailableKas();
        $this->dispatch('refresh-after-user-control.saved');
    }

    /* ── Revoke semua ── */

    public function revokeAll(): void
    {
        if (!$this->userId) {
            return;
        }

        DB::table('user_kas')->where('user_id', $this->userId)->delete();

        $this->userKasIds = [];
        $this->loadAvailableKas();

        $this->dispatch('toast', type: 'warning', message: "Semua kas {$this->userName} dicabut.");
        $this->dispatch('refresh-after-user-control.saved');
    }

    /* ── Tutup modal ── */

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'kas-user-control-actions');
    }
};
?>

<div>
    <x-modal name="kas-user-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col max-h-[85vh]">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-5 h-5 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Kelola Akun Kas
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $userName }}
                                    @if ($userEmail)
                                        <span class="text-gray-400">· {{ $userEmail }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="info">{{ count($userKasIds) }} kas aktif</x-badge>
                            @if ($empId)
                                <x-badge variant="green">EMP: {{ $empId }}</x-badge>
                            @else
                                <x-badge variant="danger">EMP ID belum diset</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── BODY ── --}}
            <div class="flex-1 px-6 py-4 space-y-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">

                {{-- ══ INFO EMP ID (read-only) ══ --}}
                @if ($empId)
                    {{-- Sudah ada — tampilkan sebagai card info --}}
                    <div
                        class="flex items-center gap-3 p-3 border border-emerald-200 rounded-xl bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/10">
                        <div
                            class="flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 shrink-0">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Karyawan terhubung
                            </p>
                            <p class="text-sm font-bold text-emerald-800 truncate dark:text-emerald-200">
                                {{ $empName ?? '(nama tidak ditemukan)' }}
                            </p>
                            <p class="text-xs font-mono text-emerald-600 dark:text-emerald-400">{{ $empId }}</p>
                        </div>
                        {{-- Hint ke mana harus edit --}}
                        <div class="text-right shrink-0">
                            <p class="text-[10px] text-emerald-500 dark:text-emerald-500">
                                Ubah via
                            </p>
                            <p class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                Edit User
                            </p>
                        </div>
                    </div>
                @else
                    {{-- Belum diset — tampilkan peringatan + arahkan ke Edit User --}}
                    <div
                        class="flex items-start gap-3 p-4 border border-amber-200 rounded-xl bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/10">
                        <svg class="w-5 h-5 text-amber-500 dark:text-amber-400 mt-0.5 shrink-0" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                EMP ID belum diisi
                            </p>
                            <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                                EMP ID dibutuhkan agar nama karyawan muncul otomatis saat insert transaksi.
                                Silakan isi melalui tombol
                                <strong>Edit User</strong>
                                → pilih karyawan lewat kolom <em>Karyawan (EMP ID)</em>, lalu simpan.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- ══ FILTER + SEARCH KAS ══ --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-[180px]">
                        <x-text-input type="text" wire:model.live.debounce.250ms="searchKas"
                            placeholder="Cari kode / nama kas..." class="w-full" />
                    </div>
                    <select wire:model.live="filterTipe"
                        class="text-sm border-gray-300 rounded-lg dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:ring-brand-green focus:border-brand-green">
                        @foreach ($tipeOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if (count($userKasIds) > 0)
                        <x-confirm-button variant="danger" :action="'revokeAll()'" title="Cabut Semua Kas"
                            message="Yakin cabut semua akses kas {{ $userName }}?" confirmText="Ya, cabut semua"
                            cancelText="Batal">
                            Cabut Semua
                        </x-confirm-button>
                    @endif
                </div>

                {{-- ══ GRID AKUN KAS ══ --}}
                @if (count($availableKas) > 0)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($availableKas as $kas)
                            <button wire:key="kas-modal-{{ $kas['acc_id'] }}"
                                wire:click="toggleKas('{{ $kas['acc_id'] }}')" type="button"
                                class="flex items-center justify-between p-3 text-left transition rounded-xl border
                                    {{ $kas['assigned']
                                        ? 'border-brand-green bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
                                        : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600' }}">

                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-sm font-semibold truncate
                                        {{ $kas['assigned'] ? 'text-brand-green dark:text-brand-lime' : 'text-gray-800 dark:text-gray-200' }}">
                                        {{ $kas['acc_name'] ?: $kas['acc_id'] }}
                                    </p>
                                    <p class="text-xs font-mono text-gray-400">{{ $kas['acc_id'] }}</p>
                                    <div class="flex gap-1 mt-1">
                                        @if ($kas['tipe_rj'])
                                            <span
                                                class="px-1.5 text-[10px] font-semibold rounded bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">RJ</span>
                                        @endif
                                        @if ($kas['tipe_ugd'])
                                            <span
                                                class="px-1.5 text-[10px] font-semibold rounded bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">UGD</span>
                                        @endif
                                        @if ($kas['tipe_ri'])
                                            <span
                                                class="px-1.5 text-[10px] font-semibold rounded bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400">RI</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="ml-3 shrink-0">
                                    @if ($kas['assigned'])
                                        <svg class="w-5 h-5 text-brand-green dark:text-brand-lime" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-300 dark:text-gray-600" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                                        </svg>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                        Tidak ada akun kas ditemukan.
                    </div>
                @endif

            </div>

            {{-- ── FOOTER ── --}}
            <div class="px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    {{-- Hint ke Edit User jika emp_id kosong --}}
                    @if (!$empId)
                        <p class="text-xs text-amber-500 dark:text-amber-400">
                            ⚠ Tutup modal ini lalu klik <strong>Edit User</strong> untuk mengisi EMP ID.
                        </p>
                    @else
                        <div></div>
                    @endif
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
