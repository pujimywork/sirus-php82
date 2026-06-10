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
    public int $itemsPerPage = 10;

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

    // ==================== RESET FILTERS ====================
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
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
     | Toggle Status Aktif (delegate ke actions)
     * ========================= */
    public function toggleActive(string $radId): void
    {
        $this->dispatch('master.radiologis.toggleActive', radId: $radId);
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

    <x-page-title
        title="Master Radiologis"
        subtitle="Kelola data tindakan radiologi untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- ==================== TOOLBAR: Search + Filter + Action ==================== --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

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
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>


            {{-- ==================== TABLE WRAPPER: card ==================== --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA (yang boleh scroll) --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        {{-- TABLE HEAD (sticky) --}}
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>ID</th>
                                <th>Nama Tindakan</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Rad JD</th>
                                <th>Rad JM</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="radiologis-row-{{ $row->rad_id }}">
                                    <td class="ds-td-token">{{ $row->rad_id }}</td>
                                    <td class="ds-td-strong">{{ $row->rad_desc }}</td>
                                    <td class="px-6 py-4">{{ $this->formatRupiah($row->rad_price) }}</td>
                                    <td class="px-6 py-4">
                                        <x-toggle :current="(string) $row->active_status" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->rad_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'Aktif' : 'Tidak Aktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="px-6 py-4">{{ $row->rad_jd ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $row->rad_jm ?? '-' }}</td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->rad_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->rad_id . '\')'"
                                                title="Hapus Radiologis" message="Yakin hapus data radiologis {{ $row->rad_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center" style="color:var(--muted)">
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
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-radiologis.master-radiologis-actions wire:key="master-radiologis-actions" />
        </div>
    </div>
</div>
