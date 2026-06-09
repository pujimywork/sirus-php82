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
        $this->dispatch('master.karyawan.openCreate');
    }

    public function openEdit(string $empId): void
    {
        $this->dispatch('master.karyawan.openEdit', empId: $empId);
    }

    /* -------------------------
    | Request Delete (delegate ke actions)
    * ------------------------- */
    public function requestDelete(string $empId): void
    {
        $this->dispatch('master.karyawan.requestDelete', empId: $empId);
    }

    /* -------------------------
     | Toggle Aktif / Non-aktif langsung dari table
     * ------------------------- */
    public function toggleActive(string $empId): void
    {
        $current = (string) DB::table('immst_employers')->where('emp_id', $empId)->value('active_record');
        $newValue = $current === '1' ? '0' : '1';

        DB::table('immst_employers')
            ->where('emp_id', $empId)
            ->update(['active_record' => $newValue]);

        $this->dispatch(
            'toast',
            type: 'success',
            message: $newValue === '1' ? 'Karyawan diaktifkan.' : 'Karyawan dinon-aktifkan.',
        );
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.karyawan.saved')]
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

        $queryBuilder = DB::table('immst_employers')
            ->select('emp_id', 'emp_name', 'phone', 'address', 'active_record')
            ->orderBy('emp_name', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword) {
                $subQuery
                    ->orWhereRaw('UPPER(emp_id) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(emp_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(phone) LIKE ?', ["%{$uppercaseKeyword}%"]);
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
        title="Master Karyawan"
        subtitle="Kelola data karyawan untuk login user &amp; coder iDRG (NIK Coder)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Karyawan" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari NIK / nama / phone..." class="block w-full" />
                    </div>

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
                            + Tambah Karyawan Baru
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>


            {{-- TABLE --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr class="text-left">
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">NIK / Emp ID</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">Nama</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">Phone</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-center text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-500 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-400">
                            @forelse($this->rows as $row)
                                <tr wire:key="karyawan-row-{{ $row->emp_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-6 py-4 font-mono text-sm text-gray-600 dark:text-gray-300">{{ $row->emp_id }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $row->emp_name }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $row->phone }}</td>

                                    <td class="px-6 py-4">
                                        @php $isActive = (string) $row->active_record === '1'; @endphp
                                        {{-- wire:key sertakan nilai active supaya Alpine re-init saat status berubah --}}
                                        <x-toggle wire:key="toggle-active-{{ $row->emp_id }}-{{ $isActive ? 1 : 0 }}"
                                            :current="$isActive ? '1' : '0'" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->emp_id }}')">
                                            {{ $isActive ? 'Aktif' : 'Non-aktif' }}
                                        </x-toggle>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->emp_id }}')"
                                                class="px-2 py-1 text-sm">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->emp_id . '\')'" title="Hapus Karyawan"
                                                message="Yakin hapus karyawan {{ $row->emp_name }} ({{ $row->emp_id }})?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-sm">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-karyawan.master-karyawan-actions wire:key="master-karyawan-actions" />
        </div>
    </div>
</div>
