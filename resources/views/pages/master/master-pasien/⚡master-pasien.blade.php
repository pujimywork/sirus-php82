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
    <x-page-title
        title="Master Pasien"
        subtitle="Kelola data pasien untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-canvas dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter + Action --}}
            <div
                class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            @php($rows = $this->rows)

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        {{-- TABLE HEAD --}}
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th>No RM</th>
                                <th>Pasien</th>
                                <th>Telepon</th>
                                <th>Alamat</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($rows as $row)
                                <tr wire:key="pasien-row-{{ $row->reg_no }}">
                                    <td class="ds-td-token">{{ $row->reg_no }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $row->reg_name }}</div>
                                        <div class="text-xs" style="color:var(--muted)">
                                            {{ $row->sex === 'L' ? 'L' : ($row->sex === 'P' ? 'P' : '-') }}
                                            @if ($row->birth_date)
                                                &bull; {{ date('d/m/Y', strtotime($row->birth_date)) }}
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-xs" style="color:var(--muted)">
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
                                    <td class="px-6 py-4 whitespace-nowrap" style="color:var(--muted)">{{ $row->phone ?? '-' }}</td>
                                    <td class="px-6 py-4 max-w-xs truncate" style="color:var(--muted)">{{ $row->address ?? '-' }}</td>
                                    <td class="ds-c px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->reg_no }}')" />

                                            <x-action-delete :action="'requestDelete(\'' . $row->reg_no . '\')'" title="Hapus Pasien"
                                                message="Yakin hapus pasien {{ $row->reg_name }}?" />
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
                        {{ $rows->links() }}
                    </div>
                </div>

                {{-- Child actions component --}}
                <livewire:pages::master.master-pasien.master-pasien-actions wire:key="master-pasien-actions" />

            </div>
        </div>
    </div>
    </div>
