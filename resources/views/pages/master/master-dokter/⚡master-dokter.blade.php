<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

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

    public function openCreate(): void
    {
        $this->dispatch('master.dokter.openCreate');
    }

    public function openEdit(string $drId): void
    {
        $this->dispatch('master.dokter.openEdit', drId: $drId);
    }

    public function requestDelete(string $drId): void
    {
        $this->dispatch('master.dokter.requestDelete', drId: $drId);
    }

    #[On('master.dokter.saved')]
    public function refreshAfterSaved(): void
    {
        $this->dispatch('$refresh');
    }

    #[Computed]
    public function baseQuery()
    {
        $search = trim($this->searchKeyword);

        $q = DB::table('rsmst_doctors')->orderBy('dr_name', 'asc');

        if ($search !== '') {
            $u = mb_strtoupper($search);

            $q->where(function ($sub) use ($search, $u) {
                if (ctype_digit($search)) {
                    $sub->orWhere('dr_id', $search);
                }

                $sub
                    ->orWhereRaw('UPPER(dr_name) LIKE ?', ["%{$u}%"])
                    ->orWhereRaw('UPPER(dr_phone) LIKE ?', ["%{$u}%"])
                    ->orWhereRaw('UPPER(dr_address) LIKE ?', ["%{$u}%"])
                    ->orWhereRaw('UPPER(poli_id) LIKE ?', ["%{$u}%"]);
            });
        }

        return $q;
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
                Master Dokter
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data dokter
            </p>
        </div>
    </header>

    <div class="w-full bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b dark:bg-gray-900 top-20">
                <div class="flex flex-col gap-3 lg:flex-row lg:justify-between">

                    <div class="w-full lg:max-w-md">
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword" placeholder="Cari dokter..."
                            class="block w-full" />
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
                            + Tambah Dokter
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                {{-- TABLE SCROLL AREA (yang boleh scroll) --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD (optional sticky) --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Poli</th>
                                <th class="px-4 py-3">Telepon</th>
                                <th class="px-4 py-3">Gaji</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">

                            @forelse($this->rows as $row)
                            <tr wire:key="dokter-{{ $row->dr_id }}">

                                <td class="px-4 py-3">{{ $row->dr_id }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $row->dr_name }}</td>
                                <td class="px-4 py-3">{{ $row->poli_id }}</td>
                                <td class="px-4 py-3">{{ $row->dr_phone }}</td>
                                <td class="px-4 py-3">{{ number_format($row->basic_salary) }}</td>

                                <td class="px-4 py-3">
                                    <x-badge :variant="$row->active_status == '1' ? 'success' : 'gray'">
                                        {{ $row->active_status == '1' ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>

                                <td class="flex gap-2 px-4 py-3">

                                    <x-outline-button wire:click="openEdit('{{ $row->dr_id }}')">
                                        Edit
                                    </x-outline-button>

                                    <x-confirm-button variant="danger" :action="'requestDelete(\''.$row->dr_id.'\')'"
                                        title="Hapus Dokter" message="Yakin hapus dokter {{ $row->dr_name }}?">
                                        Hapus
                                    </x-confirm-button>

                                </td>

                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                    Data belum ada
                                </td>
                            </tr>
                            @endforelse

                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>

            </div>

            <livewire:pages::master.master-dokter.master-dokter-actions />

        </div>
    </div>
</div>