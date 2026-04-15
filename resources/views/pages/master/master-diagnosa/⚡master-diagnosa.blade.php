<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public int $itemsPerPage = 7;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.diagnosa.openCreate');
    }

    public function openEdit(string $diagId): void
    {
        $this->dispatch('master.diagnosa.openEdit', diagId: $diagId);
    }

    /* -------------------------
    | Request Delete (delegate ke actions)
    * ------------------------- */
    public function requestDelete(string $diagId): void
    {
        $this->dispatch('master.diagnosa.requestDelete', diagId: $diagId);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.diagnosa.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsmst_mstdiags')->select('diag_id', 'diag_desc', 'icdx')->orderBy('diag_desc', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('diag_id', $searchKeyword);
                }

                $subQuery->orWhereRaw('UPPER(diag_desc) LIKE ?', ["%{$uppercaseKeyword}%"])->orWhereRaw('UPPER(icdx) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Diagnosa
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data diagnosa (ICD-10) untuk aplikasi
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Diagnosa" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari diagnosa (ID, nama, kode ICD)..." class="block w-full"
                            wire:key="search-input-{{ now() }}" />
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
                            + Tambah Data Diagnosa Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">KODE ICD X</th>
                                <th class="px-4 py-3 font-semibold">NAMA DIAGNOSA</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="diagnosa-row-{{ $row->diag_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3">{{ $row->diag_id }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge variant="info" class="font-mono">
                                            {{ $row->icdx }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->diag_desc }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->diag_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->diag_id . '\')'" title="Hapus Diagnosa"
                                                message="Yakin hapus data diagnosa {{ $row->diag_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Child actions component --}}
            <livewire:pages::master.master-diagnosa.master-diagnosa-actions wire:key="master-diagnosa-actions" />
        </div>
    </div>
</div>
