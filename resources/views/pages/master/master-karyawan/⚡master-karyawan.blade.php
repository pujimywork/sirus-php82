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

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

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
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>NIK / Emp ID</th>
                                <th>Nama</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                <tr wire:key="karyawan-row-{{ $row->emp_id }}">
                                    <td class="ds-td-token">{{ $row->emp_id }}</td>
                                    <td class="ds-td-strong">{{ $row->emp_name }}</td>
                                    <td class="px-6 py-4" style="color:var(--muted)">{{ $row->phone }}</td>

                                    <td class="px-6 py-4">
                                        @php $isActive = (string) $row->active_record === '1'; @endphp
                                        {{-- wire:key sertakan nilai active supaya Alpine re-init saat status berubah --}}
                                        <x-toggle wire:key="toggle-active-{{ $row->emp_id }}-{{ $isActive ? 1 : 0 }}"
                                            :current="$isActive ? '1' : '0'" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->emp_id }}')">
                                            {{ $isActive ? 'Aktif' : 'Non-aktif' }}
                                        </x-toggle>
                                    </td>

                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->emp_id }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->emp_id . '\')'" title="Hapus Karyawan"
                                                message="Yakin hapus karyawan {{ $row->emp_name }} ({{ $row->emp_id }})?" />
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

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Child actions component (modal CRUD) --}}
            <livewire:pages::master.master-karyawan.master-karyawan-actions wire:key="master-karyawan-actions" />
        </div>
    </div>
</div>
