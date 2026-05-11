<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterActive = '';
    public string $filterTipe = '';
    public int $itemsPerPage = 15;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterActive(): void  { $this->resetPage(); }
    public function updatedFilterTipe(): void    { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->dispatch('master.stocklocations.openCreate');
    }

    public function openEdit(string $slCode): void
    {
        $this->dispatch('master.stocklocations.openEdit', slCode: $slCode);
    }

    public function requestDelete(string $slCode): void
    {
        $this->dispatch('master.stocklocations.requestDelete', slCode: $slCode);
    }

    #[On('master.stocklocations.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('immst_stocklocations')
            ->select('sl_code', 'sl_name', 'stock_status', 'active_status', 'medis', 'nonmedis')
            ->orderBy('sl_code');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($w) use ($kw) {
                $w->whereRaw('UPPER(sl_name) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(sl_code) LIKE ?', ["%{$kw}%"]);
            });
        }

        if ($this->filterActive !== '') {
            $q->where('active_status', $this->filterActive);
        }

        if ($this->filterTipe === 'medis') {
            $q->where('medis', '1');
        } elseif ($this->filterTipe === 'nonmedis') {
            $q->where('nonmedis', '1');
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Lokasi Stok
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Daftar lokasi penyimpanan stok (gudang, apotek, ruangan, klinik) untuk transfer & mutasi obat / barang.
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label for="searchKeyword" value="Pencarian" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari kode / nama lokasi..." class="block w-full" />
                    </div>

                    {{-- TIPE --}}
                    <div class="w-full sm:w-44">
                        <x-input-label value="Tipe" />
                        <x-select-input wire:model.live="filterTipe" class="w-full mt-1">
                            <option value="">Semua Tipe</option>
                            <option value="medis">Medis</option>
                            <option value="nonmedis">Non-Medis</option>
                        </x-select-input>
                    </div>

                    {{-- STATUS AKTIF --}}
                    <div class="w-full sm:w-44">
                        <x-input-label value="Status Aktif" />
                        <x-select-input wire:model.live="filterActive" class="w-full mt-1">
                            <option value="">Semua Status</option>
                            <option value="1">Aktif</option>
                            <option value="0">Non-aktif</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-end gap-2 ml-auto">
                        <div class="w-24">
                            <x-input-label value="Per hal." />
                            <x-select-input wire:model.live="itemsPerPage" class="mt-1">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate" class="whitespace-nowrap"
                            title="Tambah lokasi stok baru">
                            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Lokasi
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold">KODE</th>
                                <th class="px-4 py-3 font-semibold">NAMA LOKASI</th>
                                <th class="px-4 py-3 font-semibold">TIPE</th>
                                <th class="px-4 py-3 font-semibold">STATUS STOK</th>
                                <th class="px-4 py-3 font-semibold">STATUS AKTIF</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="sl-{{ $row->sl_code }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        {{ $row->sl_code }}
                                    </td>

                                    <td class="px-4 py-3 font-semibold">
                                        {{ $row->sl_name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @if ((string) ($row->medis ?? '0') === '1')
                                                <x-badge variant="success">Medis</x-badge>
                                            @endif
                                            @if ((string) ($row->nonmedis ?? '0') === '1')
                                                <x-badge variant="alternative">Non-Medis</x-badge>
                                            @endif
                                            @if ((string) ($row->medis ?? '0') !== '1' && (string) ($row->nonmedis ?? '0') !== '1')
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ((string) ($row->stock_status ?? '0') === '1')
                                            <x-badge variant="success">Aktif</x-badge>
                                        @else
                                            <x-badge variant="gray">Tidak</x-badge>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ((string) ($row->active_status ?? '0') === '1')
                                            <x-badge variant="success">Aktif</x-badge>
                                        @else
                                            <x-badge variant="danger">Non-aktif</x-badge>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->sl_code }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->sl_code . '\')'"
                                                title="Hapus Lokasi Stok"
                                                message="Yakin hapus lokasi {{ $row->sl_code }} - {{ $row->sl_name }}?"
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
                                        Belum ada data lokasi stok.
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

            <livewire:pages::master.master-stocklocations.master-stocklocations-actions
                wire:key="master-stocklocations-actions" />

        </div>
    </div>
</div>
