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
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
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

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

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
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>ID</th>
                                <th>Kode ICD X</th>
                                <th>Nama Diagnosa</th>
                                <th>Status Koding</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="diagnosa-row-{{ $row->diag_id }}">
                                    <td class="ds-td-token">{{ $row->diag_id }}</td>
                                    <td class="px-6 py-4">
                                        <x-badge variant="info" class="font-mono">
                                            {{ $row->icdx }}
                                        </x-badge>
                                    </td>
                                    <td class="ds-td-strong">{{ $row->diag_desc }}</td>
                                    <td class="px-6 py-4">
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
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->diag_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->diag_id . '\')'"
                                                title="Hapus Diagnosa" message="Yakin hapus data diagnosa {{ $row->diag_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data belum ada.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Child actions component --}}
            <livewire:pages::master.master-diagnosa.master-diagnosa-actions wire:key="master-diagnosa-actions" />
        </div>
    </div>
</div>
