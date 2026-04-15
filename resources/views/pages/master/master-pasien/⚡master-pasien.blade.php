<?php

namespace App\Http\Livewire\Pages\Master\MasterPasien;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use WithPagination, MasterPasienTrait;

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
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

    /* -------------------------
     | Child modal triggers
     * ------------------------- */
    public function openCreate(): void
    {
        $this->dispatch('master.pasien.openCreate');
    }

    public function openEdit(string $regNo): void
    {
        $this->dispatch('master.pasien.openEdit', regNo: $regNo);
    }

    /* -------------------------
     | Request Delete (delegate ke actions)
     * ------------------------- */
    public function requestDelete(string $regNo): void
    {
        $this->dispatch('master.pasien.requestDelete', regNo: $regNo);
    }

    /* -------------------------
     | Refresh after child save
     * ------------------------- */
    #[On('master.pasien.saved')]
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

        $queryBuilder = DB::table('rsmst_pasiens')
            ->select(['reg_no', 'reg_name', 'sex', 'birth_date', 'address', 'phone', 'blood', 'marital_status', 'nik_bpjs', 'nokartu_bpjs', 'patient_uuid', 'no_jkn', 'reg_date'])
            ->orderBy('reg_name', 'asc');

        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                $subQuery
                    ->orWhereRaw('UPPER(reg_no) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(nik_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(nokartu_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(reg_name) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(address) LIKE ?', ["%{$uppercaseKeyword}%"])
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
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Master Pasien
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data pasien untuk aplikasi
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
                        <x-input-label for="searchKeyword" value="Cari Pasien" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama/NRM/NIK..." class="block w-full" />
                    </div>



                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('rawat-jalan.daftar') }}" wire:navigate>
                            <x-outline-button type="button">Pendaftaran Rawat Jalan</x-outline-button>
                        </a>
                        <a href="{{ route('ugd.daftar') }}" wire:navigate>
                            <x-outline-button type="button">Pendaftaran UGD</x-outline-button>
                        </a>
                        <a href="{{ route('ri.daftar') }}" wire:navigate>
                            <x-outline-button type="button">Pendaftaran Rawat Inap</x-outline-button>
                        </a>

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
                            + Tambah Data Pasien Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            @php($rows = $this->rows)

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-2 font-semibold">NO RM</th>
                                <th class="px-3 py-2 font-semibold">PASIEN</th>
                                <th class="px-3 py-2 font-semibold">TELEPON</th>
                                <th class="px-3 py-2 font-semibold">ALAMAT</th>
                                <th class="px-3 py-2 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($rows as $row)
                                <tr wire:key="pasien-row-{{ $row->reg_no }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-3 py-2 font-mono text-brand dark:text-brand-lime whitespace-nowrap">{{ $row->reg_no }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $row->reg_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->sex === 'L' ? 'L' : ($row->sex === 'P' ? 'P' : '-') }}
                                            @if ($row->birth_date)
                                                &bull; {{ date('d/m/Y', strtotime($row->birth_date)) }}
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if (!empty($row->nik_bpjs))
                                                <span>NIK: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $row->nik_bpjs }}</span></span>
                                            @endif
                                            @if (!empty($row->nokartu_bpjs))
                                                <span>BPJS: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $row->nokartu_bpjs }}</span></span>
                                            @endif
                                            @if (!empty($row->patient_uuid))
                                                <span>UUID: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $row->patient_uuid }}</span></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $row->phone ?? '-' }}</td>
                                    <td class="px-3 py-2 max-w-xs truncate text-xs text-gray-600 dark:text-gray-400">{{ $row->address ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->reg_no }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->reg_no . '\')'" title="Hapus Pasien"
                                                message="Yakin hapus pasien {{ $row->reg_name }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
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
                        {{ $rows->links() }}
                    </div>
                </div>

                {{-- Child actions component --}}
                <livewire:pages::master.master-pasien.master-pasien-actions wire:key="master-pasien-actions" />

            </div>
        </div>
    </div>
    </div>
