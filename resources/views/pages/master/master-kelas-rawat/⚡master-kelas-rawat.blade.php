<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->dispatch('master.class.openCreate');
    }

    public function openEdit(int $classId): void
    {
        $this->dispatch('master.class.openEdit', classId: $classId);
    }

    public function requestDelete(int $classId): void
    {
        $this->dispatch('master.class.requestDelete', classId: $classId);
    }

    #[On('master.class.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_class as c')
            ->selectRaw('c.class_id, c.class_desc, COUNT(r.room_id) AS jumlah_kamar')
            ->leftJoin('rsmst_rooms as r', 'c.class_id', '=', 'r.class_id')
            ->groupBy('c.class_id', 'c.class_desc')
            ->orderBy('c.class_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(c.class_desc) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('CAST(c.class_id AS VARCHAR(10)) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Kelas Rawat
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola kelas kamar rawat inap
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-xs">
                        <x-input-label for="searchKeyword" value="Cari Kelas" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari kelas..."
                            class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Kelas Rawat Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-5 py-3 font-semibold">KELAS</th>
                                <th class="px-5 py-3 font-semibold">JUMLAH KAMAR</th>
                                <th class="px-5 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="class-{{ $row->class_id }}"
                                    class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">

                                    {{-- KELAS: id + nama --}}
                                    <td class="px-5 py-4 align-top space-y-1">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">
                                            {{ $row->class_desc }}
                                        </div>
                                        <div class="font-mono text-xs text-gray-400 dark:text-gray-500">
                                            ID: {{ $row->class_id }}
                                        </div>
                                    </td>

                                    {{-- JUMLAH KAMAR --}}
                                    <td class="px-5 py-4 align-top">
                                        <x-badge variant="info">{{ $row->jumlah_kamar }} Kamar</x-badge>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-5 py-4 align-top">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit({{ $row->class_id }})" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(' . $row->class_id . ')'"
                                                title="Hapus Kelas"
                                                message="Yakin hapus kelas {{ $row->class_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data kelas tidak ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-kelas-rawat.master-kelas-rawat-actions wire:key="master-kelas-rawat-actions" />

        </div>
    </div>
</div>
