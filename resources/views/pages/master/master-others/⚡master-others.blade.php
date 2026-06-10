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
        $this->dispatch('master.others.openCreate');
    }

    public function openEdit(string $otherId): void
    {
        $this->dispatch('master.others.openEdit', otherId: $otherId);
    }

    /* -------------------------
    | Request Delete (delegate ke actions)
    * ------------------------- */
    public function requestDelete(string $otherId): void
    {
        $this->dispatch('master.others.requestDelete', otherId: $otherId);
    }

    public function toggleActive(string $otherId): void
    {
        $this->dispatch('master.others.toggleActive', otherId: $otherId);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.others.saved')]
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

        $queryBuilder = DB::table('rsmst_others')->select('other_id', 'other_desc', 'other_price', 'active_status')->orderBy('other_desc', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('other_id', $searchKeyword);
                }

                $subQuery->orWhereRaw('UPPER(other_desc) LIKE ?', ["%{$uppercaseKeyword}%"])->orWhereRaw('UPPER(other_price) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    // Helper untuk format harga
    public function formatRupiah($price)
    {
        return 'Rp ' . number_format($price, 0, ',', '.');
    }
};
?>

<div>

    <x-page-title
        title="Master Lain-lain"
        subtitle="Kelola data lain-lain (administrasi, ambulans, dll) untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Lain-lain" class="sr-only" />
                        {{-- TANPA wire:key — key dinamis (now()) bikin input remount tiap render → fokus hilang saat ketik --}}
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari lain-lain (ID, nama, harga)..." class="block w-full" />
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
                            + Tambah Data Lain-lain Baru
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
                                <th>Nama</th>
                                <th class="text-right">Harga</th>
                                <th>Status</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="others-row-{{ $row->other_id }}">
                                    <td class="ds-td-token">{{ $row->other_id }}</td>
                                    <td class="ds-td-strong">{{ $row->other_desc }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-mono text-sm dark:text-green-400" style="color:var(--muted)">
                                            {{ $this->formatRupiah($row->other_price) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-toggle :current="(string) $row->active_status" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->other_id }}')">
                                            {{ (string) $row->active_status === '1' ? 'Aktif' : 'Tidak Aktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->other_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->other_id . '\')'"
                                                title="Hapus Data" message="Yakin hapus data {{ $row->other_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center" style="color:var(--muted)">
                                        Data belum ada.
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
            <livewire:pages::master.master-others.master-others-actions wire:key="master-others-actions" />
        </div>
    </div>
</div>
