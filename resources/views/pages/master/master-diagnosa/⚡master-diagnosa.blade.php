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

        $queryBuilder = DB::table('rsmst_mstdiags')->select('diag_id', 'diag_desc', 'icdx', 'valid_code', 'accpdx', 'asterisk', 'im')->orderBy('diag_desc', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword) {
                // diag_id alfanumerik (A001, K20X, M47.80) — ikutkan di LIKE, bukan exact-digit
                $subQuery
                    ->orWhereRaw('UPPER(diag_id) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(diag_desc) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(icdx) LIKE ?', ["%{$uppercaseKeyword}%"]);
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

    <x-page-title
        title="Master Diagnosa"
        subtitle="Kelola data diagnosa (ICD-10) untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Diagnosa" class="sr-only" />
                        {{-- TANPA wire:key — key dinamis (now()) bikin input remount tiap render → fokus hilang saat ketik --}}
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari diagnosa (ID, nama, kode ICD)..." class="block w-full" />
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
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">KODE ICD X</th>
                                <th class="px-4 py-3 font-semibold">NAMA DIAGNOSA</th>
                                <th class="px-4 py-3 font-semibold">STATUS KODING</th>
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
                                        <div class="flex flex-wrap items-center gap-1">
                                            @if ((int) ($row->valid_code ?? 0) === 1)
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-green-100 text-green-800 rounded dark:bg-green-900/30 dark:text-green-300"
                                                    title="Kode valid untuk koding">Valid</span>
                                            @else
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-red-100 text-red-800 rounded dark:bg-red-900/30 dark:text-red-300"
                                                    title="Kode tidak valid — diblok di LOV diagnosa">Invalid</span>
                                            @endif
                                            @if (($row->accpdx ?? 'N') === 'N')
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-amber-100 text-amber-800 rounded dark:bg-amber-900/30 dark:text-amber-300"
                                                    title="Tidak boleh sebagai diagnosa primer">!PDX</span>
                                            @endif
                                            @if (!empty($row->asterisk))
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-purple-100 text-purple-800 rounded dark:bg-purple-900/30 dark:text-purple-300"
                                                    title="Kode asterisk — wajib pair dengan etiologi (dagger)">★</span>
                                            @endif
                                            @if (!empty($row->im))
                                                <span
                                                    class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-emerald-100 text-emerald-800 rounded dark:bg-emerald-900/30 dark:text-emerald-300"
                                                    title="Kode spesifik iDRG/INACBG Indonesian Modification">iM</span>
                                            @endif
                                        </div>
                                    </td>
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
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
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
