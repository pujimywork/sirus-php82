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

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

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

    <x-page-title
        title="Master Kelas Rawat"
        subtitle="Kelola kelas kamar rawat inap" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>Kelas</th>
                                <th>Jumlah Kamar</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="class-{{ $row->class_id }}"
                                    class="bg-canvas dark:bg-gray-900 transition">

                                    {{-- KELAS: id + nama --}}
                                    <td class="px-6 py-4 align-top space-y-1">
                                        <div class="font-medium text-ink dark:text-white">
                                            {{ $row->class_desc }}
                                        </div>
                                        <div class="font-mono text-sm" style="color:var(--muted)">
                                            ID: {{ $row->class_id }}
                                        </div>
                                    </td>

                                    {{-- JUMLAH KAMAR --}}
                                    <td class="px-6 py-4 align-top">
                                        <x-badge variant="info">{{ $row->jumlah_kamar }} Kamar</x-badge>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="ds-c px-6 py-4 align-top">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit({{ $row->class_id }})" />
                                            <x-action-delete :action="'requestDelete(' . $row->class_id . ')'"
                                                title="Hapus Kelas" message="Yakin hapus kelas {{ $row->class_desc }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data kelas tidak ditemukan.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-kelas-rawat.master-kelas-rawat-actions wire:key="master-kelas-rawat-actions" />

        </div>
    </div>
</div>
