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

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Diagnosis Keperawatan
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data SDKI, SLKI, SIKI untuk asuhan keperawatan
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
                    </div>
                </div>
            </div>


            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold w-28">KODE</th>
                                <th class="px-4 py-3 font-semibold">DIAGNOSIS</th>
                                <th class="px-4 py-3 font-semibold w-44">KATEGORI</th>
                                <th class="px-4 py-3 font-semibold w-20 text-center">SLKI</th>
                                <th class="px-4 py-3 font-semibold w-20 text-center">SIKI</th>
                                <th class="px-4 py-3 font-semibold w-36">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                @php
                                    $json = is_string($row->diagkep_json) ? json_decode($row->diagkep_json, true) : ($row->diagkep_json ?? []);
                                    $kategori = $json['sdki']['kategori'] ?? '-';
                                    $slkiCount = count($json['slki'] ?? []);
                                    $sikiCount = count($json['siki'] ?? []);
                                @endphp
                                <tr wire:key="diagkep-row-{{ $row->diagkep_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->diagkep_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->diagkep_desc }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge variant="gray">{{ $kategori }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-center">{{ $slkiCount }}</td>
                                    <td class="px-4 py-3 text-center">{{ $sikiCount }}</td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->diagkep_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->diagkep_id . '\')'" title="Hapus Diagnosis"
                                                message="Yakin hapus data diagnosis {{ $row->diagkep_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
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


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-diag-keperawatan.master-diag-keperawatan-actions wire:key="master-diagkep-actions" />
        </div>
    </div>
</div>
