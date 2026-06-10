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
        $this->dispatch('master.diagkep.openCreate');
    }

    public function openEdit(string $diagkepId): void
    {
        $this->dispatch('master.diagkep.openEdit', diagkepId: $diagkepId);
    }

    public function requestDelete(string $diagkepId): void
    {
        $this->dispatch('master.diagkep.requestDelete', diagkepId: $diagkepId);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.diagkep.saved')]
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

        $queryBuilder = DB::table('rsmst_diagkeperawatans')
            ->select('diagkep_id', 'diagkep_desc', 'diagkep_json')
            ->orderBy('diagkep_id', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                $subQuery
                    ->orWhereRaw('UPPER(diagkep_id) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(diagkep_desc) LIKE ?', ["%{$uppercaseKeyword}%"]);
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
        title="Master Diagnosis Keperawatan"
        subtitle="Kelola data SDKI, SLKI, SIKI untuk asuhan keperawatan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Diagnosis" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari kode atau nama diagnosis..." class="block w-full" />
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
                            + Tambah Data Diagnosis Keperawatan Baru
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
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="w-28">Kode</th>
                                <th>Diagnosis</th>
                                <th class="w-44">Kategori</th>
                                <th class="ds-c w-20">SLKI</th>
                                <th class="ds-c w-20">SIKI</th>
                                <th class="ds-c w-36">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                @php
                                    $json = is_string($row->diagkep_json) ? json_decode($row->diagkep_json, true) : ($row->diagkep_json ?? []);
                                    $kategori = $json['sdki']['kategori'] ?? '-';
                                    $slkiCount = count($json['slki'] ?? []);
                                    $sikiCount = count($json['siki'] ?? []);
                                @endphp
                                <tr wire:key="diagkep-row-{{ $row->diagkep_id }}">
                                    <td class="ds-td-token">{{ $row->diagkep_id }}</td>
                                    <td class="ds-td-strong">{{ $row->diagkep_desc }}</td>
                                    <td class="px-6 py-4">
                                        <x-badge variant="gray">{{ $kategori }}</x-badge>
                                    </td>
                                    <td class="px-6 py-4 text-center" style="color:var(--muted)">{{ $slkiCount }}</td>
                                    <td class="px-6 py-4 text-center" style="color:var(--muted)">{{ $sikiCount }}</td>

                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->diagkep_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->diagkep_id . '\')'"
                                                title="Hapus Diagnosis" message="Yakin hapus data diagnosis {{ $row->diagkep_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center" style="color:var(--muted)">
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


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-diag-keperawatan.master-diag-keperawatan-actions wire:key="master-diagkep-actions" />
        </div>
    </div>
</div>
