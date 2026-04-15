<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* =========================
     | Filter & Pagination state
     * ========================= */
    public string $searchKeyword = '';
    public int $itemsPerPage = 7;

    // ==================== UPDATE SEARCH KEYWORD ====================
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    // ==================== UPDATE ITEMS PER PAGE ====================
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* =========================
     | Child modal triggers
     * ========================= */

    // ==================== OPEN CREATE MODAL ====================
    public function openCreate(): void
    {
        $this->dispatch('master.radiologis.openCreate');
    }

    // ==================== OPEN EDIT MODAL ====================
    public function openEdit(string $radId): void
    {
        $this->dispatch('master.radiologis.openEdit', radId: $radId);
    }

    /* =========================
     | Request Delete (delegate ke actions)
     * ========================= */
    public function requestDelete(string $radId): void
    {
        $this->dispatch('master.radiologis.requestDelete', radId: $radId);
    }

    /* =========================
     | Refresh after child save
     * ========================= */
    #[On('master.radiologis.saved')]
    public function refreshAfterSaved(): void
    {
        // resetPage kadang tidak trigger kalau sudah di page 1 → paksa refresh
        $this->resetPage();
    }

    /* =========================
     | Computed queries
     * ========================= */

    // ==================== BASE QUERY ====================
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsmst_radiologis')->select('rad_id', 'rad_desc', 'rad_price', 'active_status', 'rad_jd', 'rad_jm')->orderBy('rad_desc', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                // Pencarian berdasarkan ID jika keyword berupa angka
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('rad_id', $searchKeyword);
                }

                $subQuery
                    ->orWhereRaw('UPPER(rad_desc) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(rad_jd) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(rad_jm) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    // ==================== ROWS (Paginated Data) ====================
    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    // ==================== FORMAT RUPIAH ====================
    public function formatRupiah($price)
    {
        return 'Rp ' . number_format($price, 0, ',', '.');
    }
};
?>


<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Radiologis
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data tindakan radiologi untuk aplikasi
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- ==================== TOOLBAR: Search + Filter + Action ==================== --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Radiologis" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari berdasarkan nama, ID, atau jam..." class="block w-full" />
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Radiologis Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>


            {{-- ==================== TABLE WRAPPER: card ==================== --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA (yang boleh scroll) --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD (sticky) --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">NAMA TINDAKAN</th>
                                <th class="px-4 py-3 font-semibold">HARGA</th>
                                <th class="px-4 py-3 font-semibold">STATUS</th>
                                <th class="px-4 py-3 font-semibold">RAD JD</th>
                                <th class="px-4 py-3 font-semibold">RAD JM</th>
                                <th class="px-4 py-3 font-semibold text-center">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="radiologis-row-{{ $row->rad_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono">{{ $row->rad_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->rad_desc }}</td>
                                    <td class="px-4 py-3">{{ $this->formatRupiah($row->rad_price) }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="$row->active_status === '1' ? 'success' : 'danger'">
                                            {{ $row->active_status === '1' ? 'Aktif' : 'Tidak Aktif' }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3">{{ $row->rad_jd ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $row->rad_jm ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-center gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->rad_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->rad_id . '\')'"
                                                title="Hapus Radiologis"
                                                message="Yakin hapus data radiologis {{ $row->rad_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mb-3 text-gray-400"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="text-gray-500 dark:text-gray-400">Belum ada data radiologis.</p>
                                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Klik tombol "Tambah
                                                Radiologis" untuk menambahkan data.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ==================== PAGINATION STICKY di bawah card ==================== --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-radiologis.master-radiologis-actions wire:key="master-radiologis-actions" />
        </div>
    </div>
</div>
